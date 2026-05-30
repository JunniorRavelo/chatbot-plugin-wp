<?php
/**
 * Footer de donación del panel de administración.
 *
 * Edita las URLs abajo para activar cada enlace. Deja vacío ("") para mostrar
 * el chip desactivado hasta que añadas la URL.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Chatbot_Donation_Footer {

    /**
     * URLs de donación — completa cada valor cuando la tengas.
     *
     * @return array<string, string> slug => url
     */
    private static function donation_urls(): array {
        return array(
            'ko_fi'           => '',
            'paypal'          => '',
            'buy_me_a_coffee' => '',
            'github_sponsors' => '',
            'patreon'         => '',
        );
    }

    private static function svg_icon( string $path_d ): string {
        return sprintf(
            '<svg class="chatbot-donation-footer__svg" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">%s</svg>',
            $path_d
        );
    }

    /**
     * @return array<string, array{label: string, svg: string}>
     */
    private static function donation_icons(): array {
        return array(
            'ko_fi'           => array(
                'label' => 'Ko-fi',
                'svg'   => self::svg_icon( '<path fill="currentColor" d="M23.881 8.948c-.773-4.085-4.859-4.593-4.859-4.593H.723c-.604 0-.679.798-.679.798s-.082 7.324-.082 11.822c0 4.747 4.413 4.742 4.413 4.742h6.406v-4.111h-3.897c-3.87 0-3.231-3.648-3.231-3.648s-.496-3.12 2.853-3.12h3.275V9.765c0-2.274 1.567-2.274 1.567-2.274h3.273V.691S23.881-.139 23.881 8.948z"/>' ),
            ),
            'paypal'          => array(
                'label' => 'PayPal',
                'svg'   => self::svg_icon( '<path fill="currentColor" d="M7.076 21.337H2.47a.641.641 0 0 1-.633-.74L4.944 3.72a.771.771 0 0 1 .762-.658h6.092c2.035 0 3.597.448 4.642 1.33 1.046.883 1.573 2.19 1.573 3.868 0 .96-.18 1.878-.537 2.73-.357.852-.88 1.585-1.567 2.19-.687.605-1.534 1.08-2.535 1.42-1.001.34-2.14.51-3.412.51H9.313l-.96 5.237h-1.277zm.633-6.293h1.205c1.17 0 2.033-.25 2.583-.748.55-.499.825-1.24.825-2.223 0-.653-.15-1.17-.448-1.55-.299-.38-.748-.57-1.348-.57H8.01l-.301 5.09z"/>' ),
            ),
            'buy_me_a_coffee' => array(
                'label' => 'Buy Me a Coffee',
                'svg'   => self::svg_icon( '<path fill="currentColor" d="M20.216 6.415h-.132c-.229-.955-.666-1.788-1.296-2.448a4.035 4.035 0 0 0-1.863-1.078 6.865 6.865 0 0 0-1.621-.19H6.79a.79.79 0 0 0-.78.78v.126c.042.622.127 1.237.127 1.846v8.415c0 .622.085 1.237.127 1.846.042.622.127 1.237.127 1.846v.126a.79.79 0 0 0 .78.78h8.415c1.846-.085 3.38-1.293 3.763-3.009h.132c.96 0 1.763-.803 1.763-1.763V8.178c0-.96-.803-1.763-1.763-1.763zm-2.415 4.415H8.415v3h9.386v-3z"/>' ),
            ),
            'github_sponsors' => array(
                'label' => 'GitHub',
                'svg'   => self::svg_icon( '<path fill="currentColor" d="M12 0C5.374 0 0 5.373 0 12c0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23A11.509 11.509 0 0 1 12 5.803c1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576C20.566 21.797 24 17.3 24 12c0-6.627-5.373-12-12-12z"/>' ),
            ),
            'patreon'         => array(
                'label' => 'Patreon',
                'svg'   => self::svg_icon( '<path fill="currentColor" d="M15.386.524c-4.764 0-8.64 3.876-8.64 8.64 0 4.75 3.876 8.613 8.64 8.613 4.75 0 8.613-3.863 8.613-8.613C23.999 4.4 20.136.524 15.386.524zM.003 23.537h4.22V.524H.003v23.013z"/>' ),
            ),
        );
    }

    /**
     * @param string                            $slug
     * @param array{label: string, svg: string} $icon
     * @param string                            $url
     */
    private static function render_chip( string $slug, array $icon, string $url ): void {
        $label   = (string) $icon['label'];
        $active  = '' !== $url;
        $classes = 'chatbot-donation-footer__chip chatbot-donation-footer__chip--' . esc_attr( $slug );

        if ( ! $active ) {
            $classes .= ' chatbot-donation-footer__chip--pending';
        }

        $title = $active
            ? $label
            : sprintf(
                /* translators: %s: platform name */
                __( '%s — añade la URL en donation-footer.php', 'chatbot-plugin-wp' ),
                $label
            );

        if ( $active ) {
            printf(
                '<a class="%1$s" href="%2$s" target="_blank" rel="noopener noreferrer" title="%3$s" role="listitem">%4$s<span class="chatbot-donation-footer__chip-label">%5$s</span></a>',
                esc_attr( $classes ),
                esc_url( $url ),
                esc_attr( $title ),
                $icon['svg'], // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG estático
                esc_html( $label )
            );
            return;
        }

        printf(
            '<span class="%1$s" title="%2$s" aria-disabled="true" role="listitem">%3$s<span class="chatbot-donation-footer__chip-label">%4$s</span></span>',
            esc_attr( $classes ),
            esc_attr( $title ),
            $icon['svg'], // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG estático
            esc_html( $label )
        );
    }

    public static function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $urls  = self::donation_urls();
        $icons = self::donation_icons();
        ?>
        <footer class="chatbot-donation-footer" aria-label="<?php esc_attr_e( 'Apoyar el plugin', 'chatbot-plugin-wp' ); ?>">
            <div class="chatbot-donation-footer__inner">
                <p class="chatbot-donation-footer__text">
                    <span class="chatbot-donation-footer__heart" aria-hidden="true">♥</span>
                    <?php esc_html_e( '¿Te gusta el plugin? Puedes apoyar el desarrollo donando en:', 'chatbot-plugin-wp' ); ?>
                </p>
                <div class="chatbot-donation-footer__chips" role="list" aria-label="<?php esc_attr_e( 'Plataformas de donación', 'chatbot-plugin-wp' ); ?>">
                    <?php foreach ( $icons as $slug => $icon ) : ?>
                        <?php self::render_chip( $slug, $icon, trim( (string) ( $urls[ $slug ] ?? '' ) ) ); ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </footer>
        <?php
    }
}