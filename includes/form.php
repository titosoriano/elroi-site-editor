<?php
/**
 * Form rendering: turns field definitions into the wp-admin form. Inputs are named after the
 * field path, e.g. elroi[hero][h1], so the posted array has the shape of the stored data.
 *
 * @package ElRoiSiteEditor
 */

defined( 'ABSPATH' ) || exit;

/**
 * Stable HTML id from a field name.
 *
 * @param string $name Field name.
 */
function elroi_field_id( $name ) {
	return 'elroi-' . substr( md5( $name ), 0, 12 );
}

/**
 * Help text that every field of a type gets.
 *
 * @param string $type Type.
 */
function elroi_type_help( $type ) {
	$help = array(
		'heading'    => 'Put *asterisks* around words to show them in the highlight style.',
		'paragraphs' => 'Leave a blank line between paragraphs.',
		'lines'      => 'One per line.',
		'link'       => 'A page on this site, e.g. /services/, or a full web address.',
	);
	return $help[ $type ] ?? '';
}

/**
 * Render a set of fields.
 *
 * @param array  $fields Normalized fields.
 * @param string $base   Name prefix, e.g. elroi or elroi[hero].
 * @param array  $values Current values (merged).
 * @param array  $ctx    root (top-level values), depth (repeater nesting), post.
 */
function elroi_render_fields( $fields, $base, $values, $ctx ) {
	$row = array();
	foreach ( $fields as $k => $f ) {
		if ( isset( $f['show_if'] ) && is_callable( $f['show_if'] ) && ! call_user_func( $f['show_if'], $ctx['post'] ) ) {
			continue;
		}
		$name = $base . '[' . $k . ']';
		$val  = $values[ $k ] ?? elroi_empty_value( $f['type'] );
		// Short fields marked 'half' sit side by side.
		if ( ! empty( $f['half'] ) ) {
			$row[] = array( $f, $name, $val );
			continue;
		}
		elroi_flush_row( $row, $ctx );
		elroi_render_field( $f, $name, $val, $ctx );
	}
	elroi_flush_row( $row, $ctx );
}

/**
 * Print fields collected for one row.
 *
 * @param array $row Fields (by reference, emptied).
 * @param array $ctx Context.
 */
function elroi_flush_row( &$row, $ctx ) {
	if ( ! $row ) {
		return;
	}
	echo '<div class="elroi-row">';
	foreach ( $row as $r ) {
		elroi_render_field( $r[0], $r[1], $r[2], $ctx );
	}
	echo '</div>';
	$row = array();
}

/**
 * Render one field.
 *
 * @param array  $f    Field.
 * @param string $name Input name.
 * @param mixed  $val  Value.
 * @param array  $ctx  Context.
 */
