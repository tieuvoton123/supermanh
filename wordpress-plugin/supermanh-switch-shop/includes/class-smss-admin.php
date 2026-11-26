<?php
defined( 'ABSPATH' ) || exit;

/**
 * Admin screens (Vietnamese UI).
 */
class SMSS_Admin {

	const CAP = 'manage_options';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		foreach ( array( 'save_settings', 'add_links', 'item_action', 'folder_add', 'folder_action', 'account_add', 'account_action' ) as $action ) {
			add_action( 'admin_post_smss_' . $action, array( __CLASS__, 'handle_' . $action ) );
		}
	}

	public static function menu() {
		$icon = 'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="#a7aaad" d="M11.7 1 4 11.3h4.7L7.2 19 16 7.8h-4.8z"/></svg>' );
		add_menu_page( 'SuperManh Switch Việt Hóa', 'Switch Việt Hóa', self::CAP, 'smss', array( __CLASS__, 'page_dashboard' ), $icon, 56 );
		add_submenu_page( 'smss', 'Tổng quan & cài đặt', 'Tổng quan & cài đặt', self::CAP, 'smss', array( __CLASS__, 'page_dashboard' ) );
		add_submenu_page( 'smss', 'Danh sách game', 'Danh sách game', self::CAP, 'smss-items', array( __CLASS__, 'page_items' ) );
		add_submenu_page( 'smss', 'Thư mục Google Drive', 'Thư mục Drive', self::CAP, 'smss-folders', array( __CLASS__, 'page_folders' ) );
		add_submenu_page( 'smss', 'Tài khoản thành viên', 'Tài khoản', self::CAP, 'smss-accounts', array( __CLASS__, 'page_accounts' ) );
	}

	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'smss' ) ) {
			return;
		}
		wp_enqueue_style( 'smss-admin', SMSS_URL . 'assets/admin.css', array(), SMSS_VERSION );
	}

	/* ------------------------------------------------------------------ */
	/* Helpers                                                             */
	/* ------------------------------------------------------------------ */

	private static function guard( $action ) {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'Không có quyền.' );
		}
		check_admin_referer( 'smss_' . $action );
	}

	private static function notice( $type, $message ) {
		$key   = 'smss_notice_' . get_current_user_id();
		$queue = (array) get_transient( $key );
		$queue[] = array( $type, $message );
		set_transient( $key, $queue, 120 );
	}

	private static function show_notices() {
		$key   = 'smss_notice_' . get_current_user_id();
		$queue = get_transient( $key );
		if ( ! $queue ) {
			return;
		}
		delete_transient( $key );
		foreach ( (array) $queue as $n ) {
			if ( ! is_array( $n ) ) {
				continue;
			}
			printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $n[0] ), wp_kses_post( $n[1] ) );
		}
	}

	private static function back( $page, array $args = array() ) {
		wp_safe_redirect( add_query_arg( array_merge( array( 'page' => $page ), $args ), admin_url( 'admin.php' ) ) );
		exit;
	}

	private static function post( $key, $default = '' ) {
		return isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : $default; // phpcs:ignore WordPress.Security
	}

	private static function header_bar( $title ) {
		?>
		<div class="smss-hero">
			<div class="smss-logo" aria-hidden="true">
				<svg viewBox="0 0 1024 1024"><path d="M600 104 L270 584 H488 L418 920 L756 412 H540 L600 104 Z" fill="#fff"/></svg>
			</div>
			<div>
				<div class="smss-brand">SUPER MẠNH <span>Switch Việt Hóa</span></div>
				<div class="smss-sub"><?php echo esc_html( $title ); ?></div>
			</div>
			<a class="smss-site" href="https://supermanh.com" target="_blank" rel="noopener">supermanh.com</a>
		</div>
		<?php
		self::show_notices();
	}

	private static function report_message( array $r ) {
		$parts = array();
		if ( ! empty( $r['folders'] ) ) {
			$parts[] = $r['folders'] . ' thư mục';
		}
		$parts[] = 'thêm mới ' . (int) $r['added'];
		$parts[] = 'cập nhật ' . (int) $r['updated'];
		if ( isset( $r['removed'] ) ) {
			$parts[] = 'gỡ ' . (int) $r['removed'];
		}
		return implode( ', ', $parts ) . ' game.';
	}

	/* ------------------------------------------------------------------ */
	/* Dashboard + settings                                                */
	/* ------------------------------------------------------------------ */

	public static function page_dashboard() {
		$s        = SMSS_Settings::all();
		$url      = SMSS_Settings::shop_url();
		$parts    = wp_parse_url( $url );
		$host     = $parts['host'] ?? '';
		$path     = ( $parts['path'] ?? '/' ) . ( isset( $parts['query'] ) ? '?' . $parts['query'] : '' );
		$protocol = $parts['scheme'] ?? 'https';
		$port     = isset( $parts['port'] ) ? $parts['port'] : ( 'https' === $protocol ? 443 : 80 );
		$items    = SMSS_Library::count( '', true );
		$accounts = count( SMSS_Accounts::all() );
		?>
		<div class="wrap smss-wrap">
			<?php self::header_bar( 'Tổng quan & cài đặt' ); ?>

			<div class="smss-stats">
				<div class="smss-stat"><b><?php echo esc_html( number_format_i18n( $items ) ); ?></b><span>bản đang hiện</span></div>
				<div class="smss-stat"><b><?php echo esc_html( SMSS_Links::human_size( SMSS_Library::total_size() ) ); ?></b><span>tổng dung lượng</span></div>
				<div class="smss-stat"><b><?php echo esc_html( count( (array) $s['folders'] ) ); ?></b><span>thư mục Drive</span></div>
				<div class="smss-stat"><b><?php echo esc_html( $accounts ); ?></b><span>tài khoản</span></div>
			</div>

			<div class="smss-grid">
				<div class="smss-card">
					<h2>Thêm kho vào Tinfoil</h2>
					<p>Trên máy Switch mở <b>Tinfoil → File Browser → nhấn (-) New</b>, điền:</p>
					<table class="smss-kv">
						<tr><th>Protocol</th><td><code><?php echo esc_html( $protocol ); ?></code></td></tr>
						<tr><th>Host</th><td><code><?php echo esc_html( $host ); ?></code></td></tr>
						<tr><th>Port</th><td><code><?php echo esc_html( $port ); ?></code></td></tr>
						<tr><th>Path</th><td><code><?php echo esc_html( $path ); ?></code></td></tr>
						<tr><th>Username / Password</th><td><?php echo $s['require_auth'] && $s['device_mode'] ? '<i>để trống</i> (Tinfoil hiện mã kích hoạt), hoặc tài khoản tạo ở trang <a href="' . esc_url( admin_url( 'admin.php?page=smss-accounts' ) ) . '">Tài khoản</a>' : ( $s['require_auth'] ? 'tài khoản tạo ở trang <a href="' . esc_url( admin_url( 'admin.php?page=smss-accounts' ) ) . '">Tài khoản</a>' : '<i>để trống (kho công khai)</i>' ); ?></td></tr>
						<tr><th>Title</th><td><code><?php echo esc_html( $s['shop_name'] ); ?></code></td></tr>
					</table>
					<p class="description">Link kho: <code><?php echo esc_html( $url ); ?></code></p>
					<p><a class="button button-primary" href="<?php echo esc_url( SMSS_App::admin_url() ); ?>" target="_blank">Mở app quản lý (điện thoại)</a> <a class="button" href="<?php echo esc_url( SMSS_App::portal_url() ); ?>" target="_blank">Trang thành viên</a></p>
					<?php if ( ! $s['enabled'] ) : ?>
						<p class="smss-warn">Kho đang <b>tắt</b>, Tinfoil sẽ không tải được.</p>
					<?php endif; ?>
					<?php if ( ! get_option( 'permalink_structure' ) ) : ?>
						<p class="smss-warn">Website đang dùng đường dẫn mặc định (?p=123), nên link shop có dạng ?smss_shop=1. Vào <b>Cài đặt → Đường dẫn tĩnh</b> chọn "Tên bài viết" để link gọn hơn.</p>
					<?php endif; ?>
					<?php if ( 'gdrive' === $s['drive_mode'] && '' === trim( $s['api_key'] ) ) : ?>
						<p class="smss-warn">Chưa có Google API key: không quét được thư mục Drive, và người chơi phải tự đăng nhập Google trong Tinfoil mới tải được file Drive.</p>
					<?php endif; ?>
				</div>

				<div class="smss-card">
					<h2>Cài đặt</h2>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'smss_save_settings' ); ?>
						<input type="hidden" name="action" value="smss_save_settings">
						<table class="form-table smss-form">
							<tr><th>Bật kho</th><td><label><input type="checkbox" name="enabled" value="1" <?php checked( $s['enabled'] ); ?>> Cho Tinfoil tải danh sách game</label></td></tr>
							<tr><th><label for="smss-name">Tên kho</label></th><td><input id="smss-name" class="regular-text" name="shop_name" value="<?php echo esc_attr( $s['shop_name'] ); ?>"></td></tr>
							<tr><th><label for="smss-motd">Lời chào trên Tinfoil</label></th><td><input id="smss-motd" class="regular-text" name="motd" value="<?php echo esc_attr( $s['motd'] ); ?>"><p class="description">Hiện khi Tinfoil mở kho. Nên viết không dấu.</p></td></tr>
							<tr><th><label for="smss-host">Tên miền kho</label></th><td><input id="smss-host" class="regular-text" name="shop_host" value="<?php echo esc_attr( $s['shop_host'] ); ?>" placeholder="<?php echo esc_attr( wp_parse_url( home_url(), PHP_URL_HOST ) ); ?>"><p class="description">Để trống = dùng tên miền website. Muốn dùng tên riêng như <code>shop.supermanh.com</code> thì trỏ subdomain đó về <b>cùng hosting</b> này (thêm alias/addon domain trên hosting) và bật SSL cho nó.</p></td></tr>
							<tr><th><label for="smss-slug">Đường dẫn kho</label></th><td><code>/</code><input id="smss-slug" name="slug" value="<?php echo esc_attr( $s['slug'] ); ?>" style="width:140px"><p class="description">Nên đổi thành chuỗi khó đoán, ví dụ <code>sm-7k2q</code>, để người lạ không đoán ra.</p></td></tr>
							<tr><th><label for="smss-key">Google API key</label></th><td><input id="smss-key" class="regular-text" name="api_key" value="<?php echo esc_attr( $s['api_key'] ); ?>" autocomplete="off"><p class="description">Dùng để đọc tên và dung lượng file, quét thư mục Drive, và cho Tinfoil tải file Drive công khai. Tạo ở Google Cloud Console → APIs & Services → Credentials, rồi giới hạn key chỉ cho <b>Google Drive API</b>.</p></td></tr>
							<tr><th>Kiểu link Google Drive</th><td>
								<label><input type="radio" name="drive_mode" value="gdrive" <?php checked( $s['drive_mode'], 'gdrive' ); ?>> <b>gdrive:</b> (Tinfoil tải trực tiếp qua Google Drive API, khuyên dùng)</label><br>
								<label><input type="radio" name="drive_mode" value="https" <?php checked( $s['drive_mode'], 'https' ); ?>> <b>https</b> (link tải trực tiếp, dễ bị Google giới hạn lượt tải)</label>
							</td></tr>
							<tr><th>Bảo mật</th><td>
								<label><input type="checkbox" name="require_auth" value="1" <?php checked( $s['require_auth'] ); ?>> Bắt buộc đăng nhập (khuyên dùng)</label><br>
								<label><input type="checkbox" name="tinfoil_only" value="1" <?php checked( $s['tinfoil_only'] ); ?>> Chỉ cho phép Tinfoil (chặn trình duyệt, công cụ khác)</label><br>
								<label><input type="checkbox" name="device_mode" value="1" <?php checked( $s['device_mode'] ); ?>> <b>Kích hoạt theo máy</b>: thành viên thêm kho vào Tinfoil không cần tài khoản, Tinfoil hiện <b>mã kích hoạt</b> riêng của máy; bạn tìm mã đó trong app để cộng điểm / gia hạn</label><br>
								<label><input type="checkbox" name="stealth" value="1" <?php checked( $s['stealth'] ); ?>> <b>Ẩn kho</b>: Google, bot, trình duyệt chỉ thấy trang "Không tìm thấy" (404) bình thường của web; chỉ Tinfoil, người có tài khoản hoặc link riêng mới vào được</label>
							</td></tr>
							<tr><th>Nâng cao</th><td>
								<label><input type="checkbox" name="host_lock" value="1" <?php checked( $s['host_lock'] ); ?>> <b>Khoá tên miền</b>: Tinfoil chỉ nhận kho từ đúng địa chỉ này (cần HTTPS), người khác không gắn danh sách của bạn sang nơi khác được</label><br>
								<?php if ( ! empty( $s['hauth'] ) ) : ?>
									<label><input type="checkbox" name="hauth_reset" value="1"> Xoá mã Tinfoil đã ghi nhận (<?php echo esc_html( implode( ', ', array_keys( (array) $s['hauth'] ) ) ); ?>). Tick khi đổi tên miền, hoặc khi thành viên thật bị báo "Shop chi mo tu dia chi chinh thuc"</label><br>
								<?php endif; ?>
								<label><input type="checkbox" name="trust_proxy" value="1" <?php checked( $s['trust_proxy'] ); ?>> Website chạy sau <b>Cloudflare / proxy</b> (lấy IP thật của thành viên để chặn dò mật khẩu đúng người)</label>
							</td></tr>
							<tr><th><label for="smss-app">App quản lý</label></th><td><code>/</code><input id="smss-app" name="app_slug" value="<?php echo esc_attr( $s['app_slug'] ); ?>" style="width:140px"> <a href="<?php echo esc_url( SMSS_App::admin_url() ); ?>" target="_blank">Mở app ↗</a><p class="description">Mở trên điện thoại (đăng nhập WordPress), rồi "Thêm vào màn hình chính".</p></td></tr>
							<tr><th><label for="smss-portal">Trang thành viên</label></th><td><code>/</code><input id="smss-portal" name="portal_slug" value="<?php echo esc_attr( $s['portal_slug'] ); ?>" style="width:140px"> <a href="<?php echo esc_url( SMSS_App::portal_url() ); ?>" target="_blank">Xem ↗</a><p class="description">Thành viên đăng nhập để xem điểm và dùng điểm mở khoá bản Việt hóa.</p></td></tr>
							<tr><th><label for="smss-contact">Liên hệ hỗ trợ</label></th><td><input id="smss-contact" class="regular-text" name="contact" value="<?php echo esc_attr( $s['contact'] ); ?>" placeholder="Zalo 09xx… / fb.com/…"></td></tr>
							<tr><th><label for="smss-appurl">Link tải app Switch</label></th><td><input id="smss-appurl" class="regular-text" name="app_url" value="<?php echo esc_attr( $s['app_url'] ); ?>"><p class="description">Trang tải app <b><?php echo esc_html( $s['app_name'] ); ?></b> (NSP/NRO). Hiện ở trang thành viên và tin nhắn gửi thành viên.</p></td></tr>
							<tr><th><label for="smss-point">Tên đơn vị điểm</label></th><td><input id="smss-point" name="point_name" value="<?php echo esc_attr( $s['point_name'] ); ?>" style="width:140px"></td></tr>
							<tr><th>Loại file</th><td><label><input type="checkbox" name="only_nsw" value="1" <?php checked( $s['only_nsw'] ); ?>> Chỉ nhận nsp / nsz / xci / xcz</label></td></tr>
						</table>
						<?php submit_button( 'Lưu cài đặt' ); ?>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	public static function handle_save_settings() {
		self::guard( 'save_settings' );
		$old  = SMSS_Settings::all();
		$mode = self::post( 'drive_mode', 'gdrive' );
		$new  = SMSS_Settings::update( array(
			'enabled'      => self::post( 'enabled' ) ? 1 : 0,
			'shop_name'    => sanitize_text_field( self::post( 'shop_name' ) ),
			'motd'         => sanitize_text_field( self::post( 'motd' ) ),
			'slug'         => SMSS_Settings::sanitize_slug( self::post( 'slug', 'tinfoil' ) ),
			'api_key'      => preg_replace( '/[^A-Za-z0-9_-]/', '', self::post( 'api_key' ) ),
			'drive_mode'   => in_array( $mode, array( 'gdrive', 'https' ), true ) ? $mode : 'gdrive',
			'require_auth' => self::post( 'require_auth' ) ? 1 : 0,
			'tinfoil_only' => self::post( 'tinfoil_only' ) ? 1 : 0,
			'only_nsw'     => self::post( 'only_nsw' ) ? 1 : 0,
			'stealth'      => self::post( 'stealth' ) ? 1 : 0,
			'device_mode'  => self::post( 'device_mode' ) ? 1 : 0,
			'host_lock'    => self::post( 'host_lock' ) ? 1 : 0,
			'trust_proxy'  => self::post( 'trust_proxy' ) ? 1 : 0,
			'shop_host'    => SMSS_Settings::sanitize_host( self::post( 'shop_host' ) ),
			'app_slug'     => SMSS_Settings::sanitize_slug( self::post( 'app_slug', 'sm-quanly' ) ),
			'portal_slug'  => SMSS_Settings::sanitize_slug( self::post( 'portal_slug', 'sm-khach' ) ),
			'contact'      => sanitize_text_field( self::post( 'contact' ) ),
			'app_url'      => esc_url_raw( (string) self::post( 'app_url' ) ),
			'point_name'   => sanitize_text_field( self::post( 'point_name' ) ) ?: 'điểm',
		) );
		if ( self::post( 'hauth_reset' ) || $old['shop_host'] !== $new['shop_host'] ) {
			$new = SMSS_Settings::update( array( 'hauth' => array() ) );
		}
		$slugs = array( $new['slug'], $new['app_slug'], $new['portal_slug'] );
		if ( count( array_unique( $slugs ) ) < 3 ) {
			$new = SMSS_Settings::update( array( 'slug' => $old['slug'], 'app_slug' => $old['app_slug'], 'portal_slug' => $old['portal_slug'] ) );
			self::notice( 'error', 'Ba đường dẫn (kho, app quản lý, trang thành viên) phải khác nhau. Đã giữ đường dẫn cũ.' );
		}
		if ( '' !== trim( (string) self::post( 'shop_host' ) ) && '' === $new['shop_host'] ) {
			self::notice( 'warning', 'Tên miền kho không hợp lệ, đã bỏ trống.' );
		}
		if ( $old['slug'] !== $new['slug'] || $old['app_slug'] !== $new['app_slug'] || $old['portal_slug'] !== $new['portal_slug'] ) {
			update_option( 'smss_flush_rules', 1, false );
		}
		self::notice( 'success', 'Đã lưu cài đặt.' );
		self::back( 'smss' );
	}

	/* ------------------------------------------------------------------ */
	/* Items                                                               */
	/* ------------------------------------------------------------------ */

	public static function page_items() {
		$search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$paged    = max( 1, isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 1 ); // phpcs:ignore WordPress.Security.NonceVerification
		$per_page = 50;
		$total    = SMSS_Library::count( $search );
		$rows     = SMSS_Library::query( $search, $paged, $per_page );
		$pages    = max( 1, (int) ceil( $total / $per_page ) );
		$folders  = (array) SMSS_Settings::get( 'folders' );
		?>
		<div class="wrap smss-wrap">
			<?php self::header_bar( 'Danh sách game' ); ?>

			<div class="smss-card">
				<h2>Thêm link</h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'smss_add_links' ); ?>
					<input type="hidden" name="action" value="smss_add_links">
					<textarea name="links" rows="6" class="large-text code" placeholder="Mỗi dòng một link. Ví dụ:
