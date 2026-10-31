<?php
/**
 * Links that survive a change of domain.
 *
 * A site is usually built on a temporary address and moved to the client's domain later. Links
 * typed as full addresses of the site itself (https://temp-host.example/about/) would keep
 * pointing at the old address. So:
 *
 * - When a link field or a `[words](url)` link in a paragraph is saved, an address on this site
 *   is stored as a path (/about/), which always follows the current domain.
 * - The plugin remembers the site's earlier addresses (it notices when the home URL changes), and
 *   links already stored with one of them are printed with the current address. Add more with the
 *   `elroi_site_hosts` filter.
 *
 * @package ElRoiSiteEditor
 */

defined( 'ABSPATH' ) || exit;

/**
 * Host names that mean "this site": the current one (with and without www), earlier ones.
 */
function elroi_site_hosts() {
	$host  = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	$hosts = array( $host, str_starts_with( $host, 'www.' ) ? substr( $host, 4 ) : 'www.' . $host );
	$hosts = array_merge( $hosts, (array) get_option( 'elroi_previous_hosts', array() ) );
	return array_values( array_unique( array_filter( array_map( 'strtolower', (array) apply_filters( 'elroi_site_hosts', $hosts ) ) ) ) );
}

/**
 * Remember the previous address when the home URL changes (runs on the first request after the move).
 */
function elroi_note_home_host() {
	$host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	$was  = (string) get_option( 'elroi_home_host', '' );
	if ( $host === $was || '' === $host ) {
		return;
	}
	if ( '' !== $was ) {
		$prev = (array) get_option( 'elroi_previous_hosts', array() );
		$prev = array_values( array_diff( array_unique( array_merge( $prev, array( $was ) ) ), array( $host ) ) );
		update_option( 'elroi_previous_hosts', $prev, true );
	}
	update_option( 'elroi_home_host', $host, true );
}
add_action( 'init', 'elroi_note_home_host', 1 );

/**
 * A full address on this site (current or earlier domain) as a site path; anything else unchanged.
 * "https://old.example/about/?a=1#team" gives "/about/?a=1#team".
 *
 * @param string $url Link.
 */
function elroi_local_path( $url ) {
	$url = trim( (string) $url );
	if ( ! preg_match( '#^(https?:)?//#i', $url ) ) {
		return $url;
	}
	$p = wp_parse_url( $url );
	if ( empty( $p['host'] ) || ! in_array( strtolower( $p['host'] ), elroi_site_hosts(), true ) ) {
		return $url;
	}
	$path = $p['path'] ?? '/';
	// WordPress in a subfolder: home_url() adds the folder back.
	$base = rtrim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
	if ( '' !== $base && str_starts_with( $path, $base . '/' ) ) {
		$path = substr( $path, strlen( $base ) );
	}
	return ( '' === $path ? '/' : $path ) . ( isset( $p['query'] ) ? '?' . $p['query'] : '' ) . ( isset( $p['fragment'] ) ? '#' . $p['fragment'] : '' );
}

/**
 * Text with `[words](https://this-site/...)` links turned into `[words](/...)`.
 *
 * @param string $text Text.
 */
function elroi_local_links( $text ) {
	if ( ! is_string( $text ) || ! str_contains( $text, '](' ) ) {
		return $text;
	}
	return preg_replace_callback( '/\[([^\]]+)\]\(([^)\s]+)\)/u', fn( $m ) => '[' . $m[1] . '](' . elroi_local_path( $m[2] ) . ')', $text );
}
