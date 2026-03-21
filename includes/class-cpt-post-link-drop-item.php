<?php
/**
 * Custom Post Type: Post Link Drop Item.
 *
 * @package Post_Link_Drop
 */

namespace Post_Link_Drop;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * CPT Grid Item class.
 */
class CPT_Post_Link_Drop_Item {

	/**
	 * Post type slug.
	 */
	const POST_TYPE = 'pld_grid_item';

	/**
	 * Meta key prefix.
	 */
	const META_PREFIX = '_pld_';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_meta_boxes' ), 10, 2 );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'add_admin_columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'render_admin_columns' ), 10, 2 );
		add_filter( 'manage_edit-' . self::POST_TYPE . '_sortable_columns', array( $this, 'sortable_columns' ) );
		add_action( 'pre_get_posts', array( $this, 'sort_by_ig_timestamp' ) );
		add_action( 'restrict_manage_posts', array( $this, 'add_status_filter' ) );
		add_action( 'pre_get_posts', array( $this, 'filter_by_status' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_pld_quick_link', array( $this, 'ajax_quick_link' ) );
		add_action( 'wp_ajax_pld_set_status', array( $this, 'ajax_set_status' ) );
	}

	/**
	 * Register the custom post type.
	 */
	public function register_post_type() {
		$labels = array(
			'name'               => __( 'Post Link Drop Items', 'post-link-drop' ),
			'singular_name'      => __( 'Post Link Drop Item', 'post-link-drop' ),
			'menu_name'          => __( 'Post Link Drop', 'post-link-drop' ),
			'add_new'            => __( 'Add New', 'post-link-drop' ),
			'add_new_item'       => __( 'Add New Post Link Drop Item', 'post-link-drop' ),
			'edit_item'          => __( 'Edit Post Link Drop Item', 'post-link-drop' ),
			'new_item'           => __( 'New Post Link Drop Item', 'post-link-drop' ),
			'view_item'          => __( 'View Post Link Drop Item', 'post-link-drop' ),
			'search_items'       => __( 'Search Post Link Drop Items', 'post-link-drop' ),
			'not_found'          => __( 'No post link drop items found', 'post-link-drop' ),
			'not_found_in_trash' => __( 'No post link drop items found in Trash', 'post-link-drop' ),
			'all_items'          => __( 'All Post Link Drop Items', 'post-link-drop' ),
		);

		$args = array(
			'labels'              => $labels,
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_position'       => 25,
			'menu_icon'           => 'dashicons-grid-view',
			'supports'            => array( 'title' ),
			'has_archive'         => false,
			'rewrite'             => false,
			'capability_type'     => 'post',
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
		);

		register_post_type( self::POST_TYPE, $args );
	}

	/**
	 * Add meta boxes.
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'pld_grid_item_details',
			__( 'Grid Item Details', 'post-link-drop' ),
			array( $this, 'render_details_meta_box' ),
			self::POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'pld_grid_item_linking',
			__( 'Linking', 'post-link-drop' ),
			array( $this, 'render_linking_meta_box' ),
			self::POST_TYPE,
			'side',
			'high'
		);

		add_meta_box(
			'pld_grid_item_media',
			__( 'Media Preview', 'post-link-drop' ),
			array( $this, 'render_media_meta_box' ),
			self::POST_TYPE,
			'side',
			'default'
		);
	}

	/**
	 * Render the details meta box.
	 *
	 * @param \WP_Post $post Current post object.
	 */
	public function render_details_meta_box( $post ) {
		wp_nonce_field( 'pld_save_meta', 'pld_meta_nonce' );

		$source       = get_post_meta( $post->ID, self::META_PREFIX . 'source', true );
		$media_id     = get_post_meta( $post->ID, self::META_PREFIX . 'ig_media_id', true );
		$permalink    = get_post_meta( $post->ID, self::META_PREFIX . 'ig_permalink', true );
		$timestamp    = get_post_meta( $post->ID, self::META_PREFIX . 'ig_timestamp', true );
		$media_type   = get_post_meta( $post->ID, self::META_PREFIX . 'media_type', true );
		$caption_raw  = get_post_meta( $post->ID, self::META_PREFIX . 'caption_raw', true );
		$alt_text     = get_post_meta( $post->ID, self::META_PREFIX . 'alt_text', true );
		?>
		<table class="form-table">
			<tr>
				<th><label><?php esc_html_e( 'Source', 'post-link-drop' ); ?></label></th>
				<td><?php echo esc_html( ucfirst( $source ?: 'Unknown' ) ); ?></td>
			</tr>
			<tr>
				<th><label><?php esc_html_e( 'Media Type', 'post-link-drop' ); ?></label></th>
				<td><?php echo esc_html( ucfirst( $media_type ?: 'Unknown' ) ); ?></td>
			</tr>
			<?php if ( $media_id ) : ?>
			<tr>
				<th><label><?php esc_html_e( 'Instagram Media ID', 'post-link-drop' ); ?></label></th>
				<td><code><?php echo esc_html( $media_id ); ?></code></td>
			</tr>
			<?php endif; ?>
			<?php if ( $timestamp ) : ?>
			<tr>
				<th><label><?php esc_html_e( 'Posted On', 'post-link-drop' ); ?></label></th>
				<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $timestamp ) ) ); ?></td>
			</tr>
			<?php endif; ?>
			<?php if ( $permalink ) : ?>
			<tr>
				<th><label><?php esc_html_e( 'Instagram Link', 'post-link-drop' ); ?></label></th>
				<td><a href="<?php echo esc_url( $permalink ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View on Instagram', 'post-link-drop' ); ?></a></td>
			</tr>
			<?php endif; ?>
			<tr>
				<th><label for="pld_caption_raw"><?php esc_html_e( 'Caption', 'post-link-drop' ); ?></label></th>
				<td><textarea id="pld_caption_raw" name="pld_caption_raw" rows="4" class="large-text" readonly><?php echo esc_textarea( $caption_raw ); ?></textarea></td>
			</tr>
			<tr>
				<th><label for="pld_alt_text"><?php esc_html_e( 'Alt Text', 'post-link-drop' ); ?></label></th>
				<td>
					<textarea id="pld_alt_text" name="pld_alt_text" rows="2" class="large-text"><?php echo esc_textarea( $alt_text ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Accessible alt text for the image. Auto-generated from caption, but can be manually edited.', 'post-link-drop' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render the linking meta box.
	 *
	 * @param \WP_Post $post Current post object.
	 */
	public function render_linking_meta_box( $post ) {
		$status          = get_post_meta( $post->ID, self::META_PREFIX . 'status', true ) ?: 'unlinked';
		$destination_url = get_post_meta( $post->ID, self::META_PREFIX . 'destination_url', true );
		?>
		<p>
			<label for="pld_status"><strong><?php esc_html_e( 'Status', 'post-link-drop' ); ?></strong></label><br>
			<select id="pld_status" name="pld_status" style="width: 100%;">
				<option value="unlinked" <?php selected( $status, 'unlinked' ); ?>><?php esc_html_e( 'Unlinked', 'post-link-drop' ); ?></option>
				<option value="linked" <?php selected( $status, 'linked' ); ?>><?php esc_html_e( 'Linked', 'post-link-drop' ); ?></option>
				<option value="hidden" <?php selected( $status, 'hidden' ); ?>><?php esc_html_e( 'Hidden', 'post-link-drop' ); ?></option>
			</select>
		</p>
		<p>
			<label for="pld_destination_url"><strong><?php esc_html_e( 'Destination URL', 'post-link-drop' ); ?></strong></label><br>
			<input type="url" id="pld_destination_url" name="pld_destination_url" value="<?php echo esc_url( $destination_url ); ?>" class="widefat" placeholder="https://example.com/page">
		</p>
		<?php
	}

	/**
	 * Render the media preview meta box.
	 *
	 * @param \WP_Post $post Current post object.
	 */
	public function render_media_meta_box( $post ) {
		$attachment_id = get_post_meta( $post->ID, self::META_PREFIX . 'attachment_id', true );

		if ( $attachment_id ) {
			$image = wp_get_attachment_image( $attachment_id, 'medium', false, array( 'style' => 'max-width:100%;height:auto;' ) );
			if ( $image ) {
				echo $image;
			} else {
				echo '<p>' . esc_html__( 'Attachment not found.', 'post-link-drop' ) . '</p>';
			}
		} else {
			echo '<p>' . esc_html__( 'No media attached.', 'post-link-drop' ) . '</p>';
		}
	}

	/**
	 * Save meta boxes.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public function save_meta_boxes( $post_id, $post ) {
		// Verify nonce.
		if ( ! isset( $_POST['pld_meta_nonce'] ) || ! wp_verify_nonce( $_POST['pld_meta_nonce'], 'pld_save_meta' ) ) {
			return;
		}

		// Check autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check permissions.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Save alt text.
		if ( isset( $_POST['pld_alt_text'] ) ) {
			update_post_meta( $post_id, self::META_PREFIX . 'alt_text', sanitize_textarea_field( $_POST['pld_alt_text'] ) );

			// Also update the attachment alt text.
			$attachment_id = get_post_meta( $post_id, self::META_PREFIX . 'attachment_id', true );
			if ( $attachment_id ) {
				update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_textarea_field( $_POST['pld_alt_text'] ) );
			}
		}

		// Save status.
		if ( isset( $_POST['pld_status'] ) ) {
			$status = sanitize_key( $_POST['pld_status'] );
			if ( in_array( $status, array( 'unlinked', 'linked', 'hidden' ), true ) ) {
				update_post_meta( $post_id, self::META_PREFIX . 'status', $status );
			}
		}

		// Save destination URL.
		if ( isset( $_POST['pld_destination_url'] ) ) {
			$url = esc_url_raw( $_POST['pld_destination_url'] );
			update_post_meta( $post_id, self::META_PREFIX . 'destination_url', $url );
		}

		// Clear grid cache.
		Plugin::clear_grid_cache();
	}

	/**
	 * Add admin columns.
	 *
	 * @param array $columns Existing columns.
	 * @return array Modified columns.
	 */
	public function add_admin_columns( $columns ) {
		$new_columns = array();
		$new_columns['cb'] = $columns['cb'];
		$new_columns['pld_thumb'] = __( 'Thumb', 'post-link-drop' );
		$new_columns['title'] = $columns['title'];
		$new_columns['pld_ig_timestamp'] = __( 'IG Date', 'post-link-drop' );
		$new_columns['pld_destination'] = __( 'Destination', 'post-link-drop' );
		$new_columns['pld_status'] = __( 'Status', 'post-link-drop' );
		$new_columns['pld_actions'] = __( 'Actions', 'post-link-drop' );
		return $new_columns;
	}

	/**
	 * Render admin columns.
	 *
	 * @param string $column  Column name.
	 * @param int    $post_id Post ID.
	 */
	public function render_admin_columns( $column, $post_id ) {
		switch ( $column ) {
			case 'pld_thumb':
				$attachment_id = get_post_meta( $post_id, self::META_PREFIX . 'attachment_id', true );
				if ( $attachment_id ) {
					echo wp_get_attachment_image( $attachment_id, array( 60, 60 ), false, array( 'style' => 'width:60px;height:60px;object-fit:cover;' ) );
				} else {
					echo '<span class="dashicons dashicons-format-image" style="font-size:40px;width:60px;height:60px;color:#ccc;"></span>';
				}
				break;

			case 'pld_ig_timestamp':
				$timestamp = get_post_meta( $post_id, self::META_PREFIX . 'ig_timestamp', true );
				if ( $timestamp ) {
					echo esc_html( date_i18n( 'M j, Y', strtotime( $timestamp ) ) );
				} else {
					echo '&mdash;';
				}
				break;

			case 'pld_destination':
				$url = get_post_meta( $post_id, self::META_PREFIX . 'destination_url', true );
				if ( $url ) {
					$display = wp_parse_url( $url, PHP_URL_PATH ) ?: $url;
					printf( '<a href="%s" target="_blank" title="%s">%s</a>', esc_url( $url ), esc_attr( $url ), esc_html( wp_trim_words( $display, 3, '...' ) ) );
				} else {
					echo '<span class="ld-quick-link-wrap" data-post-id="' . esc_attr( $post_id ) . '">';
					echo '<input type="url" class="ld-quick-link-input" placeholder="' . esc_attr__( 'Paste URL...', 'post-link-drop' ) . '" style="width:140px;">';
					echo '<button type="button" class="button button-small ld-quick-link-btn">' . esc_html__( 'Link', 'post-link-drop' ) . '</button>';
					echo '</span>';
				}
				break;

			case 'pld_status':
				$status = get_post_meta( $post_id, self::META_PREFIX . 'status', true ) ?: 'unlinked';
				$labels = array(
					'linked'   => '<span class="pld_tag pld_tag-linked">' . __( 'Linked', 'post-link-drop' ) . '</span>',
					'unlinked' => '<span class="pld_tag pld_tag-unlinked">' . __( 'Unlinked', 'post-link-drop' ) . '</span>',
					'hidden'   => '<span class="pld_tag pld_tag-hidden">' . __( 'Hidden', 'post-link-drop' ) . '</span>',
				);
				echo $labels[ $status ] ?? esc_html( ucfirst( $status ) );
				break;

			case 'pld_actions':
				$status = get_post_meta( $post_id, self::META_PREFIX . 'status', true ) ?: 'unlinked';
				echo '<span class="ld-action-buttons" data-post-id="' . esc_attr( $post_id ) . '">';
				if ( 'hidden' !== $status ) {
					echo '<button type="button" class="button ld-status-btn" data-status="hidden" title="' . esc_attr__( 'Hide', 'post-link-drop' ) . '">' . esc_html__( 'Hide', 'post-link-drop' ) . '</button> ';
				}
				if ( 'hidden' === $status ) {
					echo '<button type="button" class="button ld-status-btn" data-status="unlinked" title="' . esc_attr__( 'Unhide', 'post-link-drop' ) . '">' . esc_html__( 'Unhide', 'post-link-drop' ) . '</button> ';
				}
				echo '</span>';
				break;
		}
	}

	/**
	 * Define sortable columns.
	 *
	 * @param array $columns Sortable columns.
	 * @return array Modified columns.
	 */
	public function sortable_columns( $columns ) {
		$columns['pld_ig_timestamp'] = 'pld_ig_timestamp';
		$columns['pld_status']       = 'pld_status';
		return $columns;
	}

	/**
	 * Sort by Instagram timestamp.
	 *
	 * @param \WP_Query $query Query object.
	 */
	public function sort_by_ig_timestamp( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( self::POST_TYPE !== $query->get( 'post_type' ) ) {
			return;
		}

		$orderby = $query->get( 'orderby' );

		if ( 'pld_ig_timestamp' === $orderby ) {
			$query->set( 'meta_key', self::META_PREFIX . 'ig_timestamp' );
			$query->set( 'orderby', 'meta_value' );
		}

		if ( 'pld_status' === $orderby ) {
			$query->set( 'meta_key', self::META_PREFIX . 'status' );
			$query->set( 'orderby', 'meta_value' );
		}

		// Default sort by IG timestamp desc.
		if ( empty( $orderby ) && empty( $query->get( 'order' ) ) ) {
			$query->set( 'meta_key', self::META_PREFIX . 'ig_timestamp' );
			$query->set( 'orderby', 'meta_value' );
			$query->set( 'order', 'DESC' );
		}
	}

	/**
	 * Add status filter dropdown.
	 *
	 * @param string $post_type Current post type.
	 */
	public function add_status_filter( $post_type ) {
		if ( self::POST_TYPE !== $post_type ) {
			return;
		}

		$current = isset( $_GET['pld_status_filter'] ) ? sanitize_key( $_GET['pld_status_filter'] ) : '';
		?>
		<select name="pld_status_filter">
			<option value=""><?php esc_html_e( 'All Statuses', 'post-link-drop' ); ?></option>
			<option value="linked" <?php selected( $current, 'linked' ); ?>><?php esc_html_e( 'Linked', 'post-link-drop' ); ?></option>
			<option value="unlinked" <?php selected( $current, 'unlinked' ); ?>><?php esc_html_e( 'Unlinked', 'post-link-drop' ); ?></option>
			<option value="hidden" <?php selected( $current, 'hidden' ); ?>><?php esc_html_e( 'Hidden', 'post-link-drop' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Filter by status.
	 *
	 * @param \WP_Query $query Query object.
	 */
	public function filter_by_status( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( self::POST_TYPE !== $query->get( 'post_type' ) ) {
			return;
		}

		if ( ! empty( $_GET['pld_status_filter'] ) ) {
			$status = sanitize_key( $_GET['pld_status_filter'] );
			if ( in_array( $status, array( 'linked', 'unlinked', 'hidden' ), true ) ) {
				$query->set(
					'meta_query',
					array(
						array(
							'key'   => self::META_PREFIX . 'status',
							'value' => $status,
						),
					)
				);
			}
		}
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page.
	 */
	public function enqueue_admin_assets( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || self::POST_TYPE !== $screen->post_type ) {
			return;
		}

		wp_enqueue_script(
			'ld-admin',
			POST_LINK_DROP_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			POST_LINK_DROP_VERSION,
			true
		);

		wp_localize_script(
			'ld-admin',
			'ldAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'pld_admin_nonce' ),
			)
		);

		wp_enqueue_style(
			'ld-admin',
			POST_LINK_DROP_URL . 'assets/css/admin.css',
			array(),
			POST_LINK_DROP_VERSION
		);
	}

	/**
	 * AJAX handler for quick link.
	 */
	public function ajax_quick_link() {
		check_ajax_referer( 'pld_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'post-link-drop' ) ) );
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$url     = isset( $_POST['url'] ) ? esc_url_raw( $_POST['url'] ) : '';

		if ( ! $post_id || ! $url ) {
			wp_send_json_error( array( 'message' => __( 'Invalid data.', 'post-link-drop' ) ) );
		}

		// Validate URL scheme.
		$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'URL must be http or https.', 'post-link-drop' ) ) );
		}

		update_post_meta( $post_id, self::META_PREFIX . 'destination_url', $url );
		update_post_meta( $post_id, self::META_PREFIX . 'status', 'linked' );

		Plugin::clear_grid_cache();

		wp_send_json_success(
			array(
				'message' => __( 'Linked!', 'post-link-drop' ),
				'url'     => $url,
			)
		);
	}

	/**
	 * AJAX handler for status change.
	 */
	public function ajax_set_status() {
		check_ajax_referer( 'pld_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'post-link-drop' ) ) );
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$status  = isset( $_POST['status'] ) ? sanitize_key( $_POST['status'] ) : '';

		if ( ! $post_id || ! in_array( $status, array( 'linked', 'unlinked', 'hidden' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid data.', 'post-link-drop' ) ) );
		}

		update_post_meta( $post_id, self::META_PREFIX . 'status', $status );

		Plugin::clear_grid_cache();

		wp_send_json_success(
			array(
				'message' => __( 'Status updated.', 'post-link-drop' ),
				'status'  => $status,
			)
		);
	}

	/**
	 * Check if a media ID has already been imported.
	 *
	 * @param string $media_id Instagram media ID.
	 * @return int|false Post ID if exists, false otherwise.
	 */
	public static function get_by_ig_media_id( $media_id ) {
		// First check the cache option.
		$cache = get_option( 'post_link_drop_ig_media_ids', array() );
		if ( isset( $cache[ $media_id ] ) ) {
			// Verify the post still exists.
			if ( get_post_status( $cache[ $media_id ] ) ) {
				return $cache[ $media_id ];
			}
		}

		// Fallback to meta query.
		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'posts_per_page' => 1,
				'post_status'    => 'any',
				'meta_key'       => self::META_PREFIX . 'ig_media_id',
				'meta_value'     => $media_id,
				'fields'         => 'ids',
			)
		);

		if ( ! empty( $posts ) ) {
			// Update cache.
			$cache[ $media_id ] = $posts[0];
			self::update_media_id_cache( $cache );
			return $posts[0];
		}

		return false;
	}

	/**
	 * Update the media ID cache option.
	 *
	 * @param array $cache Cache array.
	 */
	public static function update_media_id_cache( $cache ) {
		// Cap at 500 entries.
		if ( count( $cache ) > 500 ) {
			$cache = array_slice( $cache, -500, 500, true );
		}
		update_option( 'post_link_drop_ig_media_ids', $cache, false );
	}

	/**
	 * Create a new grid item from Instagram data.
	 *
	 * @param array $data Instagram media data.
	 * @return int|\WP_Error Post ID or error.
	 */
	public static function create_from_instagram( $data ) {
		$title = ! empty( $data['caption'] ) ? wp_trim_words( $data['caption'], 10, '...' ) : 'Instagram Post';

		$post_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_title'  => sanitize_text_field( $title ),
				'post_status' => 'publish',
			)
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Set meta.
		$meta = array(
			'source'       => 'instagram',
			'ig_media_id'  => $data['id'] ?? '',
			'ig_permalink' => $data['permalink'] ?? '',
			'ig_timestamp' => $data['timestamp'] ?? '',
			'ig_username'  => $data['username'] ?? '',
			'media_type'   => strtolower( $data['media_type'] ?? 'image' ),
			'original_url' => $data['media_url'] ?? '',
			'caption_raw'  => $data['caption'] ?? '',
			'status'       => 'unlinked',
			'last_synced'  => time(),
		);

		foreach ( $meta as $key => $value ) {
			update_post_meta( $post_id, self::META_PREFIX . $key, $value );
		}

		// Update cache.
		if ( ! empty( $data['id'] ) ) {
			$cache = get_option( 'post_link_drop_ig_media_ids', array() );
			$cache[ $data['id'] ] = $post_id;
			self::update_media_id_cache( $cache );
		}

		return $post_id;
	}
}
