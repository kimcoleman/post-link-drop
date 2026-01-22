<?php
/**
 * Instagram Graph API importer.
 *
 * @package Post_Link_Drop
 */

namespace Post_Link_Drop;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Import Instagram class.
 */
class Import_Instagram {

	/**
	 * Instagram Graph API base URL.
	 */
	const API_BASE = 'https://graph.instagram.com';

	/**
	 * API fields to request.
	 */
	const MEDIA_FIELDS = 'id,caption,media_type,media_url,thumbnail_url,permalink,timestamp,children{media_type,media_url,thumbnail_url}';

	/**
	 * Test the Instagram connection.
	 *
	 * @return array|\WP_Error User data or error.
	 */
	public function test_connection() {
		$access_token = get_option( 'post_link_drop_access_token', '' );
		$user_id      = get_option( 'post_link_drop_ig_user_id', '' );

		if ( empty( $access_token ) || empty( $user_id ) ) {
			return new \WP_Error( 'missing_credentials', __( 'Access token and Instagram User ID are required.', 'post-link-drop' ) );
		}

		$url = add_query_arg(
			array(
				'fields'       => 'id,username',
				'access_token' => $access_token,
			),
			self::API_BASE . '/' . $user_id
		);

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( 200 !== $status_code ) {
			$error_message = $data['error']['message'] ?? __( 'Unknown API error.', 'post-link-drop' );
			return new \WP_Error( 'api_error', $error_message );
		}

		return $data;
	}

	/**
	 * Fetch recent media from Instagram.
	 *
	 * @param int    $limit Maximum number of items to fetch.
	 * @param string $after Pagination cursor.
	 * @return array|\WP_Error Media data or error.
	 */
	public function fetch_media( $limit = 24, $after = '' ) {
		$access_token = get_option( 'post_link_drop_access_token', '' );
		$user_id      = get_option( 'post_link_drop_ig_user_id', '' );

		if ( empty( $access_token ) || empty( $user_id ) ) {
			return new \WP_Error( 'missing_credentials', __( 'Access token and Instagram User ID are required.', 'post-link-drop' ) );
		}

		$args = array(
			'fields'       => self::MEDIA_FIELDS,
			'access_token' => $access_token,
			'limit'        => min( $limit, 100 ), // API max is 100.
		);

		if ( ! empty( $after ) ) {
			$args['after'] = $after;
		}

		$url = add_query_arg( $args, self::API_BASE . '/' . $user_id . '/media' );

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 60,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( 200 !== $status_code ) {
			$error_message = $data['error']['message'] ?? __( 'Unknown API error.', 'post-link-drop' );
			return new \WP_Error( 'api_error', $error_message );
		}

		return $data;
	}

	/**
	 * Run the import process.
	 *
	 * @return array|\WP_Error Import results or error.
	 */
	public function run_import() {
		$limit = absint( get_option( 'post_link_drop_import_limit', 24 ) );

		// Fetch media from Instagram.
		$media_data = $this->fetch_media( $limit );

		if ( is_wp_error( $media_data ) ) {
			update_option( 'post_link_drop_last_error', $media_data->get_error_message() );
			return $media_data;
		}

		if ( empty( $media_data['data'] ) ) {
			update_option( 'post_link_drop_last_import', time() );
			return array(
				'imported' => 0,
				'updated'  => 0,
				'skipped'  => 0,
			);
		}

		$results = array(
			'imported' => 0,
			'updated'  => 0,
			'skipped'  => 0,
			'errors'   => array(),
		);

		foreach ( $media_data['data'] as $media ) {
			$result = $this->process_media_item( $media );

			if ( is_wp_error( $result ) ) {
				$results['errors'][] = $result->get_error_message();
				continue;
			}

			switch ( $result ) {
				case 'imported':
					$results['imported']++;
					break;
				case 'updated':
					$results['updated']++;
					break;
				case 'skipped':
					$results['skipped']++;
					break;
			}
		}

		// Update last import time.
		update_option( 'post_link_drop_last_import', time() );

		// Clear error if successful.
		if ( empty( $results['errors'] ) ) {
			delete_option( 'post_link_drop_last_error' );
		}

		// Clear grid cache.
		Plugin::clear_grid_cache();

		return $results;
	}

