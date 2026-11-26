<?php
defined( 'ABSPATH' ) || exit;

/**
 * REST API under /wp-json/smss/v1.
 *  - /admin/*  : the mobile management app (WordPress admin, cookie + nonce).
 *  - /portal/* : the customer page (shop account, own session cookie).
 */
class SMSS_API {

	const NS = 'smss/v1';

	const PORTAL_COOKIE = 'smss_portal';

	const PORTAL_TTL = 30 * DAY_IN_SECONDS;

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function routes() {
		$admin = array( __CLASS__, 'can_admin' );
		$r     = function ( $path, $methods, $cb, $perm ) {
			register_rest_route( self::NS, $path, array(
				'methods'             => $methods,
				'callback'            => array( __CLASS__, $cb ),
				'permission_callback' => $perm,
			) );
		};

		$r( '/admin/home', 'GET', 'admin_home', $admin );
		$r( '/admin/accounts', 'GET', 'admin_accounts', $admin );
		$r( '/admin/accounts', 'POST', 'admin_account_create', $admin );
		$r( '/admin/accounts/(?P<id>\d+)', 'GET', 'admin_account', $admin );
		$r( '/admin/accounts/(?P<id>\d+)', 'POST', 'admin_account_action', $admin );
		$r( '/admin/accounts/(?P<id>\d+)', 'DELETE', 'admin_account_delete', $admin );
		$r( '/admin/accounts/(?P<id>\d+)/points', 'POST', 'admin_points', $admin );
		$r( '/admin/accounts/(?P<id>\d+)/grant', 'POST', 'admin_grant', $admin );
		$r( '/admin/accounts/(?P<id>\d+)/revoke', 'POST', 'admin_revoke', $admin );
		$r( '/admin/items', 'GET', 'admin_items', $admin );
		$r( '/admin/items', 'POST', 'admin_items_add', $admin );
		$r( '/admin/items/(?P<id>\d+)', 'POST', 'admin_item_update', $admin );
		$r( '/admin/folders/sync', 'POST', 'admin_folders_sync', $admin );
		$r( '/admin/import', 'POST', 'admin_import', $admin );
		$r( '/admin/export', 'GET', 'admin_export', $admin );
		$r( '/admin/titles', 'POST', 'admin_titles', $admin );

		$r( '/portal/login', 'POST', 'portal_login', array( __CLASS__, 'portal_csrf' ) );
		$r( '/portal/logout', 'POST', 'portal_logout', array( __CLASS__, 'portal_csrf' ) );
		$r( '/portal/me', 'GET', 'portal_me', '__return_true' );
		$r( '/portal/unlock', 'POST', 'portal_unlock', array( __CLASS__, 'portal_csrf' ) );
	}

	public static function can_admin() {
		return current_user_can( SMSS_Admin_Capability::CAP );
	}

	/* ------------------------------------------------------------------ */
	/* Shaping                                                             */
	/* ------------------------------------------------------------------ */

	private static function err( $message, $status = 400 ) {
		return new WP_Error( 'smss', $message, array( 'status' => $status ) );
	}

	public static function item_out( $i, $owned = null ) {
		$out = array(
			'id'       => (int) $i->id,
			'name'     => $i->name,
			'title_id' => $i->title_id,
			'display'  => isset( $i->display_name ) ? $i->display_name : '',
			'game'     => isset( $i->game_name ) ? $i->game_name : '',
			'icon'     => isset( $i->icon_url ) ? $i->icon_url : '',
			'size'     => (int) $i->size,
			'size_h'   => SMSS_Links::human_size( $i->size ),
			'price'    => (int) $i->price,
			'is_free'  => (bool) (int) $i->is_free,
			'enabled'  => (bool) (int) $i->enabled,
		);
		if ( isset( $i->source ) ) {
			$out['source'] = $i->gdrive_id ? 'drive' : (string) wp_parse_url( $i->url, PHP_URL_HOST );
		}
		if ( null !== $owned ) {
			$out['owned'] = $owned;
		}
		return $out;
	}

