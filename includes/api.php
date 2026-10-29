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
	// Unsaved form values while the edit screen asks for a preview (see includes/seo.php).
	if ( isset( $GLOBALS['elroi_preview'][ $post->ID ] ) ) {
		return $GLOBALS['elroi_preview'][ $post->ID ];
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
 * <img> for an attachment, with its own alt text. When the photo has no alt text in the Media
 * Library, its caption is used, then its title if the title reads like words (not a file name
 * such as IMG_0423). Pass 'alt' => '' for a decorative image.
 *
 * @param int    $id   Attachment ID.
 * @param string $size Image size.
 * @param array  $attr Attributes.
 */
function elroi_image( $id, $size = 'large', $attr = array() ) {
	if ( ! $id ) {
		return '';
	}
	$attr = (array) $attr;
	if ( ! array_key_exists( 'alt', $attr ) && '' === trim( (string) get_post_meta( (int) $id, '_wp_attachment_image_alt', true ) ) ) {
		$fallback = elroi_alt_fallback( (int) $id );
		if ( '' !== $fallback ) {
			$attr['alt'] = $fallback;
		}
	}
	return wp_get_attachment_image( (int) $id, $size, false, $attr );
}

/**
 * Alt text for a photo that has none: its caption, or its title when the title reads like words.
 *
 * @param int $id Attachment ID.
 */
function elroi_alt_fallback( $id ) {
	$caption = trim( wp_strip_all_tags( (string) wp_get_attachment_caption( $id ) ) );
	if ( '' !== $caption ) {
		return $caption;
	}
	$title = trim( (string) get_the_title( $id ) );
	return elroi_title_is_words( $title ) ? $title : '';
}

/**
 * Whether a Media Library title reads like a description rather than a file name
 * ("Team on set" yes; "IMG_0423", "DSC04645", "3L3A5388", "photo-1" no).
 *
 * @param string $title Title.
 */
function elroi_title_is_words( $title ) {
	$title = trim( (string) $title );
	if ( '' === $title || preg_match( '/\d{3,}|\.(jpe?g|png|gif|webp|avif|heic)$/i', $title ) ) {
		return false;
	}
	return 2 <= count( preg_split( '/[\s_-]+/u', $title, -1, PREG_SPLIT_NO_EMPTY ) ) && (bool) preg_match( '/\s/u', $title );
}

/**
 * Paragraphs as HTML: each one escaped and wrapped.
 *
 * @param array  $list  Paragraphs.
 * @param string $attrs Attributes for each <p>, already escaped.
 * @param bool   $rich  Turn `[words](/path)` into links (elroi_rich()).
 */
function elroi_paragraphs( $list, $attrs = '', $rich = false ) {
	$out = '';
	foreach ( (array) $list as $p ) {
		$out .= '<p' . ( $attrs ? ' ' . $attrs : '' ) . '>' . ( $rich ? elroi_rich( $p ) : elroi_text( $p ) ) . '</p>';
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
