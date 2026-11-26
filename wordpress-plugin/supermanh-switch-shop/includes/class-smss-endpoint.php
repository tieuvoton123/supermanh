<?php
defined( 'ABSPATH' ) || exit;

/**
 * Serves the Tinfoil index at /<slug> (default /tinfoil) and /<slug>/<key>.
 */
class SMSS_Endpoint {

	/** Failed logins allowed per IP per window before answering 429. */
	const MAX_FAILS   = 15;
	const FAIL_WINDOW = 900;

	public static function init() {
		add_action( 'init', array( __CLASS__, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'parse_request', array( __CLASS__, 'maybe_serve' ), 0 );
		add_filter( 'robots_txt', array( __CLASS__, 'robots_txt' ), 99, 2 );
	}

	/** Search engines that honour robots.txt never request the shop at all. */
	public static function robots_txt( $output, $public ) {
		$slug = SMSS_Settings::get( 'slug' );
		if ( false === strpos( $output, 'User-agent: *' ) ) {
			$output .= "\nUser-agent: *\n";
		}
		return preg_replace( '/User-agent: \*\n/', "User-agent: *\nDisallow: /{$slug}\n", $output, 1 );
	}

	public static function add_rewrite_rules() {
		$slug = preg_quote( SMSS_Settings::get( 'slug' ), '#' );
		add_rewrite_rule( '^' . $slug . '/?$', 'index.php?smss_shop=1', 'top' );
		add_rewrite_rule( '^' . $slug . '/([a-z0-9]{24})/?$', 'index.php?smss_shop=1&smss_key=$matches[1]', 'top' );
	}

	public static function query_vars( $vars ) {
		$vars[] = 'smss_shop';
		$vars[] = 'smss_key';
		return $vars;
	}

	public static function maybe_serve( $wp ) {
		if ( empty( $wp->query_vars['smss_shop'] ) ) {
			return;
		}
		$key = isset( $wp->query_vars['smss_key'] ) ? (string) $wp->query_vars['smss_key'] : '';

		if ( SMSS_Settings::get( 'stealth' ) && self::should_hide( $key ) ) {
			// Hand the request back to WordPress as a normal "page not found":
			// same theme 404 page, same status, nothing hints that a shop exists.
			$wp->query_vars = array( 'error' => '404' );
			return;
		}
		self::serve( $key );
	}

	/**
	 * Stealth mode: who gets the site's 404 page instead of the shop.
	 * Allowed through: a valid personal link, any request carrying Basic Auth
	 * credentials (checked afterwards), and Tinfoil itself. Everyone else -
	 * search engine bots, link previewers, scanners, a curious browser - sees
	 * the theme's ordinary "page not found".
	 */
	private static function should_hide( $key ) {
		if ( '' !== $key ) {
			return null === SMSS_Accounts::get_by_key( $key );
		}
		list( $user ) = self::basic_credentials();
		if ( '' !== $user ) {
			return false;
		}
		return ! self::is_tinfoil();
	}

	/**
	 * Visitor IP. Behind Cloudflare or a reverse proxy REMOTE_ADDR is the proxy,
	 * which would make every customer share one login-attempt counter.
	 */
	public static function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		if ( SMSS_Settings::get( 'trust_proxy' ) ) {
			foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR' ) as $h ) {
				if ( ! empty( $_SERVER[ $h ] ) ) {
					$first = trim( explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $h ] ) ) )[0] );
					if ( filter_var( $first, FILTER_VALIDATE_IP ) ) {
						return $first;
					}
				}
			}
		}
		return $ip;
	}

	/**
	 * Host lock: remember the Hauth Tinfoil sends for this host on the first
	 * logged-in request, then require the same value.
	 *
	 * @return string '' when fine, otherwise the reason.
	 */
	private static function check_hauth() {
		if ( ! SMSS_Settings::get( 'host_lock' ) || ! self::is_tinfoil() ) {
			return '';
		}
		$hauth = self::header( 'Hauth' );
		$host  = strtolower( isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '' );
		// "0" = client could not compute it (e.g. the SUPER MANH CyberFoil build).
		if ( '' === $hauth || '0' === $hauth || '' === $host ) {
			return '';
		}
		$known = (array) SMSS_Settings::get( 'hauth' );
		if ( empty( $known[ $host ] ) ) {
			$known[ $host ] = substr( $hauth, 0, 128 );
			SMSS_Settings::update( array( 'hauth' => $known ) );
			return '';
		}
		return hash_equals( (string) $known[ $host ], $hauth ) ? '' : 'Shop chi mo tu dia chi chinh thuc.';
	}

	private static function header( $name ) {
		$key = 'HTTP_' . strtoupper( str_replace( '-', '_', $name ) );
		return isset( $_SERVER[ $key ] ) ? trim( (string) wp_unslash( $_SERVER[ $key ] ) ) : '';
	}

	/** Basic Auth credentials, including the copies CGI/FastCGI setups leave behind. */
	public static function basic_credentials() {
		if ( isset( $_SERVER['PHP_AUTH_USER'] ) ) {
			return array( (string) wp_unslash( $_SERVER['PHP_AUTH_USER'] ), (string) wp_unslash( $_SERVER['PHP_AUTH_PW'] ?? '' ) );
		}
		foreach ( array( 'HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION' ) as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) && 0 === stripos( $_SERVER[ $key ], 'basic ' ) ) {
				$decoded = base64_decode( substr( $_SERVER[ $key ], 6 ), true );
				if ( false !== $decoded && false !== strpos( $decoded, ':' ) ) {
					return explode( ':', $decoded, 2 );
				}
			}
		}
		return array( '', '' );
	}

	/**
	 * Password hashing is deliberately slow, and Tinfoil sends the password with
	 * every request. A correct password is remembered for 10 minutes under a key
	 * derived from it and from the stored hash (so a password change drops it).
	 */
	public static function password_ok( $pass, $hash ) {
		$key = 'smss_pw_' . substr( hash_hmac( 'sha256', $pass . '|' . $hash, wp_salt( 'auth' ) ), 0, 40 );
		if ( get_transient( $key ) ) {
			return true;
		}
		if ( ! wp_check_password( $pass, $hash ) ) {
			return false;
		}
		set_transient( $key, 1, 10 * MINUTE_IN_SECONDS );
		return true;
	}

	private static function is_tinfoil() {
		return '' !== self::header( 'Uid' ) && '' !== self::header( 'Version' );
	}

	private static function fail_key() {
		return 'smss_fail_' . md5( self::client_ip() );
	}

	private static function send( $status, $body, $auth_challenge = false ) {
		nocache_headers();
		status_header( $status );
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'X-Robots-Tag: noindex, nofollow, noarchive, nosnippet' );
		header( 'Referrer-Policy: no-referrer' );
		if ( $auth_challenge ) {
			header( 'WWW-Authenticate: Basic realm="SUPER MANH Shop", charset="UTF-8"' );
		}
		$json = wp_json_encode( $body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( 200 === $status ) {
			// Unchanged list: answer 304 with no body.
			$etag = '"' . md5( $json ) . '"';
			header( 'ETag: ' . $etag );
			if ( isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) && trim( (string) wp_unslash( $_SERVER['HTTP_IF_NONE_MATCH'] ) ) === $etag ) {
				status_header( 304 );
				exit;
			}
		}
		echo $json; // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	/**
	 * Tinfoil without a login: identify the console by its Uid, show its
	 * activation code, and serve what that console is entitled to.
	 */
	private static function serve_device() {
		$uid = self::header( 'Uid' );
		if ( ! SMSS_Accounts::valid_uid( $uid ) ) {
			self::send( 200, array( 'files' => array(), 'success' => SMSS_Settings::get( 'motd' ) . "\nKhong doc duoc ma may cua Switch nay. Lien he " . SMSS_Settings::get( 'contact' ) . ' de duoc cap tai khoan.' ) );
		}
		$reason = self::check_hauth();
		if ( '' !== $reason ) {
			self::send( 403, array( 'error' => $reason ) );
		}
		$account = SMSS_Accounts::device_account( $uid, self::client_ip() );
		if ( is_wp_error( $account ) ) {
			self::send( 429, array( 'error' => $account->get_error_message() ) );
		}
		// Note: Uauth is derived from the shop URL (+ login), identical on every
		// console, so it cannot identify a device. The console's identity is the
		// Uid (hash of its eMMC CID), which is never shown to anyone: the code is
		// an HMAC of it with a server secret.
		$ver = substr( preg_replace( '/[^0-9A-Za-z.\-]/', '', self::header( 'Version' ) ), 0, 20 );
		if ( $ver && $ver !== $account->client_ver ) {
			SMSS_Accounts::update( $account->id, array( 'client_ver' => $ver ) );
		}
		SMSS_Accounts::touch( $account, self::client_ip() );

		$s       = SMSS_Settings::all();
		$pinline = ' | PIN web: ' . SMSS_Accounts::current_pin( $account );
		$code    = 'Ma kich hoat: ' . SMSS_Accounts::format_code( $account->username );
		$portal  = preg_replace( '~^https?://~', '', SMSS_App::portal_url() );
		$expired = SMSS_Accounts::is_expired( $account );

		if ( ! (int) $account->enabled ) {
			$index = array( 'files' => array(), 'success' => $s['motd'] . "\n" . $code . "\nMay nay da bi khoa. Lien he " . $s['contact'] . '.' );
		} elseif ( $expired ) {
			$index = array( 'files' => array(), 'success' => $s['motd'] . "\n" . $code . "\nDa het han " . mysql2date( 'd/m/Y', $account->expires_at ) . '. Lien he ' . $s['contact'] . ' de gia han.' );
		} else {
			$index = SMSS_Library::build_index( $account );
			if ( SMSS_Accounts::is_activated( $account ) ) {
				$line = $code . ' | Diem: ' . number_format( (int) $account->points, 0, ',', '.' );
				if ( $account->expires_at ) {
					$line .= ' | Han: ' . mysql2date( 'd/m/Y', $account->expires_at );
				}
				$index['success'] = $s['motd'] . "\n" . $line . "\nMo khoa them: " . $portal . $pinline;
			} else {
				$index['success'] = $s['motd'] . "\n" . $code . "\nGui ma nay cho " . $s['contact'] . ' de kich hoat, roi tat han Tinfoil va mo lai.';
			}
		}
		self::send( 200, $index );
	}

	public static function serve( $key = '' ) {
		$settings = SMSS_Settings::all();

		if ( ! $settings['enabled'] ) {
			self::send( 503, array( 'error' => 'Shop đang tạm đóng.' ) );
		}
		if ( $settings['tinfoil_only'] && ! self::is_tinfoil() ) {
			self::send( 403, array( 'error' => 'Chỉ mở cho Tinfoil.' ) );
		}

		if ( $settings['require_auth'] ) {
			$fails = (int) get_transient( self::fail_key() );
			if ( $fails >= self::MAX_FAILS ) {
				self::send( 429, array( 'error' => 'Sai mật khẩu quá nhiều lần, thử lại sau 15 phút.' ) );
			}

			$account = null;
			if ( '' !== $key ) {
				$account = SMSS_Accounts::get_by_key( $key );
			} else {
				list( $user, $pass ) = self::basic_credentials();
				if ( '' !== $user ) {
					$candidate = SMSS_Accounts::get_by_username( $user );
					if ( $candidate && self::password_ok( $pass, $candidate->pass_hash ) ) {
						$account = $candidate;
					}
				}
			}

			if ( ! $account && '' === $key && SMSS_Settings::get( 'device_mode' ) && self::is_tinfoil() ) {
				list( $user ) = self::basic_credentials();
				if ( '' === $user ) {
					self::serve_device();
				}
			}

			if ( ! $account ) {
				set_transient( self::fail_key(), $fails + 1, self::FAIL_WINDOW );
				self::send( 401, array( 'error' => 'Sai tên đăng nhập hoặc mật khẩu.' ), true );
			}

			$uid    = self::header( 'Uid' );
			$reason = SMSS_Accounts::check_access( $account, '' === $uid ? '' : substr( hash( 'sha256', $uid ), 0, 32 ) );
			if ( '' !== $reason ) {
				self::send( 403, array( 'error' => $reason ) );
			}
			$reason = self::check_hauth();
			if ( '' !== $reason ) {
				self::send( 403, array( 'error' => $reason ) );
			}
			SMSS_Accounts::touch( $account, self::client_ip() );
			self::send( 200, SMSS_Library::build_index( $account ) );
		}

		self::send( 200, SMSS_Library::build_index( null ) );
	}
}