	public static function account_out( $a ) {
		return array(
			'id'          => (int) $a->id,
			'username'    => $a->username,
			'kind'        => SMSS_Accounts::is_device( $a ) ? 'device' : 'user',
			'code'        => SMSS_Accounts::is_device( $a ) ? SMSS_Accounts::format_code( $a->username ) : '',
			'note'        => $a->note,
			'points'      => (int) $a->points,
			'enabled'     => (bool) (int) $a->enabled,
			'all_access'  => (bool) (int) $a->all_access,
			'expires_at'  => $a->expires_at,
			'expired'     => SMSS_Accounts::is_expired( $a ),
			'max_devices' => (int) $a->max_devices,
			'devices'     => count( SMSS_Accounts::devices( $a ) ),
			'last_seen'   => $a->last_seen,
			'last_ip'     => $a->last_ip,
			'hits'        => (int) $a->hits,
			'sig_state'   => isset( $a->sig_state ) ? (int) $a->sig_state : 0,
			'sig_bad'     => isset( $a->sig_bad ) ? (int) $a->sig_bad : 0,
			'sig_bad_at'  => isset( $a->sig_bad_at ) ? $a->sig_bad_at : null,
			'client_ver'  => isset( $a->client_ver ) ? $a->client_ver : '',
			'owned_count' => isset( $a->owned_count ) ? (int) $a->owned_count : SMSS_Accounts::grant_count( $a->id ),
			'created_at'  => $a->created_at,
		);
	}

	/** What a customer types into Tinfoil. */
	public static function tinfoil_info( $account = null ) {
		$parts = wp_parse_url( SMSS_Settings::shop_url() );
		$https = 'https' === ( $parts['scheme'] ?? 'https' );
		$info  = array(
			'protocol' => $https ? 'https' : 'http',
			'host'     => $parts['host'] ?? '',
			'port'     => isset( $parts['port'] ) ? (int) $parts['port'] : ( $https ? 443 : 80 ),
			'path'     => ( $parts['path'] ?? '/' ) . ( isset( $parts['query'] ) ? '?' . $parts['query'] : '' ),
			'title'    => SMSS_Settings::get( 'shop_name' ),
		);
		if ( $account ) {
			$info['username']     = SMSS_Accounts::is_device( $account ) ? '' : $account->username;
			$info['code']         = SMSS_Accounts::is_device( $account ) ? SMSS_Accounts::format_code( $account->username ) : '';
			$info['personal_url'] = SMSS_Settings::shop_url( $account->access_key );
		}
		return $info;
	}

	/* ------------------------------------------------------------------ */
	/* Admin                                                               */
	/* ------------------------------------------------------------------ */