	/**
	 * Process a single media item.
	 *
	 * @param array $media Instagram media data.
	 * @return string|\WP_Error Result status or error.
	 */
	private function process_media_item( $media ) {
		$media_id = $media['id'] ?? '';

		if ( empty( $media_id ) ) {
			return new \WP_Error( 'missing_id', __( 'Media item missing ID.', 'post-link-drop' ) );
		}

		// Check if already imported.
		$existing_post_id = CPT_Post_Link_Drop_Item::get_by_ig_media_id( $media_id );

		if ( $existing_post_id ) {
			// Update existing item.
			$updated = $this->update_existing_item( $existing_post_id, $media );
			return $updated ? 'updated' : 'skipped';
		}

		// Create new grid item.
		$post_id = CPT_Post_Link_Drop_Item::create_from_instagram( $media );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Generate alt text.
		$alt_text = post_link_drop()->alt_text->generate(
			$media['caption'] ?? '',
			$media['timestamp'] ?? ''
		);
		update_post_meta( $post_id, CPT_Post_Link_Drop_Item::META_PREFIX . 'alt_text', $alt_text );

		// Sideload media.
		$attachment_id = post_link_drop()->media->sideload_for_grid_item( $post_id, $media, $alt_text );

		if ( is_wp_error( $attachment_id ) ) {
			// Log error but don't fail the import.
			update_post_meta( $post_id, CPT_Post_Link_Drop_Item::META_PREFIX . 'sideload_error', $attachment_id->get_error_message() );
		}

		// Store carousel count if applicable.
		if ( 'CAROUSEL_ALBUM' === ( $media['media_type'] ?? '' ) && ! empty( $media['children']['data'] ) ) {
			update_post_meta( $post_id, CPT_Post_Link_Drop_Item::META_PREFIX . 'carousel_count', count( $media['children']['data'] ) );
		}

		return 'imported';
	}

	/**
	 * Update an existing grid item.
	 *
	 * @param int   $post_id Existing post ID.
	 * @param array $media   Instagram media data.
	 * @return bool True if updated.
	 */
	private function update_existing_item( $post_id, $media ) {
		$updated = false;

		// Update caption if changed.
		$current_caption = get_post_meta( $post_id, CPT_Post_Link_Drop_Item::META_PREFIX . 'caption_raw', true );
		$new_caption     = $media['caption'] ?? '';

		if ( $current_caption !== $new_caption ) {
			update_post_meta( $post_id, CPT_Post_Link_Drop_Item::META_PREFIX . 'caption_raw', $new_caption );
			$updated = true;
		}

		// Update permalink if changed.
		$current_permalink = get_post_meta( $post_id, CPT_Post_Link_Drop_Item::META_PREFIX . 'ig_permalink', true );
		$new_permalink     = $media['permalink'] ?? '';

		if ( $current_permalink !== $new_permalink && ! empty( $new_permalink ) ) {
			update_post_meta( $post_id, CPT_Post_Link_Drop_Item::META_PREFIX . 'ig_permalink', $new_permalink );
			$updated = true;
		}

		// Update timestamp if changed.
		$current_timestamp = get_post_meta( $post_id, CPT_Post_Link_Drop_Item::META_PREFIX . 'ig_timestamp', true );
		$new_timestamp     = $media['timestamp'] ?? '';

		if ( $current_timestamp !== $new_timestamp && ! empty( $new_timestamp ) ) {
			update_post_meta( $post_id, CPT_Post_Link_Drop_Item::META_PREFIX . 'ig_timestamp', $new_timestamp );
			$updated = true;
		}

		// Check if attachment is missing and re-sideload.
		if ( ! post_link_drop()->media->attachment_exists( $post_id ) ) {
			$alt_text      = get_post_meta( $post_id, CPT_Post_Link_Drop_Item::META_PREFIX . 'alt_text', true );
			$attachment_id = post_link_drop()->media->sideload_for_grid_item( $post_id, $media, $alt_text );

			if ( ! is_wp_error( $attachment_id ) ) {
				$updated = true;
			}
		}

		// Update last synced timestamp.
		if ( $updated ) {
			update_post_meta( $post_id, CPT_Post_Link_Drop_Item::META_PREFIX . 'last_synced', time() );
		}

		return $updated;
	}

	/**
	 * Get the next page cursor from API response.
	 *
	 * @param array $response API response.
	 * @return string|null Cursor or null.
	 */
	public function get_next_cursor( $response ) {
		return $response['paging']['cursors']['after'] ?? null;
	}

	/**
	 * Check if there are more pages.
	 *
	 * @param array $response API response.
	 * @return bool True if more pages exist.
	 */
	public function has_next_page( $response ) {
		return ! empty( $response['paging']['next'] );
	}
}
