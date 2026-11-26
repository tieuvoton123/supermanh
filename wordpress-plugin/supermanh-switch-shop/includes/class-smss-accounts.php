<?php
defined( 'ABSPATH' ) || exit;

/**
 * Shop accounts: what players type into Tinfoil (username + password),
 * or a personal link with a key for clients without Basic Auth.
 */
class SMSS_Accounts {

	/** Characters Tinfoil cannot send in a password. */
	const FORBIDDEN = '@&/?#=';

	public static function validate_password( $password ) {
		if ( '' === $password ) {
			return 'Mật khẩu không được để trống.';
		}
		if ( strpbrk( $password, self::FORBIDDEN ) !== false || preg_match( '/[\x00-\x1F\x7F]/', $password ) ) {
			return 'Mật khẩu không được chứa @ & / ? # = (Tinfoil không gửi được các ký tự này).';
		}
		return '';
	}

	public static function validate_username( $username ) {
		if ( ! preg_match( '/^[A-Za-z0-9._-]{3,60}$/', $username ) ) {
			return 'Tên đăng nhập 3–60 ký tự, chỉ gồm chữ không dấu, số, dấu chấm, gạch ngang, gạch dưới.';
		}
		return '';
	}

	/* ---- Device activation ------------------------------------------- */

	const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

	/** Tinfoil's per-console Uid header, as sent. */
	public static function valid_uid( $uid ) {
		// CyberFoil sends SHA-256 of the console's eMMC CID; when it cannot read the
		// CID it sends 64 zeros, which every such console would share.
		return (bool) preg_match( '/^[A-Za-z0-9_-]{8,128}$/', (string) $uid ) && ! preg_match( '/^0+$/', (string) $uid );
	}

	private static function secret() {
		$s = (string) SMSS_Settings::get( 'device_secret' );
		if ( '' === $s ) {
			$s = wp_generate_password( 48, false, false );
			SMSS_Settings::update( array( 'device_secret' => $s ) );
		}
		return $s;
	}

	/** Stable 8-character code for one console (same code after reinstalling Tinfoil). */
	public static function device_code( $uid ) {
		$raw  = hash_hmac( 'sha256', strtolower( $uid ), self::secret(), true );
		$code = '';
		for ( $i = 0; $i < 8; $i++ ) {
			$code .= self::CODE_ALPHABET[ ord( $raw[ $i ] ) & 31 ];
		}
		return $code;
	}

	/** "ABCD1234" -> "ABCD 1234" */
	public static function format_code( $code ) {
		return substr( $code, 0, 4 ) . ' ' . substr( $code, 4 );
	}

	/** "abcd 1234" / "ABCD-1234" -> "ABCD1234", or '' when it is not a code. */
	public static function normalize_code( $text ) {
		$c = strtoupper( preg_replace( '/[\s-]+/', '', (string) $text ) );
		return preg_match( '/^[' . self::CODE_ALPHABET . ']{8}$/', $c ) ? $c : '';
	}

	/**
	 * Server-side device check on every request, on top of the Uid.
	 * Tinfoil also sends an "Uauth" value whose meaning is not documented, so it
	 * is learned before it is enforced:
	 *   state 0 -> first value seen, remembered (state 1)
	 *   state 1 -> same value again: confirmed stable for this console (state 2);
	 *              a different value: it is not stable here, keep learning
	 *   state 2 -> must match; a mismatch means another device is using this
	 *              console's identity and is refused.
	 *
	 * @return bool false = refuse
	 */
	public static function verify_signature( $account, $uauth ) {
		global $wpdb;
		if ( '' === (string) $uauth ) {
			return true;
		}
		$sig   = hash( 'sha256', 'smss|' . $uauth );
		$table = SMSS_DB::accounts_table();
		$state = (int) $account->sig_state;
		if ( 2 === $state ) {
			if ( hash_equals( (string) $account->sig_hash, $sig ) ) {
				return true;
			}
			$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET sig_bad = sig_bad + 1, sig_bad_at = %s WHERE id = %d", current_time( 'mysql' ), $account->id ) );
			return false;
		}
		if ( 1 === $state && hash_equals( (string) $account->sig_hash, $sig ) ) {
			$wpdb->update( $table, array( 'sig_state' => 2 ), array( 'id' => $account->id ) );
		} elseif ( 0 === $state || ! hash_equals( (string) $account->sig_hash, $sig ) ) {
			$wpdb->update( $table, array( 'sig_hash' => $sig, 'sig_state' => 1 ), array( 'id' => $account->id ) );
		}
		return true;
	}

	/** Admin: forget the learned device signature (Tinfoil reinstalled, console replaced). */
	public static function reset_signature( $id ) {
		self::update( $id, array( 'sig_hash' => '', 'sig_state' => 0, 'sig_bad' => 0, 'sig_bad_at' => null ) );
	}

