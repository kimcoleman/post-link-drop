<?php
/**
 * Media sideloading functionality.
 *
 * @package Post_Link_Drop
 */

namespace Post_Link_Drop;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Media Sideload class.
 */
class Media_Sideload {

	/**
	 * Download and sideload a remote image to WordPress Media Library.
	 *
	 * @param string $url       Remote URL of the image.
	 * @param int    $post_id   Post ID to attach the media to.
	 * @param string $alt_text  Alt text for the image.
	 * @param string $filename  Optional custom filename.
	 * @return int|\WP_Error Attachment ID or error.
	 */
	public function sideload( $url, $post_id = 0, $alt_text = '', $filename = '' ) {
		// Validate URL.
		if ( empty( $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return new \WP_Error( 'invalid_url', __( 'Invalid media URL.', 'post-link-drop' ) );
		}

		// Check URL scheme.
		$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return new \WP_Error( 'invalid_scheme', __( 'URL must be http or https.', 'post-link-drop' ) );
		}

		// Include required files.
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// Download the file.
		$tmp_file = download_url( $url, 30 );

		if ( is_wp_error( $tmp_file ) ) {
			return $tmp_file;
		}

		// Determine filename.
		if ( empty( $filename ) ) {
			$filename = $this->generate_filename( $url );
		}

		// Prepare file array.
		$file_array = array(
			'name'     => $filename,
			'tmp_name' => $tmp_file,
		);

		// Sideload the file.
		$attachment_id = media_handle_sideload( $file_array, $post_id );

		// Cleanup temp file if still exists.
		if ( file_exists( $tmp_file ) ) {
			@unlink( $tmp_file );
		}

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		// Set alt text.
		if ( ! empty( $alt_text ) ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $alt_text ) );
		}

		return $attachment_id;
	}

	/**
	 * Generate a filename from URL.
	 *
	 * @param string $url Remote URL.
	 * @return string Generated filename.
	 */
	private function generate_filename( $url ) {
		// Parse URL to get path.
		$path = wp_parse_url( $url, PHP_URL_PATH );

		if ( $path ) {
			$filename = basename( $path );

			// Remove query string artifacts.
			$filename = preg_replace( '/\?.*$/', '', $filename );

			// Ensure we have an extension.
			$extension = pathinfo( $filename, PATHINFO_EXTENSION );
			if ( in_array( strtolower( $extension ), array( 'jpg', 'jpeg', 'png', 'gif', 'webp' ), true ) ) {
				return sanitize_file_name( $filename );
			}
		}

		// Generate a unique filename.
		return 'instagram-' . uniqid() . '.jpg';
	}

	/**
	 * Get the media URL for an Instagram item.
	 *
	 * Handles different media types (image, video, carousel).
	 *
	 * @param array $media Instagram media data.
	 * @return string|null Media URL or null.
	 */
	public function get_media_url( $media ) {
		$media_type = $media['media_type'] ?? 'IMAGE';

		switch ( strtoupper( $media_type ) ) {
			case 'IMAGE':
				return $media['media_url'] ?? null;

			case 'VIDEO':
				// Use thumbnail for videos.
				return $media['thumbnail_url'] ?? null;

			case 'CAROUSEL_ALBUM':
				// Use first child's media.
				if ( ! empty( $media['children']['data'] ) ) {
					$first_child = $media['children']['data'][0];
					$child_type  = $first_child['media_type'] ?? 'IMAGE';

					if ( 'VIDEO' === strtoupper( $child_type ) ) {
						return $first_child['thumbnail_url'] ?? null;
					}
					return $first_child['media_url'] ?? null;
				}
				// Fallback to main media URL.
				return $media['media_url'] ?? null;

			default:
				return $media['media_url'] ?? null;
		}
	}

	/**
	 * Check if an attachment exists for a grid item.
	 *
	 * @param int $post_id Grid item post ID.
	 * @return bool True if attachment exists.
	 */
	public function attachment_exists( $post_id ) {
		$attachment_id = get_post_meta( $post_id, CPT_Post_Link_Drop_Item::META_PREFIX . 'attachment_id', true );

		if ( ! $attachment_id ) {
			return false;
		}

		// Verify attachment still exists.
		return (bool) get_post( $attachment_id );
	}

	/**
	 * Sideload media for a grid item.
	 *
	 * @param int    $post_id  Grid item post ID.
	 * @param array  $media    Instagram media data.
	 * @param string $alt_text Alt text for the image.
	 * @return int|\WP_Error Attachment ID or error.
	 */
	public function sideload_for_grid_item( $post_id, $media, $alt_text = '' ) {
		$url = $this->get_media_url( $media );

		if ( ! $url ) {
			return new \WP_Error( 'no_media_url', __( 'Could not determine media URL.', 'post-link-drop' ) );
		}

		// Generate filename from Instagram media ID.
		$media_id = $media['id'] ?? uniqid();
		$extension = 'jpg';

		// Try to get extension from URL.
		$path = wp_parse_url( $url, PHP_URL_PATH );
		if ( $path ) {
			$ext = pathinfo( $path, PATHINFO_EXTENSION );
			if ( in_array( strtolower( $ext ), array( 'jpg', 'jpeg', 'png', 'gif', 'webp' ), true ) ) {
				$extension = strtolower( $ext );
			}
		}

		$filename = 'instagram-' . sanitize_file_name( $media_id ) . '.' . $extension;

		$attachment_id = $this->sideload( $url, $post_id, $alt_text, $filename );

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		// Save attachment ID to grid item.
		update_post_meta( $post_id, CPT_Post_Link_Drop_Item::META_PREFIX . 'attachment_id', $attachment_id );

		return $attachment_id;
	}

	/**
	 * Delete attachment associated with a grid item.
	 *
	 * @param int $post_id Grid item post ID.
	 * @return bool True on success.
	 */
	public function delete_attachment( $post_id ) {
		$attachment_id = get_post_meta( $post_id, CPT_Post_Link_Drop_Item::META_PREFIX . 'attachment_id', true );

		if ( $attachment_id ) {
			wp_delete_attachment( $attachment_id, true );
			delete_post_meta( $post_id, CPT_Post_Link_Drop_Item::META_PREFIX . 'attachment_id' );
			return true;
		}

		return false;
	}
}
