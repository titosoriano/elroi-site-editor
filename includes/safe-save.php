<?php
/**
 * Safe saving outside the form: tools, scripts and AI agents change only what they name.
 *
 *   elroi_update( $page_id, array( 'hero' => array( 'h1' => 'New *title*' ) ) ); // other fields stay
 *   elroi_set( 'hero.photo', 123, $page_id );                                    // one value by path
 *   elroi_update( $page_id, array( 'story' => null ) );                          // null removes a key
 *
 * Groups (named keys) merge key by key; lists (repeaters, paragraphs, galleries) are replaced
 * as a whole. Every change keeps a revision of the content before and after it, so it can be
 * compared and restored from the page's Revisions screen.
 *
 * A guard catches the common mistake of writing the page meta directly with an array
 * (update_post_meta( $id, '_elroi_content', array( ... ) )): that would have replaced every
 * field with the few given. The guard merges the array into what is stored instead. A string
 * that is not valid JSON is refused, because the page would lose all its content. To replace
 * the whole content on purpose, write a complete JSON string.
 *
 * @package ElRoiSiteEditor
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether an array is a list (0, 1, 2...) rather than named keys.
 *
 * @param mixed $a Value.
 */
function elroi_is_list( $a ) {
	return is_array( $a ) && ( array() === $a || array_keys( $a ) === range( 0, count( $a ) - 1 ) );
}

/**
 * Lay changes over stored values: named keys merge recursively, lists and scalars replace,
 * null removes the key.
 *
 * @param array $old     Stored values.
 * @param array $changes Changes.
 */
function elroi_merge_changes( $old, $changes ) {
	$old = is_array( $old ) ? $old : array();
	foreach ( (array) $changes as $k => $v ) {
		if ( null === $v ) {
			unset( $old[ $k ] );
		} elseif ( is_array( $v ) && ! elroi_is_list( $v ) && isset( $old[ $k ] ) && is_array( $old[ $k ] ) && ! elroi_is_list( $old[ $k ] ) ) {
			$old[ $k ] = elroi_merge_changes( $old[ $k ], $v );
		} else {
			$old[ $k ] = $v;
		}
	}
	return $old;
}

/**
 * Stored values of a page straight from the database (never the edit-screen preview).
 *
 * @param WP_Post $post Page.
 * @param array   $cfg  Template config.
 */
function elroi_stored( $post, $cfg ) {
	$raw  = get_post_meta( $post->ID, $cfg['meta_key'], true );
	$data = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
	return is_array( $data ) ? $data : array();
}

/**
 * Write a page's values as JSON, bypassing the guard.
 *
 * @param WP_Post $post Page.
 * @param array   $cfg  Template config.
 * @param array   $data Values.
 */
function elroi_write( $post, $cfg, $data ) {
	$GLOBALS['elroi_writing'] = true;
	if ( $data ) {
		update_post_meta( $post->ID, $cfg['meta_key'], wp_slash( wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) );
	} else {
		delete_post_meta( $post->ID, $cfg['meta_key'] );
	}
	$GLOBALS['elroi_writing'] = false;
}

/**
 * Change some fields of a page and keep everything else. Returns the stored values, or a
 * WP_Error when the page has no registered template.
 *
 * @param int|WP_Post $post    Page.
 * @param array       $changes Values to change (see elroi_merge_changes()).
 */
function elroi_update( $post, $changes ) {
	$post = get_post( $post );
	$cfg  = $post ? elroi_template_for( $post ) : null;
	if ( ! $cfg ) {
		return new WP_Error( 'elroi_no_template', 'This page is not built from fields.' );
	}
	$old = elroi_stored( $post, $cfg );
	$new = elroi_merge_changes( $old, (array) $changes );
	if ( $new === $old ) {
		return $old;
	}
	// Revision of the content as it was (only made when it differs from the latest revision).
	wp_save_post_revision( $post->ID );
	elroi_write( $post, $cfg, $new );
	wp_save_post_revision( $post->ID );
	return $new;
}

/**
 * Change one value by dot path, e.g. elroi_set( 'hero.h1', 'New *title*', 42 ). Null removes it.
 *
 * @param string      $path  Dot path.
 * @param mixed       $value Value.
 * @param int|WP_Post $post  Page.
 */
function elroi_set( $path, $value, $post ) {
	$changes = $value;
	foreach ( array_reverse( explode( '.', (string) $path ) ) as $k ) {
		$changes = array( $k => $changes );
	}
	return elroi_update( $post, $changes );
}

/**
 * Meta keys that hold page content.
 */
function elroi_meta_keys() {
	return array_unique( array_column( elroi_templates(), 'meta_key' ) );
}

/**
 * The guard on direct meta writes (see the file comment).
 *
 * @param null|bool $check Short-circuit value.
 * @param int       $id    Object ID.
 * @param string    $key   Meta key.
 * @param mixed     $value Value (unslashed).
 */
function elroi_guard_meta( $check, $id, $key, $value ) {
	if ( null !== $check || ! empty( $GLOBALS['elroi_writing'] ) || ! in_array( $key, elroi_meta_keys(), true ) ) {
		return $check;
	}
	$post = get_post( $id );
	if ( ! $post || 'revision' === $post->post_type ) {
		return $check; // Revisions keep exact copies.
	}
	$cfg = elroi_template_for( $post );
	if ( ! $cfg || $cfg['meta_key'] !== $key ) {
		return $check;
	}
	if ( is_object( $value ) ) {
		$value = json_decode( wp_json_encode( $value ), true );
	}
	if ( is_array( $value ) ) {
		_doing_it_wrong( 'update_post_meta', esc_html( "Page content in {$key} is JSON; the array given was merged into the stored fields. Use elroi_update() to change some fields." ), '1.2.0' );
		return ! is_wp_error( elroi_update( $post, $value ) );
	}
	if ( is_string( $value ) && '' !== trim( $value ) && ! is_array( json_decode( $value, true ) ) ) {
		_doing_it_wrong( 'update_post_meta', esc_html( "Refused to store text that is not JSON in {$key}: the page would lose its content." ), '1.2.0' );
		return false;
	}
	return $check;
}
add_filter( 'update_post_metadata', 'elroi_guard_meta', 5, 4 );
add_filter( 'add_post_metadata', 'elroi_guard_meta', 5, 4 );
