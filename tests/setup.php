<?php
/**
 * Test site content: a home page, a landing page and an About page, plus two generated photos.
 * Idempotent.
 *
 * @package ElRoiSiteEditor
 */

defined( 'ABSPATH' ) || exit;

$page = function ( $slug, $title, $template = '' ) {
	$found = get_page_by_path( $slug );
	$id    = $found ? $found->ID : wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => $slug ) );
	if ( $template ) {
		update_post_meta( $id, '_wp_page_template', $template );
	}
	return $id;
};
$home = $page( 'home', 'Home' );
$page( 'landing', 'Landing', 'templates/landing.php' );
$page( 'about', 'About', 'templates/landing.php' );
update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $home );

// Two small photos to pick in the forms.
if ( ! get_option( 'elroi_test_images' ) && function_exists( 'imagecreatetruecolor' ) ) {
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$ids = array();
	foreach ( array( array( 37, 185, 165 ), array( 6, 42, 58 ) ) as $i => $rgb ) {
		$im = imagecreatetruecolor( 800, 600 );
		imagefill( $im, 0, 0, imagecolorallocate( $im, ...$rgb ) );
		$up = wp_upload_dir();
		$f  = $up['path'] . "/test-photo-$i.jpg";
		imagejpeg( $im, $f );
		$id = wp_insert_attachment( array( 'post_mime_type' => 'image/jpeg', 'post_title' => "Test photo $i", 'post_status' => 'inherit' ), $f );
		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $f ) );
		$ids[] = $id;
	}
	update_option( 'elroi_test_images', $ids );
}
