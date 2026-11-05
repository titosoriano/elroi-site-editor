<?php
/**
 * Recommended plugins: the set ElRoi installs on every client site, so nothing is forgotten on
 * a new WordPress install.
 *
 * - Plugins > ElRoi recommended lists each one with why it is needed, whether it is active, an
 *   Install or Activate button, and what to set up after installing.
 * - Administrators see a notice on the Dashboard and the Plugins screen while any is missing
 *   (dismissible; it comes back when the missing set changes). Tools > Site Health reports it too.
 * - AI agents read the same list through the `elroi/recommended-plugins` ability (or
 *   elroi_recommended_status() through the site's MCP), tell the user which are missing, and
 *   install them with the user's OK (`wp plugin install <slug> --activate`).
 *
 * Add, change or remove entries with the `elroi_recommended_plugins` filter.
 *
 * @package ElRoiSiteEditor
 */

defined( 'ABSPATH' ) || exit;

/** Whether the site runs on a LiteSpeed web server (Hostinger and others). */
function elroi_on_litespeed() {
	$server = isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : '';
	return (bool) apply_filters( 'elroi_on_litespeed', false !== stripos( $server, 'litespeed' ) || defined( 'LSCWP_V' ) );
}

/**
 * The recommended plugins: slug => [ name, file, level (required|recommended|optional), why,
 * setup (what to do after installing), source (wordpress.org or a download page), applies ].
 *
 * @return array<string, array>
 */
function elroi_recommended_plugins() {
	$list = array(
		'seo-by-rank-math'   => array(
			'name'  => 'Rank Math SEO',
			'file'  => 'seo-by-rank-math/rank-math.php',
			'level' => 'required',
			'why'   => 'Titles, descriptions, sitemaps, structured data and the page score. ElRoi Site Editor makes it read designed pages as visitors see them.',
			'setup' => 'Run the setup wizard (Easy mode is fine). Titles & Meta > Local SEO: "Organization" with the business details. Turn on the Instant Indexing module for Bing (IndexNow). Keep every page and post at 80+.',
		),
		'litespeed-cache'    => array(
			'name'    => 'LiteSpeed Cache',
			'file'    => 'litespeed-cache/litespeed-cache.php',
			'level'   => 'recommended',
			'why'     => 'Page cache on LiteSpeed servers (Hostinger): pages load from a ready-made copy.',
			'setup'   => 'With ElRoi Starter: Tools > Site Health > "Speed settings" > Apply the recommended settings (keeps the animations working). Never turn on "Load JS Delayed", JS combine, UCSS or LiteSpeed lazy load. Not on a LiteSpeed server? Use the host\'s cache instead.',
			'applies' => elroi_on_litespeed(),
		),
		'better-wp-security' => array(
			'name'  => 'Kadence Security',
			'file'  => 'better-wp-security/better-wp-security.php',
			'level' => 'required',
			'why'   => 'Brute-force protection, two-factor sign-in and file hardening.',
			'setup' => 'Run its setup. Turn on brute-force protection and two-factor for administrators. Do not hide or rename the login page: it can cut off the AI agent connection (MCP).',
		),
		'updraftplus'        => array(
			'name'  => 'UpdraftPlus',
			'file'  => 'updraftplus/updraftplus.php',
			'level' => 'required',
			'why'   => 'Daily backups to Google Drive or Dropbox, so the site can be restored after a mistake or a hack.',
			'setup' => 'Settings > UpdraftPlus Backups > Settings: files and database daily, keep at least 7 file and 14 database copies, remote storage Google Drive or Dropbox (connect it). Run "Backup Now" once. ElRoi Starter checks this in Site Health.',
		),
		'fluent-smtp'        => array(
			'name'  => 'FluentSMTP',
			'file'  => 'fluent-smtp/fluent-smtp.php',
			'level' => 'recommended',
			'why'   => 'Sends the site\'s emails (form messages, password resets) through a real mailbox so they do not land in spam.',
			'setup' => 'Can wait until the design is finished, or skip it when a CRM form handles leads. Connect the mailbox (Hostinger email: smtp.hostinger.com, port 465, SSL), then send a test email.',
		),
		'google-site-kit'    => array(
			'name'  => 'Site Kit by Google',
			'file'  => 'google-site-kit/google-site-kit.php',
			'level' => 'recommended',
			'why'   => 'Connects Google Search Console and Analytics, with reports in wp-admin.',
			'setup' => 'Connect it after the final domain is live: sign in with the Google account that owns the client\'s Search Console and Analytics.',
		),
		'novamira'           => array(
			'name'   => 'Novamira',
			'file'   => 'novamira/novamira.php',
			'level'  => 'optional',
			'why'    => 'Lets AI agents work on the site through MCP (read and change files, run WP-CLI).',
			'setup'  => 'Download it from novamira.ai and upload it. Connect it to the agent with OAuth. Remove it, or revoke its connections, when the project is handed over if the client should not have agent access.',
			'source' => 'https://novamira.ai/',
		),
	);
	/**
	 * Filters the recommended plugins.
	 *
	 * @param array $list slug => [ name, file, level, why, setup, source (default: its wordpress.org
	 *                    page), applies (default true) ].
	 */
	$list = (array) apply_filters( 'elroi_recommended_plugins', $list );
	foreach ( $list as $slug => $p ) {
		$list[ $slug ] = wp_parse_args(
			$p,
			array(
				'source'  => 'https://wordpress.org/plugins/' . $slug . '/',
				'applies' => true,
				'level'   => 'recommended',
				'why'     => '',
				'setup'   => '',
			)
		);
	}
	return $list;
}

