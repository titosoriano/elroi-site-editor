<?php
/**
 * The page form: a meta box on every page whose template is registered, saving to that template's
 * meta key as JSON with revisions. The block editor and the empty content field are turned off on
 * those pages unless the template asks to keep them.
 *
 * @package ElRoiSiteEditor
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'use_block_editor_for_post',
	function ( $use, $post ) {
		$cfg = elroi_template_for( $post );
		return $cfg && ! $cfg['block_editor'] ? false : $use;
	},
	10,
	2
);

add_action(
	'load-post.php',
	function () {
		$id  = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$cfg = $id ? elroi_template_for( $id ) : null;
		if ( $cfg && ! $cfg['block_editor'] ) {
			remove_post_type_support( 'page', 'editor' );
		}
	}
);

add_action(
	'add_meta_boxes_page',
	function ( $post ) {
		$cfg = elroi_template_for( $post );
		if ( $cfg ) {
			add_meta_box( 'elroi-content', $cfg['box_title'], 'elroi_box', 'page', 'normal', 'high' );
		}
	}
);

add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		$on_page     = in_array( $hook, array( 'post.php', 'post-new.php' ), true ) && elroi_template_for( get_post() );
		$on_settings = str_contains( (string) $hook, 'elroi-settings' );
		if ( ! $on_page && ! $on_settings ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'elroi-admin', ELROI_SE_URL . 'assets/admin.css', array(), ELROI_SE_VERSION );
		wp_enqueue_script( 'elroi-admin', ELROI_SE_URL . 'assets/admin.js', array( 'jquery' ), ELROI_SE_VERSION, true );
	}
);

/**
 * Top-level fields shown for this page (a field may hide itself with 'show_if' => callable( $post )).
 *
 * @param array   $cfg  Template config.
 * @param WP_Post $post Page.
 */
function elroi_visible_fields( $cfg, $post ) {
	return array_filter( $cfg['fields'], fn( $f ) => ! isset( $f['show_if'] ) || ! is_callable( $f['show_if'] ) || call_user_func( $f['show_if'], $post ) );
}

/**
 * The meta box.
 *
 * @param WP_Post $post Page.
 */
function elroi_box( $post ) {
	$cfg    = elroi_template_for( $post );
	$values = elroi_content( $post );
	wp_nonce_field( 'elroi_save', 'elroi_nonce' );
	echo '<div class="elroi">';
	echo '<p class="elroi-intro">Edit the words and pictures of this page here, then click <strong>Update</strong>. The layout and styles stay as designed. Every update is kept as a revision, so you can compare or go back.';
	if ( $cfg['placeholders'] && elroi_placeholders() ) {
		echo ' Values from Site Settings can be typed as <code>{name}</code>, for example <code>' . esc_html( array_key_first( elroi_placeholders() ) ) . '</code>.';
	}
	echo '</p>';
	if ( $cfg['intro'] ) {
		echo '<p>' . esc_html( $cfg['intro'] ) . '</p>';
	}
	elroi_render_fields(
		$cfg['fields'],
		'elroi',
		$values,
		array(
			'root'  => $values,
			'depth' => 0,
			'post'  => $post,
		)
	);
	echo '</div>';
}

/**
 * Build the data to store from the posted form.
 *
 * @param array   $cfg  Template config.
 * @param WP_Post $post Page.
 * @param array   $in   Posted `elroi` values (unslashed).
 * @param array   $old  Currently stored values.
 */
function elroi_build_page( $cfg, $post, $in, $old ) {
	$visible = elroi_visible_fields( $cfg, $post );
	// Options of checkboxes can come from a top-level lines field: clean those first.
	$root = array();
	foreach ( $visible as $k => $f ) {
		if ( 'lines' === $f['type'] ) {
			$root[ $k ] = elroi_split_lines( $in[ $k ] ?? '' );
		}
	}
	$new = elroi_strip_defaults( $visible, elroi_build_fields( $visible, $in, $root ) );
	// Keep what the form did not show: hidden fields and keys outside the schema.
	foreach ( $old as $k => $v ) {
		if ( ! isset( $visible[ $k ] ) && ! array_key_exists( $k, $new ) ) {
			$new[ $k ] = $v;
		}
	}
	return $new;
}

add_action(
	'save_post_page',
	function ( $post_id, $post ) {
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['elroi_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['elroi_nonce'] ), 'elroi_save' ) || ! current_user_can( 'edit_page', $post_id ) ) {
			return;
		}
		$cfg = elroi_template_for( $post );
		if ( ! $cfg ) {
			return;
		}
		$in  = isset( $_POST['elroi'] ) ? wp_unslash( (array) $_POST['elroi'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- cleaned field by field in elroi_build_fields()
		$new = elroi_build_page( $cfg, $post, $in, elroi_saved( $post ) );
		update_post_meta( $post_id, $cfg['meta_key'], wp_slash( wp_json_encode( $new, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) );
		elroi_save_alts();
	},
	10,
	2
);

/**
 * Save posted alt texts onto their images.
 */
function elroi_save_alts() {
	foreach ( (array) ( $_POST['elroi_alt'] ?? array() ) as $att_id => $alt ) { // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput -- nonce checked by the caller
		$att_id = absint( $att_id );
		if ( $att_id && 'attachment' === get_post_type( $att_id ) && current_user_can( 'edit_post', $att_id ) ) {
			update_post_meta( $att_id, '_wp_attachment_image_alt', sanitize_text_field( wp_unslash( $alt ) ) );
		}
	}
}
