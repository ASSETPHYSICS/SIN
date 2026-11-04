<?php
/**
 * Einzige Vorlage: gibt Kopf und Body der Seite aus (ohne the_content-Filter wie wpautop).
 * Alles andere (404, Archive, Suche) bekommt die Fehlerseite der Sprache (fallback-de|en.html, vom Import aus
 * content/v1-build/_fehler/ kopiert; HA 5.2).
 */

defined( 'ABSPATH' ) || exit;

$sin_post = is_singular( 'page' ) ? get_queried_object() : null;
$sin_show = $sin_post && 'publish' === $sin_post->post_status && '' !== sin_page_meta( '_v1_file' );

if ( $sin_show ) {
	$sin_lang = sin_page_meta( '_v1_lang' );
	$sin_head = sin_page_meta( '_v1_head' );
	$sin_body = $sin_post->post_content;
} else {
	status_header( 404 );
	nocache_headers();
	$sin_lang = sin_lang();
	$sin_doc  = (string) @file_get_contents( get_theme_file_path( "fallback-$sin_lang.html" ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	preg_match( '#<head>\n?(.*?)\n?</head>\s*<body>\n?(.*?)\n?</body>#s', $sin_doc, $sin_m );
	$sin_head = $sin_m[1] ?? '<meta charset="utf-8"><title>404</title>';
	$sin_body = $sin_m[2] ?? '';
}
?><!doctype html>
<html lang="<?php echo esc_attr( $sin_lang ); ?>">
<head>
<?php echo $sin_head . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- Kopf aus dem Build, nur per Import (wp-cli) gesetzt. ?>
</head>
<body>
<?php echo sin_render_body( $sin_body ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- HTML aus dem Build, nur per Import gesetzt. ?>
</body>
</html>
