<?php
/**
 * Plugin Name: Post Link Drop
 * Plugin URI: https://github.com/kimcoleman/post-link-drop
 * Description: Import Instagram posts and build a locally-hosted image grid for link-in-bio style pages.
 * Version: 1.0.0
 * Author: Kim Coleman
 * Author URI: https://github.com/kimcoleman/
 * License: GPL-3.0+
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: post-link-drop
 * Domain Path: /languages
 *
 * @package Post_Link_Drop
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Plugin version.
define( 'POST_LINK_DROP_VERSION', '1.0.0' );

// Plugin directory path.
define( 'POST_LINK_DROP_PATH', plugin_dir_path( __FILE__ ) );

// Plugin directory URL.
define( 'POST_LINK_DROP_URL', plugin_dir_url( __FILE__ ) );

// Plugin basename.
define( 'POST_LINK_DROP_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Load the plugin.
 */
require_once POST_LINK_DROP_PATH . 'includes/class-plugin.php';

/**
 * Initialize the plugin.
 *
 * @return Post_Link_Drop\Plugin
 */
function post_link_drop() {
	return Post_Link_Drop\Plugin::instance();
}

// Start the plugin.
post_link_drop();