/**
 * Each recommended plugin with its state on this site: active, inactive (installed) or missing.
 *
 * @return array<string, array>
 */
function elroi_recommended_status() {
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$installed = get_plugins();
	$out       = array();
	foreach ( elroi_recommended_plugins() as $slug => $p ) {
		$file = $p['file'];
		if ( ! isset( $installed[ $file ] ) ) {
			// Installed in a differently named folder (e.g. a zip upload)?
			foreach ( array_keys( $installed ) as $f ) {
				if ( basename( $f ) === basename( $file ) && str_starts_with( dirname( $f ), $slug ) ) {
					$file = $f;
					break;
				}
			}
		}
		$state                = ! isset( $installed[ $file ] ) ? 'missing' : ( is_plugin_active( $file ) ? 'active' : 'inactive' );
		$from_wporg           = str_starts_with( $p['source'], 'https://wordpress.org/plugins/' );
		$out[ $slug ]         = $p;
		$out[ $slug ]['file'] = $file;
		$out[ $slug ]['state'] = $state;
		$out[ $slug ]['cli']  = 'active' === $state ? '' : ( 'inactive' === $state ? 'wp plugin activate ' . $slug : ( $from_wporg ? 'wp plugin install ' . $slug . ' --activate' : '' ) );
	}
	return $out;
}

/**
 * The plugins still to install or activate (required and recommended ones that apply here).
 *
 * @return array<string, array>
 */
function elroi_recommended_missing() {
	return array_filter(
		elroi_recommended_status(),
		fn( $p ) => $p['applies'] && 'active' !== $p['state'] && in_array( $p['level'], array( 'required', 'recommended' ), true )
	);
}

/**
 * Install or Activate link for one plugin (empty when active or not on wordpress.org).
 *
 * @param string $slug Plugin slug.
 * @param array  $p    Status entry.
 */
function elroi_recommended_action( $slug, $p ) {
	if ( 'inactive' === $p['state'] && current_user_can( 'activate_plugin', $p['file'] ) ) {
		return '<a class="button button-primary" href="' . esc_url( wp_nonce_url( self_admin_url( 'plugins.php?action=activate&plugin=' . rawurlencode( $p['file'] ) ), 'activate-plugin_' . $p['file'] ) ) . '">Activate</a>';
	}
	if ( 'missing' === $p['state'] && current_user_can( 'install_plugins' ) ) {
		if ( str_starts_with( $p['source'], 'https://wordpress.org/plugins/' ) ) {
			return '<a class="button button-primary" href="' . esc_url( wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=' . rawurlencode( $slug ) ), 'install-plugin_' . $slug ) ) . '">Install</a>';
		}
		return '<a class="button" href="' . esc_url( $p['source'] ) . '" target="_blank" rel="noreferrer">Download</a>';
	}
	return '';
}

add_action(
	'admin_menu',
	function () {
		add_plugins_page( 'Recommended plugins', 'ElRoi recommended', 'install_plugins', 'elroi-recommended', 'elroi_recommended_page' );
	}
);

/** Plugins > ElRoi recommended. */
function elroi_recommended_page() {
	$labels = array(
		'active'   => '<span style="color:#008a20">Active</span>',
		'inactive' => '<span style="color:#996800">Installed, not active</span>',
		'missing'  => '<span style="color:#d63638">Not installed</span>',
	);
	echo '<div class="wrap"><h1>Recommended plugins</h1>';
	echo '<p>The plugins ElRoi installs on every client website. Required ones protect and rank the site; recommended ones make it faster and send its email; optional ones help while building. After installing, follow the setup notes.</p>';
	echo '<table class="widefat striped" style="max-width:1100px"><thead><tr><th>Plugin</th><th>Why</th><th>Status</th><th></th><th>After installing</th></tr></thead><tbody>';
	foreach ( elroi_recommended_status() as $slug => $p ) {
		$state = $p['applies'] ? $labels[ $p['state'] ] : '<span style="color:#646970">Not needed on this server</span>';
		printf(
			'<tr><td><strong>%1$s</strong><br><span style="color:#646970">%2$s</span></td><td>%3$s</td><td>%4$s</td><td>%5$s</td><td>%6$s</td></tr>',
			esc_html( $p['name'] ),
			esc_html( ucfirst( $p['level'] ) ),
			esc_html( $p['why'] ),
			$state, // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup above.
			$p['applies'] ? elroi_recommended_action( $slug, $p ) : '', // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the function.
			esc_html( $p['setup'] )
		);
	}
	echo '</tbody></table></div>';
}

// Notice on the Dashboard and the Plugins screen while required or recommended plugins are missing.
add_action(
	'admin_notices',
	function () {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! current_user_can( 'install_plugins' ) || ! $screen || ! in_array( $screen->id, array( 'dashboard', 'plugins' ), true ) ) {
			return;
		}
		$missing = elroi_recommended_missing();
		if ( ! $missing || get_user_meta( get_current_user_id(), 'elroi_recommended_dismissed', true ) === md5( implode( ',', array_keys( $missing ) ) ) ) {
			return;
		}
		$names = implode( ', ', wp_list_pluck( $missing, 'name' ) );
		printf(
			'<div class="notice notice-warning"><p><strong>ElRoi recommended plugins:</strong> %1$s %2$s. <a href="%3$s">Review and install</a> &middot; <a href="%4$s">Dismiss</a></p></div>',
			esc_html( count( $missing ) > 1 ? 'these are not active yet:' : 'this one is not active yet:' ),
			esc_html( $names ),
			esc_url( admin_url( 'plugins.php?page=elroi-recommended' ) ),
			esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=elroi_dismiss_recommended' ), 'elroi_dismiss_recommended' ) )
		);
	}
);

