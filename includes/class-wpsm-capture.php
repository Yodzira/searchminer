<?php
/**
 * Front-end search capture.
 *
 * @package SearchMiner
 */

defined( 'ABSPATH' ) || exit;

/**
 * Hooks the main query and stores one row per human search view.
 */
final class WPSM_Capture {

	/** Common crawler fingerprints (substring, case-insensitive). */
	private static $bots = 'bot|crawl|spider|slurp|bingpreview|facebookexternalhit|yandex|baidu|duckduck|ahrefs|semrush|mj12bot|dotbot|petalbot|applebot|grep|monitor';

	/**
	 * Bootstrap.
	 */
	public static function init() {
		add_action( 'wp', array( __CLASS__, 'maybe_capture' ), 20 );
		add_action( 'wpsm_daily_prune', array( 'WPSM_Repository', 'prune' ) );
	}

	/**
	 * Capture the main search query when all conditions are met.
	 */
	public static function maybe_capture() {
		if ( is_admin() || ! is_search() || is_feed() || is_preview() || is_robots() ) {
			return;
		}

		$settings = WPSM_Settings::all();
		if ( empty( $settings['enabled'] ) ) {
			return;
		}

		if ( 'guests' === $settings['capture_mode'] && is_user_logged_in() ) {
			return;
		}

		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';

		if ( self::ip_is_excluded( $ip, $settings['exclude_ips'] ) || self::ua_is_bot( $ua ) ) {
			return;
		}

		$term = get_search_query( false );
		if ( '' === trim( (string) $term ) ) {
			return;
		}

		global $wp_query;
		$found = isset( $wp_query->found_posts ) ? (int) $wp_query->found_posts : 0;
		$zero  = ( 0 === $found );

		$is_woo = self::is_product_search();

		WPSM_Repository::record( $term, $zero, $is_woo );

		if ( ! empty( $settings['track_clicks'] ) && ! $zero ) {
			self::enqueue_results_js( WPSM_Normalizer::key( $term ) );
		}
	}

	/**
	 * Is this a WooCommerce product search?
	 *
	 * @return bool
	 */
	private static function is_product_search() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return false;
		}
		$post_type = get_query_var( 'post_type' );
		if ( is_array( $post_type ) ) {
			return in_array( 'product', $post_type, true );
		}
		return 'product' === $post_type;
	}

	/**
	 * UA-based crawler check. Deliberately fuzzy: missing a rare bot is fine,
	 * capturing thousands of crawl requests is not.
	 *
	 * @param string $ua User agent string.
	 * @return bool
	 */
	public static function ua_is_bot( $ua ) {
		$ua = (string) $ua;
		if ( '' === $ua ) {
			return true; // Empty UA is never a human with a browser.
		}
		return (bool) preg_match( '/' . self::$bots . '/i', $ua );
	}

	/**
	 * Match an IP against the exclusion list (exact or prefix with .*).
	 *
	 * @param string $ip   Remote address.
	 * @param string $list Newline/comma separated rules.
	 * @return bool
	 */
	public static function ip_is_excluded( $ip, $list ) {
		$ip   = (string) $ip;
		$list = (string) $list;
		if ( '' === trim( $list ) || '' === $ip ) {
			return false;
		}

		foreach ( preg_split( '/[\s,]+/', $list ) as $rule ) {
			$rule = trim( $rule );
			if ( '' === $rule ) {
				continue;
			}
			if ( '*' === substr( $rule, -1 ) ) {
				if ( 0 === strpos( $ip, rtrim( $rule, '*' ) ) ) {
					return true;
				}
			} elseif ( $ip === $rule ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Queue the tiny click/no-click tracker for a search results page.
	 *
	 * @param string $hash Query grouping key.
	 */
	private static function enqueue_results_js( $hash ) {
		wp_register_script(
			'wpsm-search',
			plugins_url( 'assets/js/search-results.js', WPSM_FILE ),
			array(),
			WPSM_VERSION,
			true
		);
		wp_enqueue_script( 'wpsm-search' );

		$inline = sprintf(
			'window.WPSM_Q=%s;window.WPSM_URL=%s;',
			wp_json_encode( $hash ),
			wp_json_encode( esc_url_raw( admin_url( 'admin-post.php?action=wpsm_beacon' ) ) )
		);
		wp_add_inline_script( 'wpsm-search', $inline, 'before' );
	}
}