	public static function admin_home() {
		global $wpdb;
		$acc = SMSS_DB::accounts_table();
		return array(
			'items'    => SMSS_Library::count( '', true ),
			'size_h'   => SMSS_Links::human_size( SMSS_Library::total_size() ),
			'accounts' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$acc}" ),
			'active7'  => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$acc} WHERE last_seen >= %s", gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 7 * DAY_IN_SECONDS ) ) ),
			'totals'   => SMSS_Points::totals(),
			'recent'   => array_map( function ( $l ) {
				return array(
					'username'   => ( 'device' === $l->kind && $l->username ) ? SMSS_Accounts::format_code( $l->username ) : $l->username,
					'account_id' => (int) $l->account_id,
					'delta'      => (int) $l->delta,
					'type'       => $l->type,
					'note'       => $l->note,
					'at'         => $l->created_at,
				);
			}, SMSS_Points::recent( 25 ) ),
			'shop'     => self::tinfoil_info(),
			'portal'   => SMSS_App::portal_url(),
			'point'    => SMSS_Settings::get( 'point_name' ),
		);
	}

	public static function admin_accounts( WP_REST_Request $req ) {
		global $wpdb;
		$acc    = SMSS_DB::accounts_table();
		$grants = SMSS_DB::grants_table();
		$s      = trim( (string) $req->get_param( 's' ) );
		$sql    = "SELECT a.*, (SELECT COUNT(*) FROM {$grants} g WHERE g.account_id = a.id) AS owned_count FROM {$acc} a";
		if ( '' !== $s ) {
			$like  = '%' . $wpdb->esc_like( $s ) . '%';
			$code  = '%' . $wpdb->esc_like( strtoupper( preg_replace( '/[\s-]+/', '', $s ) ) ) . '%';
			$sql  .= $wpdb->prepare( ' WHERE a.username LIKE %s OR a.username LIKE %s OR a.note LIKE %s', $like, $code, $like );
		}
		$sql .= ' ORDER BY COALESCE(a.last_seen, a.created_at) DESC LIMIT 300';
		return array_map( array( __CLASS__, 'account_out' ), $wpdb->get_results( $sql ) );
	}

	public static function admin_account( WP_REST_Request $req ) {
		$a = SMSS_Accounts::get( (int) $req['id'] );
		if ( ! $a ) {
			return self::err( 'Không tìm thấy tài khoản.', 404 );
		}
		return array(
			'account' => self::account_out( $a ),
			'owned'   => array_map( function ( $i ) {
				return array(
					'id'         => (int) $i->id,
					'name'       => $i->name,
					'title_id'   => $i->title_id,
					'icon'       => $i->icon_url,
					'size_h'     => SMSS_Links::human_size( $i->size ),
					'enabled'    => (bool) (int) $i->enabled,
					'granted_at' => $i->granted_at,
				);
			}, SMSS_Accounts::granted_items( $a->id ) ),
			'history' => array_map( function ( $l ) {
				return array(
					'delta'   => (int) $l->delta,
					'balance' => (int) $l->balance,
					'type'    => $l->type,
					'note'    => $l->note,
					'at'      => $l->created_at,
				);
			}, SMSS_Points::history( $a->id, 100 ) ),
			'tinfoil' => self::tinfoil_info( $a ),
			'portal'  => SMSS_App::portal_url( $a->access_key ),
		);
	}

	public static function admin_account_create( WP_REST_Request $req ) {
		$username = sanitize_user( (string) $req->get_param( 'username' ), true );
		$password = (string) $req->get_param( 'password' );
		if ( '' === $password ) {
			$password = wp_generate_password( 10, false );
		}
		$expires = (string) $req->get_param( 'expires' );
		$expires = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $expires ) ? $expires . ' 23:59:59' : null;
		$id      = SMSS_Accounts::create(
			$username,
			$password,
			$expires,
			(int) $req->get_param( 'max_devices' ),
			sanitize_text_field( (string) $req->get_param( 'note' ) ),
			$req->get_param( 'all_access' ) ? 1 : 0
		);
		if ( is_wp_error( $id ) ) {
			return self::err( $id->get_error_message() );
		}
		$points = (int) $req->get_param( 'points' );
		if ( $points > 0 ) {
			SMSS_Points::topup( $id, $points, 'Nạp lúc tạo tài khoản' );
		}
		$a = SMSS_Accounts::get( $id );
		return array(
			'account'  => self::account_out( $a ),
			'password' => $password,
			'tinfoil'  => self::tinfoil_info( $a ),
			'portal'   => SMSS_App::portal_url( $a->access_key ),
		);
	}

	public static function admin_account_action( WP_REST_Request $req ) {
		$a = SMSS_Accounts::get( (int) $req['id'] );
		if ( ! $a ) {
			return self::err( 'Không tìm thấy tài khoản.', 404 );
		}
		$extra = array();
		switch ( (string) $req->get_param( 'action' ) ) {
			case 'toggle':
				SMSS_Accounts::update( $a->id, array( 'enabled' => $a->enabled ? 0 : 1 ) );
				break;
			case 'all_access':
				SMSS_Accounts::update( $a->id, array( 'all_access' => $a->all_access ? 0 : 1 ) );
				break;
			case 'extend':
				$days = max( 1, min( 3650, (int) $req->get_param( 'days' ) ?: 30 ) );
				$now  = current_time( 'timestamp' );
				$from = ( $a->expires_at && strtotime( $a->expires_at ) > $now ) ? strtotime( $a->expires_at ) : $now;
				SMSS_Accounts::update( $a->id, array( 'expires_at' => gmdate( 'Y-m-d 23:59:59', $from + $days * DAY_IN_SECONDS ) ) );
				break;
			case 'forever':
				SMSS_Accounts::update( $a->id, array( 'expires_at' => null ) );
				break;
			case 'reset_sig':
				SMSS_Accounts::reset_signature( $a->id );
				break;
			case 'reset_devices':
				SMSS_Accounts::update( $a->id, array( 'devices' => '[]' ) );
				break;
			case 'new_password':
				$extra['password'] = wp_generate_password( 10, false );
				SMSS_Accounts::update( $a->id, array( 'password' => $extra['password'] ) );
				break;
			case 'new_key':
				SMSS_Accounts::update( $a->id, array( 'access_key' => SMSS_Accounts::new_key() ) );
				break;
			case 'update':
				$data = array();
				if ( null !== $req->get_param( 'note' ) ) {
					$data['note'] = sanitize_text_field( (string) $req->get_param( 'note' ) );
				}
				if ( null !== $req->get_param( 'max_devices' ) ) {
					$data['max_devices'] = max( 0, (int) $req->get_param( 'max_devices' ) );
				}
				if ( $data ) {
					SMSS_Accounts::update( $a->id, $data );
				}
				break;
			default:
				return self::err( 'Thao tác không hợp lệ.' );
		}
		$res = self::admin_account( $req );
		return is_array( $res ) ? array_merge( $res, $extra ) : $res;
	}

	public static function admin_account_delete( WP_REST_Request $req ) {
		SMSS_Accounts::delete( (int) $req['id'] );
		return array( 'deleted' => true );
	}

	public static function admin_points( WP_REST_Request $req ) {
		$a = SMSS_Accounts::get( (int) $req['id'] );
		if ( ! $a ) {
			return self::err( 'Không tìm thấy tài khoản.', 404 );
		}
		$res = SMSS_Points::topup( $a->id, (int) $req->get_param( 'amount' ), sanitize_text_field( (string) $req->get_param( 'note' ) ) );
		if ( is_wp_error( $res ) ) {
			return self::err( $res->get_error_message() );
		}
		return self::admin_account( $req );
	}

	/** Give items without spending points (gift, or sold outside the shop). */
	public static function admin_grant( WP_REST_Request $req ) {
		$a = SMSS_Accounts::get( (int) $req['id'] );
		if ( ! $a ) {
			return self::err( 'Không tìm thấy tài khoản.', 404 );
		}
		SMSS_Accounts::grant( $a->id, (array) $req->get_param( 'item_ids' ) );
		return self::admin_account( $req );
	}

	public static function admin_revoke( WP_REST_Request $req ) {
		SMSS_Points::revoke( (int) $req['id'], (int) $req->get_param( 'item_id' ), (bool) $req->get_param( 'refund' ) );
		return self::admin_account( $req );
	}

	public static function admin_items( WP_REST_Request $req ) {
		$s     = trim( (string) $req->get_param( 's' ) );
		$page  = max( 1, (int) $req->get_param( 'page' ) );
		$total = SMSS_Library::count( $s );
		return array(
			'total' => $total,
			'page'  => $page,
			'pages' => max( 1, (int) ceil( $total / 50 ) ),
			'items' => array_map( function ( $i ) {
				return self::item_out( $i );
			}, SMSS_Library::query( $s, $page, 50 ) ),
		);
	}

	public static function admin_items_add( WP_REST_Request $req ) {
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		$report = SMSS_Library::add_lines( (string) $req->get_param( 'lines' ) );
		$price  = (int) $req->get_param( 'price' );
		if ( $price > 0 && ( $report['added'] || $report['updated'] ) ) {
			// Price the rows that were just added (still at 0).
			global $wpdb;
			$wpdb->query( $wpdb->prepare( 'UPDATE ' . SMSS_DB::items_table() . ' SET price = %d WHERE price = 0 AND is_free = 0 AND created_at >= %s', $price, gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 600 ) ) );
		}
		return $report;
	}

	public static function admin_item_update( WP_REST_Request $req ) {
		global $wpdb;
		$item = SMSS_Library::get( (int) $req['id'] );
		if ( ! $item ) {
			return self::err( 'Không tìm thấy bản này.', 404 );
		}
		$data = array();
		if ( null !== $req->get_param( 'price' ) ) {
			$data['price'] = max( 0, (int) $req->get_param( 'price' ) );
		}
		if ( null !== $req->get_param( 'is_free' ) ) {
			$data['is_free'] = $req->get_param( 'is_free' ) ? 1 : 0;
		}
		if ( null !== $req->get_param( 'enabled' ) ) {
			$data['enabled'] = $req->get_param( 'enabled' ) ? 1 : 0;
		}
		if ( null !== $req->get_param( 'display' ) ) {
			$data['display_name'] = mb_substr( sanitize_text_field( (string) $req->get_param( 'display' ) ), 0, 200 );
		}
		if ( null !== $req->get_param( 'name' ) ) {
			$name = SMSS_Links::clean_name( (string) $req->get_param( 'name' ) );
			if ( '' !== $name ) {
				$data['name']     = $name;
				$data['title_id'] = SMSS_Links::title_id( $name );
			}
		}
		if ( $data ) {
			$data['updated_at'] = current_time( 'mysql' );
			$wpdb->update( SMSS_DB::items_table(), $data, array( 'id' => $item->id ) );
		}
		return self::item_out( SMSS_Library::get( $item->id ) );
	}

	public static function admin_import( WP_REST_Request $req ) {
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		$res = SMSS_Library::import( (string) $req->get_param( 'text' ), (int) $req->get_param( 'price' ) );
		return is_wp_error( $res ) ? self::err( $res->get_error_message() ) : $res;
	}

	public static function admin_export() {
		return array( 'items' => SMSS_Library::export() );
	}

	/** Look every Title ID up again (names and cover links). */
	public static function admin_titles() {
		return array( 'named' => SMSS_Titles::enrich( true ) );
	}

	public static function admin_folders_sync() {
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		SMSS_Library::sync_all_folders();
		return array( 'folders' => array_values( (array) SMSS_Settings::get( 'folders' ) ) );
	}

	/* ------------------------------------------------------------------ */
	/* Customer portal                                                     */
	/* ------------------------------------------------------------------ */

	/**
	 * The portal cookie is SameSite=Lax and every state-changing call must carry
	 * the X-SMSS header, which a cross-site form cannot add.
	 */
	public static function portal_csrf( WP_REST_Request $req ) {
		return '1' === $req->get_header( 'x_smss' );
	}

	private static function session_key( $token ) {
		return 'smss_ps_' . hash( 'sha256', $token );
	}

	public static function portal_account() {
		$token = isset( $_COOKIE[ self::PORTAL_COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::PORTAL_COOKIE ] ) ) : '';
		if ( ! preg_match( '/^[A-Za-z0-9]{40}$/', $token ) ) {
			return null;
		}
		$id = (int) get_transient( self::session_key( $token ) );
		if ( ! $id ) {
			return null;
		}
		$a = SMSS_Accounts::get( $id );
		return ( $a && (int) $a->enabled ) ? $a : null;
	}

	public static function start_session( $account ) {
		$token = wp_generate_password( 40, false, false );
		set_transient( self::session_key( $token ), (int) $account->id, self::PORTAL_TTL );
		setcookie( self::PORTAL_COOKIE, $token, array(
			'expires'  => time() + self::PORTAL_TTL,
			'path'     => '/',
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax',
		) );
		$_COOKIE[ self::PORTAL_COOKIE ] = $token;
	}

	public static function portal_login( WP_REST_Request $req ) {
		$fail_key = 'smss_pfail_' . md5( SMSS_Endpoint::client_ip() );
		$fails    = (int) get_transient( $fail_key );
		if ( $fails >= 10 ) {
			return self::err( 'Sai quá nhiều lần, thử lại sau 15 phút.', 429 );
		}
		$login = (string) $req->get_param( 'username' );
		$code  = SMSS_Accounts::normalize_code( $login );
		$a     = $code ? SMSS_Accounts::get_by_username( $code ) : null;
		if ( $a && SMSS_Accounts::is_device( $a ) ) {
			// Device accounts: the code (shown to the shop) plus the PIN that only
			// appears inside Tinfoil on that console.
			if ( ! SMSS_Accounts::pin_ok( $a, (string) $req->get_param( 'password' ) ) ) {
				set_transient( $fail_key, $fails + 1, 900 );
				return self::err( 'Sai mã PIN. Mở Tinfoil trên máy của bạn để xem PIN web.', 401 );
			}
		} else {
			$a = SMSS_Accounts::get_by_username( $login );
			if ( $a && SMSS_Accounts::is_device( $a ) ) {
				$a = null;
			}
		}
		if ( ! $a || ( ! SMSS_Accounts::is_device( $a ) && ! SMSS_Endpoint::password_ok( (string) $req->get_param( 'password' ), $a->pass_hash ) ) ) {
			set_transient( $fail_key, $fails + 1, 900 );
			return self::err( 'Sai tên đăng nhập hoặc mật khẩu.', 401 );
		}
		if ( ! (int) $a->enabled ) {
			return self::err( 'Tài khoản đã bị khoá.', 403 );
		}
		self::start_session( $a );
		return array( 'ok' => true );
	}

	public static function portal_logout() {
		$token = isset( $_COOKIE[ self::PORTAL_COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::PORTAL_COOKIE ] ) ) : '';
		if ( $token ) {
			delete_transient( self::session_key( $token ) );
		}
		setcookie( self::PORTAL_COOKIE, '', array( 'expires' => time() - 3600, 'path' => '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ) );
		return array( 'ok' => true );
	}

	public static function portal_me() {
		global $wpdb;
		$a = self::portal_account();
		if ( ! $a ) {
			return self::err( 'Chưa đăng nhập.', 401 );
		}
		$owned = array_flip( array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT item_id FROM ' . SMSS_DB::grants_table() . ' WHERE account_id = %d', $a->id ) ) ) );
		$rows  = $wpdb->get_results( 'SELECT * FROM ' . SMSS_DB::items_table() . ' WHERE enabled = 1 ORDER BY name ASC' );
		return array(
			'account' => array(
				'username'   => SMSS_Accounts::is_device( $a ) ? SMSS_Accounts::format_code( $a->username ) : $a->username,
				'kind'       => SMSS_Accounts::is_device( $a ) ? 'device' : 'user',
				'points'     => (int) $a->points,
				'expires_at' => $a->expires_at,
				'expired'    => SMSS_Accounts::is_expired( $a ),
				'all_access' => (bool) (int) $a->all_access,
			),
			'items'   => array_map( function ( $i ) use ( $owned, $a ) {
				return self::item_out( $i, (bool) (int) $a->all_access || (bool) (int) $i->is_free || isset( $owned[ (int) $i->id ] ) );
			}, $rows ),
			'history' => array_map( function ( $l ) {
				return array( 'delta' => (int) $l->delta, 'balance' => (int) $l->balance, 'type' => $l->type, 'note' => $l->note, 'at' => $l->created_at );
			}, SMSS_Points::history( $a->id, 30 ) ),
			'tinfoil' => self::tinfoil_info( $a ),
			'contact' => SMSS_Settings::get( 'contact' ),
			'point'   => SMSS_Settings::get( 'point_name' ),
			'shop'    => SMSS_Settings::get( 'shop_name' ),
		);
	}

	public static function portal_unlock( WP_REST_Request $req ) {
		$a = self::portal_account();
		if ( ! $a ) {
			return self::err( 'Chưa đăng nhập.', 401 );
		}
		if ( SMSS_Accounts::is_expired( $a ) ) {
			return self::err( 'Tài khoản đã hết hạn, liên hệ ' . SMSS_Settings::get( 'contact' ) . ' để gia hạn.', 403 );
		}
		$res = SMSS_Points::unlock( $a->id, (int) $req->get_param( 'item_id' ) );
		if ( is_wp_error( $res ) ) {
			return self::err( $res->get_error_message() );
		}
		return $res;
	}
}

/** Shared capability constant (the admin screens may not be loaded on REST requests). */
class SMSS_Admin_Capability {
	const CAP = 'manage_options';
}
