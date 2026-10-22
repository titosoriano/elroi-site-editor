<?php
/**
 * Test runner for the local Playground site: open /elroi-tests/run.php. Prints JSON with each
 * check and whether it passed. It changes the test site's content and puts it back.
 *
 * @package ElRoiSiteEditor
 */

require dirname( __DIR__ ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/post.php';
require_once ABSPATH . 'wp-admin/includes/template.php';
require_once __DIR__ . '/setup.php';
wp_set_current_user( 1 );
header( 'Content-Type: application/json' );

$results = array();
$check   = function ( $name, $ok, $info = null ) use ( &$results ) {
	$results[] = array( 'ok' => (bool) $ok, 'check' => $name ) + ( $ok || null === $info ? array() : array( 'info' => $info ) );
};

/**
 * Post the form as a browser would: render the box, read every input (no <template> contents,
 * only ticked checkboxes) and parse it like PHP parses a request.
 *
 * @param WP_Post $post Page.
 */
function elroi_test_form( $post ) {
	ob_start();
	elroi_box( $post );
	$html = ob_get_clean();
	$dom  = new DOMDocument();
	libxml_use_internal_errors( true );
	$dom->loadHTML( '<?xml encoding="utf-8"?><div>' . $html . '</div>' );
	foreach ( iterator_to_array( $dom->getElementsByTagName( 'template' ) ) as $t ) {
		$t->parentNode->removeChild( $t );
	}
	$pairs = array();
	$ids   = array();
	foreach ( $dom->getElementsByTagName( 'input' ) as $i ) {
		if ( $i->getAttribute( 'id' ) ) {
			$ids[] = $i->getAttribute( 'id' );
		}
		if ( ! $i->getAttribute( 'name' ) || ( 'checkbox' === $i->getAttribute( 'type' ) && ! $i->hasAttribute( 'checked' ) ) ) {
			continue;
		}
		$pairs[] = rawurlencode( $i->getAttribute( 'name' ) ) . '=' . rawurlencode( $i->getAttribute( 'value' ) );
	}
	foreach ( $dom->getElementsByTagName( 'textarea' ) as $i ) {
		$ids[]   = $i->getAttribute( 'id' );
		$pairs[] = rawurlencode( $i->getAttribute( 'name' ) ) . '=' . rawurlencode( $i->textContent );
	}
	foreach ( $dom->getElementsByTagName( 'select' ) as $s ) {
		$ids[] = $s->getAttribute( 'id' );
		foreach ( $s->getElementsByTagName( 'option' ) as $o ) {
			if ( $o->hasAttribute( 'selected' ) ) {
				$pairs[] = rawurlencode( $s->getAttribute( 'name' ) ) . '=' . rawurlencode( $o->getAttribute( 'value' ) );
			}
		}
	}
	parse_str( implode( '&', $pairs ), $post_data );
	return array( $post_data, count( $ids ) - count( array_unique( $ids ) ), $html );
}

/**
 * Save a page through WordPress with the given form data, as the Update button does.
 *
 * @param int   $id   Page ID.
 * @param array $data Form data.
 */
function elroi_test_save( $id, $data ) {
	$_POST                = wp_slash( $data );
	$_POST['elroi_nonce'] = wp_create_nonce( 'elroi_save' );
	wp_update_post( array( 'ID' => $id ) );
	$_POST = array();
}

$imgs    = (array) get_option( 'elroi_test_images', array() );
$home    = get_post( (int) get_option( 'page_on_front' ) );
$landing = get_page_by_path( 'landing' );
$about   = get_page_by_path( 'about' );
$backup  = array();
foreach ( array( $home, $landing, $about ) as $p ) {
	$cfg                = elroi_template_for( $p );
	$backup[ $p->ID ]   = get_post_meta( $p->ID, $cfg['meta_key'], true );
}

// 1. Registry.
$check( 'home page uses the front-page schema', 'Home page' === ( elroi_template_for( $home )['label'] ?? '' ) );
$check( 'landing page uses its template schema', 'Landing page' === ( elroi_template_for( $landing )['label'] ?? '' ) );
$check( 'block editor is off on registered pages', ! use_block_editor_for_post( $landing ) );

// 2. Defaults and placeholders.
$c = elroi_content( $home );
$check( 'defaults fill an empty page', 'We build *better homes.*' === $c['hero']['h1'] && 2 === count( $c['services'] ) );
$check( 'heading highlight becomes <em>', 'We build <em>better homes.</em>' === elroi_heading( $c['hero']['h1'] ) );
$check( 'placeholders come from Site Settings', '10+ years' === elroi_text( $c['facts']['a'] ) );
$check( 'text is escaped', '&lt;b&gt;x&lt;/b&gt;' === elroi_text( '<b>x</b>' ) );

// 3. Saving the unchanged form stores nothing new and changes nothing.
foreach ( array( $home, $landing ) as $p ) {
	list( $form, $dups ) = elroi_test_form( $p );
	$check( "no duplicate ids in the {$p->post_name} form", 0 === $dups, $dups );
	$before = elroi_content( $p );
	elroi_test_save( $p->ID, $form );
	$check( "unchanged save keeps the {$p->post_name} content", elroi_content( $p ) == $before, array( 'saved' => elroi_saved( $p ) ) );
}
$check( 'unchanged home save stores only changes (nothing)', array() === elroi_saved( $home ), elroi_saved( $home ) );

// 4. Real edits on the landing page: nested repeaters, checkboxes from a lines field, numbers, select.
list( $form ) = elroi_test_form( $landing );
$form['elroi']['h1']         = 'Spring *offer*';
$form['elroi']['intro']      = "First paragraph.\n\nSecond <script>x</script> paragraph.";
$form['elroi']['categories'] = "Kitchen\nRoof";
$form['elroi']['sections']   = array(
	'nA' => array(
		'heading'    => 'Why us',
		'paragraphs' => 'One.',
		'bullets'    => "Fast\n\nClean",
		'image_id'   => (string) ( $imgs[0] ?? '' ),
		'cards'      => array(
			'nB1' => array( 'heading' => 'Card one', 'text' => 'A' ),
			'nB2' => array( 'heading' => '', 'text' => '' ),
		),
	),
);
$form['elroi']['photos']     = array(
	'p1' => array( 'id' => (string) ( $imgs[1] ?? '' ), 'cats' => array( 'Roof', 'Bogus' ) ),
	'p2' => array( 'id' => (string) ( $imgs[0] ?? '' ), 'cats' => array( 'Kitchen' ), '__extra' => wp_json_encode( array( 'src' => 'https://old.example/x.jpg', 'n' => 3 ) ) ),
);
$form['elroi']['framing']    = array( '0.52', '', '1.4' );
$form['elroi']['style']      = 'Dark';
$form['elroi']['team_note']  = 'should be ignored (field hidden on this page)';
$form['elroi_alt']           = array( ( $imgs[0] ?? 0 ) => 'Teal test photo' );
elroi_test_save( $landing->ID, $form );
$s = elroi_saved( $landing );
$check( 'paragraphs split and cleaned', array( 'First paragraph.', 'Second paragraph.' ) === ( $s['intro'] ?? null ), $s['intro'] ?? null );
$check( 'nested repeater saved, empty card dropped', 1 === count( $s['sections'][0]['cards'] ?? array() ) && array( 'Fast', 'Clean' ) === $s['sections'][0]['bullets'], $s['sections'] ?? null );
$check( 'checkboxes keep only listed options', array( 'Roof' ) === $s['photos'][0]['cats'], $s['photos'] ?? null );
$check( 'hidden extra keys survive the form', 'https://old.example/x.jpg' === ( $s['photos'][1]['src'] ?? '' ) && 3 === ( $s['photos'][1]['n'] ?? 0 ), $s['photos'][1] ?? null );
$check( 'numbers fill blanks with their default', array( 0.52, 0.33, 1.4 ) === ( $s['framing'] ?? null ), $s['framing'] ?? null );
$check( 'select saved', 'Dark' === ( $s['style'] ?? '' ) );
$check( 'field hidden by show_if is not saved', ! isset( $s['team_note'] ) );
$check( 'alt text saved on the image', ! $imgs || 'Teal test photo' === get_post_meta( $imgs[0], '_wp_attachment_image_alt', true ) );
$check( 'a revision keeps the meta', (bool) array_filter( wp_get_post_revisions( $landing->ID ), fn( $r ) => '' !== get_post_meta( $r->ID, '_elroi_content', true ) ) );

// Round trip after edits: the form shows what was saved and saving it again changes nothing.
list( $form2 ) = elroi_test_form( $landing );
elroi_test_save( $landing->ID, $form2 );
$check( 'edited page round-trips through the form', elroi_saved( $landing ) == $s, array( 'before' => $s, 'after' => elroi_saved( $landing ) ) );

// Hidden field on another page keeps its stored value when saved from a page that hides it.
update_post_meta( $landing->ID, '_elroi_content', wp_slash( wp_json_encode( array_merge( $s, array( 'team_note' => 'kept' ) ) ) ) );
list( $form3 ) = elroi_test_form( $landing );
elroi_test_save( $landing->ID, $form3 );
$check( 'stored value of a hidden field is kept', 'kept' === ( elroi_saved( $landing )['team_note'] ?? '' ) );
list( , , $about_html ) = elroi_test_form( $about );
$check( 'show_if shows the field on the About page', str_contains( $about_html, 'Only on the About page' ) );

// Restoring a revision brings the content back.
$revs = array_values( wp_get_post_revisions( $landing->ID ) );
list( $f4 ) = elroi_test_form( $landing );
$f4['elroi']['h1'] = 'Changed again';
elroi_test_save( $landing->ID, $f4 );
$target = null;
foreach ( wp_get_post_revisions( $landing->ID ) as $r ) {
	$m = json_decode( (string) get_post_meta( $r->ID, '_elroi_content', true ), true );
	if ( ( $m['h1'] ?? '' ) === 'Spring *offer*' ) {
		$target = $r;
		break;
	}
}
if ( $target ) {
	wp_restore_post_revision( $target->ID );
}
$check( 'restoring a revision brings back the old content', 'Spring *offer*' === ( elroi_saved( $landing )['h1'] ?? '' ) );

// Home: a changed default is stored, an emptied field falls back to the default.
list( $hf ) = elroi_test_form( $home );
$hf['elroi']['hero']['eyebrow'] = '';
$hf['elroi']['hero']['h1']      = 'New *title*';
elroi_test_save( $home->ID, $hf );
$check( 'only the changed home field is stored', array( 'hero' => array( 'h1' => 'New *title*' ) ) === elroi_saved( $home ), elroi_saved( $home ) );
$check( 'emptied field shows the default', 'Demo Co.' === elroi_content( $home )['hero']['eyebrow'] );

// 5. Site Settings.
$cfg = elroi_settings_config();
$out = elroi_settings_sanitize( array( 'phone' => '(555) 222-3333', 'email' => 'not an email', 'instagram' => '', 'years' => '' ) );
$check( 'settings store changes, clear clearable fields', array( 'phone' => '(555) 222-3333', 'instagram' => '-' ) === $out, $out );
update_option( $cfg['option'], $out );
$check( 'settings read with defaults and cleared values', '(555) 222-3333' === elroi_setting( 'phone' ) && 'hello@example.com' === elroi_setting( 'email' ) && '' === elroi_setting( 'instagram' ) && '10+' === elroi_setting( 'years' ) );
$check( 'settings screen capability is the client capability', 'edit_others_pages' === apply_filters( 'option_page_capability_elroi_settings', 'manage_options' ) );
delete_option( $cfg['option'] );

// 6. Locks for an Editor (an in-memory user; no account is created).
$admin = wp_get_current_user();
$ed    = new WP_User();
$ed->ID      = 987654;
$ed->roles   = array( 'editor' );
$ed->caps    = array( 'editor' => true );
$ed->allcaps = array_merge( get_role( 'editor' )->capabilities, array( 'editor' => true ) );
$GLOBALS['current_user'] = $ed;
$data = apply_filters( 'wp_insert_post_data', array( 'post_name' => 'moved', 'post_parent' => 9, 'post_status' => 'draft', 'menu_order' => 4, 'post_type' => 'page' ), array( 'ID' => $landing->ID ), array(), false );
$check( 'editor cannot delete a designed page', ! current_user_can( 'delete_post', $landing->ID ) );
$check( 'editor cannot change slug, parent, status or order', array( 'landing', 0, 'publish', 0 ) === array( $data['post_name'], $data['post_parent'], $data['post_status'], $data['menu_order'] ), $data );
$check( 'editor cannot change the template', false === apply_filters( 'update_post_metadata', null, $landing->ID, '_wp_page_template', 'default', '' ) );
$check( 'editor can edit the page and open settings', current_user_can( 'edit_post', $landing->ID ) && current_user_can( elroi_client_cap() ) );
$GLOBALS['current_user'] = null;
wp_set_current_user( $admin->ID );
$check( 'admin can still delete', current_user_can( 'delete_post', $landing->ID ) );

// 7. Guide renders.
ob_start();
elroi_guide_page();
elroi_guide_widget();
$guide = ob_get_clean();
$check( 'guide lists the registered page types', str_contains( $guide, 'Landing page' ) && str_contains( $guide, 'Home page' ) && str_contains( $guide, 'ElRoi Marketing' ) );

// 8. Updates from GitHub, with GitHub's answers simulated (no network, a fake token).
if ( ! defined( 'ELROI_SE_GITHUB_TOKEN' ) ) {
	define( 'ELROI_SE_GITHUB_TOKEN', 'test-token' );
}
delete_site_transient( 'elroi_se_release' );
$seen = array();
$mock = function ( $pre, $args, $url ) use ( &$seen ) {
	$seen[] = array( $url, $args['headers']['Authorization'] ?? '', $args['redirection'] ?? null );
	if ( str_ends_with( $url, '/releases/latest' ) ) {
		return array( 'response' => array( 'code' => 200, 'message' => 'OK' ), 'headers' => array(), 'body' => wp_json_encode( array( 'tag_name' => 'v9.9.9', 'body' => 'Notes', 'published_at' => '2026-10-22', 'assets' => array( array( 'name' => 'elroi-site-editor.zip', 'url' => 'https://api.github.com/repos/' . ELROI_SE_REPO . '/releases/assets/1', 'browser_download_url' => 'https://github.com/' . ELROI_SE_REPO . '/releases/download/v9.9.9/elroi-site-editor.zip' ) ) ) ), 'cookies' => array() );
	}
	if ( str_contains( $url, '/releases/assets/1' ) ) {
		return array( 'response' => array( 'code' => 302, 'message' => 'Found' ), 'headers' => array( 'location' => 'https://objects.example/signed.zip' ), 'body' => '', 'cookies' => array() );
	}
	if ( 'https://objects.example/signed.zip' === $url ) {
		if ( ! empty( $args['filename'] ) ) {
			file_put_contents( $args['filename'], 'zip' ); // phpcs:ignore
		}
		return array( 'response' => array( 'code' => 200, 'message' => 'OK' ), 'headers' => array(), 'body' => '', 'cookies' => array() );
	}
	return $pre;
};
add_filter( 'pre_http_request', $mock, 10, 3 );
$t   = apply_filters( 'pre_set_site_transient_update_plugins', (object) array( 'response' => array() ) );
$upd = $t->response[ plugin_basename( ELROI_SE_FILE ) ] ?? null;
$check( 'a newer GitHub release shows as an update', $upd && '9.9.9' === $upd->new_version, $t );
$file = apply_filters( 'upgrader_pre_download', false, $upd ? $upd->package : '', null, array() );
$check( 'the update downloads through the signed link', is_string( $file ) && file_exists( $file ), $file );
$signed = array_values( array_filter( $seen, fn( $x ) => 'https://objects.example/signed.zip' === $x[0] ) );
$check( 'the token is sent to GitHub but not to the signed link', 'Bearer test-token' === ( $seen[0][1] ?? '' ) && $signed && '' === $signed[0][1], $seen );
if ( is_string( $file ) ) {
	wp_delete_file( $file );
}
// Without a token (the public repository): no Authorization header, the public download link,
// and WordPress downloads it itself.
add_filter( 'elroi_se_github_token', '__return_empty_string' );
delete_site_transient( 'elroi_se_release' );
$seen = array();
$t    = apply_filters( 'pre_set_site_transient_update_plugins', (object) array( 'response' => array() ) );
$upd  = $t->response[ plugin_basename( ELROI_SE_FILE ) ] ?? null;
$check( 'without a token the update uses the public download link', $upd && str_starts_with( $upd->package, 'https://github.com/' ) && '' === ( $seen[0][1] ?? 'x' ), array( $upd, $seen ) );
$check( 'without a token WordPress downloads the package itself', false === apply_filters( 'upgrader_pre_download', false, $upd ? $upd->package : '', null, array() ) );
remove_filter( 'elroi_se_github_token', '__return_empty_string' );
remove_filter( 'pre_http_request', $mock, 10 );
delete_site_transient( 'elroi_se_release' );

// Put the content back.
foreach ( $backup as $id => $raw ) {
	$cfg = elroi_template_for( $id );
	'' === $raw ? delete_post_meta( $id, $cfg['meta_key'] ) : update_post_meta( $id, $cfg['meta_key'], wp_slash( $raw ) );
}

$failed = array_values( array_filter( $results, fn( $r ) => ! $r['ok'] ) );
echo wp_json_encode(
	array(
		'passed' => count( $results ) - count( $failed ),
		'failed' => count( $failed ),
		'fails'  => $failed,
		'all'    => array_column( $results, 'check' ),
	),
	JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
);
