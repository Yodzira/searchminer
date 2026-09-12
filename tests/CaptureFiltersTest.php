<?php
/**
 * Bot filter and IP exclusion tests (pure logic).
 *
 * @package SearchMiner
 */

/**
 * @group capture
 */
class CaptureFiltersTest extends WP_UnitTestCase {

	public function test_bots_detected() {
		$this->assertTrue( WPSM_Capture::ua_is_bot( 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)' ) );
		$this->assertTrue( WPSM_Capture::ua_is_bot( 'Mozilla/5.0 (compatible; YandexBot/3.0; +http://yandex.com/bots)' ) );
		$this->assertTrue( WPSM_Capture::ua_is_bot( 'AhrefsSiteAudit/6.1' ) );
	}

	public function test_humans_pass() {
		$this->assertFalse( WPSM_Capture::ua_is_bot( 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36' ) );
		$this->assertFalse( WPSM_Capture::ua_is_bot( 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1' ) );
	}

	public function test_empty_ua_is_bot() {
		$this->assertTrue( WPSM_Capture::ua_is_bot( '' ) );
	}

	public function test_exact_ip_excluded() {
		$list = "10.0.0.5\n192.168.1.10";
		$this->assertTrue( WPSM_Capture::ip_is_excluded( '10.0.0.5', $list ) );
		$this->assertFalse( WPSM_Capture::ip_is_excluded( '10.0.0.6', $list ) );
	}

	public function test_prefix_ip_excluded() {
		$this->assertTrue( WPSM_Capture::ip_is_excluded( '192.168.1.55', '192.168.1.*' ) );
		$this->assertFalse( WPSM_Capture::ip_is_excluded( '192.168.2.55', '192.168.1.*' ) );
	}

	public function test_empty_list_and_ip() {
		$this->assertFalse( WPSM_Capture::ip_is_excluded( '8.8.8.8', '' ) );
		$this->assertFalse( WPSM_Capture::ip_is_excluded( '', '10.0.0.5' ) );
	}
}
