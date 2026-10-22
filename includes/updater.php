<?php
/**
 * Updates from the private GitHub repository: when a newer release exists, WordPress shows the
 * usual "update available" and installs it with the Update button.
 *
 * Each site needs a read-only GitHub token in wp-config.php (never in this repository):
 *   define( 'ELROI_SE_GITHUB_TOKEN', 'github_pat_...' );
 * A release must have a tag like v1.2.0 and an asset named elroi-site-editor.zip whose top folder
 * is elroi-site-editor/ (bin/release.sh builds it).
 *
 * @package ElRoiSiteEditor
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'ELROI_SE_REPO' ) ) {
	define( 'ELROI_SE_REPO', 'titosoriano/elroi-site-editor' );
}

/**
 * Request headers for the GitHub API.
 *
 * @param string $accept Accept header.
 */
function elroi_se_github_headers( $accept = 'application/vnd.github+json' ) {
	return array(
		'Accept'               => $accept,
		'Authorization'        => 'Bearer ' . ELROI_SE_GITHUB_TOKEN,
		'X-GitHub-Api-Version' => '2022-11-28',
		'User-Agent'           => 'elroi-site-editor/' . ELROI_SE_VERSION,
	);
}

/**
 * Latest release: version, notes, package URL, date. Cached for 6 hours; null when unavailable.
 */
function elroi_se_latest_release() {
	if ( ! defined( 'ELROI_SE_GITHUB_TOKEN' ) || ! ELROI_SE_GITHUB_TOKEN ) {
		return null;
	}
	$cached = get_site_transient( 'elroi_se_release' );
	if ( false !== $cached ) {
		return $cached ? $cached : null;
	}
	$res     = wp_remote_get( 'https://api.github.com/repos/' . ELROI_SE_REPO . '/releases/latest', array( 'headers' => elroi_se_github_headers(), 'timeout' => 10 ) );
	$release = '';
	if ( 200 === wp_remote_retrieve_response_code( $res ) ) {
		$data = json_decode( wp_remote_retrieve_body( $res ), true );
		foreach ( (array) ( $data['assets'] ?? array() ) as $asset ) {
			if ( 'elroi-site-editor.zip' === ( $asset['name'] ?? '' ) ) {
				$release = array(
					'version' => ltrim( (string) $data['tag_name'], 'v' ),
					'notes'   => (string) ( $data['body'] ?? '' ),
					'package' => (string) $asset['url'],
					'date'    => (string) ( $data['published_at'] ?? '' ),
				);
			}
		}
	}
	set_site_transient( 'elroi_se_release', $release, $release ? 6 * HOUR_IN_SECONDS : HOUR_IN_SECONDS );
	return $release ? $release : null;
}

add_filter(
	'pre_set_site_transient_update_plugins',
	function ( $transient ) {
		$release = elroi_se_latest_release();
		if ( ! is_object( $transient ) || ! $release || ! version_compare( $release['version'], ELROI_SE_VERSION, '>' ) ) {
			return $transient;
		}
		$file                          = plugin_basename( ELROI_SE_FILE );
		$transient->response[ $file ] = (object) array(
			'slug'        => 'elroi-site-editor',
			'plugin'      => $file,
			'new_version' => $release['version'],
			'package'     => $release['package'],
			'url'         => 'https://github.com/' . ELROI_SE_REPO,
		);
		return $transient;
	}
);

add_filter(
	'plugins_api',
	function ( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || 'elroi-site-editor' !== ( $args->slug ?? '' ) ) {
			return $result;
		}
		$release = elroi_se_latest_release();
		return (object) array(
			'name'          => 'ElRoi Site Editor',
			'slug'          => 'elroi-site-editor',
			'version'       => $release ? $release['version'] : ELROI_SE_VERSION,
			'author'        => 'ElRoi Marketing',
			'last_updated'  => $release ? $release['date'] : '',
			'sections'      => array( 'changelog' => $release ? nl2br( esc_html( $release['notes'] ) ) : '' ),
			'download_link' => $release ? $release['package'] : '',
		);
	},
	10,
	3
);

// GitHub answers the asset URL with a redirect to a signed download link that must not receive
// the token, so resolve the redirect here and download the signed link without it.
add_filter(
	'upgrader_pre_download',
	function ( $reply, $package ) {
		$prefix = 'https://api.github.com/repos/' . ELROI_SE_REPO . '/releases/assets/';
		if ( ! is_string( $package ) || ! str_starts_with( $package, $prefix ) || ! defined( 'ELROI_SE_GITHUB_TOKEN' ) ) {
			return $reply;
		}
		$res = wp_remote_get(
			$package,
			array(
				'headers'     => elroi_se_github_headers( 'application/octet-stream' ),
				'redirection' => 0,
				'timeout'     => 30,
			)
		);
		$to  = wp_remote_retrieve_header( $res, 'location' );
		if ( ! $to ) {
			return new WP_Error( 'elroi_se_download', 'Could not get the update from GitHub. Check ELROI_SE_GITHUB_TOKEN in wp-config.php.' );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		return download_url( $to );
	},
	10,
	2
);
