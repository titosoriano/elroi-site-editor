<?php
/**
 * Site Health: Rank Math actually prints the SEO tags.
 *
 * Rank Math turns off its whole front end (titles, meta descriptions, Open Graph, schema,
 * sitemap) until the site is connected to a Rank Math account or registration is skipped
 * (option `rank_math_registration_skip`). A site can then look finished and send search engines
 * no description at all. This test is critical while that happens, and offers a button that
 * sets the option, the same as "Skip" in Rank Math's setup wizard.
 *
 * @package ElRoiSiteEditor
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether Rank Math is active but prints nothing on the front end: no account connected and
 * registration not skipped. False when Rank Math is not active.
 */
function elroi_rank_math_blocked() {
	if ( ! defined( 'RANK_MATH_VERSION' ) ) {
		return false;
	}
	if ( class_exists( '\RankMath\Helper' ) && method_exists( '\RankMath\Helper', 'is_invalid_registration' ) ) {
		return (bool) \RankMath\Helper::is_invalid_registration();
	}
	// Older Rank Math: the same rule as Conditional::is_invalid_registration().
	if ( ( defined( 'RANK_MATH_REGISTRATION_SKIP' ) && RANK_MATH_REGISTRATION_SKIP ) || get_option( 'rank_math_registration_skip' ) ) {
		return false;
	}
	$data = get_option( 'rank_math_connect_data', false );
	return ! ( is_array( $data ) && ! empty( $data['connected'] ) && ! empty( $data['api_key'] ) );
}

/** Site Health test. */
function elroi_rank_math_health_test() {
	$result = array(
		'label'       => 'Rank Math prints titles, descriptions and schema',
		'status'      => 'good',
		'badge'       => array( 'label' => 'SEO', 'color' => 'blue' ),
		'description' => '<p>Rank Math is connected to an account or its registration was skipped, so every page gets its SEO title, meta description, social tags and structured data.</p>',
		'actions'     => '',
		'test'        => 'elroi_rank_math',
	);
	if ( ! defined( 'RANK_MATH_VERSION' ) ) {
		$result['description'] = '<p>Rank Math is not active (see ElRoi recommended plugins).</p>';
		return $result;
	}
	if ( elroi_rank_math_blocked() ) {
		$result['label']       = 'Rank Math is active but prints no SEO tags';
		$result['status']      = 'critical';
		$result['description'] = '<p>Rank Math switches off its titles, meta descriptions, social tags, structured data and sitemap until the site is connected to a Rank Math account or registration is skipped. Search engines see pages without descriptions until then.</p>';
		if ( current_user_can( 'manage_options' ) ) {
			$result['actions'] = '<a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=elroi_rank_math_skip' ), 'elroi_rank_math_skip' ) ) . '">Turn Rank Math on without an account</a> or connect an account in Rank Math > Dashboard.';
		}
	}
	return $result;
}

add_filter(
	'site_status_tests',
	function ( $tests ) {
		$tests['direct']['elroi_rank_math'] = array(
			'label' => 'Rank Math prints SEO tags',
			'test'  => 'elroi_rank_math_health_test',
		);
		return $tests;
	}
);

add_action(
	'admin_post_elroi_rank_math_skip',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Only administrators can change this.' );
		}
		check_admin_referer( 'elroi_rank_math_skip' );
		update_option( 'rank_math_registration_skip', 1 );
		wp_safe_redirect( admin_url( 'site-health.php' ) );
		exit;
	}
);

// Administrators also see it on the Dashboard, where a finished-looking site would hide it.
add_action(
	'admin_notices',
	function () {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! in_array( $screen->id, array( 'dashboard', 'plugins' ), true ) || ! current_user_can( 'manage_options' ) || ! elroi_rank_math_blocked() ) {
			return;
		}
		echo '<div class="notice notice-error"><p><strong>Rank Math prints no SEO tags yet.</strong> Without a connected account or a skipped registration, pages have no SEO title, description or schema. <a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=elroi_rank_math_skip' ), 'elroi_rank_math_skip' ) ) . '">Turn it on without an account</a> or connect one in Rank Math > Dashboard.</p></div>';
	}
);
