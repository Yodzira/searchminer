<?php
/**
 * Standalone smoke runner for pure-logic classes (no WP required).
 * Shims the two WP helpers the classes rely on, then asserts.
 * Usage: php tests/smoke-runner.php
 */

error_reporting( E_ALL );

// Plugin files guard on ABSPATH — define it before requiring them.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

// --- Minimal WP shims -------------------------------------------------------
if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return is_array( $value ) ? array_map( 'wp_unslash', $value ) : stripslashes( $value );
	}
}
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $text, $remove_breaks = false ) {
		$text = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $text );
		$text = strip_tags( $text );
		if ( $remove_breaks ) {
			$text = preg_replace( '/[\r\n\t ]+/', ' ', $text );
		}
		return trim( $text );
	}
}
// ---------------------------------------------------------------------------

require __DIR__ . '/../includes/class-wpsm-normalizer.php';
require __DIR__ . '/../includes/class-wpsm-capture.php';

$pass = 0;
$fail = 0;

function check( $label, $cond ) {
	global $pass, $fail;
	if ( $cond ) {
		$pass++;
		echo "  ok   {$label}\n";
	} else {
		$fail++;
		echo "  FAIL {$label}\n";
	}
}

echo "== Normalizer ==\n";
check( 'case + trim', 'iphone 15 case' === WPSM_Normalizer::normalize( '  IPhone 15   CASE ' ) );
check( 'yo folding', WPSM_Normalizer::normalize( 'Ёлка' ) === WPSM_Normalizer::normalize( 'елка' ) );
check( 'yo to e text', 'елка' === WPSM_Normalizer::normalize( 'Ёлка' ) );
check( 'tab collapse', 'a b' === WPSM_Normalizer::normalize( "a\t\tb" ) );
check( 'nbsp collapse', 'a b' === WPSM_Normalizer::normalize( "a\xC2\xA0b" ) );
check( 'tags stripped', 'bold query' === WPSM_Normalizer::normalize( '<b>bold</b> query' ) );
check( 'key stable', WPSM_Normalizer::key( '  Красная ПЛАНТАЦИя ' ) === WPSM_Normalizer::key( 'красная плантация' ) );
check( 'key is md5', (bool) preg_match( '/^[0-9a-f]{32}$/', (string) WPSM_Normalizer::key( 'anything' ) ) );
check( 'empty key', '' === WPSM_Normalizer::key( '   ' ) );
check( 'null key', '' === WPSM_Normalizer::key( null ) );
check( 'long capped', mb_strlen( WPSM_Normalizer::normalize( str_repeat( 'а', 500 ) ), 'UTF-8' ) <= 191 );
check( 'non-string safe', '' === WPSM_Normalizer::normalize( array( 'x' ) ) );

echo "== Capture filters ==\n";
check( 'googlebot', WPSM_Capture::ua_is_bot( 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)' ) );
check( 'yandexbot', WPSM_Capture::ua_is_bot( 'Mozilla/5.0 (compatible; YandexBot/3.0; +http://yandex.com/bots)' ) );
check( 'ahrefs', WPSM_Capture::ua_is_bot( 'AhrefsSiteAudit/6.1' ) );
check( 'empty ua is bot', WPSM_Capture::ua_is_bot( '' ) );
check( 'chrome human', ! WPSM_Capture::ua_is_bot( 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36' ) );
check( 'iphone human', ! WPSM_Capture::ua_is_bot( 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1' ) );

$list = "10.0.0.5\n192.168.1.10";
check( 'exact ip in list', WPSM_Capture::ip_is_excluded( '10.0.0.5', $list ) );
check( 'exact ip not in list', ! WPSM_Capture::ip_is_excluded( '10.0.0.6', $list ) );
check( 'prefix match', WPSM_Capture::ip_is_excluded( '192.168.1.55', '192.168.1.*' ) );
check( 'prefix no match', ! WPSM_Capture::ip_is_excluded( '192.168.2.55', '192.168.1.*' ) );
check( 'empty list pass', ! WPSM_Capture::ip_is_excluded( '8.8.8.8', '' ) );
check( 'empty ip pass', ! WPSM_Capture::ip_is_excluded( '', '10.0.0.5' ) );

echo "\nPASS: {$pass}, FAIL: {$fail}\n";
exit( $fail > 0 ? 1 : 0 );
