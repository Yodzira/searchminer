<?php
/**
 * Query normalization for grouping.
 *
 * @package SearchMiner
 */

defined( 'ABSPATH' ) || exit;

/**
 * Turns a raw search phrase into a canonical form used for grouping.
 * Deliberately conservative: case, spacing and ё→е only. No stopword
 * stripping in v1 — the user must still recognize their own words.
 */
final class WPSM_Normalizer {

	/**
	 * Normalize a raw query.
	 *
	 * @param string $raw Raw search phrase.
	 * @return string Normalized string, max 191 chars, may be empty.
	 */
	public static function normalize( $raw ) {
		if ( ! is_string( $raw ) ) {
			return '';
		}

		$s = wp_unslash( $raw );

		// Collapse unescaped markup coming from odd clients.
		$s = wp_strip_all_tags( $s );

		if ( function_exists( 'mb_strtolower' ) ) {
			$s = mb_strtolower( $s, 'UTF-8' );
		} else {
			$s = strtolower( $s );
		}

		// Cyrillic yo folding so "ёлка" and "елка" group together.
		$map = array( 'ё' => 'е', 'Ё' => 'е' );
		$s   = strtr( $s, $map );

		// Normalize whitespace of any kind (incl. NBSP) to single spaces.
		$s = preg_replace( '/[\s\x{00A0}\x{2000}-\x{200B}]+/u', ' ', $s );
		$s = trim( (string) $s );

		// Hard cap to fit the indexed column comfortably.
		if ( function_exists( 'mb_substr' ) ) {
			$s = mb_substr( $s, 0, 191, 'UTF-8' );
		} else {
			$s = substr( $s, 0, 191 );
		}

		return $s;
	}

	/**
	 * Grouping key for a raw query.
	 *
	 * @param string $raw Raw search phrase.
	 * @return string 32-char hex, or empty string when there is nothing to store.
	 */
	public static function key( $raw ) {
		$norm = self::normalize( $raw );
		if ( '' === $norm || '' === trim( $norm ) ) {
			return '';
		}
		return md5( $norm );
	}
}
