<?php
/**
 * Field types: normalizing definitions, cleaning posted values, and laying saved values over defaults.
 *
 * A field is an array: type, label, help, default, plus type options. Types:
 *   text, heading (text where *words* get the highlight style), textarea (one paragraph),
 *   paragraphs (blank line between paragraphs), lines (one item per line), url, link (site path or
 *   URL), email, number, select (options), checkboxes (options, or options_from: the key of a
 *   `lines` field at the top level), image (attachment ID), gallery (attachment IDs),
 *   numbers (a fixed list of numbers, one per label), group (fields), repeater (fields, item).
 *
 * Empty values are not stored; a value equal to its default is not stored either, so a page only
 * keeps what was changed. Reading lays the stored values over the defaults: an empty text or an
 * empty list shows the default.
 *
 * @package ElRoiSiteEditor
 */

defined( 'ABSPATH' ) || exit;

/**
 * Types whose value is a list.
 */
function elroi_list_types() {
	return array( 'paragraphs', 'lines', 'gallery', 'checkboxes', 'numbers', 'repeater' );
}

/**
 * Fill in a field definition (recursively for groups and repeaters).
 *
 * @param array  $f   Field.
 * @param string $key Field key.
 */
function elroi_normalize_field( $f, $key ) {
	$f         = (array) $f;
	$f['type'] = $f['type'] ?? 'text';
	$f['key']  = $key;
	$f['label'] = $f['label'] ?? ucfirst( str_replace( '_', ' ', $key ) );
	$f['help']  = $f['help'] ?? '';
	if ( ! array_key_exists( 'default', $f ) ) {
		$f['default'] = elroi_empty_value( $f['type'] );
	}
	if ( in_array( $f['type'], array( 'group', 'repeater' ), true ) ) {
		$fields = array();
		foreach ( (array) ( $f['fields'] ?? array() ) as $k => $sub ) {
			$fields[ $k ] = elroi_normalize_field( $sub, $k );
		}
		$f['fields'] = $fields;
	}
	if ( 'repeater' === $f['type'] ) {
		$f['item']   = $f['item'] ?? 'Item';
		$f['title']  = $f['title'] ?? (string) array_key_first( $f['fields'] );
		$f['layout'] = $f['layout'] ?? 'list';
	}
	if ( 'group' === $f['type'] ) {
		$def = array();
		foreach ( $f['fields'] as $k => $sub ) {
			$def[ $k ] = $sub['default'];
		}
		$f['default'] = $def;
	}
	return $f;
}

/**
 * Empty value of a type.
 *
 * @param string $type Type.
 */
function elroi_empty_value( $type ) {
	if ( 'group' === $type || in_array( $type, elroi_list_types(), true ) ) {
		return array();
	}
	return 'image' === $type ? 0 : '';
}

/**
 * Whether a value counts as empty.
 *
 * @param mixed $v Value.
 */
function elroi_is_empty( $v ) {
	if ( is_array( $v ) ) {
		foreach ( $v as $x ) {
			if ( ! elroi_is_empty( $x ) ) {
				return false;
			}
		}
		return true;
	}
	return null === $v || '' === $v || 0 === $v || ( is_string( $v ) && '' === trim( $v ) );
}

/**
 * One line of plain text.
 *
 * @param mixed $v Value.
 */
function elroi_clean_line( $v ) {
	return sanitize_text_field( preg_replace( '/\s*\R\s*/', ' ', is_scalar( $v ) ? (string) $v : '' ) );
}

/**
 * Paragraphs from a textarea (blank line = new paragraph).
 *
 * @param mixed $text Text.
 */
function elroi_split_paragraphs( $text ) {
	$out = array();
	foreach ( preg_split( '/\R\s*\R/', trim( is_scalar( $text ) ? (string) $text : '' ) ) as $p ) {
		$p = elroi_clean_line( $p );
		if ( '' !== $p ) {
			$out[] = $p;
		}
	}
	return $out;
}

