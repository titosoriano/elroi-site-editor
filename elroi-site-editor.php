<?php
/**
 * Plugin Name:       ElRoi Site Editor
 * Plugin URI:        https://github.com/titosoriano/elroi-site-editor
 * Description:       Simple forms in wp-admin so clients can change the words and pictures of their website while the design stays locked. The theme describes the fields; this plugin builds the forms, saves with revisions, adds Site Settings, Editor access and a "How to edit" guide.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            ElRoi Marketing
 * License:           GPL-2.0-or-later
 * Text Domain:       elroi-site-editor
 *
 * @package ElRoiSiteEditor
 */

defined( 'ABSPATH' ) || exit;

define( 'ELROI_SE_VERSION', '1.0.0' );
define( 'ELROI_SE_FILE', __FILE__ );
define( 'ELROI_SE_DIR', __DIR__ );
define( 'ELROI_SE_URL', plugin_dir_url( __FILE__ ) );

require_once ELROI_SE_DIR . '/includes/fields.php';
require_once ELROI_SE_DIR . '/includes/registry.php';
require_once ELROI_SE_DIR . '/includes/api.php';
require_once ELROI_SE_DIR . '/includes/form.php';
require_once ELROI_SE_DIR . '/includes/editor.php';
require_once ELROI_SE_DIR . '/includes/settings.php';
require_once ELROI_SE_DIR . '/includes/access.php';
require_once ELROI_SE_DIR . '/includes/updater.php';
