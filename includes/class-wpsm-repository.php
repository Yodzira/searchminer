<?php
/**
 * Database access layer. All SQL lives here and is always prepared.
 *
 * @package SearchMiner
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads/writes for the two plugin tables plus lifecycle helpers.
 */
final class WPSM_Repository {

	/**
	 * Queries table name, e.g. wp_wpsm_queries.
	 *
	 * @return string
	 */
	public static function queries_table() {
		global $wpdb;
		return $wpdb->prefix . 'wpsm_queries';
	}

	/**
	 * Daily aggregates table name.
	 *
	 * @return string
	 */
	public static function daily_table() {
		global $wpdb;
		return $wpdb->prefix . 'wpsm_daily';
	}

	/**
	 * Create tables on activation.
	 */
	public static function activate() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$q       = self::queries_table();
		$d       = self::daily_table();

		dbDelta(
			"CREATE TABLE {$q} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				qhash CHAR(32) NOT NULL,
				query_text TEXT NOT NULL,
				query_norm VARCHAR(191) NOT NULL,
				hits BIGINT UNSIGNED NOT NULL DEFAULT 1,
				zero_hits BIGINT UNSIGNED NOT NULL DEFAULT 0,
				click_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
				no_click_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
				is_woo TINYINT(1) NOT NULL DEFAULT 0,
				first_seen DATETIME NOT NULL,
				last_seen DATETIME NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY qhash (qhash),
				KEY query_norm (query_norm),
				KEY last_seen (last_seen)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$d} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				day DATE NOT NULL,
				searches INT UNSIGNED NOT NULL DEFAULT 0,
				zero_searches INT UNSIGNED NOT NULL DEFAULT 0,
				PRIMARY KEY  (id),
				UNIQUE KEY day (day)
			) {$charset};"
		);

		self::schedule_prune();
	}

	/**
	 * Clear scheduled maintenance on deactivation.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'wpsm_daily_prune' );
	}

	/**
	 * Schedule daily pruning.
	 */
	public static function schedule_prune() {
		if ( ! wp_next_scheduled( 'wpsm_daily_prune' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'wpsm_daily_prune' );
		}
	}

	/**
	 * Record one search occurrence.
	 *
	 * @param string $raw   Raw query text.
	 * @param bool   $zero  Whether the search returned zero results.
	 * @param bool   $is_woo Whether it was a product search.
	 * @return void
	 */
	public static function record( $raw, $zero, $is_woo ) {
		global $wpdb;

		$norm = WPSM_Normalizer::normalize( $raw );
		if ( '' === $norm ) {
			return;
		}
		$hash = md5( $norm );
		$now  = current_time( 'mysql', true );
		$t    = self::queries_table();

		$raw_store = function_exists( 'mb_substr' )
			? mb_substr( (string) $raw, 0, 500, 'UTF-8' )
			: substr( (string) $raw, 0, 500 );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is internal.
				"INSERT INTO {$t} (qhash, query_text, query_norm, hits, zero_hits, click_count, no_click_count, is_woo, first_seen, last_seen)
				 VALUES (%s, %s, %s, %d, %d, 0, 0, %d, %s, %s)
				 ON DUPLICATE KEY UPDATE
					hits = hits + 1,
					zero_hits = zero_hits + %d,
					is_woo = %d,
					last_seen = %s",
				$hash,
				$raw_store,
				$norm,
				$zero ? 0 : 1,
				$zero ? 1 : 0,
				$is_woo ? 1 : 0,
				$now,
				$now,
				$zero ? 1 : 0,
				$is_woo ? 1 : 0,
				$now
			)
		);

		self::bump_day( $zero ? 1 : 0 );
	}

	/**
	 * Increment today's aggregate row.
	 *
	 * @param int $zero 1 when the search was zero-result.
	 */
	private static function bump_day( $zero ) {
		global $wpdb;

		$day = gmdate( 'Y-m-d' );
		$t   = self::daily_table();

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is internal.
				"INSERT INTO {$t} (day, searches, zero_searches)
				 VALUES (%s, 1, %d)
				 ON DUPLICATE KEY UPDATE searches = searches + 1, zero_searches = zero_searches + %d",
				$day,
				$zero,
				$zero
			)
		);
	}

	/**
	 * Whether a recorded query hash exists in the queries table.
	 *
	 * Cheap indexed read used by the public beacon before any write.
	 *
	 * @param string $hash 32-char hex.
	 * @return bool
	 */
	public static function query_exists( $hash ) {
		global $wpdb;

		if ( ! preg_match( '/^[0-9a-f]{32}$/', $hash ) ) {
			return false;
		}
		$t = self::queries_table();

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is internal.
				"SELECT 1 FROM {$t} WHERE qhash = %s LIMIT 1",
				$hash
			)
		);
	}

	/**
	 * Register a click or a no-click beacon for a known query hash.
	 *
	 * @param string $hash 32-char hex.
	 * @param string $what 'c' click, 'n' no-click.
	 * @return bool True when a row was updated.
	 */
	public static function count_engagement( $hash, $what ) {
		global $wpdb;

		if ( ! preg_match( '/^[0-9a-f]{32}$/', $hash ) ) {
			return false;
		}
		$col = ( 'c' === $what ) ? 'click_count' : 'no_click_count';
		$t   = self::queries_table();

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$res = $wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is internal, column from whitelist above.
				"UPDATE {$t} SET {$col} = {$col} + 1 WHERE qhash = %s",
				$hash
			)
		);

		return $res > 0;
	}

	/**
	 * Top queries by a metric.
	 *
	 * @param string $metric 'hits'|'zero'|'noclick'.
	 * @param int    $days   Look-back window.
	 * @param int    $limit  Row limit.
	 * @return array[]
	 */
	public static function top( $metric, $days, $limit = 10 ) {
		global $wpdb;

		$map = array(
			'hits'    => 'hits',
			'zero'    => 'zero_hits',
			'noclick' => 'no_click_count',
		);
		if ( ! isset( $map[ $metric ] ) ) {
			return array();
		}

		$days = max( 1, min( 365, (int) $days ) );
		$col  = $map[ $metric ];
		$t    = self::queries_table();

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table, whitelisted column.
				"SELECT query_text, query_norm, hits, zero_hits, click_count, no_click_count, is_woo, last_seen
				 FROM {$t}
				 WHERE last_seen >= DATE_SUB( UTC_TIMESTAMP(), INTERVAL %d DAY )
				 ORDER BY {$col} DESC, last_seen DESC
				 LIMIT %d",
				$days,
				$limit
			),
			ARRAY_A
		);
	}

	/**
	 * Top zero-result queries only.
	 *
	 * @param int $days  Look-back window.
	 * @param int $limit Row limit.
	 * @return array[]
	 */
	public static function top_zero( $days, $limit = 5 ) {
		global $wpdb;

		$days = max( 1, min( 365, (int) $days ) );
		$t    = self::queries_table();

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
				"SELECT query_text, query_norm, hits, zero_hits, click_count, no_click_count, is_woo, last_seen
				 FROM {$t}
				 WHERE zero_hits > 0
				 AND last_seen >= DATE_SUB( UTC_TIMESTAMP(), INTERVAL %d DAY )
				 ORDER BY zero_hits DESC, last_seen DESC
				 LIMIT %d",
				$days,
				$limit
			),
			ARRAY_A
		);
	}

	/**
	 * Daily series for charts.
	 *
	 * @param int $days Look-back window.
	 * @return array[] Rows of day/searches/zero_searches.
	 */
	public static function daily_series( $days ) {
		global $wpdb;

		$days = max( 1, min( 365, (int) $days ) );
		$t    = self::daily_table();

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
				"SELECT day, searches, zero_searches FROM {$t}
				 WHERE day >= DATE_SUB( UTC_DATE(), INTERVAL %d DAY )
				 ORDER BY day ASC",
				$days
			),
			ARRAY_A
		);
	}

	/**
	 * Prune rows older than the retention window.
	 */
	public static function prune() {
		global $wpdb;

		$days = max( 1, (int) WPSM_Settings::get( 'retention' ) );
		$t    = self::queries_table();

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
				"DELETE FROM {$t} WHERE last_seen < DATE_SUB( UTC_TIMESTAMP(), INTERVAL %d DAY )",
				$days
			)
		);
	}

	/**
	 * Drop every trace of stored data (user-invoked "erase").
	 */
	public static function erase_all() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( 'TRUNCATE TABLE ' . self::queries_table() );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( 'TRUNCATE TABLE ' . self::daily_table() );
	}
}