/**
 * Lines from a textarea (one per line).
 *
 * @param mixed $text Text.
 */
function elroi_split_lines( $text ) {
	return array_values( array_filter( array_map( 'sanitize_text_field', preg_split( '/\R/', is_scalar( $text ) ? (string) $text : '' ) ), 'strlen' ) );
}

/**
 * Options of a select or checkboxes field.
 *
 * @param array $f    Field.
 * @param array $root Top-level values (for options_from).
 */
function elroi_field_options( $f, $root ) {
	if ( ! empty( $f['options_from'] ) ) {
		$list = $root[ $f['options_from'] ] ?? array();
		return is_array( $list ) ? array_values( $list ) : elroi_split_lines( $list );
	}
	return array_values( (array) ( $f['options'] ?? array() ) );
}

/**
 * Clean one posted value into its stored shape. Returns null when empty.
 *
 * @param array $f    Field.
 * @param mixed $in   Posted value.
 * @param array $root Top-level cleaned values (for options_from).
 */
function elroi_build_value( $f, $in, $root = array() ) {
	switch ( $f['type'] ) {
		case 'paragraphs':
			$v = elroi_split_paragraphs( $in );
			break;
		case 'lines':
			$v = elroi_split_lines( $in );
			break;
		case 'url':
			$v = esc_url_raw( trim( is_scalar( $in ) ? (string) $in : '' ) );
			break;
		case 'email':
			$v = sanitize_email( is_scalar( $in ) ? (string) $in : '' );
			break;
		case 'number':
			$s = trim( is_scalar( $in ) ? (string) $in : '' );
			$v = is_numeric( $s ) ? $s + 0 : '';
			break;
		case 'select':
			$s = elroi_clean_line( $in );
			$v = in_array( $s, array_map( 'strval', elroi_field_options( $f, $root ) ), true ) ? $s : '';
			break;
		case 'checkboxes':
			$picked = array_map( 'elroi_clean_line', (array) $in );
			$v      = array_values( array_intersect( array_map( 'strval', elroi_field_options( $f, $root ) ), $picked ) );
			break;
		case 'image':
			$v = absint( is_scalar( $in ) ? $in : 0 );
			break;
		case 'gallery':
			$v = array_values( array_filter( array_map( 'absint', is_array( $in ) ? $in : explode( ',', (string) $in ) ) ) );
			break;
		case 'numbers':
			$v      = array();
			$in     = array_values( (array) $in );
			$labels = (array) ( $f['labels'] ?? array() );
			$given  = false;
			foreach ( array_keys( $labels ) as $i ) {
				$s = trim( (string) ( $in[ $i ] ?? '' ) );
				if ( is_numeric( $s ) ) {
					$given = true;
				}
				$v[] = is_numeric( $s ) ? $s + 0 : ( $f['default'][ $i ] ?? 0 );
			}
			if ( ! $given ) {
				$v = array();
			}
			break;
		case 'group':
			$v = elroi_build_fields( $f['fields'], (array) $in, $root );
			break;
		case 'repeater':
			$v = array();
			foreach ( is_array( $in ) ? $in : array() as $item ) {
				$item  = (array) $item;
				$built = elroi_build_fields( $f['fields'], $item, $root );
				if ( ! empty( $item['__extra'] ) ) {
					// Keys the form does not show, carried through the form so saving never drops them.
					$extra = json_decode( wp_unslash( (string) $item['__extra'] ), true );
					if ( is_array( $extra ) ) {
						$built = array_merge( $built, array_diff_key( elroi_clean_deep( $extra ), $built ) );
					}
				}
				if ( $built ) {
					$v[] = $built;
				}
			}
			break;
		default: // text, heading, textarea, link.
			$v = elroi_clean_line( $in );
	}
	return elroi_is_empty( $v ) ? null : $v;
}

