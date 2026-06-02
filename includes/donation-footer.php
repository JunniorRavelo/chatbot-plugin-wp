<?php
/**
 * Admin panel donation footer.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Multch_Donation_Footer {

	private const GITHUB_SPONSORS_URL = 'https://github.com/sponsors/JunniorRavelo';

	private static function plugin_slug(): string {
		return dirname( plugin_basename( MULTCH_PLUGIN_FILE ) );
	}

	private static function share_url(): string {
		return 'https://wordpress.org/plugins/' . self::plugin_slug() . '/';
	}

	private static function review_url(): string {
		return 'https://wordpress.org/support/plugin/' . self::plugin_slug() . '/reviews/#new-post';
	}

	private static function github_icon(): string {
		return sprintf(
			'<svg class="multch-donation-footer__svg" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 0C5.374 0 0 5.373 0 12c0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23A11.509 11.509 0 0 1 12 5.803c1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576C20.566 21.797 24 17.3 24 12c0-6.627-5.373-12-12-12z"/></svg>'
		);
	}

	private static function share_icon(): string {
		return sprintf(
			'<svg class="multch-donation-footer__svg" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92s2.92-1.31 2.92-2.92-1.31-2.92-2.92-2.92z"/></svg>'
		);
	}

	private static function star_icon(): string {
		return sprintf(
			'<svg class="multch-donation-footer__svg" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 17.27 18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>'
		);
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$share_url = self::share_url();
		?>
		<footer class="multch-donation-footer" aria-label="<?php esc_attr_e( 'Support the plugin', 'multiai-chatbot' ); ?>">
			<div class="multch-donation-footer__inner">
				<div class="multch-donation-footer__message">
					<p class="multch-donation-footer__lead">
						<span class="multch-donation-footer__heart" aria-hidden="true">♥</span>
						<?php esc_html_e( 'Enjoying MultiAI ChatBot?', 'multiai-chatbot' ); ?>
					</p>
					<p class="multch-donation-footer__sub">
						<?php esc_html_e( 'Sponsor development, share the plugin, or leave a review — every bit helps.', 'multiai-chatbot' ); ?>
					</p>
				</div>
				<div class="multch-donation-footer__chips" role="group" aria-label="<?php esc_attr_e( 'Ways to support', 'multiai-chatbot' ); ?>">
					<a
						class="multch-donation-footer__chip multch-donation-footer__chip--github_sponsors"
						href="<?php echo esc_url( self::GITHUB_SPONSORS_URL ); ?>"
						target="_blank"
						rel="noopener noreferrer"
						title="<?php esc_attr_e( 'GitHub Sponsors', 'multiai-chatbot' ); ?>"
					>
						<?php echo self::github_icon(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG estático ?>
						<span class="multch-donation-footer__chip-label"><?php esc_html_e( 'Sponsor', 'multiai-chatbot' ); ?></span>
					</a>
					<button
						type="button"
						class="multch-donation-footer__chip multch-donation-footer__chip--share"
						data-copy-url="<?php echo esc_url( $share_url ); ?>"
						data-copied-label="<?php esc_attr_e( 'Link copied', 'multiai-chatbot' ); ?>"
						title="<?php esc_attr_e( 'Copy plugin link to share', 'multiai-chatbot' ); ?>"
					>
						<?php echo self::share_icon(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG estático ?>
						<span class="multch-donation-footer__chip-label"><?php esc_html_e( 'Share', 'multiai-chatbot' ); ?></span>
					</button>
					<a
						class="multch-donation-footer__chip multch-donation-footer__chip--review"
						href="<?php echo esc_url( self::review_url() ); ?>"
						target="_blank"
						rel="noopener noreferrer"
						title="<?php esc_attr_e( 'Write a review on WordPress.org', 'multiai-chatbot' ); ?>"
					>
						<?php echo self::star_icon(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG estático ?>
						<span class="multch-donation-footer__chip-label"><?php esc_html_e( 'Review', 'multiai-chatbot' ); ?></span>
					</a>
				</div>
			</div>
		</footer>
		<?php
	}
}
