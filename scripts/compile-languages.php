<?php
/**
 * Compile languages/*.po to .mo (WordPress POMO).
 *
 * Usage: php scripts/compile-languages.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	if ( 'cli' === php_sapi_name() ) {
		define( 'ABSPATH', dirname( __DIR__ ) . '/' );
	} else {
		exit;
	}
}

$multch_root     = dirname( __DIR__ );
$multch_lang_dir = $multch_root . '/languages';

require_once $multch_root . '/scripts/lib/pomo/po.php';
require_once $multch_root . '/scripts/lib/pomo/mo.php';

$multch_files = glob( $multch_lang_dir . '/multiai-chatbot-*.po' );
if ( ! $multch_files ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI dev script; static message only.
	echo "Error: No .po files found in languages/\n";
	exit( 1 );
}

foreach ( $multch_files as $multch_po_path ) {
	$multch_mo_path = preg_replace( '/\.po$/', '.mo', $multch_po_path );
	$multch_po      = new PO();
	if ( ! $multch_po->import_from_file( $multch_po_path ) ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI dev script; path escaped below.
		echo 'Error: Failed to import: ' . esc_html( $multch_po_path ) . PHP_EOL;
		exit( 1 );
	}
	$multch_mo = new MO();
	$multch_mo->entries      = $multch_po->entries;
	$multch_mo->headers      = $multch_po->headers;
	$multch_mo->set_header( 'Project-Id-Version', 'MultiAI ChatBot' );
	if ( ! $multch_mo->export_to_file( $multch_mo_path ) ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI dev script; path escaped below.
		echo 'Error: Failed to write: ' . esc_html( $multch_mo_path ) . PHP_EOL;
		exit( 1 );
	}
	echo 'compiled: ' . esc_html( basename( $multch_mo_path ) ) . PHP_EOL;
}