/**
 * Clean a set of fields; keys are written in field order, empty ones left out.
 *
 * @param array $fields Normalized fields.
 * @param array $in     Posted values.
 * @param array $root   Top-level cleaned values.
 */
function elroi_build_fields( $fields, $in, $root = array() ) {
	$out = array();
	foreach ( $fields as $k => $f ) {
		$v = elroi_build_value( $f, $in[ $k ] ?? null, $root );
		if ( null !== $v ) {
			$out[ $k ] = $v;
		}
	}
	return $out;
}

/**
 * Drop values equal to their default, so only changes are stored.
 *
 * @param array $fields Normalized fields.
 * @param array $values Built values.
 */
function elroi_strip_defaults( $fields, $values ) {
	foreach ( $fields as $k => $f ) {
		if ( ! array_key_exists( $k, $values ) ) {
			continue;
		}
		if ( 'group' === $f['type'] ) {
			$values[ $k ] = elroi_strip_defaults( $f['fields'], $values[ $k ] );
			if ( ! $values[ $k ] ) {
				unset( $values[ $k ] );
			}
		} elseif ( ! elroi_is_empty( $f['default'] ) && $values[ $k ] == $f['default'] ) { // phpcs:ignore Universal.Operators.StrictComparisons -- numbers may be stored as int or float.
			unset( $values[ $k ] );
		}
	}
	return $values;
}

/**
 * Saved values over the defaults: an empty value shows the default; every schema key exists.
 * Saved keys that are not in the schema are kept.
 *
 * @param array $fields Normalized fields.
 * @param array $saved  Saved values.
 */
function elroi_merge_fields( $fields, $saved ) {
	$saved = is_array( $saved ) ? $saved : array();
	$out   = $saved;
	foreach ( $fields as $k => $f ) {
		$out[ $k ] = elroi_merge_value( $f, $saved[ $k ] ?? null );
	}
	return $out;
}

/**
 * One value over its default.
 *
 * @param array $f     Field.
 * @param mixed $saved Saved value.
 */
function elroi_merge_value( $f, $saved ) {
	if ( 'group' === $f['type'] ) {
		return elroi_merge_fields( $f['fields'], $saved );
	}
	if ( 'repeater' === $f['type'] ) {
		$items = ! elroi_is_empty( $saved ) && is_array( $saved ) ? array_values( $saved ) : (array) $f['default'];
		return array_map( fn( $item ) => elroi_fill_item( $f['fields'], $item ), $items );
	}
	return elroi_is_empty( $saved ) ? $f['default'] : $saved;
}

/**
 * A repeater item with every field key present (empty when missing; no defaults inside items).
 *
 * @param array $fields Item fields.
 * @param mixed $item   Saved item.
 */
function elroi_fill_item( $fields, $item ) {
	$item = is_array( $item ) ? $item : array();
	foreach ( $fields as $k => $sub ) {
		$v = $item[ $k ] ?? null;
		if ( 'group' === $sub['type'] ) {
			$item[ $k ] = elroi_fill_item( $sub['fields'], $v );
		} elseif ( 'repeater' === $sub['type'] ) {
			$item[ $k ] = array_map( fn( $x ) => elroi_fill_item( $sub['fields'], $x ), is_array( $v ) ? array_values( $v ) : array() );
		} elseif ( null === $v ) {
			$item[ $k ] = elroi_empty_value( $sub['type'] );
		}
	}
	return $item;
}

/**
 * Sanitize kept data of unknown shape: strings are cleaned, numbers and booleans kept.
 *
 * @param mixed $v Value.
 */
function elroi_clean_deep( $v ) {
	if ( is_array( $v ) ) {
		return array_map( 'elroi_clean_deep', $v );
	}
	if ( is_int( $v ) || is_float( $v ) || is_bool( $v ) || null === $v ) {
		return $v;
	}
	return sanitize_text_field( (string) $v );
}
