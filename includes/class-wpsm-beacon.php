<?php
/**
 * Public engagement beacon endpoint.
 *
 * Accepts click/no-click counters for search pages already recorded.
 * No nonce on purpose: the beacon must survive full-page caching, it only
 * increments a counter for a hash that must already exist, and it is rate
 * limited per IP+hash. Worst case an abuser inflates a counter.
 *
 * @package SearchMiner
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles POSTs from assets/js/search-results.js.
 */
final class WPSM_Beacon {

	/**
	 * Bootstrap.
	 */
	public static function init() {
		add_action( 'admin_post_nopriv_wpsm_beacon', array( __CLASS__, 'handle' ) );
		add_action( 'admin_post_wpsm_beacon', array( __CLASS__, 'handle' ) );
	}

	/**
	 * Process a beacon. Always answers 204 and never leaks why it refused.
	 */
	public static function handle() {
		if ( ! isset( $_POST['q'], $_POST['a'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- public counter, see file docblock.
			self::no_content();
		}

		$q = isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$a = isset( $_POST['a'] ) ? sanitize_key( wp_unslash( $_POST['a'] ) ) : '';       // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( ! preg_match( '/^[0-9a-f]{32}$/', $q ) || ! in_array( $a, array( 'c', 'n' ), true ) ) {
			self::no_content();
		}

		if ( ! self::rate_limit_ok( $q . $a ) ) {
			self::no_content();
		}

		WPSM_Repository::count_engagement( $q, $a );

		self::no_content();
	}

	/**
	 * Sliding-ish window limiter: max 10 beacons per 60s per IP+hash+action.
	 *
	 * @param string $key Rate key.
	 * @return bool
	 */
	private static function rate_limit_ok( $key ) {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- hashed, never stored.
		$id = 'wpsm_rl_' . md5( $ip . '|' . $key );

		$count = (int) get_transient( $id );
		if ( $count >= 10 ) {
			return false;
		}
		set_transient( $id, $count + 1, MINUTE_IN_SECONDS );
		return true;
	}

	/**
	 * 204, nothing else.
	 */
	private static function no_content() {
		if ( ! headers_sent() ) {
			status_header( 204 );
		}
		exit;
	}
}
