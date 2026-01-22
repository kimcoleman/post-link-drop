<?php
/**
 * Grid rendering and shortcode.
 *
 * @package Post_Link_Drop
 */

namespace Post_Link_Drop;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Render Grid class.
 */
class Render_Grid {

	/**
	 * Shortcode tag.
	 */
	const SHORTCODE = 'post_link_drop_grid';

	/**
	 * Transient prefix.
	 */
	const TRANSIENT_PREFIX = 'pld_grid_';

	/**
	 * Cache expiration in seconds.
	 */
	const CACHE_EXPIRATION = HOUR_IN_SECONDS;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_shortcode( self::SHORTCODE, array( $this, 'render_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	/**
	 * Register frontend assets.
	 */
	public function register_assets() {
		wp_register_style(
			'link-drop-grid',
			POST_LINK_DROP_URL . 'assets/css/grid.css',
			array(),
			POST_LINK_DROP_VERSION
		);
	}

	/**
	 * Render the shortcode.
	 *
	 * @param array  $atts    Shortcode attributes.
	 * @param string $content Shortcode content (unused).
	 * @return string HTML output.
	 */
	public function render_shortcode( $atts, $content = null ) {
		$atts = shortcode_atts(
			array(
				'count'   => 12,
				'status'  => 'linked',
				'columns' => 4,
				'gap'     => '1rem',
				'new_tab' => 'true',
				'orderby' => 'ig_timestamp',
				'order'   => 'DESC',
				'class'   => '',
			),
			$atts,
			self::SHORTCODE
		);

		// Sanitize attributes.
		$count   = absint( $atts['count'] );
		$status  = sanitize_key( $atts['status'] );
		$columns = absint( $atts['columns'] );
		$gap     = sanitize_text_field( $atts['gap'] );
		$new_tab = filter_var( $atts['new_tab'], FILTER_VALIDATE_BOOLEAN );
		$orderby = sanitize_key( $atts['orderby'] );
		$order   = strtoupper( $atts['order'] ) === 'ASC' ? 'ASC' : 'DESC';
		$class   = sanitize_html_class( $atts['class'] );

		// Generate cache key.
		$cache_key = self::TRANSIENT_PREFIX . md5( wp_json_encode( $atts ) );

		// Check cache.
		$cached = get_transient( $cache_key );
		if ( false !== $cached ) {
			wp_enqueue_style( 'link-drop-grid' );
			return $cached;
		}

		// Build query args.
		$query_args = array(
			'post_type'      => CPT_Post_Link_Drop_Item::POST_TYPE,
			'posts_per_page' => $count,
			'post_status'    => 'publish',
			'orderby'        => 'meta_value',
			'order'          => $order,
			'meta_key'       => CPT_Post_Link_Drop_Item::META_PREFIX . 'ig_timestamp',
		);

		// Status filter.
		if ( 'all' !== $status ) {
			$query_args['meta_query'] = array(
				array(
					'key'   => CPT_Post_Link_Drop_Item::META_PREFIX . 'status',
					'value' => $status,
				),
			);
		} else {
			// Exclude hidden items even when showing "all".
			$query_args['meta_query'] = array(
				array(
					'key'     => CPT_Post_Link_Drop_Item::META_PREFIX . 'status',
					'value'   => 'hidden',
					'compare' => '!=',
				),
			);
		}

		$query = new \WP_Query( $query_args );

		if ( ! $query->have_posts() ) {
			return '';
		}

		// Build HTML.
		$wrapper_class = 'pld-grid';
		if ( $class ) {
			$wrapper_class .= ' ' . $class;
		}

		$inline_style = sprintf(
			'--pld-columns: %d; --pld-gap: %s;',
			$columns,
			esc_attr( $gap )
		);

		ob_start();
		?>
		<div class="<?php echo esc_attr( $wrapper_class ); ?>" style="<?php echo esc_attr( $inline_style ); ?>">
			<?php
			while ( $query->have_posts() ) {
				$query->the_post();
				$this->render_grid_item( get_the_ID(), $new_tab );
			}
			wp_reset_postdata();
			?>
		</div>
		<?php
		$html = ob_get_clean();

		// Cache the output.
		set_transient( $cache_key, $html, self::CACHE_EXPIRATION );

		// Enqueue styles.
		wp_enqueue_style( 'link-drop-grid' );

		return $html;
	}

	/**
	 * Render a single grid item.
	 *
	 * @param int  $post_id Post ID.
	 * @param bool $new_tab Whether to open links in new tab.
	 */
	private function render_grid_item( $post_id, $new_tab = true ) {
		$attachment_id   = get_post_meta( $post_id, CPT_Post_Link_Drop_Item::META_PREFIX . 'attachment_id', true );
		$destination_url = get_post_meta( $post_id, CPT_Post_Link_Drop_Item::META_PREFIX . 'destination_url', true );
		$alt_text        = get_post_meta( $post_id, CPT_Post_Link_Drop_Item::META_PREFIX . 'alt_text', true );

		if ( ! $attachment_id || ! $destination_url ) {
			return;
		}

		// Get image.
		$image = wp_get_attachment_image_src( $attachment_id, 'pld_square' );
		if ( ! $image ) {
			$image = wp_get_attachment_image_src( $attachment_id, 'medium' );
		}

		if ( ! $image ) {
			return;
		}

		$img_url    = $image[0];
		$img_width  = $image[1];
		$img_height = $image[2];

		// Build accessible link label.
		$domain = wp_parse_url( $destination_url, PHP_URL_HOST );
		if ( $domain ) {
			$domain = preg_replace( '/^www\./', '', $domain );
		} else {
			$domain = __( 'linked content', 'post-link-drop' );
		}

		$aria_label = $alt_text
			? sprintf( __( '%1$s - Visit %2$s', 'post-link-drop' ), $alt_text, $domain )
			: sprintf( __( 'Visit %s', 'post-link-drop' ), $domain );

		if ( $new_tab ) {
			/* translators: %s: accessible link label */
			$aria_label = sprintf( __( '%s (opens in a new tab)', 'post-link-drop' ), $aria_label );
		}

		// Build attributes.
		$link_attrs = array(
			'href'       => esc_url( $destination_url ),
			'class'      => 'pld-grid__item',
			'aria-label' => $aria_label,
		);

		if ( $new_tab ) {
			$link_attrs['target'] = '_blank';
			$link_attrs['rel']    = 'noopener';
		}

		$link_attr_string = '';
		foreach ( $link_attrs as $attr => $value ) {
			$link_attr_string .= sprintf( ' %s="%s"', esc_attr( $attr ), esc_attr( $value ) );
		}

		?>
		<a<?php echo $link_attr_string; ?>>
			<img
				src="<?php echo esc_url( $img_url ); ?>"
				alt="<?php echo esc_attr( $alt_text ); ?>"
				width="<?php echo esc_attr( $img_width ); ?>"
				height="<?php echo esc_attr( $img_height ); ?>"
				loading="lazy"
				class="pld-grid__image"
			>
			<span class="pld-grid__overlay" aria-hidden="true">
				<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
					<polyline points="15 3 21 3 21 9"/>
					<line x1="10" y1="14" x2="21" y2="3"/>
				</svg>
			</span>
		</a>
		<?php
	}
}
