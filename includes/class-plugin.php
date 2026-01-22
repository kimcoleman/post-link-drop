<?php
/**
 * Main plugin class.
 *
 * @package Post_Link_Drop
 */

namespace Post_Link_Drop;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Main plugin class - singleton.
 */
class Plugin {

	/**
	 * Single instance of the class.
	 *
	 * @var Plugin
	 */
	private static $instance = null;

	/**
	 * Post Link Drop Item instance.
	 *
	 * @var CPT_Post_Link_Drop_Item
	 */
	public $cpt;

	/**
	 * Settings instance.
	 *
	 * @var Settings
	 */
	public $settings;

	/**
	 * Render Grid instance.
	 *
	 * @var Render_Grid
	 */
	public $render;

	/**
	 * Alt Text instance.
	 *
	 * @var Alt_Text
	 */
	public $alt_text;

	/**
	 * Media Sideload instance.
	 *
	 * @var Media_Sideload
	 */
	public $media;

	/**
	 * Import Instagram instance.
	 *
	 * @var Import_Instagram
	 */
	public $importer;

	/**
	 * Jobs instance.
	 *
	 * @var Jobs
	 */
	public $jobs;

	/**
	 * Get the single instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->init_modules();
		$this->register_hooks();
	}

	/**
	 * Load required files.
	 */
	private function load_dependencies() {
		require_once POST_LINK_DROP_PATH . 'includes/class-cpt-post-link-drop-item.php';
		require_once POST_LINK_DROP_PATH . 'includes/class-settings.php';
		require_once POST_LINK_DROP_PATH . 'includes/class-render-grid.php';
		require_once POST_LINK_DROP_PATH . 'includes/class-alt-text.php';
		require_once POST_LINK_DROP_PATH . 'includes/class-media-sideload.php';
		require_once POST_LINK_DROP_PATH . 'includes/class-import-instagram.php';
		require_once POST_LINK_DROP_PATH . 'includes/class-jobs.php';
	}

	/**
	 * Initialize modules.
	 */
	private function init_modules() {
		$this->alt_text = new Alt_Text();
		$this->media    = new Media_Sideload();
		$this->cpt      = new CPT_Post_Link_Drop_Item();
		$this->settings = new Settings();
		$this->render   = new Render_Grid();
		$this->importer = new Import_Instagram();
		$this->jobs     = new Jobs();
	}

	/**
	 * Register activation/deactivation hooks.
	 */
	private function register_hooks() {
		register_activation_hook( POST_LINK_DROP_PATH . 'post-link-drop.php', array( $this, 'activate' ) );
		register_deactivation_hook( POST_LINK_DROP_PATH . 'post-link-drop.php', array( $this, 'deactivate' ) );
	}

	/**
	 * Plugin activation.
	 */
	public function activate() {
		// Register CPT so rewrite rules can be flushed.
		$this->cpt->register_post_type();

		// Add custom image size.
		add_image_size( 'pld_square', 600, 600, true );

		// Flush rewrite rules.
		flush_rewrite_rules();

		// Schedule cron job if import is enabled.
		$this->jobs->schedule_import();

		// Set default options.
		$defaults = array(
			'import_enabled'  => false,
			'import_interval' => 'hourly',
			'import_limit'    => 24,
			'access_token'    => '',
			'ig_user_id'      => '',
		);

		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( 'post_link_drop_' . $key ) ) {
				add_option( 'post_link_drop_' . $key, $value );
			}
		}
	}

	/**
	 * Plugin deactivation.
	 */
	public function deactivate() {
		// Clear scheduled cron.
		$this->jobs->unschedule_import();

		// Flush rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Clear grid cache transients.
	 */
	public static function clear_grid_cache() {
		global $wpdb;
		$wpdb->query(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_pld_grid_%' OR option_name LIKE '_transient_timeout_pld_grid_%'"
		);
	}
}