add_action(
	'admin_post_elroi_dismiss_recommended',
	function () {
		check_admin_referer( 'elroi_dismiss_recommended' );
		update_user_meta( get_current_user_id(), 'elroi_recommended_dismissed', md5( implode( ',', array_keys( elroi_recommended_missing() ) ) ) );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
		exit;
	}
);

/** Site Health test. */
function elroi_recommended_health_test() {
	$missing = elroi_recommended_missing();
	$result  = array(
		'label'       => 'ElRoi recommended plugins are active',
		'status'      => 'good',
		'badge'       => array( 'label' => 'ElRoi', 'color' => 'blue' ),
		'description' => '<p>Rank Math, security, backups and the other plugins every ElRoi site uses are installed and active.</p>',
		'actions'     => '',
		'test'        => 'elroi_recommended',
	);
	if ( $missing ) {
		$result['label']       = 'Recommended plugins are missing';
		$result['status']      = 'recommended';
		$result['description'] = '<p>Not active yet: ' . esc_html( implode( ', ', wp_list_pluck( $missing, 'name' ) ) ) . '.</p>';
		$result['actions']     = '<a href="' . esc_url( admin_url( 'plugins.php?page=elroi-recommended' ) ) . '">Review and install</a>';
	}
	return $result;
}

add_filter(
	'site_status_tests',
	function ( $tests ) {
		$tests['direct']['elroi_recommended'] = array(
			'label' => 'ElRoi recommended plugins',
			'test'  => 'elroi_recommended_health_test',
		);
		return $tests;
	}
);

add_action(
	'wp_abilities_api_init',
	function () {
		if ( ! function_exists( 'wp_register_ability' ) || ! apply_filters( 'elroi_abilities', true ) ) {
			return;
		}
		wp_register_ability(
			'elroi/recommended-plugins',
			array(
				'label'               => 'Recommended plugins',
				'description'         => 'Lists the plugins ElRoi installs on every client site, each with its state here (active, inactive or missing), level (required, recommended, optional), why it is needed, what to set up after installing, and the WP-CLI command to install or activate it. Check this first on a new site and tell the user which are missing before installing anything.',
				'category'            => 'elroi-site-editor',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => (object) array(),
					'additionalProperties' => false,
				),
				'output_schema'       => array( 'type' => 'object' ),
				'permission_callback' => fn() => current_user_can( 'install_plugins' ) || current_user_can( 'activate_plugins' ),
				'execute_callback'    => function () {
					$all = elroi_recommended_status();
					return array(
						'plugins' => $all,
						'missing' => array_keys( elroi_recommended_missing() ),
					);
				},
				'meta'                => array(
					'public'      => true,
					'annotations' => array(
						'readonly'   => true,
						'idempotent' => true,
					),
				),
			)
		);
	}
);
