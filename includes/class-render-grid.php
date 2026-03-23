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
		add_action( 'rest_api_init', array( $this, 'register_rest_route' ) );
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

		wp_register_script(
			'link-drop-grid',
			POST_LINK_DROP_URL . 'assets/js/grid.js',
			array(),
			POST_LINK_DROP_VERSION,
			true
		);
	}

	/**
	 * Register REST API route for load more.
	 */
	public function register_rest_route() {
		register_rest_route(
			'post-link-drop/v1',
			'/grid',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_load_more' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'page'    => array(
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'count'   => array(
						'default'           => 12,
						'sanitize_callback' => 'absint',
					),
					'status'  => array(
						'default'           => 'linked',
						'sanitize_callback' => 'sanitize_key',
					),
					'orderby' => array(
						'default'           => 'ig_timestamp',
						'sanitize_callback' => 'sanitize_key',
					),
					'order'   => array(
						'default'           => 'DESC',
						'sanitize_callback' => 'sanitize_key',
					),
					'new_tab' => array(
						'default' => 'true',
					),
				),
			)
		);
	}

	/**
	 * REST endpoint callback for load more.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function rest_load_more( $request ) {
		$page    = max( 1, $request->get_param( 'page' ) );
		$count   = max( 1, min( 100, $request->get_param( 'count' ) ) );
		$status  = $request->get_param( 'status' );
		$orderby = $request->get_param( 'orderby' );
		$order   = strtoupper( $request->get_param( 'order' ) ) === 'ASC' ? 'ASC' : 'DESC';
		$new_tab = filter_var( $request->get_param( 'new_tab' ), FILTER_VALIDATE_BOOLEAN );

		$query_args = array(
			'post_type'      => CPT_Post_Link_Drop_Item::POST_TYPE,
			'posts_per_page' => $count,
			'paged'          => $page,
			'post_status'    => 'publish',
			'orderby'        => 'meta_value',
			'order'          => $order,
			'meta_key'       => CPT_Post_Link_Drop_Item::META_PREFIX . 'ig_timestamp',
		);

		if ( 'all' !== $status ) {
			$query_args['meta_query'] = array(
				array(
					'key'   => CPT_Post_Link_Drop_Item::META_PREFIX . 'status',
					'value' => $status,
				),
			);
		} else {
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
			return rest_ensure_response( array(
				'html'     => '',
				'has_more' => false,
			) );
		}

		ob_start();
		while ( $query->have_posts() ) {
			$query->the_post();
			$this->render_grid_item( get_the_ID(), $new_tab );
		}
		wp_reset_postdata();
		$html = ob_get_clean();

		return rest_ensure_response( array(
			'html'     => $html,
			'has_more' => $page < $query->max_num_pages,
		) );
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

		// Generate cache key and stable grid ID.
		$cache_key = self::TRANSIENT_PREFIX . md5( wp_json_encode( $atts ) );
		$grid_id   = 'pld-grid-' . substr( md5( wp_json_encode( $atts ) ), 0, 12 );

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

		$has_more = $query->max_num_pages > 1;

		ob_start();
		?>
		<style>#<?php echo esc_html( $grid_id ); ?>{--pld-gap:<?php echo esc_html( $gap ); ?>}@media(min-width:769px){#<?php echo esc_html( $grid_id ); ?>{--pld-columns:<?php echo absint( $columns ); ?>}}</style>
		<div id="<?php echo esc_attr( $grid_id ); ?>" class="<?php echo esc_attr( $wrapper_class ); ?>">
			<?php
			while ( $query->have_posts() ) {
				$query->the_post();
				$this->render_grid_item( get_the_ID(), $new_tab );
			}
			wp_reset_postdata();
			?>
		</div>
		<?php if ( $has_more ) : ?>
		<div class="pld-load-more__wrapper">
			<button
				class="pld-load-more"
				data-grid="<?php echo esc_attr( $grid_id ); ?>"
				data-page="1"
				data-count="<?php echo esc_attr( $count ); ?>"
				data-status="<?php echo esc_attr( $status ); ?>"
				data-orderby="<?php echo esc_attr( $orderby ); ?>"
				data-order="<?php echo esc_attr( $order ); ?>"
				data-new-tab="<?php echo $new_tab ? 'true' : 'false'; ?>"
			><?php esc_html_e( 'Load More', 'post-link-drop' ); ?></button>
		</div>
		<?php endif; ?>
		<?php
		$html = ob_get_clean();

		// Cache the output.
		set_transient( $cache_key, $html, self::CACHE_EXPIRATION );

		// Enqueue styles and scripts.
		wp_enqueue_style( 'link-drop-grid' );
		if ( $has_more ) {
			wp_enqueue_script( 'link-drop-grid' );
			wp_localize_script(
				'link-drop-grid',
				'pldGrid',
				array(
					'restUrl' => rest_url( 'post-link-drop/v1/grid' ),
				)
			);
		}

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
		$image = wp_get_attachment_image_src( $attachment_id, 'full' );

		if ( ! $image ) {
			return;
		}

		$img_url    = $image[0];
		$img_width  = $image[1];
		$img_height = $image[2];
		$is_square  = ( $img_width > 0 && $img_height > 0 && $img_width === $img_height );

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
		$item_class = 'pld-grid__item' . ( $is_square ? ' pld-grid__item--square' : '' );

		$link_attrs = array(
			'href'       => esc_url( $destination_url ),
			'class'      => $item_class,
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
