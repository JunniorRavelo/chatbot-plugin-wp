<?php
/**
 * Admin settings panel.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Chatbot_Admin_Settings {

	const OPTION_KEY = 'chatbot_plugin_settings';

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_action( 'admin_post_chatbot_export_csv', array( __CLASS__, 'export_csv' ) );
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function default_settings(): array {
		return array(
			'widget_enabled'        => true,
			'welcome_message'       => "Hola. Soy un agente de IA. Puedo cometer errores; verifica la información importante antes de tomar decisiones.\n\n¿En qué puedo ayudarte?",
			'system_prompt'         => 'Eres un asistente útil del sitio web. Responde en español de forma clara, breve y amable. Si no sabes algo, dilo con honestidad.',
			'streaming_enabled'     => true,
			'rate_limit_per_minute' => 10,
			'provider'              => 'gemini',
			'api_key'               => '',
			'model'                 => 'gemini-2.0-flash',
			'model_candidates'      => 'gemini-2.0-flash-lite',
			'ollama_base_url'       => 'http://127.0.0.1:11434',
			'openai_base_url'       => 'https://api.openai.com/v1',
			'request_timeout'       => 22,
			'style_preset'          => 'default',
			'style_primary'         => '',
			'style_accent'          => '',
			'style_radius'          => '',
			'style_position'        => 'center-right',
			'widget_title'          => 'Agente IA',
			'widget_subtitle'       => 'Sistema en línea',
		);
	}

	public static function register_menu(): void {
		add_menu_page(
			__( 'Chatbot', 'chatbot-plugin-wp' ),
			__( 'Chatbot', 'chatbot-plugin-wp' ),
			'manage_options',
			'chatbot-plugin',
			array( __CLASS__, 'render_page' ),
			'dashicons-format-chat',
			58
		);
	}

	public static function register_settings(): void {
		register_setting(
			'chatbot_plugin_group',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
				'default'           => self::default_settings(),
			)
		);
	}

	/**
	 * @param array<string, mixed>|mixed $input
	 * @return array<string, mixed>
	 */
	public static function sanitize_settings( $input ): array {
		$defaults = self::default_settings();
		$input    = is_array( $input ) ? $input : array();
		$current  = Chatbot_Plugin::get_settings();

		$out = array();

		$out['widget_enabled']        = ! empty( $input['widget_enabled'] );
		$out['welcome_message']       = sanitize_textarea_field( $input['welcome_message'] ?? $defaults['welcome_message'] );
		$out['system_prompt']         = sanitize_textarea_field( $input['system_prompt'] ?? $defaults['system_prompt'] );
		$out['streaming_enabled']     = ! empty( $input['streaming_enabled'] );
		$out['rate_limit_per_minute'] = max( 1, min( 60, (int) ( $input['rate_limit_per_minute'] ?? $defaults['rate_limit_per_minute'] ) ) );

		$provider = sanitize_key( $input['provider'] ?? 'gemini' );
		$out['provider'] = in_array( $provider, array( 'gemini', 'ollama', 'openai_compatible' ), true ) ? $provider : 'gemini';

		$new_key = isset( $input['api_key'] ) ? trim( (string) $input['api_key'] ) : '';
		if ( '' !== $new_key ) {
			$out['api_key'] = $new_key;
		} else {
			$out['api_key'] = (string) ( $current['api_key'] ?? '' );
		}

		$out['model']            = sanitize_text_field( $input['model'] ?? $defaults['model'] );
		$out['model_candidates'] = sanitize_text_field( $input['model_candidates'] ?? $defaults['model_candidates'] );
		$out['ollama_base_url']  = esc_url_raw( $input['ollama_base_url'] ?? $defaults['ollama_base_url'] );
		$out['openai_base_url']  = esc_url_raw( $input['openai_base_url'] ?? $defaults['openai_base_url'] );
		$out['request_timeout']  = max( 5, min( 120, (int) ( $input['request_timeout'] ?? $defaults['request_timeout'] ) ) );

		$preset = sanitize_key( $input['style_preset'] ?? 'default' );
		$out['style_preset'] = in_array( $preset, array( 'default', 'dark-glass', 'minimal', 'ocean' ), true ) ? $preset : 'default';
		$out['style_primary']  = sanitize_hex_color( $input['style_primary'] ?? '' ) ?: '';
		$out['style_accent']   = sanitize_hex_color( $input['style_accent'] ?? '' ) ?: '';
		$out['style_radius']   = sanitize_text_field( $input['style_radius'] ?? '' );
		$position = sanitize_key( $input['style_position'] ?? 'center-right' );
		$out['style_position'] = in_array( $position, array( 'center-right', 'bottom-right' ), true ) ? $position : 'center-right';

		$out['widget_title']    = sanitize_text_field( $input['widget_title'] ?? $defaults['widget_title'] );
		$out['widget_subtitle'] = sanitize_text_field( $input['widget_subtitle'] ?? $defaults['widget_subtitle'] );

		return wp_parse_args( $out, $defaults );
	}

	public static function enqueue_admin_assets( string $hook ): void {
		if ( 'toplevel_page_chatbot-plugin' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'chatbot-plugin-admin',
			CHATBOT_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			CHATBOT_PLUGIN_VERSION
		);

		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( (string) $_GET['tab'] ) ) : 'general';
		if ( 'style' === $tab ) {
			wp_enqueue_style(
				'chatbot-plugin-admin-preview',
				CHATBOT_PLUGIN_URL . 'assets/css/chatbot.css',
				array( 'chatbot-plugin-admin' ),
				CHATBOT_PLUGIN_VERSION
			);
		}
	}

	public static function export_csv(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sin permisos.', 'chatbot-plugin-wp' ) );
		}
		check_admin_referer( 'chatbot_export_csv' );

		$days = isset( $_GET['days'] ) ? max( 1, min( 365, (int) $_GET['days'] ) ) : 30;
		$csv  = Chatbot_Telemetry::export_csv( $days );

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=chatbot-telemetry-' . gmdate( 'Y-m-d' ) . '.csv' );
		echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tab      = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( (string) $_GET['tab'] ) ) : 'general';
		$settings = Chatbot_Plugin::get_settings();
		$tabs     = array(
			'general' => __( 'General', 'chatbot-plugin-wp' ),
			'model'   => __( 'Modelo IA', 'chatbot-plugin-wp' ),
			'style'   => __( 'Estilo del chat', 'chatbot-plugin-wp' ),
			'stats'   => __( 'Estadísticas', 'chatbot-plugin-wp' ),
		);

		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'general';
		}

		$widget_on = ! empty( $settings['widget_enabled'] );
		?>
		<div class="wrap chatbot-admin-wrap">
			<header class="chatbot-admin-header">
				<div class="chatbot-admin-header__brand">
					<span class="chatbot-admin-header__icon dashicons dashicons-format-chat" aria-hidden="true"></span>
					<div>
						<h1><?php esc_html_e( 'Chatbot Plugin', 'chatbot-plugin-wp' ); ?></h1>
						<p class="chatbot-admin-header__desc">
							<?php esc_html_e( 'Configura el agente de IA, el proveedor y la apariencia del widget en tu sitio.', 'chatbot-plugin-wp' ); ?>
						</p>
					</div>
				</div>
				<span class="chatbot-admin-badge <?php echo $widget_on ? 'chatbot-admin-badge--on' : 'chatbot-admin-badge--off'; ?>">
					<?php
					echo $widget_on
						? esc_html__( 'Widget activo', 'chatbot-plugin-wp' )
						: esc_html__( 'Widget desactivado', 'chatbot-plugin-wp' );
					?>
				</span>
			</header>

			<nav class="nav-tab-wrapper chatbot-admin-nav" aria-label="<?php esc_attr_e( 'Secciones de configuración', 'chatbot-plugin-wp' ); ?>">
				<?php foreach ( $tabs as $id => $label ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=chatbot-plugin&tab=' . $id ) ); ?>"
						class="nav-tab<?php echo $tab === $id ? ' nav-tab-active' : ''; ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<?php if ( 'stats' === $tab ) : ?>
				<div class="chatbot-admin-body">
					<?php self::render_stats_tab(); ?>
				</div>
			<?php else : ?>
				<form method="post" action="options.php" class="chatbot-admin-form">
					<?php settings_fields( 'chatbot_plugin_group' ); ?>

					<div class="chatbot-admin-body">
						<?php if ( 'general' === $tab ) : ?>
							<?php self::render_general_fields( $settings ); ?>
						<?php elseif ( 'model' === $tab ) : ?>
							<?php self::render_model_fields( $settings ); ?>
						<?php elseif ( 'style' === $tab ) : ?>
							<?php self::render_style_fields( $settings ); ?>
						<?php endif; ?>
					</div>

					<div class="chatbot-admin-footer">
						<?php submit_button( __( 'Guardar cambios', 'chatbot-plugin-wp' ), 'primary', 'submit', false ); ?>
						<span class="chatbot-admin-footer__hint">
							<?php esc_html_e( 'Los cambios se aplican de inmediato en el sitio público.', 'chatbot-plugin-wp' ); ?>
						</span>
					</div>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * @param string $title
	 * @param string $description
	 */
	private static function card_open( string $title, string $description = '' ): void {
		?>
		<div class="chatbot-admin-card">
			<div class="chatbot-admin-card__head">
				<h2><?php echo esc_html( $title ); ?></h2>
				<?php if ( '' !== $description ) : ?>
					<p><?php echo esc_html( $description ); ?></p>
				<?php endif; ?>
			</div>
			<div class="chatbot-admin-card__body">
		<?php
	}

	private static function card_close(): void {
		?>
			</div>
		</div>
		<?php
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	private static function render_general_fields( array $settings ): void {
		self::card_open(
			__( 'Visibilidad y textos', 'chatbot-plugin-wp' ),
			__( 'Controla dónde aparece el chat y los mensajes que ve el visitante.', 'chatbot-plugin-wp' )
		);
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Widget global', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<label class="chatbot-admin-toggle">
						<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[widget_enabled]" value="1" <?php checked( ! empty( $settings['widget_enabled'] ) ); ?> />
						<span><?php esc_html_e( 'Mostrar en todo el sitio (wp_footer)', 'chatbot-plugin-wp' ); ?></span>
					</label>
					<p class="description"><?php esc_html_e( 'También puedes usar el shortcode [chatbot_widget].', 'chatbot-plugin-wp' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Mensaje de bienvenida', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<textarea name="<?php echo esc_attr( self::OPTION_KEY ); ?>[welcome_message]" rows="4" class="large-text"><?php echo esc_textarea( (string) $settings['welcome_message'] ); ?></textarea>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Instrucciones del sistema', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<textarea name="<?php echo esc_attr( self::OPTION_KEY ); ?>[system_prompt]" rows="5" class="large-text"><?php echo esc_textarea( (string) $settings['system_prompt'] ); ?></textarea>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Streaming simulado', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<label class="chatbot-admin-toggle">
						<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[streaming_enabled]" value="1" <?php checked( ! empty( $settings['streaming_enabled'] ) ); ?> />
						<span><?php esc_html_e( 'Activar respuesta por trozos', 'chatbot-plugin-wp' ); ?></span>
					</label>
				</td>
			</tr>
		</table>
		<?php
		self::card_close();

		self::card_open(
			__( 'Cabecera del widget', 'chatbot-plugin-wp' ),
			__( 'Título y subtítulo mostrados en la barra superior del chat.', 'chatbot-plugin-wp' )
		);
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Título del widget', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<input type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[widget_title]" value="<?php echo esc_attr( (string) $settings['widget_title'] ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Subtítulo', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<input type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[widget_subtitle]" value="<?php echo esc_attr( (string) $settings['widget_subtitle'] ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Límite por minuto (IP)', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<input type="number" min="1" max="60" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[rate_limit_per_minute]" value="<?php echo esc_attr( (string) $settings['rate_limit_per_minute'] ); ?>" class="small-text" />
					<p class="description"><?php esc_html_e( 'Protege contra abuso de la API por visitante.', 'chatbot-plugin-wp' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
		self::card_close();
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	private static function render_model_fields( array $settings ): void {
		$provider = (string) ( $settings['provider'] ?? 'gemini' );
		self::card_open(
			__( 'Proveedor de IA', 'chatbot-plugin-wp' ),
			__( 'Elige el motor y configura credenciales y modelos.', 'chatbot-plugin-wp' )
		);
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Proveedor', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[provider]" id="chatbot-provider">
						<option value="gemini" <?php selected( $provider, 'gemini' ); ?>>Google Gemini</option>
						<option value="ollama" <?php selected( $provider, 'ollama' ); ?>>Ollama</option>
						<option value="openai_compatible" <?php selected( $provider, 'openai_compatible' ); ?>>OpenAI-compatible</option>
					</select>
				</td>
			</tr>
			<tr class="chatbot-field-api-key">
				<th scope="row"><?php esc_html_e( 'API Key', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<input type="password" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[api_key]" value="" placeholder="<?php echo ! empty( $settings['api_key'] ) ? '••••••••' : ''; ?>" autocomplete="new-password" />
					<p class="description"><?php esc_html_e( 'Deja vacío para mantener la clave actual. En producción puedes definir CHATBOT_GEMINI_API_KEY o CHATBOT_OPENAI_API_KEY en wp-config.php.', 'chatbot-plugin-wp' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Modelo', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<input type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[model]" value="<?php echo esc_attr( (string) $settings['model'] ); ?>" />
					<p class="description"><?php esc_html_e( 'Ej: gemini-2.0-flash, llama3, gpt-4o-mini', 'chatbot-plugin-wp' ); ?></p>
				</td>
			</tr>
			<tr class="chatbot-field-gemini">
				<th scope="row"><?php esc_html_e( 'Modelos de respaldo (Gemini)', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<input type="text" class="large-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[model_candidates]" value="<?php echo esc_attr( (string) $settings['model_candidates'] ); ?>" />
					<p class="description"><?php esc_html_e( 'Separados por coma.', 'chatbot-plugin-wp' ); ?></p>
				</td>
			</tr>
			<tr class="chatbot-field-ollama">
				<th scope="row"><?php esc_html_e( 'URL base Ollama', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<input type="url" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[ollama_base_url]" value="<?php echo esc_attr( (string) $settings['ollama_base_url'] ); ?>" />
				</td>
			</tr>
			<tr class="chatbot-field-openai">
				<th scope="row"><?php esc_html_e( 'URL base OpenAI-compatible', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<input type="url" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[openai_base_url]" value="<?php echo esc_attr( (string) $settings['openai_base_url'] ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Timeout (segundos)', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<input type="number" min="5" max="120" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[request_timeout]" value="<?php echo esc_attr( (string) $settings['request_timeout'] ); ?>" />
				</td>
			</tr>
		</table>
		<script>
		(function () {
			const sel = document.getElementById('chatbot-provider');
			if (!sel) return;
			function toggle() {
				const v = sel.value;
				document.querySelectorAll('.chatbot-field-api-key').forEach(el => {
					el.style.display = v === 'ollama' ? 'none' : '';
				});
				document.querySelectorAll('.chatbot-field-gemini').forEach(el => {
					el.style.display = v === 'gemini' ? '' : 'none';
				});
				document.querySelectorAll('.chatbot-field-ollama').forEach(el => {
					el.style.display = v === 'ollama' ? '' : 'none';
				});
				document.querySelectorAll('.chatbot-field-openai').forEach(el => {
					el.style.display = v === 'openai_compatible' ? '' : 'none';
				});
			}
			sel.addEventListener('change', toggle);
			toggle();
		})();
		</script>
		<?php
		self::card_close();
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	private static function render_style_fields( array $settings ): void {
		$preset = (string) ( $settings['style_preset'] ?? 'default' );
		$title  = (string) ( $settings['widget_title'] ?? 'Agente IA' );
		?>
		<div class="chatbot-admin-layout chatbot-admin-layout--split">
			<div>
		<?php
		self::card_open(
			__( 'Apariencia', 'chatbot-plugin-wp' ),
			__( 'Presets y colores personalizados del widget.', 'chatbot-plugin-wp' )
		);
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Preset', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_preset]">
						<option value="default" <?php selected( $preset, 'default' ); ?>><?php esc_html_e( 'Por defecto', 'chatbot-plugin-wp' ); ?></option>
						<option value="dark-glass" <?php selected( $preset, 'dark-glass' ); ?>><?php esc_html_e( 'Dark glass', 'chatbot-plugin-wp' ); ?></option>
						<option value="minimal" <?php selected( $preset, 'minimal' ); ?>><?php esc_html_e( 'Minimal', 'chatbot-plugin-wp' ); ?></option>
						<option value="ocean" <?php selected( $preset, 'ocean' ); ?>><?php esc_html_e( 'Ocean', 'chatbot-plugin-wp' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Color primario', 'chatbot-plugin-wp' ); ?></th>
				<td><input type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_primary]" value="<?php echo esc_attr( (string) $settings['style_primary'] ); ?>" placeholder="#2563eb" class="regular-text" /></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Color acento', 'chatbot-plugin-wp' ); ?></th>
				<td><input type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_accent]" value="<?php echo esc_attr( (string) $settings['style_accent'] ); ?>" placeholder="#7c3aed" class="regular-text" /></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Radio de borde', 'chatbot-plugin-wp' ); ?></th>
				<td><input type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_radius]" value="<?php echo esc_attr( (string) $settings['style_radius'] ); ?>" placeholder="1.5rem" class="regular-text" /></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Posición', 'chatbot-plugin-wp' ); ?></th>
				<td>
					<select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_position]">
						<option value="center-right" <?php selected( $settings['style_position'] ?? '', 'center-right' ); ?>><?php esc_html_e( 'Centro derecha', 'chatbot-plugin-wp' ); ?></option>
						<option value="bottom-right" <?php selected( $settings['style_position'] ?? '', 'bottom-right' ); ?>><?php esc_html_e( 'Abajo derecha', 'chatbot-plugin-wp' ); ?></option>
					</select>
				</td>
			</tr>
		</table>
		<?php
		self::card_close();
		?>
			</div>
			<div class="chatbot-admin-preview-card">
				<div class="chatbot-admin-card">
					<div class="chatbot-admin-card__head">
						<h2><?php esc_html_e( 'Vista previa', 'chatbot-plugin-wp' ); ?></h2>
						<p><?php esc_html_e( 'Así se verá el panel con el preset seleccionado.', 'chatbot-plugin-wp' ); ?></p>
					</div>
					<div class="chatbot-admin-card__body">
						<div class="chatbot-admin-preview">
							<div class="chatbot-admin-preview__frame">
								<div id="chatbot-style-preview" class="cb-widget cb-preset-<?php echo esc_attr( $preset ); ?>" style="<?php echo esc_attr( self::preview_inline_style( $settings ) ); ?>">
									<p class="chatbot-admin-preview__title"><?php echo esc_html( $title ); ?></p>
									<p class="chatbot-admin-preview__sub"><?php echo esc_html( (string) ( $settings['widget_subtitle'] ?? '' ) ); ?></p>
									<span class="chatbot-admin-preview__bubble"><?php esc_html_e( '¡Hola! ¿En qué puedo ayudarte?', 'chatbot-plugin-wp' ); ?></span>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	private static function preview_inline_style( array $settings ): string {
		$parts = array();
		if ( ! empty( $settings['style_primary'] ) ) {
			$parts[] = '--cb-primary:' . $settings['style_primary'];
		}
		if ( ! empty( $settings['style_accent'] ) ) {
			$parts[] = '--cb-accent:' . $settings['style_accent'];
		}
		if ( ! empty( $settings['style_radius'] ) ) {
			$parts[] = '--cb-radius:' . $settings['style_radius'];
		}
		return implode( ';', $parts );
	}

	private static function render_stats_tab(): void {
		$days    = isset( $_GET['days'] ) ? max( 7, min( 90, (int) $_GET['days'] ) ) : 30;
		$summary = Chatbot_Telemetry::get_summary( $days );
		$page    = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
		$per     = 25;
		$events  = Chatbot_Telemetry::get_recent_events( $per, ( $page - 1 ) * $per );
		$total   = Chatbot_Telemetry::count_events();

		$totals = $summary['totals'] ?? array();
		$export_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=chatbot_export_csv&days=' . $days ),
			'chatbot_export_csv'
		);

		$periods = array( 7, 30, 90 );
		?>
		<div class="chatbot-admin-stats-toolbar">
			<p><?php esc_html_e( 'Telemetría de uso del chatbot en tu sitio.', 'chatbot-plugin-wp' ); ?></p>
			<div style="display:flex;flex-wrap:wrap;align-items:center;gap:0.75rem;">
				<div class="chatbot-admin-pills" role="group" aria-label="<?php esc_attr_e( 'Periodo', 'chatbot-plugin-wp' ); ?>">
					<?php foreach ( $periods as $p ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=chatbot-plugin&tab=stats&days=' . $p ) ); ?>"
							class="<?php echo (int) $days === $p ? 'is-active' : ''; ?>">
							<?php echo esc_html( sprintf( /* translators: %d: number of days */ __( '%dd', 'chatbot-plugin-wp' ), $p ) ); ?>
						</a>
					<?php endforeach; ?>
				</div>
				<a class="button chatbot-admin-export" href="<?php echo esc_url( $export_url ); ?>"><?php esc_html_e( 'Exportar CSV', 'chatbot-plugin-wp' ); ?></a>
			</div>
		</div>

		<div class="chatbot-admin-kpi-grid">
			<div class="chatbot-admin-kpi">
				<span class="chatbot-admin-kpi__label"><?php esc_html_e( 'Total peticiones', 'chatbot-plugin-wp' ); ?></span>
				<span class="chatbot-admin-kpi__value"><?php echo esc_html( number_format_i18n( (int) ( $totals['total_requests'] ?? 0 ) ) ); ?></span>
			</div>
			<div class="chatbot-admin-kpi chatbot-admin-kpi--success">
				<span class="chatbot-admin-kpi__label"><?php esc_html_e( 'Éxitos', 'chatbot-plugin-wp' ); ?></span>
				<span class="chatbot-admin-kpi__value"><?php echo esc_html( number_format_i18n( (int) ( $totals['success_count'] ?? 0 ) ) ); ?></span>
			</div>
			<div class="chatbot-admin-kpi chatbot-admin-kpi--error">
				<span class="chatbot-admin-kpi__label"><?php esc_html_e( 'Errores', 'chatbot-plugin-wp' ); ?></span>
				<span class="chatbot-admin-kpi__value"><?php echo esc_html( number_format_i18n( (int) ( $totals['error_count'] ?? 0 ) ) ); ?></span>
			</div>
			<div class="chatbot-admin-kpi">
				<span class="chatbot-admin-kpi__label"><?php esc_html_e( 'Latencia media', 'chatbot-plugin-wp' ); ?></span>
				<span class="chatbot-admin-kpi__value"><?php echo esc_html( number_format_i18n( (float) ( $totals['avg_latency_ms'] ?? 0 ), 0 ) ); ?> <small style="font-size:0.55em;font-weight:600;color:var(--cb-admin-muted);">ms</small></span>
			</div>
		</div>

		<div class="chatbot-admin-stats-grid">
			<div class="chatbot-admin-card">
				<div class="chatbot-admin-card__head">
					<h2><?php esc_html_e( 'Por estado', 'chatbot-plugin-wp' ); ?></h2>
				</div>
				<div class="chatbot-admin-card__body">
					<table class="widefat striped">
						<thead><tr><th><?php esc_html_e( 'Estado', 'chatbot-plugin-wp' ); ?></th><th><?php esc_html_e( 'Cantidad', 'chatbot-plugin-wp' ); ?></th></tr></thead>
						<tbody>
							<?php
							$by_status = (array) ( $summary['by_status'] ?? array() );
							if ( empty( $by_status ) ) :
								?>
								<tr><td colspan="2"><?php esc_html_e( 'Sin datos en este periodo.', 'chatbot-plugin-wp' ); ?></td></tr>
							<?php else : ?>
								<?php foreach ( $by_status as $row ) : ?>
									<tr><td><?php echo esc_html( (string) ( $row['status'] ?? '' ) ); ?></td><td><?php echo esc_html( number_format_i18n( (int) ( $row['count'] ?? 0 ) ) ); ?></td></tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
			<div class="chatbot-admin-card">
				<div class="chatbot-admin-card__head">
					<h2><?php esc_html_e( 'Por proveedor', 'chatbot-plugin-wp' ); ?></h2>
				</div>
				<div class="chatbot-admin-card__body">
					<table class="widefat striped">
						<thead><tr><th><?php esc_html_e( 'Proveedor', 'chatbot-plugin-wp' ); ?></th><th><?php esc_html_e( 'Cantidad', 'chatbot-plugin-wp' ); ?></th></tr></thead>
						<tbody>
							<?php
							$by_provider = (array) ( $summary['by_provider'] ?? array() );
							if ( empty( $by_provider ) ) :
								?>
								<tr><td colspan="2"><?php esc_html_e( 'Sin datos en este periodo.', 'chatbot-plugin-wp' ); ?></td></tr>
							<?php else : ?>
								<?php foreach ( $by_provider as $row ) : ?>
									<tr><td><?php echo esc_html( (string) ( $row['provider'] ?? '' ) ); ?></td><td><?php echo esc_html( number_format_i18n( (int) ( $row['count'] ?? 0 ) ) ); ?></td></tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<div class="chatbot-admin-card chatbot-admin-events">
			<div class="chatbot-admin-card__head">
				<h2><?php esc_html_e( 'Últimos eventos', 'chatbot-plugin-wp' ); ?></h2>
				<p><?php esc_html_e( 'Registro detallado de las peticiones más recientes.', 'chatbot-plugin-wp' ); ?></p>
			</div>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Fecha', 'chatbot-plugin-wp' ); ?></th>
						<th><?php esc_html_e( 'Proveedor', 'chatbot-plugin-wp' ); ?></th>
						<th><?php esc_html_e( 'Modelo', 'chatbot-plugin-wp' ); ?></th>
						<th><?php esc_html_e( 'Estado', 'chatbot-plugin-wp' ); ?></th>
						<th><?php esc_html_e( 'Latencia', 'chatbot-plugin-wp' ); ?></th>
						<th><?php esc_html_e( 'Error', 'chatbot-plugin-wp' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $events ) ) : ?>
						<tr><td colspan="6"><?php esc_html_e( 'Sin eventos aún.', 'chatbot-plugin-wp' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $events as $event ) : ?>
							<?php
							$status = (string) ( $event['status'] ?? '' );
							$is_ok  = in_array( $status, array( 'ok', 'success' ), true );
							?>
							<tr>
								<td><?php echo esc_html( (string) ( $event['created_at'] ?? '' ) ); ?></td>
								<td><?php echo esc_html( (string) ( $event['provider'] ?? '' ) ); ?></td>
								<td><?php echo esc_html( (string) ( $event['model'] ?? '' ) ); ?></td>
								<td>
									<span class="chatbot-admin-status <?php echo $is_ok ? 'chatbot-admin-status--ok' : 'chatbot-admin-status--err'; ?>">
										<?php echo esc_html( $status ); ?>
									</span>
								</td>
								<td><?php echo esc_html( number_format_i18n( (int) ( $event['latency_ms'] ?? 0 ) ) ); ?> ms</td>
								<td><?php echo esc_html( (string) ( $event['error_code'] ?? '—' ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		<?php
		$pages = (int) ceil( $total / $per );
		if ( $pages > 1 ) {
			echo '<nav class="chatbot-admin-tablenav" aria-label="' . esc_attr__( 'Paginación', 'chatbot-plugin-wp' ) . '">';
			for ( $i = 1; $i <= min( $pages, 10 ); $i++ ) {
				$url = admin_url( 'admin.php?page=chatbot-plugin&tab=stats&days=' . $days . '&paged=' . $i );
				$class = $page === $i ? ' style="color:var(--cb-admin-primary);border-color:var(--cb-admin-primary);"' : '';
				echo '<a href="' . esc_url( $url ) . '"' . $class . '>' . esc_html( (string) $i ) . '</a>';
			}
			echo '</nav>';
		}
		?>
		</div>
		<?php
	}
}
