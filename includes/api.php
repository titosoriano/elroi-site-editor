<?php
/**
 * Template functions: read the content and settings, and print them safely.
 *
 *   $c = elroi_content();                       // all fields of the current page, defaults filled in
 *   echo elroi_heading( $c['hero']['h1'] );     // escaped, *words* become <em>
 *   echo elroi_text( $c['hero']['lede'] );      // escaped, {placeholders} filled from Site Settings
 *   echo elroi_image( $c['hero']['photo'], 'large' );
 *   echo esc_html( elroi_setting( 'phone' ) );
 *
 * @package ElRoiSiteEditor
 */

defined( 'ABSPATH' ) || exit;

/**
 * Stored values of a page (raw, as saved), or an empty array.
 *
 * @param int|WP_Post|null $post Page.
 */
function elroi_saved( $post = null ) {
	$post = get_post( $post );
	$cfg  = $post ? elroi_template_for( $post ) : null;
	if ( ! $cfg ) {
		return array();
	}
	$raw  = get_post_meta( $post->ID, $cfg['meta_key'], true );
	$data = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
	return is_array( $data ) ? $data : array();
}

/**
 * Content of a page: saved values over the defaults, every field present.
 *
 * @param int|WP_Post|null $post Page, default the current one.
 */
function elroi_content( $post = null ) {
	$post = get_post( $post );
	$cfg  = $post ? elroi_template_for( $post ) : null;
	return $cfg ? elroi_merge_fields( $cfg['fields'], elroi_saved( $post ) ) : array();
}

/**
 * One value by dot path, e.g. elroi_get( 'hero.h1' ).
 *
 * @param string           $path    Dot path.
 * @param int|WP_Post|null $post    Page.
 * @param mixed            $default Returned when missing.
 */
function elroi_get( $path, $post = null, $default = '' ) {
	$v = elroi_content( $post );
	foreach ( explode( '.', $path ) as $k ) {
		if ( ! is_array( $v ) || ! array_key_exists( $k, $v ) ) {
			return $default;
		}
		$v = $v[ $k ];
	}
	return $v;
}

/**
 * Placeholder values: every Site Setting as {key}, plus any added with the `elroi_placeholders` filter.
 */
function elroi_placeholders() {
	static $map = null;
	if ( null === $map ) {
		$map = array();
		foreach ( elroi_setting() as $k => $v ) {
			if ( is_scalar( $v ) ) {
				$map[ '{' . $k . '}' ] = (string) $v;
			}
		}
		$map = (array) apply_filters( 'elroi_placeholders', $map );
	}
	return $map;
}

/**
 * Plain text with placeholders filled in, escaped for HTML.
 *
 * @param mixed $text Text.
 */
function elroi_text( $text ) {
	return esc_html( strtr( is_scalar( $text ) ? (string) $text : '', elroi_placeholders() ) );
}

/**
 * A heading: like elroi_text(), with *words* in the highlight style (<em>).
 *
 * @param mixed  $text Heading.
 * @param string $tag  Highlight element.
 */
function elroi_heading( $text, $tag = 'em' ) {
	$tag = tag_escape( $tag );
	return preg_replace( '/\*(.+?)\*/', "<{$tag}>$1</{$tag}>", elroi_text( $text ) );
}

/**
 * Escaped URL from a site path (/services/) or a full URL.
 *
 * @param mixed $link Link.
 */
function elroi_link( $link ) {
	$link = is_scalar( $link ) ? trim( (string) $link ) : '';
	if ( '' === $link ) {
		return '';
	}
	return esc_url( preg_match( '#^(https?:|mailto:|tel:|\#)#i', $link ) ? $link : home_url( $link ) );
}

/**
 * <img> for an attachment, with its own alt text.
 *
 * @param int    $id   Attachment ID.
 * @param string $size Image size.
 * @param array  $attr Attributes.
 */
function elroi_image( $id, $size = 'large', $attr = array() ) {
	return $id ? wp_get_attachment_image( (int) $id, $size, false, $attr ) : '';
}

/**
 * Paragraphs as HTML: each one escaped and wrapped.
 *
 * @param array  $list  Paragraphs.
 * @param string $attrs Attributes for each <p>, already escaped.
 */
function elroi_paragraphs( $list, $attrs = '' ) {
	$out = '';
	foreach ( (array) $list as $p ) {
		$out .= '<p' . ( $attrs ? ' ' . $attrs : '' ) . '>' . elroi_text( $p ) . '</p>';
	}
	return $out;
}

/**
 * All settings, or one. Empty fields give their default; a cleared "clearable" field gives ''.
 *
 * @param string|null $key Setting key.
 */
function elroi_setting( $key = null ) {
	$cfg = elroi_settings_config();
	if ( ! $cfg ) {
		return null === $key ? array() : '';
	}
	$saved = (array) get_option( $cfg['option'], array() );
	$all   = array();
	foreach ( $cfg['sections'] as $sec ) {
		foreach ( $sec['fields'] as $k => $f ) {
			$v = $saved[ $k ] ?? '';
			if ( '-' === $v && ! empty( $f['clearable'] ) ) {
				$all[ $k ] = '';
			} else {
				$all[ $k ] = elroi_is_empty( $v ) ? $f['default'] : $v;
			}
		}
	}
	return null === $key ? $all : ( $all[ $key ] ?? '' );
}

/**
 * tel: value (+1XXXXXXXXXX for 10-digit US numbers) from a displayed phone number.
 *
 * @param string $label Phone as shown, e.g. "(845) 263-0330".
 */
function elroi_tel( $label ) {
	$digits = preg_replace( '/\D/', '', (string) $label );
	if ( 10 === strlen( $digits ) ) {
		$digits = '1' . $digits;
	}
	return '+' . $digits;
}
