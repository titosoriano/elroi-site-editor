<?php
/**
 * Client access: Editors change words and pictures; the structure of designed pages stays locked.
 *
 * Locked pages are those whose template is registered (unless it sets 'lock' => false), the home
 * page and the posts page. On them only administrators can change the template, slug, parent,
 * status or order, or delete the page. Also adds the "How to edit" guide and a dashboard widget.
 *
 * @package ElRoiSiteEditor
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether a page's structure is locked for non-administrators.
 *
 * @param int|WP_Post $post Page.
 */
function elroi_is_locked_page( $post ) {
	$post = get_post( $post );
	if ( ! $post || 'page' !== $post->post_type ) {
		return false;
	}
	$cfg    = elroi_template_for( $post );
	$locked = ( $cfg && $cfg['lock'] ) || in_array( $post->ID, array( (int) get_option( 'page_on_front' ), (int) get_option( 'page_for_posts' ) ), true );
	return (bool) apply_filters( 'elroi_is_locked_page', $locked, $post );
}

/**
 * Whether the current user may change page structure.
 */
function elroi_can_restructure() {
	return current_user_can( 'manage_options' );
}

add_filter(
	'map_meta_cap',
	function ( $caps, $cap, $user_id, $args ) {
		if ( 'delete_post' === $cap && ! empty( $args[0] ) && elroi_is_locked_page( $args[0] ) && ! user_can( $user_id, 'manage_options' ) ) {
			$caps[] = 'do_not_allow';
		}
		return $caps;
	},
	10,
	4
);

add_filter(
	'wp_insert_post_data',
	function ( $data, $postarr ) {
		$id = (int) ( $postarr['ID'] ?? 0 );
		if ( ! $id || 'revision' === $data['post_type'] || elroi_can_restructure() || ! elroi_is_locked_page( $id ) ) {
			return $data;
		}
		$old = get_post( $id );
		foreach ( array( 'post_name', 'post_parent', 'post_status', 'menu_order' ) as $field ) {
			$data[ $field ] = $old->$field;
		}
		return $data;
	},
	10,
	2
);

add_filter(
	'update_post_metadata',
	function ( $check, $id, $key, $value ) {
		if ( '_wp_page_template' === $key && ! elroi_can_restructure() && elroi_is_locked_page( $id ) && get_post_meta( $id, '_wp_page_template', true ) !== $value ) {
			return false;
		}
		return $check;
	},
	10,
	4
);

add_action(
	'add_meta_boxes_page',
	function ( $post ) {
		if ( ! elroi_can_restructure() && elroi_is_locked_page( $post ) ) {
			remove_meta_box( 'pageparentdiv', 'page', 'side' );
			remove_meta_box( 'slugdiv', 'page', 'normal' );
		}
	},
	20
);

add_action(
	'admin_head-post.php',
	function () {
		if ( ! elroi_can_restructure() && elroi_is_locked_page( get_post() ) ) {
			echo '<style>#misc-publishing-actions .edit-post-status,#misc-publishing-actions .edit-visibility,#misc-publishing-actions .edit-timestamp,#edit-slug-buttons{display:none}</style>';
		}
	}
);

add_action(
	'wp_dashboard_setup',
	function () {
		if ( current_user_can( elroi_client_cap() ) ) {
			wp_add_dashboard_widget( 'elroi-guide', 'Editing your website', 'elroi_guide_widget' );
		}
	}
);

/**
 * Pages using each registered template: label => list of WP_Post.
 */
function elroi_pages_by_template() {
	$out = array();
	foreach ( elroi_templates() as $tpl => $cfg ) {
		if ( 'front-page' === $tpl ) {
			$front = (int) get_option( 'page_on_front' );
			$pages = $front ? array( get_post( $front ) ) : array();
		} else {
			$pages = get_pages(
				array(
					'meta_key'    => '_wp_page_template', // phpcs:ignore WordPress.DB.SlowDBQuery
					'meta_value'  => $tpl, // phpcs:ignore WordPress.DB.SlowDBQuery
					'post_status' => 'publish,draft,private',
				)
			);
		}
		if ( $pages ) {
			$out[ $cfg['label'] ] = array_merge( $out[ $cfg['label'] ] ?? array(), $pages );
		}
	}
	return $out;
}

/**
 * Dashboard widget.
 */
function elroi_guide_widget() {
	?>
	<p>The design of this website is fixed. You change its words and pictures here:</p>
	<ul style="list-style:disc;padding-left:18px">
		<?php if ( elroi_settings_config() ) : ?>
			<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=elroi-settings' ) ); ?>">Site Settings</a> (phones, addresses, email and other details used on every page)</li>
		<?php endif; ?>
		<li><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=page' ) ); ?>">Pages</a></li>
		<li><a href="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>">Blog posts</a></li>
	</ul>
	<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=elroi-guide' ) ); ?>">Read the 5-minute guide</a></p>
	<?php
}

