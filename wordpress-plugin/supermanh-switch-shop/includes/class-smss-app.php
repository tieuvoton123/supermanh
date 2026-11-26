<?php
defined( 'ABSPATH' ) || exit;

/**
 * Two installable mobile web apps (PWA):
 *  - /sm-quanly : management app for the site admin (WordPress login).
 *  - /sm-khach  : customer page - balance, unlock patches with points, Tinfoil setup.
 */
class SMSS_App {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'parse_request', array( __CLASS__, 'maybe_serve' ), 1 );
		add_filter( 'robots_txt', array( __CLASS__, 'robots_txt' ), 100 );
	}

	public static function add_rewrite_rules() {
		foreach ( array( 'admin' => SMSS_Settings::get( 'app_slug' ), 'portal' => SMSS_Settings::get( 'portal_slug' ) ) as $kind => $slug ) {
			$slug = preg_quote( $slug, '#' );
			add_rewrite_rule( '^' . $slug . '/?$', 'index.php?smss_app=' . $kind, 'top' );
			add_rewrite_rule( '^' . $slug . '/manifest\.json$', 'index.php?smss_app=' . $kind . '&smss_asset=manifest', 'top' );
		}
	}

	public static function query_vars( $vars ) {
		$vars[] = 'smss_app';
		$vars[] = 'smss_asset';
		return $vars;
	}

	public static function robots_txt( $output ) {
		foreach ( array( SMSS_Settings::get( 'app_slug' ), SMSS_Settings::get( 'portal_slug' ) ) as $slug ) {
			$output = preg_replace( '/User-agent: \*\n/', "User-agent: *\nDisallow: /{$slug}\n", $output, 1 );
		}
		return $output;
	}

	private static function url_for( $kind, $args = array() ) {
		$slug = SMSS_Settings::get( 'admin' === $kind ? 'app_slug' : 'portal_slug' );
		$host = 'portal' === $kind ? SMSS_Settings::get( 'shop_host' ) : '';
		if ( $host ) {
			$url = 'https://' . $host . '/' . $slug;
		} elseif ( get_option( 'permalink_structure' ) ) {
			$url = home_url( '/' . $slug );
		} else {
			$url  = home_url( '/' );
			$args = array_merge( array( 'smss_app' => $kind ), $args );
		}
		return $args ? add_query_arg( $args, $url ) : $url;
	}

	public static function admin_url() {
		return self::url_for( 'admin' );
	}

	/** Customer page; with a key it logs the customer in on first open. */
	public static function portal_url( $key = '' ) {
		return self::url_for( 'portal', $key ? array( 'k' => $key ) : array() );
	}

	public static function maybe_serve( $wp ) {
		if ( empty( $wp->query_vars['smss_app'] ) ) {
			return;
		}
		$kind = 'admin' === $wp->query_vars['smss_app'] ? 'admin' : 'portal';

		if ( 'manifest' === ( $wp->query_vars['smss_asset'] ?? '' ) ) {
			self::manifest( $kind );
		}

		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow, noarchive' );
		header( 'Referrer-Policy: no-referrer' );
		header( 'X-Frame-Options: DENY' );

		if ( 'admin' === $kind ) {
			if ( ! is_user_logged_in() ) {
				wp_safe_redirect( wp_login_url( self::admin_url() ) );
				exit;
			}
			if ( ! current_user_can( SMSS_Admin_Capability::CAP ) ) {
				status_header( 403 );
				exit( 'Không có quyền.' );
			}
		} else {
			// Personal link: /sm-khach?k=<key> logs in, then drops the key from the address bar.
			$key = isset( $_GET['k'] ) ? sanitize_text_field( wp_unslash( $_GET['k'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
			if ( '' !== $key ) {
				$account = SMSS_Accounts::get_by_key( $key );
				if ( $account && (int) $account->enabled ) {
					SMSS_API::start_session( $account );
				}
				wp_safe_redirect( self::portal_url() );
				exit;
			}
		}
		self::render( $kind );
	}

	private static function manifest( $kind ) {
		$admin = 'admin' === $kind;
		header( 'Content-Type: application/manifest+json; charset=utf-8' );
		echo wp_json_encode( array(
			'name'             => $admin ? 'SM Quản lý' : SMSS_Settings::get( 'shop_name' ),
			'short_name'       => $admin ? 'SM Quản lý' : 'SM Shop',
			'start_url'        => $admin ? self::admin_url() : self::portal_url(),
			'scope'            => $admin ? self::admin_url() : self::portal_url(),
			'display'          => 'standalone',
			'background_color' => '#0f1115',
			'theme_color'      => '#ff4b1f',
			'icons'            => array(
				array( 'src' => SMSS_URL . 'assets/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable' ),
				array( 'src' => SMSS_URL . 'assets/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable' ),
			),
		), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		exit;
	}

	private static function render( $kind ) {
		$admin  = 'admin' === $kind;
		$config = array(
			'api'     => esc_url_raw( rest_url( SMSS_API::NS ) ),
			'nonce'   => $admin ? wp_create_nonce( 'wp_rest' ) : '',
			'wpAdmin' => $admin ? admin_url( 'admin.php?page=smss' ) : '',
			'logout'  => $admin ? wp_logout_url( self::admin_url() ) : '',
			'site'    => home_url( '/' ),
		);
		$title = $admin ? 'SM Quản lý' : SMSS_Settings::get( 'shop_name' );
		$base  = trailingslashit( $admin ? self::admin_url() : self::portal_url() );
		header( 'Content-Type: text/html; charset=utf-8' );
		?><!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#14161b">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="<?php echo esc_attr( $title ); ?>">
<title><?php echo esc_html( $title ); ?></title>
<link rel="manifest" href="<?php echo esc_url( $base . 'manifest.json' ); ?>">
<link rel="icon" href="<?php echo esc_url( SMSS_URL . 'assets/icon-192.png' ); ?>">
<link rel="apple-touch-icon" href="<?php echo esc_url( SMSS_URL . 'assets/icon-192.png' ); ?>">
<link rel="stylesheet" href="<?php echo esc_url( SMSS_URL . 'assets/app.css?v=' . SMSS_VERSION ); ?>">
</head>
<body class="<?php echo $admin ? 'is-admin' : 'is-portal'; ?>">
<div id="app"><div class="boot"><div class="logo"></div></div></div>
<div id="toast" role="status" aria-live="polite"></div>
<script>window.SMSS = <?php echo wp_json_encode( $config, JSON_UNESCAPED_SLASHES ); ?>;</script>
<script src="<?php echo esc_url( SMSS_URL . 'assets/app-common.js?v=' . SMSS_VERSION ); ?>"></script>
<script src="<?php echo esc_url( SMSS_URL . 'assets/' . ( $admin ? 'app-admin.js' : 'app-portal.js' ) . '?v=' . SMSS_VERSION ); ?>"></script>
</body>
</html>
		<?php
		exit;
	}
}
