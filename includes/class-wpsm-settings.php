<?php
/**
 * Settings storage and sanitization.
 *
 * @package SearchMiner
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings handler. Single option row, sanitized as a whole.
 */
final class WPSM_Settings {

	const OPTION = 'wpsm_settings';

	/** @var array|null Cached copy. */
	private static $cache = null;

	/**
	 * Bootstrap hooks.
	 */
	public static function init() {
		load_plugin_textdomain( 'searchminer', false, dirname( plugin_basename( WPSM_FILE ) ) . '/languages' );
	}

	/**
	 * Default values.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'enabled'       => 1,
			'capture_mode'  => 'all',   // all | guests.
			'retention'     => 90,      // days.
			'exclude_ips'   => '',      // one per line, exact or prefix with trailing .*
			'track_clicks'  => 1,
		);
	}

	/**
	 * Get all settings merged with defaults.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null === self::$cache ) {
			$stored      = get_option( self::OPTION, array() );
			self::$cache = wp_parse_args( is_array( $stored ) ? $stored : array(), self::defaults() );
		}
		return self::$cache;
	}

	/**
	 * Get one setting.
	 *
	 * @param string $key Setting key.
	 * @return mixed
	 */
	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Sanitize a raw settings array. Everything unknown is dropped.
	 *
	 * @param array $raw Raw input.
	 * @return array Clean settings.
	 */
	public static function sanitize( $raw ) {
		$d    = self::defaults();
		$clean = array();

		$clean['enabled']      = empty( $raw['enabled'] ) ? 0 : 1;
		$clean['capture_mode'] = ( isset( $raw['capture_mode'] ) && 'guests' === $raw['capture_mode'] ) ? 'guests' : 'all';
		$clean['track_clicks'] = empty( $raw['track_clicks'] ) ? 0 : 1;

		$retention          = isset( $raw['retention'] ) ? absint( $raw['retention'] ) : $d['retention'];
		$allowed            = array( 30, 60, 90, 180, 365 );
		$clean['retention'] = in_array( $retention, $allowed, true ) ? $retention : $d['retention'];

		$ips                 = array();
		if ( isset( $raw['exclude_ips'] ) && is_string( $raw['exclude_ips'] ) ) {
			foreach ( preg_split( '/[\s,]+/', $raw['exclude_ips'] ) as $ip ) {
				$ip = trim( $ip );
				if ( '' === $ip ) {
					continue;
				}
				// Prefix rules end with .*: validate the partial IP before the star.
				$validate = ( '*' === substr( $ip, -1 ) ) ? rtrim( rtrim( $ip, '*' ), '.' ) : $ip;
				if ( self::valid_ip_or_prefix( $validate ) ) {
					$ips[] = $ip;
				}
			}
		}
		$clean['exclude_ips'] = implode( "\n", array_slice( array_unique( $ips ), 0, 100 ) );

		self::$cache = $clean;
		return $clean;
	}

	/**
	 * Persist sanitized settings.
	 *
	 * @param array $raw Raw input.
	 */
	public static function save( $raw ) {
		update_option( self::OPTION, self::sanitize( $raw ) );
	}

	/**
	 * A full IP, or a 1–3 octet prefix used with a trailing .* rule.
	 *
	 * @param string $candidate Candidate address.
	 * @return bool
	 */
	private static function valid_ip_or_prefix( $candidate ) {
		$candidate = (string) $candidate;
		if ( '' === $candidate ) {
			return false;
		}
		if ( filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
			return true;
		}
		if ( ! preg_match( '/^\d{1,3}(?:\.\d{1,3}){0,3}$/', $candidate ) ) {
			return false;
		}
		foreach ( explode( '.', $candidate ) as $octet ) {
			if ( (int) $octet > 255 ) {
				return false;
			}
		}
		return true;
	}
}