https://drive.google.com/file/d/1AbC.../view?usp=sharing
https://drive.google.com/drive/folders/1XyZ...          ← cả thư mục
https://host-cua-ban.com/game.nsz | Tên game [0100ABCD12340000][v0].nsz
gdrive:1AbC... | Tên game [0100ABCD12340000][v0].nsz | 5.2G"></textarea>
					<p class="description">Dán link file Google Drive, link thư mục Drive (sẽ tự quét, tự cập nhật mỗi giờ) hoặc link tải trực tiếp bất kỳ. Muốn đặt tên hoặc dung lượng thì thêm <code>| Tên file | dung lượng</code>. Tên file nên có <code>[TitleID][vVersion]</code> để Tinfoil hiện ảnh và thông tin game.</p>
					<?php submit_button( 'Thêm vào kho', 'primary', 'submit', false ); ?>
				</form>
			</div>

			<form method="get" class="smss-search">
				<input type="hidden" name="page" value="smss-items">
				<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Tìm tên game hoặc Title ID">
				<button class="button">Tìm</button>
				<span class="smss-count"><?php echo esc_html( number_format_i18n( $total ) ); ?> game</span>
			</form>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'smss_item_action' ); ?>
				<input type="hidden" name="action" value="smss_item_action">
				<input type="hidden" name="paged" value="<?php echo esc_attr( $paged ); ?>">
				<input type="hidden" name="s" value="<?php echo esc_attr( $search ); ?>">
				<table class="widefat striped smss-table">
					<thead><tr>
						<td class="check-column"><input type="checkbox" onclick="document.querySelectorAll('.smss-cb').forEach(c=>c.checked=this.checked)"></td>
						<th>Tên file</th><th>Title ID</th><th>Dung lượng</th><th>Điểm cần</th><th>Nguồn</th><th>Trạng thái</th><th></th>
					</tr></thead>
					<tbody>
					<?php if ( ! $rows ) : ?>
						<tr><td colspan="8" class="smss-empty">Chưa có game nào. Dán link vào ô phía trên.</td></tr>
					<?php endif; ?>
					<?php foreach ( $rows as $row ) : ?>
						<?php
						if ( $row->gdrive_id ) {
							$src_link = 'https://drive.google.com/file/d/' . rawurlencode( $row->gdrive_id ) . '/view';
							$src      = 'folder' === $row->source ? 'Drive · ' . ( $folders[ $row->folder_id ]['name'] ?? 'thư mục' ) : 'Drive';
						} else {
							$src_link = $row->url;
							$src      = (string) wp_parse_url( $row->url, PHP_URL_HOST );
						}
						?>
						<tr class="<?php echo $row->enabled ? '' : 'smss-off'; ?>">
							<th class="check-column"><input class="smss-cb" type="checkbox" name="ids[]" value="<?php echo esc_attr( $row->id ); ?>"></th>
							<td class="smss-name"><?php echo esc_html( $row->name ); ?></td>
							<td><code><?php echo esc_html( $row->title_id ?: '—' ); ?></code></td>
							<td><?php echo esc_html( SMSS_Links::human_size( $row->size ) ); ?></td>
							<td><?php echo (int) $row->is_free ? 'Miễn phí' : esc_html( number_format_i18n( (int) $row->price ) ); ?></td>
							<td><a href="<?php echo esc_url( $src_link ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $src ); ?></a></td>
							<td><?php echo $row->enabled ? '<span class="smss-pill on">Đang hiện</span>' : '<span class="smss-pill">Ẩn</span>'; ?></td>
							<td class="smss-actions">
								<button class="button-link" name="do" value="toggle:<?php echo esc_attr( $row->id ); ?>"><?php echo $row->enabled ? 'Ẩn' : 'Hiện'; ?></button>
								<?php if ( 'manual' === $row->source ) : ?>
									· <button class="button-link smss-danger" name="do" value="delete:<?php echo esc_attr( $row->id ); ?>" onclick="return confirm('Xoá game này khỏi kho? (File trên Drive không bị xoá)')">Xoá</button>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<div class="smss-bulk">
					<select name="bulk">
						<option value="">Thao tác hàng loạt</option>
						<option value="enable">Hiện</option>
						<option value="disable">Ẩn</option>
						<option value="delete">Xoá link tự thêm</option>
					</select>
					<button class="button" name="do" value="bulk">Áp dụng</button>
					<?php
					echo wp_kses_post( paginate_links( array(
						'base'    => add_query_arg( 'paged', '%#%' ),
						'format'  => '',
						'current' => $paged,
						'total'   => $pages,
					) ) );
					?>
				</div>
				<p class="description">Game lấy từ thư mục Drive chỉ ẩn được, không xoá được. Muốn bỏ hẳn thì xoá file trên Drive hoặc gỡ thư mục ở trang Thư mục Drive.</p>
			</form>
		</div>
		<?php
	}

	public static function handle_add_links() {
		self::guard( 'add_links' );
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		$report = SMSS_Library::add_lines( self::post( 'links' ) );
		self::notice( $report['errors'] ? 'warning' : 'success', 'Đã xử lý: ' . self::report_message( $report ) );
		foreach ( array_slice( $report['errors'], 0, 20 ) as $err ) {
			self::notice( 'error', $err );
		}
		self::back( 'smss-items' );
	}

	public static function handle_item_action() {
		global $wpdb;
		self::guard( 'item_action' );
		$table = SMSS_DB::items_table();
		$do    = (string) self::post( 'do' );
		$ids   = array_map( 'intval', (array) self::post( 'ids', array() ) );

		if ( preg_match( '/^(toggle|delete):(\d+)$/', $do, $m ) ) {
			$id = (int) $m[2];
			if ( 'toggle' === $m[1] ) {
				$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET enabled = 1 - enabled WHERE id = %d", $id ) );
			} else {
				$wpdb->delete( $table, array( 'id' => $id, 'source' => 'manual' ) );
				self::notice( 'success', 'Đã xoá.' );
			}
		} elseif ( 'bulk' === $do && $ids ) {
			$bulk = (string) self::post( 'bulk' );
			$in   = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			if ( 'enable' === $bulk || 'disable' === $bulk ) {
				$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET enabled = %d WHERE id IN ({$in})", array_merge( array( 'enable' === $bulk ? 1 : 0 ), $ids ) ) );
				self::notice( 'success', 'Đã cập nhật ' . count( $ids ) . ' game.' );
			} elseif ( 'delete' === $bulk ) {
				$n = $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE source = 'manual' AND id IN ({$in})", $ids ) );
				self::notice( 'success', 'Đã xoá ' . (int) $n . ' link.' );
			}
		}
		$args = array();
		if ( self::post( 'paged' ) ) {
			$args['paged'] = (int) self::post( 'paged' );
		}
		if ( self::post( 's' ) ) {
			$args['s'] = sanitize_text_field( self::post( 's' ) );
		}
		self::back( 'smss-items', $args );
	}

	/* ------------------------------------------------------------------ */
	/* Drive folders                                                       */
	/* ------------------------------------------------------------------ */

	public static function page_folders() {
		$folders = (array) SMSS_Settings::get( 'folders' );
		$next    = wp_next_scheduled( 'smss_sync_folders' );
		?>
		<div class="wrap smss-wrap">
			<?php self::header_bar( 'Thư mục Google Drive' ); ?>
			<div class="smss-card">
				<h2>Thêm thư mục</h2>
				<?php if ( ! SMSS_Drive::has_key() ) : ?>
					<p class="smss-warn">Cần nhập <b>Google API key</b> ở trang <a href="<?php echo esc_url( admin_url( 'admin.php?page=smss' ) ); ?>">Tổng quan & cài đặt</a> trước.</p>
				<?php endif; ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="smss-inline">
					<?php wp_nonce_field( 'smss_folder_add' ); ?>
					<input type="hidden" name="action" value="smss_folder_add">
					<input type="url" name="folder" class="regular-text" placeholder="https://drive.google.com/drive/folders/..." required>
					<?php submit_button( 'Thêm & quét', 'primary', 'submit', false ); ?>
				</form>
				<p class="description">Thư mục phải chia sẻ <b>"Bất kỳ ai có đường liên kết"</b> (quyền Người xem). Kho quét cả thư mục con, tự cập nhật mỗi giờ: thêm game mới, gỡ game đã xoá trên Drive.
				<?php if ( $next ) : ?>Lần quét tự động tới: <?php echo esc_html( wp_date( 'H:i d/m', $next ) ); ?>.<?php endif; ?></p>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'smss_folder_action' ); ?>
				<input type="hidden" name="action" value="smss_folder_action">
				<table class="widefat striped smss-table">
					<thead><tr><th>Thư mục</th><th>Số game</th><th>Quét lần cuối</th><th>Tình trạng</th><th></th></tr></thead>
					<tbody>
					<?php if ( ! $folders ) : ?>
						<tr><td colspan="5" class="smss-empty">Chưa có thư mục nào.</td></tr>
					<?php endif; ?>
					<?php foreach ( $folders as $f ) : ?>
						<tr>
							<td><a href="<?php echo esc_url( 'https://drive.google.com/drive/folders/' . rawurlencode( $f['id'] ) ); ?>" target="_blank" rel="noopener"><b><?php echo esc_html( $f['name'] ); ?></b></a><br><code class="smss-dim"><?php echo esc_html( $f['id'] ); ?></code></td>
							<td><?php echo esc_html( number_format_i18n( (int) $f['count'] ) ); ?></td>
							<td><?php echo esc_html( $f['last_sync'] ? mysql2date( 'H:i d/m/Y', $f['last_sync'] ) : '—' ); ?></td>
							<td><?php echo $f['error'] ? '<span class="smss-pill err">' . esc_html( $f['error'] ) . '</span>' : '<span class="smss-pill on">OK</span>'; ?></td>
							<td class="smss-actions">
								<button class="button" name="do" value="sync:<?php echo esc_attr( $f['id'] ); ?>">Quét lại</button>
								<button class="button-link smss-danger" name="do" value="remove:<?php echo esc_attr( $f['id'] ); ?>" onclick="return confirm('Gỡ thư mục này và toàn bộ game của nó khỏi kho? (File trên Drive không bị xoá)')">Gỡ</button>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<?php if ( $folders ) : ?>
					<p><button class="button" name="do" value="sync_all">Quét lại tất cả</button></p>
				<?php endif; ?>
			</form>
		</div>
		<?php
	}

	public static function handle_folder_add() {
		self::guard( 'folder_add' );
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		$parsed = SMSS_Links::parse( (string) self::post( 'folder' ) );
		if ( 'drive_folder' !== $parsed['type'] ) {
			self::notice( 'error', 'Đây không phải link thư mục Google Drive (dạng drive.google.com/drive/folders/...).' );
		} else {
			$res = SMSS_Library::add_folder( $parsed['id'] );
			if ( is_wp_error( $res ) ) {
				self::notice( 'error', $res->get_error_message() );
			} else {
				self::notice( 'success', 'Đã thêm thư mục: ' . self::report_message( $res ) );
			}
		}
		self::back( 'smss-folders' );
	}

	public static function handle_folder_action() {
		self::guard( 'folder_action' );
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		$do = (string) self::post( 'do' );
		if ( 'sync_all' === $do ) {
			SMSS_Library::sync_all_folders();
			self::notice( 'success', 'Đã quét lại tất cả thư mục.' );
		} elseif ( preg_match( '/^(sync|remove):([A-Za-z0-9_-]+)$/', $do, $m ) ) {
			if ( 'sync' === $m[1] ) {
				$res = SMSS_Library::sync_folder( $m[2] );
				self::notice( is_wp_error( $res ) ? 'error' : 'success', is_wp_error( $res ) ? $res->get_error_message() : 'Đã quét: ' . self::report_message( $res ) );
			} else {
				SMSS_Library::remove_folder( $m[2] );
				self::notice( 'success', 'Đã gỡ thư mục khỏi kho.' );
			}
		}
		self::back( 'smss-folders' );
	}

	/* ------------------------------------------------------------------ */
	/* Accounts                                                            */
	/* ------------------------------------------------------------------ */

	public static function page_accounts() {
		$accounts = SMSS_Accounts::all();
		?>
		<div class="wrap smss-wrap">
			<?php self::header_bar( 'Tài khoản thành viên' ); ?>
			<div class="smss-card">
				<h2>Tạo tài khoản</h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="smss-account-form">
					<?php wp_nonce_field( 'smss_account_add' ); ?>
					<input type="hidden" name="action" value="smss_account_add">
					<label>Tên đăng nhập<input name="username" required pattern="[A-Za-z0-9._\-]{3,60}"></label>
					<label>Mật khẩu<input name="password" required value="<?php echo esc_attr( wp_generate_password( 10, false ) ); ?>"></label>
					<label>Hết hạn<input type="date" name="expires"></label>
					<label>Số máy tối đa<input type="number" name="max_devices" min="0" value="0" style="width:90px"></label>
					<label class="smss-grow">Ghi chú<input name="note" placeholder="Tên thành viên, số điện thoại..."></label>
					<?php submit_button( 'Tạo', 'primary', 'submit', false ); ?>
				</form>
				<p class="description">Để trống "Hết hạn" nghĩa là dùng vĩnh viễn. "Số máy tối đa" = 0 là không giới hạn; đặt 1 thì tài khoản chỉ dùng được trên máy Switch đầu tiên đăng nhập. Mật khẩu không được chứa <code>@ &amp; / ? # =</code>.</p>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'smss_account_action' ); ?>
				<input type="hidden" name="action" value="smss_account_action">
				<table class="widefat striped smss-table">
					<thead><tr><th>Tài khoản</th><th>Điểm</th><th>Đã có</th><th>Hết hạn</th><th>Máy</th><th>Lần cuối vào kho</th><th>Link riêng</th><th></th></tr></thead>
					<tbody>
					<?php if ( ! $accounts ) : ?>
						<tr><td colspan="8" class="smss-empty">Chưa có tài khoản. Tạo ít nhất một tài khoản để đăng nhập trong Tinfoil.</td></tr>
					<?php endif; ?>
					<?php foreach ( $accounts as $a ) : ?>
						<?php
						$devices = SMSS_Accounts::devices( $a );
						$expired = SMSS_Accounts::is_expired( $a );
						?>
						<tr class="<?php echo ( $a->enabled && ! $expired ) ? '' : 'smss-off'; ?>">
							<td><b><?php echo esc_html( $a->username ); ?></b>
								<?php if ( ! $a->enabled ) : ?><span class="smss-pill err">Khoá</span><?php endif; ?>
								<?php if ( $expired ) : ?><span class="smss-pill err">Hết hạn</span><?php endif; ?>
								<?php if ( $a->note ) : ?><br><span class="smss-dim"><?php echo esc_html( $a->note ); ?></span><?php endif; ?></td>
							<td><b><?php echo esc_html( number_format_i18n( (int) $a->points ) ); ?></b></td>
							<td><?php echo (int) $a->all_access ? 'Tất cả' : esc_html( (int) $a->owned_count ); ?></td>
							<td><?php echo esc_html( $a->expires_at ? mysql2date( 'd/m/Y', $a->expires_at ) : 'Vĩnh viễn' ); ?></td>
							<td><?php echo esc_html( count( $devices ) . ' / ' . ( (int) $a->max_devices ?: '∞' ) ); ?></td>
							<td><?php echo esc_html( $a->last_seen ? mysql2date( 'H:i d/m/Y', $a->last_seen ) . ' · ' . $a->last_ip : 'Chưa' ); ?></td>
							<td><input class="smss-copy" readonly value="<?php echo esc_attr( SMSS_Settings::shop_url( $a->access_key ) ); ?>" onclick="this.select()"></td>
							<td class="smss-actions">
								<button class="button-link" name="do" value="toggle:<?php echo esc_attr( $a->id ); ?>"><?php echo $a->enabled ? 'Khoá' : 'Mở khoá'; ?></button> ·
								<button class="button-link" name="do" value="extend30:<?php echo esc_attr( $a->id ); ?>">+30 ngày</button> ·
								<button class="button-link" name="do" value="devices:<?php echo esc_attr( $a->id ); ?>">Xoá máy</button> ·
								<button class="button-link" name="do" value="password:<?php echo esc_attr( $a->id ); ?>">Đổi mật khẩu</button> ·
								<button class="button-link" name="do" value="newkey:<?php echo esc_attr( $a->id ); ?>">Đổi link</button> ·
								<button class="button-link smss-danger" name="do" value="delete:<?php echo esc_attr( $a->id ); ?>" onclick="return confirm('Xoá tài khoản này?')">Xoá</button>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p class="description"><b>Link riêng</b> dành cho app không có ô đăng nhập: dán cả link vào ô Path/URL, không cần nhập mật khẩu. Đừng chia sẻ link này công khai.</p>
			</form>
		</div>
		<?php
	}

	public static function handle_account_add() {
		self::guard( 'account_add' );
		$expires = sanitize_text_field( self::post( 'expires' ) );
		$expires = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $expires ) ? $expires . ' 23:59:59' : null;
		$res     = SMSS_Accounts::create(
			sanitize_user( self::post( 'username' ), true ),
			(string) self::post( 'password' ),
			$expires,
			(int) self::post( 'max_devices' ),
			sanitize_text_field( self::post( 'note' ) )
		);
		if ( is_wp_error( $res ) ) {
			self::notice( 'error', $res->get_error_message() );
		} else {
			self::notice( 'success', 'Đã tạo tài khoản <b>' . esc_html( self::post( 'username' ) ) . '</b>, mật khẩu: <code>' . esc_html( self::post( 'password' ) ) . '</code> (chỉ hiện lần này, hãy gửi cho thành viên).' );
		}
		self::back( 'smss-accounts' );
	}

	public static function handle_account_action() {
		self::guard( 'account_action' );
		if ( ! preg_match( '/^(toggle|extend30|devices|password|newkey|delete):(\d+)$/', (string) self::post( 'do' ), $m ) ) {
			self::back( 'smss-accounts' );
		}
		$account = SMSS_Accounts::get( (int) $m[2] );
		if ( ! $account ) {
			self::back( 'smss-accounts' );
		}
		switch ( $m[1] ) {
			case 'toggle':
				SMSS_Accounts::update( $account->id, array( 'enabled' => $account->enabled ? 0 : 1 ) );
				break;
			case 'extend30':
				$from = ( $account->expires_at && strtotime( $account->expires_at ) > current_time( 'timestamp' ) ) ? strtotime( $account->expires_at ) : current_time( 'timestamp' );
				SMSS_Accounts::update( $account->id, array( 'expires_at' => gmdate( 'Y-m-d 23:59:59', $from + 30 * DAY_IN_SECONDS ) ) );
				self::notice( 'success', 'Đã gia hạn ' . esc_html( $account->username ) . ' thêm 30 ngày.' );
				break;
			case 'devices':
				SMSS_Accounts::update( $account->id, array( 'devices' => '[]' ) );
				self::notice( 'success', 'Đã xoá danh sách máy của ' . esc_html( $account->username ) . '.' );
				break;
			case 'password':
				$pass = wp_generate_password( 10, false );
				SMSS_Accounts::update( $account->id, array( 'password' => $pass ) );
				self::notice( 'success', 'Mật khẩu mới của <b>' . esc_html( $account->username ) . '</b>: <code>' . esc_html( $pass ) . '</code>' );
				break;
			case 'newkey':
				SMSS_Accounts::update( $account->id, array( 'access_key' => SMSS_Accounts::new_key() ) );
				self::notice( 'success', 'Đã đổi link riêng, link cũ không dùng được nữa.' );
				break;
			case 'delete':
				SMSS_Accounts::delete( $account->id );
				self::notice( 'success', 'Đã xoá tài khoản.' );
				break;
		}
		self::back( 'smss-accounts' );
	}
}
