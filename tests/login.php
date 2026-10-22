<?php
/**
 * Local Playground only: signs the browser in as user 1 and opens wp-admin. Never shipped
 * (tests/ is left out of the release zip).
 *
 * @package ElRoiSiteEditor
 */

require dirname( __DIR__ ) . '/wp-load.php';
if ( ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( '127.0.0.1', 'localhost' ), true ) ) {
	exit;
}
wp_set_auth_cookie( 1 );
wp_safe_redirect( admin_url( isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
exit;