function elroi_render_field( $f, $name, $val, $ctx ) {
	$type = $f['type'];
	if ( 'group' === $type ) {
		elroi_render_group( $f, $name, (array) $val, $ctx );
		return;
	}
	if ( 'repeater' === $type ) {
		elroi_render_repeater( $f, $name, (array) $val, $ctx );
		return;
	}
	if ( 'image' === $type ) {
		elroi_render_image( $name, $f['label'], (int) $val, $f['help'] );
		return;
	}
	$id   = elroi_field_id( $name );
	$help = trim( $f['help'] . ' ' . elroi_type_help( $type ) . ( ! empty( $f['rich'] ) ? ' Add a link by typing [the words](/page/).' : '' ) );
	$desc = $help ? ' aria-describedby="' . esc_attr( $id ) . '-h"' : '';
	$attr = ! empty( $f['title_source'] ) ? ' data-elroi-title-source' : '';
	if ( ! empty( $f['link_text'] ) ) {
		$attr .= ' data-elroi-linktext';
	}
	echo '<div class="elroi-field">';

	switch ( $type ) {
		case 'paragraphs':
		case 'lines':
		case 'textarea':
			$text = is_array( $val ) ? implode( 'paragraphs' === $type ? "\n\n" : "\n", $val ) : (string) $val;
			$rows = $f['rows'] ?? ( 'paragraphs' === $type ? 5 : 3 );
			printf( '<label for="%s">%s</label>', esc_attr( $id ), esc_html( $f['label'] ) );
			printf( '<textarea id="%s" name="%s" rows="%d"%s%s>%s</textarea>', esc_attr( $id ), esc_attr( $name ), (int) $rows, $desc, $attr, esc_textarea( $text ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			break;

		case 'select':
			printf( '<label for="%s">%s</label>', esc_attr( $id ), esc_html( $f['label'] ) );
			printf( '<select id="%s" name="%s"%s>', esc_attr( $id ), esc_attr( $name ), $desc ); // phpcs:ignore WordPress.Security.EscapeOutput
			echo '<option value="">—</option>';
			foreach ( elroi_field_options( $f, $ctx['root'] ) as $o ) {
				printf( '<option value="%s"%s>%s</option>', esc_attr( $o ), selected( (string) $val, (string) $o, false ), esc_html( $o ) );
			}
			echo '</select>';
			break;

		case 'checkboxes':
			$picked = array_map( 'strval', (array) $val );
			printf( '<fieldset class="elroi-checks"%s><legend>%s</legend>', $desc, esc_html( $f['label'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			foreach ( elroi_field_options( $f, $ctx['root'] ) as $o ) {
				printf( '<label><input type="checkbox" name="%s[]" value="%s"%s> %s</label>', esc_attr( $name ), esc_attr( $o ), checked( in_array( (string) $o, $picked, true ), true, false ), esc_html( $o ) );
			}
			echo '</fieldset>';
			break;

		case 'gallery':
			$ids = array_map( 'absint', (array) $val );
			printf( '<span class="elroi-label">%s</span>', esc_html( $f['label'] ) );
			echo '<div data-elroi-gallery>';
			printf( '<input type="hidden" name="%s" value="%s" data-elroi-gallery-ids>', esc_attr( $name ), esc_attr( implode( ',', $ids ) ) );
			echo '<div class="elroi-gallery" data-elroi-gallery-preview>';
			foreach ( $ids as $gid ) {
				echo wp_get_attachment_image( $gid, 'thumbnail' ); // phpcs:ignore WordPress.Security.EscapeOutput
			}
			$no_alt = count( array_filter( $ids, fn( $gid ) => '' === trim( (string) get_post_meta( $gid, '_wp_attachment_image_alt', true ) ) ) );
			echo '</div><button type="button" class="button" data-elroi-gallery-pick>Choose photos</button>';
			printf( '<p class="elroi-warn" data-elroi-gallery-warn%s>%s</p>', $no_alt ? '' : ' hidden', esc_html( elroi_alt_warning( $no_alt ) ) );
			echo '</div>';
			break;

		case 'numbers':
			$nums = array_values( (array) $val );
			printf( '<fieldset class="elroi-numbers"%s><legend>%s</legend>', $desc, esc_html( $f['label'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			foreach ( array_values( (array) ( $f['labels'] ?? array() ) ) as $i => $lab ) {
				$nid = $id . '-' . $i;
				printf( '<label for="%s">%s</label><input type="text" inputmode="decimal" id="%s" name="%s[%d]" value="%s">', esc_attr( $nid ), esc_html( $lab ), esc_attr( $nid ), esc_attr( $name ), (int) $i, esc_attr( isset( $nums[ $i ] ) ? (string) $nums[ $i ] : '' ) );
			}
			echo '</fieldset>';
			break;

		default:
			$input = array(
				'url'    => 'url',
				'email'  => 'email',
				'number' => 'text',
			)[ $type ] ?? 'text';
			$mode  = 'number' === $type ? ' inputmode="decimal"' : '';
			printf( '<label for="%s">%s</label>', esc_attr( $id ), esc_html( $f['label'] ) );
			printf( '<input type="%s" id="%s" name="%s" value="%s"%s%s%s>', esc_attr( $input ), esc_attr( $id ), esc_attr( $name ), esc_attr( is_scalar( $val ) ? (string) $val : '' ), $desc, $attr, $mode ); // phpcs:ignore WordPress.Security.EscapeOutput
	}

	if ( $help ) {
		printf( '<p class="description" id="%s-h">%s</p>', esc_attr( $id ), esc_html( $help ) );
	}
	if ( ! empty( $f['link_text'] ) ) {
		printf( '<p class="elroi-warn" data-elroi-linktext-warn%s>Say where the link goes, e.g. "See our kitchen remodels" instead of "Learn more". Google and screen readers read link words on their own.</p>', elroi_is_vague_link_text( is_scalar( $val ) ? (string) $val : '' ) ? '' : ' hidden' );
	}
	echo '</div>';
}

/**
 * Words that say nothing about where a link goes ("Learn more", "Click here").
 * Filter: `elroi_vague_link_words`.
 */
function elroi_vague_link_words() {
	return (array) apply_filters( 'elroi_vague_link_words', array( 'learn more', 'read more', 'click here', 'here', 'more', 'see more', 'view more', 'more info', 'more information', 'details', 'go', 'link', 'this', 'continue', 'click', 'find out more' ) );
}

/**
 * Whether link words say nothing about where the link goes.
 *
 * @param string $text Link words.
 */
function elroi_is_vague_link_text( $text ) {
	$t = strtolower( trim( preg_replace( '/[^\p{L}\p{N}\s]+/u', ' ', elroi_plain( $text ) ) ) );
	$t = preg_replace( '/\s+/', ' ', $t );
	return '' !== $t && in_array( $t, elroi_vague_link_words(), true );
}

/**
 * Warning for gallery photos without alt text.
 *
 * @param int $n Photos without alt text.
 */
function elroi_alt_warning( $n ) {
	return 1 === $n ? '1 photo has no description (alt text). Add it in the Media Library so Google and screen readers know what it shows.' : $n . ' photos have no description (alt text). Add it in the Media Library so Google and screen readers know what they show.';
}

/**
 * A group: a titled block of fields (a section heading at the top level, a fieldset inside items).
 *
 * @param array  $f      Field.
 * @param string $name   Input name.
 * @param array  $values Values.
 * @param array  $ctx    Context.
 */
function elroi_render_group( $f, $name, $values, $ctx ) {
	$top = 0 === $ctx['depth'] && 1 === substr_count( $name, '[' );
	if ( $top ) {
		printf( '<h3 class="elroi-h">%s</h3>', esc_html( $f['label'] ) );
		if ( $f['help'] ) {
			printf( '<p class="description">%s</p>', esc_html( $f['help'] ) );
		}
		elroi_render_fields( $f['fields'], $name, $values, $ctx );
		return;
	}
	printf( '<fieldset class="elroi-group"><legend>%s</legend>', esc_html( $f['label'] ) );
	if ( $f['help'] ) {
		printf( '<p class="description">%s</p>', esc_html( $f['help'] ) );
	}
	elroi_render_fields( $f['fields'], $name, $values, $ctx );
	echo '</fieldset>';
}

/**
 * A repeater: items with Add, Move and Remove, and a <template> for new items.
 *
 * @param array  $f     Field.
 * @param string $name  Input name.
 * @param array  $items Items.
 * @param array  $ctx   Context.
 */
function elroi_render_repeater( $f, $name, $items, $ctx ) {
	$top   = 0 === $ctx['depth'] && 1 === substr_count( $name, '[' );
	$ph    = '__' . chr( 65 + min( 25, $ctx['depth'] ) ) . '__';
	$inner = array_merge( $ctx, array( 'depth' => $ctx['depth'] + 1 ) );
	$grid  = 'grid' === $f['layout'];
	if ( $top ) {
		printf( '<h3 class="elroi-h">%s</h3>', esc_html( $f['label'] ) );
	}
	?>
	<div class="elroi-repeater<?php echo $top ? ' elroi-repeater--top' : ''; ?>" data-elroi-repeater>
		<?php if ( ! $top ) : ?>
			<p class="elroi-label"><?php echo esc_html( $f['label'] ); ?></p>
		<?php endif; ?>
		<?php if ( $f['help'] ) : ?>
			<p class="description"><?php echo esc_html( $f['help'] ); ?></p>
		<?php endif; ?>
		<div class="elroi-items<?php echo $grid ? ' elroi-items--grid' : ''; ?>" data-elroi-items>
			<?php
			foreach ( array_values( $items ) as $i => $item ) {
				elroi_render_item( $f, $name . '[i' . $i . ']', (array) $item, $inner, $i + 1 );
			}
			?>
		</div>
		<p class="elroi-add">
			<button type="button" class="button" data-elroi-add><?php echo esc_html( $f['add_label'] ?? 'Add ' . strtolower( $f['item'] ) ); ?></button>
			<?php if ( ! empty( $f['multi_image'] ) ) : ?>
				<button type="button" class="button button-primary" data-elroi-add-images="<?php echo esc_attr( $f['multi_image'] ); ?>">Add several photos</button>
			<?php endif; ?>
		</p>
		<template data-elroi-template><?php elroi_render_item( $f, $name . '[' . $ph . ']', array(), $inner, 0 ); ?></template>
	</div>
	<?php
}

/**
 * One repeater item.
 *
 * @param array  $f    Repeater field.
 * @param string $name Item name prefix.
 * @param array  $item Item values.
 * @param array  $ctx  Context.
 * @param int    $n    Position (0 for a new item).
 */
function elroi_render_item( $f, $name, $item, $ctx, $n ) {
	$title_key = $f['title'];
	$title     = isset( $item[ $title_key ] ) && is_scalar( $item[ $title_key ] ) ? (string) $item[ $title_key ] : '';
	$label     = '' !== $title ? $title : ( $n ? $f['item'] . ' ' . $n : 'New ' . strtolower( $f['item'] ) );
	$fields    = $f['fields'];
	if ( isset( $fields[ $title_key ] ) ) {
		$fields[ $title_key ]['title_source'] = true;
	}
	$extra = array_diff_key( $item, $f['fields'] );
	$grid  = 'grid' === $f['layout'];
	$tag   = $grid ? 'div' : 'details';
	?>
	<<?php echo $tag; // phpcs:ignore ?> class="elroi-item<?php echo $grid ? ' elroi-item--card' : ''; ?>" data-elroi-item>
		<?php if ( $grid ) : ?>
			<span class="screen-reader-text" data-elroi-title><?php echo esc_html( $label ); ?></span>
		<?php else : ?>
			<summary><span class="elroi-kind"><?php echo esc_html( $f['item'] ); ?></span> <span data-elroi-title><?php echo esc_html( $label ); ?></span></summary>
		<?php endif; ?>
		<div class="elroi-item__body">
			<?php
			if ( ! $grid ) {
				elroi_item_tools( $f['item'] );
			}
			elroi_render_fields( $fields, $name, $item, $ctx );
			if ( $extra ) {
				printf( '<input type="hidden" name="%s" value="%s">', esc_attr( $name . '[__extra]' ), esc_attr( wp_json_encode( $extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) );
			}
			if ( $grid ) {
				elroi_item_tools( $f['item'] );
			}
			?>
		</div>
	</<?php echo $tag; // phpcs:ignore ?>>
	<?php
}

/**
 * Move and remove buttons.
 *
 * @param string $what Item name.
 */
function elroi_item_tools( $what ) {
	?>
	<p class="elroi-tools">
		<button type="button" class="button button-small" data-elroi-up>Move up</button>
		<button type="button" class="button button-small" data-elroi-down>Move down</button>
		<button type="button" class="button-link elroi-danger" data-elroi-remove>Remove <?php echo esc_html( strtolower( $what ) ); ?></button>
	</p>
	<?php
}

/**
 * Image picker with its alt text (saved on the image itself, so it reads the same everywhere).
 *
 * @param string $name  Input name for the attachment ID.
 * @param string $label Label.
 * @param int    $id    Attachment ID.
 * @param string $help  Help text.
 */
function elroi_render_image( $name, $label, $id, $help = '' ) {
	$id  = absint( $id );
	$src = $id ? wp_get_attachment_image_url( $id, 'medium' ) : '';
	$alt = $id ? (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) : '';
	?>
	<div class="elroi-field elroi-image" data-elroi-image>
		<span class="elroi-label"><?php echo esc_html( $label ); ?></span>
		<?php if ( $help ) : ?>
			<p class="description"><?php echo esc_html( $help ); ?></p>
		<?php endif; ?>
		<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo $id ? (int) $id : ''; ?>" data-elroi-image-id>
		<div class="elroi-image__preview" data-elroi-image-preview><?php echo $src ? '<img src="' . esc_url( $src ) . '" alt="">' : ''; ?></div>
		<p class="elroi-image__actions">
			<button type="button" class="button" data-elroi-image-pick><?php echo $id ? 'Change image' : 'Choose image'; ?></button>
			<button type="button" class="button-link elroi-danger" data-elroi-image-remove<?php echo $id ? '' : ' hidden'; ?>>Remove image</button>
		</p>
		<label class="elroi-image__alt"<?php echo $id ? '' : ' hidden'; ?> data-elroi-image-altwrap>
			<span>Image description (alt text): what the photo shows, for screen readers and Google</span>
			<input type="text" name="<?php echo $id ? 'elroi_alt[' . (int) $id . ']' : ''; ?>" value="<?php echo esc_attr( $alt ); ?>" data-elroi-image-alt<?php echo $id ? ' required' : ''; ?>>
		</label>
	</div>
	<?php
}