	/**
	 * 6-digit PIN shown only inside Tinfoil, needed with the code to sign in on
	 * the customer page. Valid 24 hours; a new one appears after that.
	 */
	public static function current_pin( $account ) {
		if ( '' !== (string) $account->pin && $account->pin_until && strtotime( $account->pin_until ) > current_time( 'timestamp' ) ) {
			return $account->pin;
		}
		$pin = str_pad( (string) random_int( 0, 999999 ), 6, '0', STR_PAD_LEFT );
		self::update( $account->id, array( 'pin' => $pin, 'pin_until' => gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) + DAY_IN_SECONDS ) ) );
		return $pin;
	}

	public static function pin_ok( $account, $pin ) {
		return '' !== (string) $account->pin && $account->pin_until
			&& strtotime( $account->pin_until ) > current_time( 'timestamp' )
			&& hash_equals( (string) $account->pin, preg_replace( '/\D/', '', (string) $pin ) );
	}

	public static function is_device( $account ) {
		return isset( $account->kind ) && 'device' === $account->kind;
	}

	/**
	 * The account of the console sending this Uid, created on its first visit.
	 *
	 * @return object|WP_Error
	 */
	public static function device_account( $uid, $ip ) {
		global $wpdb;
		$table = SMSS_DB::accounts_table();
		$hash  = hash( 'sha256', strtolower( $uid ) );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE uid_hash = %s LIMIT 1", $hash ) );
		if ( $row ) {
			return $row;
		}
		// Against scripts inventing Uids: a few new consoles per IP per hour.
		$key = 'smss_devnew_' . md5( $ip );
		$n   = (int) get_transient( $key );
		if ( $n >= 10 ) {
			return new WP_Error( 'smss_device', 'Qua nhieu may moi tu mot mang, thu lai sau.' );
		}
		set_transient( $key, $n + 1, HOUR_IN_SECONDS );

		// Unique on the server: on a clash (practically never) derive the next one.
		$code = '';
		for ( $i = 0; $i < 20; $i++ ) {
			$try = self::device_code( 0 === $i ? $uid : $uid . '#' . $i );
			if ( ! self::get_by_username( $try ) ) {
				$code = $try;
				break;
			}
		}
		if ( '' === $code ) {
			return new WP_Error( 'smss_device', 'Khong tao duoc ma may, thu lai.' );
		}
		$wpdb->insert( $table, array(
			'username'    => $code,
			'pass_hash'   => '',
			'access_key'  => self::new_key(),
			'kind'        => 'device',
			'uid_hash'    => $hash,
			'note'        => '',
			'max_devices' => 0,
			'devices'     => '[]',
			'enabled'     => 1,
			'created_at'  => current_time( 'mysql' ),
		) );
		return self::get( (int) $wpdb->insert_id );
	}

	/** Has the admin done anything for this console yet (points, items, expiry, all access)? */
	public static function is_activated( $account ) {
		return (int) $account->all_access || (int) $account->points > 0 || ! empty( $account->expires_at ) || self::grant_count( $account->id ) > 0;
	}

	public static function new_key() {
		return strtolower( wp_generate_password( 24, false, false ) );
	}

	/** @return int|WP_Error */
	public static function create( $username, $password, $expires_at = null, $max_devices = 0, $note = '', $all_access = 0 ) {
		global $wpdb;
		$err = self::validate_username( $username ) ?: self::validate_password( $password );
		if ( $err ) {
			return new WP_Error( 'smss_account', $err );
		}
		if ( self::get_by_username( $username ) ) {
			return new WP_Error( 'smss_account', 'Tên đăng nhập "' . esc_html( $username ) . '" đã tồn tại.' );
		}
		$wpdb->insert( SMSS_DB::accounts_table(), array(
			'username'    => $username,
			'pass_hash'   => wp_hash_password( $password ),
			'access_key'  => self::new_key(),
			'note'        => $note,
			'expires_at'  => $expires_at,
			'max_devices' => max( 0, (int) $max_devices ),
			'all_access'  => $all_access ? 1 : 0,
			'devices'     => '[]',
			'enabled'     => 1,
			'created_at'  => current_time( 'mysql' ),
		) );
		return (int) $wpdb->insert_id;
	}

	public static function update( $id, array $data ) {
		global $wpdb;
		if ( isset( $data['password'] ) ) {
			$err = self::validate_password( $data['password'] );
			if ( $err ) {
				return new WP_Error( 'smss_account', $err );
			}
			$data['pass_hash'] = wp_hash_password( $data['password'] );
			unset( $data['password'] );
		}
		$wpdb->update( SMSS_DB::accounts_table(), $data, array( 'id' => (int) $id ) );
		return true;
	}

	public static function delete( $id ) {
		global $wpdb;
		$wpdb->delete( SMSS_DB::accounts_table(), array( 'id' => (int) $id ) );
		$wpdb->delete( SMSS_DB::grants_table(), array( 'account_id' => (int) $id ) );
		$wpdb->delete( SMSS_DB::ledger_table(), array( 'account_id' => (int) $id ) );
	}

	public static function owns( $account_id, $item_id ) {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . SMSS_DB::grants_table() . ' WHERE account_id = %d AND item_id = %d', $account_id, $item_id ) );
	}

	/* ---- Purchased games ---------------------------------------------- */

	/** @return int number of newly granted items */
	public static function grant( $account_id, array $item_ids, $ledger_id = null ) {
		global $wpdb;
		$table = SMSS_DB::grants_table();
		$added = 0;
		$owned = array_flip( array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT item_id FROM {$table} WHERE account_id = %d", $account_id ) ) ) );
		foreach ( array_unique( array_map( 'intval', $item_ids ) ) as $item_id ) {
			if ( $item_id <= 0 ) {
				continue;
			}
			if ( ! isset( $owned[ $item_id ] ) ) {
				$wpdb->insert( $table, array(
					'account_id' => (int) $account_id,
					'item_id'    => $item_id,
					'ledger_id'  => $ledger_id ? (int) $ledger_id : null,
					'created_at' => current_time( 'mysql' ),
				) );
				$added++;
			}
		}
		return $added;
	}

	public static function revoke( $account_id, $item_id ) {
		global $wpdb;
		$wpdb->delete( SMSS_DB::grants_table(), array( 'account_id' => (int) $account_id, 'item_id' => (int) $item_id ) );
	}

	/** Items this account owns, newest first. */
	public static function granted_items( $account_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			'SELECT i.id, i.name, i.title_id, i.icon_url, i.size, i.enabled, g.created_at AS granted_at, g.ledger_id
			 FROM ' . SMSS_DB::grants_table() . ' g JOIN ' . SMSS_DB::items_table() . ' i ON i.id = g.item_id
			 WHERE g.account_id = %d ORDER BY g.created_at DESC, i.name ASC',
			$account_id
		) );
	}

	public static function grant_count( $account_id ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . SMSS_DB::grants_table() . ' WHERE account_id = %d', $account_id ) );
	}

	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . SMSS_DB::accounts_table() . ' WHERE id = %d', $id ) );
	}

	public static function get_by_username( $username ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . SMSS_DB::accounts_table() . ' WHERE username = %s', $username ) );
	}

	public static function get_by_key( $key ) {
		global $wpdb;
		if ( ! preg_match( '/^[a-z0-9]{24}$/', $key ) ) {
			return null;
		}
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . SMSS_DB::accounts_table() . ' WHERE access_key = %s', $key ) );
	}

	public static function all() {
		global $wpdb;
		return $wpdb->get_results( 'SELECT a.*, (SELECT COUNT(*) FROM ' . SMSS_DB::grants_table() . ' g WHERE g.account_id = a.id) AS owned_count FROM ' . SMSS_DB::accounts_table() . ' a ORDER BY a.username ASC' );
	}

	public static function devices( $account ) {
		$list = json_decode( (string) $account->devices, true );
		return is_array( $list ) ? $list : array();
	}

	public static function is_expired( $account ) {
		return ! empty( $account->expires_at ) && strtotime( $account->expires_at ) < current_time( 'timestamp' );
	}

	/**
	 * Final checks once the credentials matched: enabled, not expired, device limit.
	 *
	 * @param string $uid Tinfoil device UID header ('' when the client sent none).
	 * @return string '' when allowed, otherwise the reason.
	 */
	public static function check_access( $account, $uid ) {
		if ( ! (int) $account->enabled ) {
			return 'Tài khoản đã bị khoá.';
		}
		if ( self::is_expired( $account ) ) {
			return 'Tài khoản đã hết hạn.';
		}
		$max = (int) $account->max_devices;
		if ( $max > 0 ) {
			if ( '' === $uid ) {
				return 'Tài khoản này chỉ dùng được trên Tinfoil.';
			}
			$devices = self::devices( $account );
			if ( ! in_array( $uid, $devices, true ) ) {
				if ( count( $devices ) >= $max ) {
					return 'Tài khoản đã dùng đủ ' . $max . ' máy.';
				}
				$devices[] = $uid;
				self::update( $account->id, array( 'devices' => wp_json_encode( array_values( $devices ) ) ) );
			}
		}
		return '';
	}

	public static function touch( $account, $ip ) {
		global $wpdb;
		// One write per visit, not per request: Tinfoil reloads the list often.
		if ( $account->last_ip === $ip && $account->last_seen && current_time( 'timestamp' ) - strtotime( $account->last_seen ) < 5 * MINUTE_IN_SECONDS ) {
			return;
		}
		$wpdb->query( $wpdb->prepare(
			'UPDATE ' . SMSS_DB::accounts_table() . ' SET last_seen = %s, last_ip = %s, hits = hits + 1 WHERE id = %d',
			current_time( 'mysql' ), $ip, $account->id
		) );
	}
}
