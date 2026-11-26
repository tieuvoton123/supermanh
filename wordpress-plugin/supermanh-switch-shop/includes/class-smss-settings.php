<?php
defined( 'ABSPATH' ) || exit;

/**
 * Plugin settings, stored in one option.
 */
class SMSS_Settings {

	const OPTION = 'smss_settings';

	public static function defaults() {
		return array(
			'enabled'      => 1,
			'slug'         => 'tinfoil',
			'shop_name'    => 'SUPER MẠNH Shop',
			// Shown by Tinfoil when the shop loads. Plain ASCII renders everywhere.
			'motd'         => 'SUPER MANH SHOP - supermanh.com',
			'api_key'      => '',
			// gdrive = Tinfoil's native Google Drive protocol (recommended),
			// https  = direct download link, for clients without gdrive: support.
			'drive_mode'   => 'gdrive',
			'require_auth' => 1,
			'tinfoil_only' => 0,
			'only_nsw'     => 1,
			// Answer bots and browsers with the site's normal 404 page, so the shop
			// cannot be discovered or indexed. Tinfoil is unaffected.
			'stealth'      => 1,
			// Device activation (no login): Tinfoil shows an 8-character code tied to the
			// Switch; the admin finds that code in the app and tops up / extends it.
			'device_mode'  => 1,
			'device_secret' => '',
			// Host lock (same idea as Ownfoil): the index carries "referrer", so Tinfoil
			// refuses to load it from any other address, and the Hauth header Tinfoil
			// sends for this host is remembered and required afterwards.
			'host_lock'    => 0,
			'hauth'        => array(),
			// Site is behind Cloudflare / a reverse proxy: read the visitor IP from its header.
			'trust_proxy'  => 0,
			// Optional own (sub)domain for the shop, e.g. shop.supermanh.com. It must
			// point to this same WordPress. Empty = the site's own domain.
			'shop_host'    => '',
			'folders'      => array(),
			// Mobile management app for the admin, at /<app_slug>.
			'app_slug'     => 'sm-quanly',
			// Customer page (balance, unlock with points), at /<portal_slug>.
			'portal_slug'  => 'sm-khach',
			// Where customers ask for points (shown in Tinfoil and on the customer page).
			'contact'      => 'supermanh.com',
			'point_name'   => 'điểm',
		);
	}

	/** Parsed once per request. */
	private static $cache = null;

	public static function all() {
		if ( null === self::$cache ) {
			$saved       = get_option( self::OPTION, array() );
			self::$cache = wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
		}
		return self::$cache;
	}

	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	public static function update( array $values ) {
		$all = array_merge( self::all(), $values );
		// Autoloaded: comes with WordPress's own options query, no extra query per request.
		update_option( self::OPTION, $all, true );
		self::$cache = $all;
		return $all;
	}

	/** Shop URL Tinfoil loads, e.g. https://supermanh.com/tinfoil */
	public static function shop_url( $key = '' ) {
		$slug = self::get( 'slug' );
		$host = self::get( 'shop_host' );
		if ( $host ) {
			return 'https://' . $host . '/' . $slug . ( $key ? '/' . $key : '' );
		}
		if ( get_option( 'permalink_structure' ) ) {
			return home_url( '/' . $slug . ( $key ? '/' . $key : '' ) );
		}
		$args = array( 'smss_shop' => 1 );
		if ( $key ) {
			$args['smss_key'] = $key;
		}
		return add_query_arg( $args, home_url( '/' ) );
	}

	/** "https://Shop.SuperManh.com/abc" -> "shop.supermanh.com" */
	public static function sanitize_host( $host ) {
		$host = strtolower( trim( (string) $host ) );
		$host = preg_replace( '~^[a-z]+://~', '', $host );
		$host = preg_replace( '~[/?#].*$~', '', $host );
		return preg_match( '/^(?=.{3,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $host ) ? $host : '';
	}

	public static function sanitize_slug( $slug ) {
		$slug = trim( sanitize_title( $slug ), '/' );
		return '' === $slug ? 'tinfoil' : $slug;
	}
}
