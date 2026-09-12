<?php
/**
 * Normalizer tests.
 *
 * @package SearchMiner
 */

/**
 * @group normalizer
 */
class NormalizerTest extends WP_UnitTestCase {

	public function test_case_and_trim() {
		$this->assertSame( 'iphone 15 case', WPSM_Normalizer::normalize( '  IPhone 15   CASE ' ) );
	}

	public function test_cyrillic_yo_folding() {
		$this->assertSame( WPSM_Normalizer::normalize( 'Ёлка' ), WPSM_Normalizer::normalize( 'елка' ) );
		$this->assertSame( 'елка игрушка', WPSM_Normalizer::normalize( 'ЁЛКА  игрушка' ) );
	}

	public function test_whitespace_collapsing() {
		$this->assertSame( 'a b', WPSM_Normalizer::normalize( "a\t\tb" ) );
		$this->assertSame( 'a b', WPSM_Normalizer::normalize( "a\xC2\xA0b" ) );
	}

	public function test_tags_stripped() {
		$this->assertSame( 'bold query', WPSM_Normalizer::normalize( '<b>bold</b> query' ) );
	}

	public function test_keys_group_and_separate() {
		$this->assertSame( WPSM_Normalizer::key( '  Красная ПЛАНТАЦИя ' ), WPSM_Normalizer::key( 'красная плантация' ) );
		$this->assertNotEquals( WPSM_Normalizer::key( 'apple' ), WPSM_Normalizer::key( 'apple inc' ) );
		$this->assertMatchesRegularExpression( '/^[0-9a-f]{32}$/', (string) WPSM_Normalizer::key( 'anything' ) );
	}

	public function test_empty_queries() {
		$this->assertSame( '', WPSM_Normalizer::normalize( '   ' ) );
		$this->assertSame( '', WPSM_Normalizer::key( '  ' ) );
		$this->assertSame( '', WPSM_Normalizer::key( null ) );
		$this->assertSame( '', WPSM_Normalizer::key( array( 'x' ) ) );
	}

	public function test_long_query_capped() {
		$this->assertLessThanOrEqual( 191, mb_strlen( WPSM_Normalizer::normalize( str_repeat( 'а', 500 ) ), 'UTF-8' ) );
	}
}