/**
 * The "How to edit" guide, built from what the theme registered.
 */
function elroi_guide_page() {
	$cfg     = elroi_settings_config();
	$support = $cfg && $cfg['support'] ? $cfg['support'] : 'your website team';
	$keys    = array_keys( elroi_placeholders() );
	?>
	<div class="wrap" style="max-width:820px">
		<h1>How to edit your website</h1>
		<p>The layout, colors and fonts are designed to stay exactly as they are. What you change are the words and pictures, in simple forms. Nothing you type there can break the design.</p>

		<h2>Where things are</h2>
		<table class="widefat striped" role="presentation">
			<tbody>
				<?php if ( $cfg ) : ?>
					<tr><th scope="row" style="width:34%">Phones, addresses, email and other details used on many pages</th><td><a href="<?php echo esc_url( admin_url( 'admin.php?page=elroi-settings' ) ); ?>">Site Settings</a>. Change it once and it updates everywhere.</td></tr>
				<?php endif; ?>
				<?php foreach ( elroi_pages_by_template() as $label => $pages ) : ?>
					<tr>
						<th scope="row" style="width:34%"><?php echo esc_html( $label ); ?></th>
						<td>
							<?php
							$links = array();
							foreach ( array_slice( $pages, 0, 12 ) as $p ) {
								$links[] = '<a href="' . esc_url( get_edit_post_link( $p ) ) . '">' . esc_html( get_the_title( $p ) ) . '</a>';
							}
							echo implode( ', ', $links ); // phpcs:ignore WordPress.Security.EscapeOutput
							if ( count( $pages ) > 12 ) {
								echo ' and ' . (int) ( count( $pages ) - 12 ) . ' more in Pages.';
							}
							?>
							Open the page and use the form under the title.
						</td>
					</tr>
				<?php endforeach; ?>
				<tr><th scope="row">Blog posts</th><td>Posts &gt; Add New. Write in the editor, and add a <strong>featured image</strong>, a short <strong>excerpt</strong> and a category.</td></tr>
			</tbody>
		</table>

		<h2>Writing in the forms</h2>
		<ul style="list-style:disc;padding-left:18px">
			<li><strong>Paragraphs:</strong> leave a blank line between them.</li>
			<li><strong>Lists:</strong> one item per line.</li>
			<li><strong>Highlighted words in a heading:</strong> put them between asterisks, for example <code>Ready to start *your project?*</code></li>
			<?php if ( $keys ) : ?>
				<li><strong>Details from Site Settings:</strong> type their name in curly brackets and the current value appears, for example <?php echo implode( ', ', array_map( fn( $k ) => '<code>' . esc_html( $k ) . '</code>', array_slice( $keys, 0, 4 ) ) ); // phpcs:ignore ?>.</li>
			<?php endif; ?>
			<li><strong>An empty field</strong> goes back to the original text, where there is one.</li>
			<li>Click a grey bar to open a section or item. Use <strong>Move up</strong>, <strong>Move down</strong> and <strong>Remove</strong> to reorder or delete, and the <strong>Add</strong> buttons for new ones.</li>
			<li>Keep the main heading (H1) of each page about its subject: Google reads it first.</li>
		</ul>

		<h2>Pictures</h2>
		<ul style="list-style:disc;padding-left:18px">
			<li>Click <strong>Choose image</strong> or <strong>Change image</strong>, then upload a photo or pick one from the Media Library.</li>
			<li>Use real photos, at least 1600 pixels wide for large ones, in JPG or WebP.</li>
			<li>Every photo needs a <strong>description</strong> (alt text) that says what it shows. People using screen readers hear it and Google reads it. The form will not save without it.</li>
		</ul>

		<h2>Saving and undoing</h2>
		<ul style="list-style:disc;padding-left:18px">
			<li>Click <strong>Update</strong> to publish, then open the page to see it. The <strong>Preview</strong> button does not show changes made in these forms.</li>
			<li>Every update is kept as a revision. In the <strong>Publish</strong> box, click <strong>Browse</strong> next to Revisions to compare versions, then <strong>Restore This Revision</strong> to go back.</li>
		</ul>

		<h2>What is locked, and why</h2>
		<p>On the designed pages you cannot change the page address, template or parent, unpublish them or delete them: those changes break links, search rankings and the layout. For a new page, a new kind of section, a menu change or anything that looks wrong, contact <?php echo esc_html( $support ); ?>.</p>
	</div>
	<?php
}
