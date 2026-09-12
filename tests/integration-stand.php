<?php
/**
 * SearchMiner integration tests — run INSIDE the QA container:
 *   wp eval-file /tmp/integration.php --allow-root
 *
 * Exercises the real plugin classes against the real database.
 * Leaves the store empty at the end.
 *
 * @package SearchMiner
 */

defined( 'ABSPATH' ) || exit;

$pass = 0;
$fail = 0;

function check( $label, $cond ) {
	// wp eval-file executes in a function scope — counters must be global.
	if ( $cond ) {
		$GLOBALS['pass'] = ( $GLOBALS['pass'] ?? 0 ) + 1;
		echo "  ok   {$label}\n";
	} else {
		$GLOBALS['fail'] = ( $GLOBALS['fail'] ?? 0 ) + 1;
		echo "  FAIL {$label}\n";
	}
}

echo "== SearchMiner integration ==\n";

// 0. Plugin is active and classes available.
check( 'plugin active', is_plugin_active( 'searchminer/searchminer.php' ) );
check( 'classes loaded', class_exists( 'WPSM_Repository' ) && class_exists( 'WPSM_Capture' ) );

// 1. Clean slate.
WPSM_Repository::erase_all();
check( 'erase clears store', 0 === count( WPSM_Repository::top( 'hits', 365, 100 ) ) );

// 2. Grouping and counters.
WPSM_Repository::record( '  Кухонные ВЕСЫ ', false, false );
WPSM_Repository::record( 'кухонные весы', false, false );
WPSM_Repository::record( 'кухонные весы', true, false );
WPSM_Repository::record( 'несуществующий товар', true, true );

$rows = WPSM_Repository::top( 'hits', 30, 10 );
check( 'two groups from four searches', 2 === count( $rows ) );

$by_norm = array();
foreach ( $rows as $r ) {
	$by_norm[ $r['query_norm'] ] = $r;
}
check( 'grouped hits=3', 3 === (int) $by_norm['кухонные весы']['hits'] );
check( 'grouped zero=1', 1 === (int) $by_norm['кухонные весы']['zero_hits'] );
check( 'woo flag on product query', 1 === (int) $by_norm['несуществующий товар']['is_woo'] );

// 3. Engagement.
$hash = WPSM_Normalizer::key( 'кухонные весы' );
check( 'click on known hash', WPSM_Repository::count_engagement( $hash, 'c' ) );
check( 'click on unknown hash rejected', ! WPSM_Repository::count_engagement( str_repeat( '0', 32 ), 'c' ) );
check( 'bad hash format rejected', ! WPSM_Repository::count_engagement( "'; DROP TABLE wp_wpsm_queries;--", 'c' ) );

$rows = WPSM_Repository::top_zero( 30, 10 );
$norms = wp_list_pluck( $rows, 'query_norm' );
sort( $norms );
check( 'top_zero excludes non-zero rows', 2 === count( $norms ) && 'кухонные весы' === $norms[0] && 'несуществующий товар' === $norms[1] );

// 4. Daily series.
$series = WPSM_Repository::daily_series( 30 );
check( 'daily series has today', 1 === count( $series ) && 4 === (int) $series[0]['searches'] && 2 === (int) $series[0]['zero_searches'] );

// 5. Prune honours retention (simulated old row).
WPSM_Repository::record( 'старинный запрос', false, false );
global $wpdb;
$wpdb->query( $wpdb->prepare( 'UPDATE ' . WPSM_Repository::queries_table() . ' SET last_seen = DATE_SUB( UTC_TIMESTAMP(), INTERVAL 400 DAY ) WHERE query_norm = %s', 'старинный запрос' ) ); // phpcs:ignore
WPSM_Settings::save( array( 'retention' => 90 ) );
WPSM_Repository::prune();
$rows = WPSM_Repository::top( 'hits', 365, 100 );
$found_old = false;
foreach ( $rows as $r ) {
	if ( 'старинный запрос' === $r['query_norm'] ) {
		$found_old = true;
	}
}
check( 'prune removed 400-day-old row', ! $found_old );
check( 'prune kept fresh rows', 2 === count( $rows ) );

// 6. Settings sanitization.
WPSM_Settings::save(
	array(
		'enabled'      => '1',
		'capture_mode' => 'hackers',
		'retention'    => 7,
		'exclude_ips'  => "10.0.0.5\nnot-an-ip\n192.168.0.*",
		'evil_key'     => 'drop table',
	)
);
$s = WPSM_Settings::all();
check( 'bad mode falls back to all', 'all' === $s['capture_mode'] );
check( 'bad retention falls back to 90', 90 === $s['retention'] );
check( 'ip list filtered', "10.0.0.5\n192.168.0.*" === $s['exclude_ips'] );
check( 'unknown keys dropped', ! isset( $s['evil_key'] ) );

// 7. Erase leaves tables in place.
WPSM_Repository::erase_all();
global $wpdb;
$t = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', WPSM_Repository::queries_table() ) ); // phpcs:ignore
check( 'tables survive erase', ! empty( $t ) );
check( 'store empty after erase', 0 === count( WPSM_Repository::top( 'hits', 365, 100 ) ) );

echo "\nINTEGRATION PASS: " . ( $GLOBALS['pass'] ?? 0 ) . ", FAIL: " . ( $GLOBALS['fail'] ?? 0 ) . "\n";
exit( $fail > 0 ? 1 : 0 );
