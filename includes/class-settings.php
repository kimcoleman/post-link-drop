<?php
/**
 * Settings page for Post Link Drop.
 *
 * @package Post_Link_Drop
 */

namespace Post_Link_Drop;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Settings class.
 */
class Settings {

	/**
	 * Settings page slug.
	 */
	const PAGE_SLUG = 'link-drop-settings';

	/**
	 * Option group.
	 */
	const OPTION_GROUP = 'post_link_drop_options';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_pld_run_import', array( $this, 'handle_run_import' ) );
		add_action( 'admin_post_pld_test_connection', array( $this, 'handle_test_connection' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
	}

	/**
	 * Add settings page to menu.
	 */
	public function add_settings_page() {
		add_submenu_page(
			'edit.php?post_type=' . CPT_Post_Link_Drop_Item::POST_TYPE,
			__( 'Settings', 'post-link-drop' ),
			__( 'Settings', 'post-link-drop' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings.
	 */
	public function register_settings() {
		// Instagram Connection Section.
		add_settings_section(
			'pld_instagram_section',
			__( 'Instagram Connection', 'post-link-drop' ),
			array( $this, 'render_instagram_section' ),
			self::PAGE_SLUG
		);

		register_setting( self::OPTION_GROUP, 'post_link_drop_access_token', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( self::OPTION_GROUP, 'post_link_drop_ig_user_id', array( 'sanitize_callback' => 'sanitize_text_field' ) );

		add_settings_field(
			'post_link_drop_access_token',
			__( 'Access Token', 'post-link-drop' ),
			array( $this, 'render_access_token_field' ),
			self::PAGE_SLUG,
			'pld_instagram_section'
		);

		add_settings_field(
			'post_link_drop_ig_user_id',
			__( 'Instagram User ID', 'post-link-drop' ),
			array( $this, 'render_user_id_field' ),
			self::PAGE_SLUG,
			'pld_instagram_section'
		);

		// Import Settings Section.
		add_settings_section(
			'pld_import_section',
			__( 'Import Settings', 'post-link-drop' ),
			array( $this, 'render_import_section' ),
			self::PAGE_SLUG
		);

		register_setting( self::OPTION_GROUP, 'post_link_drop_import_enabled', array( 'sanitize_callback' => array( $this, 'sanitize_checkbox' ) ) );
		register_setting( self::OPTION_GROUP, 'post_link_drop_import_interval', array( 'sanitize_callback' => 'sanitize_key' ) );
		register_setting( self::OPTION_GROUP, 'post_link_drop_import_limit', array( 'sanitize_callback' => 'absint' ) );

		add_settings_field(
			'post_link_drop_import_enabled',
			__( 'Enable Auto-Import', 'post-link-drop' ),
			array( $this, 'render_import_enabled_field' ),
			self::PAGE_SLUG,
			'pld_import_section'
		);

		add_settings_field(
			'post_link_drop_import_interval',
			__( 'Import Interval', 'post-link-drop' ),
			array( $this, 'render_import_interval_field' ),
			self::PAGE_SLUG,
			'pld_import_section'
		);

		add_settings_field(
			'post_link_drop_import_limit',
			__( 'Import Limit', 'post-link-drop' ),
			array( $this, 'render_import_limit_field' ),
			self::PAGE_SLUG,
			'pld_import_section'
		);
	}

	/**
	 * Sanitize checkbox value.
	 *
	 * @param mixed $value Input value.
	 * @return bool Sanitized value.
	 */
	public function sanitize_checkbox( $value ) {
		return (bool) $value;
	}

	/**
	 * Render Instagram section description.
	 */
	public function render_instagram_section() {
		echo '<p>' . esc_html__( 'Connect your Instagram Business or Creator account. You need a long-lived access token from the Instagram Graph API.', 'post-link-drop' ) . '</p>';
	}

	/**
	 * Render import section description.
	 */
	public function render_import_section() {
		echo '<p>' . esc_html__( 'Configure how often and how many posts to import from Instagram.', 'post-link-drop' ) . '</p>';
	}

	/**
	 * Render access token field.
	 */
	public function render_access_token_field() {
		$value = get_option( 'post_link_drop_access_token', '' );
		?>
		<input type="password" name="post_link_drop_access_token" id="post_link_drop_access_token" value="<?php echo esc_attr( $value ); ?>" class="regular-text" autocomplete="off">
		<p class="description">
			<?php esc_html_e( 'Your Instagram Graph API long-lived access token.', 'post-link-drop' ); ?>
			<a href="https://developers.facebook.com/docs/instagram-basic-display-api/guides/long-lived-access-tokens" target="_blank" rel="noopener"><?php esc_html_e( 'Learn more', 'post-link-drop' ); ?></a>
		</p>
		<?php
	}

	/**
	 * Render user ID field.
	 */
	public function render_user_id_field() {
		$value = get_option( 'post_link_drop_ig_user_id', '' );
		?>
		<input type="text" name="post_link_drop_ig_user_id" id="post_link_drop_ig_user_id" value="<?php echo esc_attr( $value ); ?>" class="regular-text">
		<p class="description"><?php esc_html_e( 'Your Instagram Business/Creator account user ID (numeric).', 'post-link-drop' ); ?></p>
		<?php
	}

	/**
	 * Render import enabled field.
	 */
	public function render_import_enabled_field() {
		$value = get_option( 'post_link_drop_import_enabled', false );
		?>
		<label>
			<input type="checkbox" name="post_link_drop_import_enabled" value="1" <?php checked( $value ); ?>>
			<?php esc_html_e( 'Automatically import new posts on a schedule', 'post-link-drop' ); ?>
		</label>
		<?php
	}

	/**
	 * Render import interval field.
	 */
	public function render_import_interval_field() {
		$value = get_option( 'post_link_drop_import_interval', 'hourly' );
		?>
		<select name="post_link_drop_import_interval" id="post_link_drop_import_interval">
			<option value="pld_15min" <?php selected( $value, 'pld_15min' ); ?>><?php esc_html_e( 'Every 15 minutes', 'post-link-drop' ); ?></option>
			<option value="pld_30min" <?php selected( $value, 'pld_30min' ); ?>><?php esc_html_e( 'Every 30 minutes', 'post-link-drop' ); ?></option>
			<option value="hourly" <?php selected( $value, 'hourly' ); ?>><?php esc_html_e( 'Hourly', 'post-link-drop' ); ?></option>
			<option value="twicedaily" <?php selected( $value, 'twicedaily' ); ?>><?php esc_html_e( 'Twice Daily', 'post-link-drop' ); ?></option>
			<option value="daily" <?php selected( $value, 'daily' ); ?>><?php esc_html_e( 'Daily', 'post-link-drop' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Render import limit field.
	 */
	public function render_import_limit_field() {
		$value = get_option( 'post_link_drop_import_limit', 24 );
		?>
		<select name="post_link_drop_import_limit" id="post_link_drop_import_limit">
			<option value="12" <?php selected( $value, 12 ); ?>>12</option>
			<option value="24" <?php selected( $value, 24 ); ?>>24</option>
			<option value="48" <?php selected( $value, 48 ); ?>>48</option>
			<option value="100" <?php selected( $value, 100 ); ?>>100</option>
		</select>
		<p class="description"><?php esc_html_e( 'Maximum number of recent posts to import per run.', 'post-link-drop' ); ?></p>
		<?php
	}

	/**
	 * Render settings page.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$last_import = get_option( 'post_link_drop_last_import', 0 );
		$last_error  = get_option( 'post_link_drop_last_error', '' );
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<form action="options.php" method="post">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button( __( 'Save Settings', 'post-link-drop' ) );
				?>
			</form>

			<hr>

			<h2><?php esc_html_e( 'Import Actions', 'post-link-drop' ); ?></h2>

			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'Last Import', 'post-link-drop' ); ?></th>
					<td>
						<?php if ( $last_import ) : ?>
							<?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_import ) ); ?>
						<?php else : ?>
							<?php esc_html_e( 'Never', 'post-link-drop' ); ?>
						<?php endif; ?>
					</td>
				</tr>
				<?php if ( $last_error ) : ?>
				<tr>
					<th><?php esc_html_e( 'Last Error', 'post-link-drop' ); ?></th>
					<td><code style="color:#d63638;"><?php echo esc_html( $last_error ); ?></code></td>
				</tr>
				<?php endif; ?>
			</table>

			<p>
				<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" style="display:inline;">
					<?php wp_nonce_field( 'pld_test_connection', 'pld_test_nonce' ); ?>
					<input type="hidden" name="action" value="pld_test_connection">
					<?php submit_button( __( 'Test Connection', 'post-link-drop' ), 'secondary', 'submit', false ); ?>
				</form>
				&nbsp;
				<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" style="display:inline;">
					<?php wp_nonce_field( 'pld_run_import', 'pld_import_nonce' ); ?>
					<input type="hidden" name="action" value="pld_run_import">
					<?php submit_button( __( 'Run Import Now', 'post-link-drop' ), 'primary', 'submit', false ); ?>
				</form>
			</p>

			<hr>

			<h2><?php esc_html_e( 'Shortcode Usage', 'post-link-drop' ); ?></h2>
			<p><?php esc_html_e( 'Use the following shortcode to display your grid:', 'post-link-drop' ); ?></p>
			<pre><code>[link_drop_grid]</code></pre>

			<p><?php esc_html_e( 'Available attributes:', 'post-link-drop' ); ?></p>
			<ul>
				<li><code>count="12"</code> - <?php esc_html_e( 'Number of items to show', 'post-link-drop' ); ?></li>
				<li><code>columns="3"</code> - <?php esc_html_e( 'Number of columns', 'post-link-drop' ); ?></li>
				<li><code>gap="1rem"</code> - <?php esc_html_e( 'Gap between items', 'post-link-drop' ); ?></li>
				<li><code>status="linked"</code> - <?php esc_html_e( 'Filter by status (linked, unlinked, all)', 'post-link-drop' ); ?></li>
				<li><code>class=""</code> - <?php esc_html_e( 'Additional CSS class for wrapper', 'post-link-drop' ); ?></li>
			</ul>
		</div>
		<?php
	}

	/**
	 * Handle run import action.
	 */
	public function handle_run_import() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'post-link-drop' ) );
		}

		check_admin_referer( 'pld_run_import', 'pld_import_nonce' );

		// Rate limit: 1 run per 2 minutes.
		$last_manual_run = get_option( 'post_link_drop_last_manual_import', 0 );
		if ( $last_manual_run && ( time() - $last_manual_run ) < 120 ) {
			$redirect = add_query_arg(
				array(
					'post_type' => CPT_Post_Link_Drop_Item::POST_TYPE,
					'page'      => self::PAGE_SLUG,
					'pld_notice' => 'rate_limited',
				),
				admin_url( 'edit.php' )
			);
			wp_safe_redirect( $redirect );
			exit;
		}

		update_option( 'post_link_drop_last_manual_import', time() );

		// Run import.
		$result = post_link_drop()->importer->run_import();

		$notice = 'import_success';
		if ( is_wp_error( $result ) ) {
			$notice = 'import_error';
			update_option( 'post_link_drop_last_error', $result->get_error_message() );
		}

		$redirect = add_query_arg(
			array(
				'post_type' => CPT_Post_Link_Drop_Item::POST_TYPE,
				'page'      => self::PAGE_SLUG,
				'pld_notice' => $notice,
			),
			admin_url( 'edit.php' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Handle test connection action.
	 */
	public function handle_test_connection() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'post-link-drop' ) );
		}

		check_admin_referer( 'pld_test_connection', 'pld_test_nonce' );

		$result = post_link_drop()->importer->test_connection();

		$notice = 'connection_success';
		if ( is_wp_error( $result ) ) {
			$notice = 'connection_error';
			update_option( 'post_link_drop_last_error', $result->get_error_message() );
		}

		$redirect = add_query_arg(
			array(
				'post_type' => CPT_Post_Link_Drop_Item::POST_TYPE,
				'page'      => self::PAGE_SLUG,
				'pld_notice' => $notice,
			),
			admin_url( 'edit.php' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Display admin notices.
	 */
	public function admin_notices() {
		if ( ! isset( $_GET['pld_notice'] ) ) {
			return;
		}

		$notice = sanitize_key( $_GET['pld_notice'] );
		$class  = 'notice-success';
		$message = '';

		switch ( $notice ) {
			case 'import_success':
				$message = __( 'Import completed successfully.', 'post-link-drop' );
				break;
			case 'import_error':
				$class   = 'notice-error';
				$message = __( 'Import failed. Check the error message below.', 'post-link-drop' );
				break;
			case 'connection_success':
				$message = __( 'Connection successful! Your Instagram account is connected.', 'post-link-drop' );
				break;
			case 'connection_error':
				$class   = 'notice-error';
				$message = __( 'Connection failed. Please check your access token and user ID.', 'post-link-drop' );
				break;
			case 'rate_limited':
				$class   = 'notice-warning';
				$message = __( 'Please wait at least 2 minutes between manual imports.', 'post-link-drop' );
				break;
		}

		if ( $message ) {
			printf( '<div class="notice %s is-dismissible"><p>%s</p></div>', esc_attr( $class ), esc_html( $message ) );
		}
	}
}
