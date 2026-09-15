<?php
/**
 * Repository/database behaviour tests.
 *
 * @package SearchMiner
 */

/**
 * @group repository
 */
class RepositoryTest extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		WPSM_Repository::erase_all(); // Clean slate — custom tables persist across tests.
	}

	public function test_record_increments_and_groups() {
		WPSM_Repository::record( 'запчасти ваз', false, false );
		WPSM_Repository::record( '  ЗАПЧАСТИ ВАЗ ', false, false );
		WPSM_Repository::record( 'запчасти ваз', true, false );

		$rows = WPSM_Repository::top( 'hits', 30, 10 );

		$this->assertCount( 1, $rows, 'Same normalized query must occupy one row.' );
		$this->assertSame( 3, (int) $rows[0]['hits'] );
		$this->assertSame( 1, (int) $rows[0]['zero_hits'] );
		$this->assertSame( 'запчасти ваз', $rows[0]['query_norm'] );
	}

	public function test_empty_query_not_recorded() {
		WPSM_Repository::record( '   ', false, false );
		$this->assertCount( 0, WPSM_Repository::top( 'hits', 30, 10 ) );
	}

	public function test_query_exists_matches_recorded_hash_only() {
		WPSM_Repository::record( 'кухонные весы', false, false );
		$hash = WPSM_Normalizer::key( 'кухонные весы' );

		$this->assertTrue( WPSM_Repository::query_exists( $hash ) );
		$this->assertFalse( WPSM_Repository::query_exists( str_repeat( 'a', 32 ) ) );
		$this->assertFalse( WPSM_Repository::query_exists( "'; DROP TABLE wp_wpsm_queries;--" ) );
	}

	public function test_engagement_on_known_hash_only() {
		WPSM_Repository::record( 'кухонные весы', false, false );
		$hash = WPSM_Normalizer::key( 'кухонные весы' );

		$this->assertTrue( WPSM_Repository::count_engagement( $hash, 'c' ) );
		$this->assertTrue( WPSM_Repository::count_engagement( $hash, 'n' ) );
		$this->assertFalse( WPSM_Repository::count_engagement( str_repeat( 'a', 32 ), 'c' ) );
		$this->assertFalse( WPSM_Repository::count_engagement( "'; DROP TABLE wp_wpsm_queries;--", 'c' ) );

		$row = WPSM_Repository::top( 'hits', 30, 1 )[0];
		$this->assertSame( 1, (int) $row['click_count'] );
		$this->assertSame( 1, (int) $row['no_click_count'] );
	}

	public function test_daily_series() {
		WPSM_Repository::record( 'one', false, false );
		WPSM_Repository::record( 'two', true, false );

		$series = WPSM_Repository::daily_series( 30 );
		$this->assertSame( 2, (int) $series[0]['searches'] );
		$this->assertSame( 1, (int) $series[0]['zero_searches'] );
	}

	public function test_top_zero_excludes_non_zero() {
		WPSM_Repository::record( 'found it', false, false );
		WPSM_Repository::record( 'not found', true, false );

		$rows = WPSM_Repository::top_zero( 30, 10 );
		$this->assertCount( 1, $rows );
		$this->assertSame( 'not found', $rows[0]['query_norm'] );
	}

	public function test_prune_removes_old_rows() {
		global $wpdb;

		WPSM_Repository::record( 'fresh query', false, false );
		WPSM_Repository::record( 'ancient query', false, false );

		$table = WPSM_Repository::queries_table();
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- test-only.
			$wpdb->prepare(
				"UPDATE {$table} SET last_seen = DATE_SUB( UTC_TIMESTAMP(), INTERVAL 400 DAY ) WHERE query_text = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
				'ancient query'
			)
		);

		WPSM_Settings::save( array( 'retention' => 90 ) );
		WPSM_Repository::prune();

		$rows = WPSM_Repository::top( 'hits', 365, 10 );
		$this->assertCount( 1, $rows );
		$this->assertSame( 'fresh query', $rows[0]['query_text'] );
	}

	public function test_erase_all() {
		WPSM_Repository::record( 'to be erased', true, true );
		WPSM_Repository::erase_all();

		$this->assertCount( 0, WPSM_Repository::top( 'hits', 365, 10 ) );
		$this->assertCount( 0, WPSM_Repository::daily_series( 365 ) );

		global $wpdb;
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', WPSM_Repository::queries_table() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- test-only.
		$this->assertNotEmpty( $found, 'Tables must survive erase — only rows go away.' );
	}
}
