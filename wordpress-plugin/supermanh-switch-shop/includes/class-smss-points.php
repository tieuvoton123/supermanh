<?php
defined( 'ABSPATH' ) || exit;

/**
 * Points: the admin tops up an account (after the customer paid, outside the
 * plugin), the customer spends points to unlock a Việt hóa patch.
 * Every change is written to the ledger with the balance after it.
 */
class SMSS_Points {

	private static function log( $account_id, $delta, $type, $item_id = null, $note = '' ) {
		global $wpdb;
		$balance = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT points FROM ' . SMSS_DB::accounts_table() . ' WHERE id = %d', $account_id ) );
		$wpdb->insert( SMSS_DB::ledger_table(), array(
			'account_id' => (int) $account_id,
			'delta'      => (int) $delta,
			'balance'    => $balance,
			'type'       => $type,
			'item_id'    => $item_id ? (int) $item_id : null,
			'note'       => mb_substr( (string) $note, 0, 255 ),
			'created_by' => get_current_user_id(),
			'created_at' => current_time( 'mysql' ),
		) );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Add (or, with a negative amount, remove) points.
	 *
	 * @return int|WP_Error new balance
	 */
	public static function topup( $account_id, $amount, $note = '' ) {
		global $wpdb;
		$amount = (int) $amount;
		if ( 0 === $amount ) {
			return new WP_Error( 'smss_points', 'Số điểm phải khác 0.' );
		}
		$table = SMSS_DB::accounts_table();
		if ( $amount < 0 ) {
			// Never below zero.
			$done = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET points = points + %d WHERE id = %d AND points + %d >= 0", $amount, $account_id, $amount ) );
			if ( ! $done ) {
				return new WP_Error( 'smss_points', 'Không đủ điểm để trừ.' );
			}
		} else {
			$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET points = points + %d WHERE id = %d", $amount, $account_id ) );
		}
		self::log( $account_id, $amount, $amount > 0 ? 'topup' : 'adjust', null, $note );
		return self::balance( $account_id );
	}

	public static function balance( $account_id ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT points FROM ' . SMSS_DB::accounts_table() . ' WHERE id = %d', $account_id ) );
	}

	/**
	 * Spend points on one item. The balance check and the deduction are one
	 * UPDATE, so a double tap cannot spend twice or go negative.
	 *
	 * @return array|WP_Error {balance, price}
	 */
	public static function unlock( $account_id, $item_id ) {
		global $wpdb;
		$item = SMSS_Library::get( $item_id );
		if ( ! $item || ! (int) $item->enabled ) {
			return new WP_Error( 'smss_unlock', 'Bản này không còn trong shop.' );
		}
		if ( (int) $item->is_free ) {
			return new WP_Error( 'smss_unlock', 'Bản này miễn phí, đã có sẵn trong Tinfoil.' );
		}
		if ( SMSS_Accounts::owns( $account_id, $item_id ) ) {
			return new WP_Error( 'smss_unlock', 'Bạn đã mở khoá bản này rồi.' );
		}
		$price = (int) $item->price;
		$table = SMSS_DB::accounts_table();
		// MySQL reports 0 affected rows when nothing changes, so a 0-point item skips the UPDATE.
		$done  = 0 === $price ? 1 : $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET points = points - %d WHERE id = %d AND points >= %d", $price, $account_id, $price ) );
		if ( ! $done ) {
			return new WP_Error( 'smss_unlock', 'Không đủ điểm. Cần ' . number_format( $price, 0, ',', '.' ) . ', bạn còn ' . number_format( self::balance( $account_id ), 0, ',', '.' ) . '.' );
		}
		$ledger_id = self::log( $account_id, -$price, 'unlock', $item_id, $item->name );
		if ( ! SMSS_Accounts::grant( $account_id, array( $item_id ), $ledger_id ) ) {
			// Granted in parallel by another request: give the points back.
			$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET points = points + %d WHERE id = %d", $price, $account_id ) );
			self::log( $account_id, $price, 'refund', $item_id, 'Hoàn điểm (mở khoá trùng)' );
			return new WP_Error( 'smss_unlock', 'Bạn đã mở khoá bản này rồi.' );
		}
		return array( 'balance' => self::balance( $account_id ), 'price' => $price );
	}

	/**
	 * Admin: take an item back from an account, optionally refunding what was paid.
	 */
	public static function revoke( $account_id, $item_id, $refund = true ) {
		global $wpdb;
		$paid = (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT -l.delta FROM ' . SMSS_DB::grants_table() . ' g JOIN ' . SMSS_DB::ledger_table() . ' l ON l.id = g.ledger_id
			 WHERE g.account_id = %d AND g.item_id = %d',
			$account_id, $item_id
		) );
		SMSS_Accounts::revoke( $account_id, $item_id );
		if ( $refund && $paid > 0 ) {
			$wpdb->query( $wpdb->prepare( 'UPDATE ' . SMSS_DB::accounts_table() . ' SET points = points + %d WHERE id = %d', $paid, $account_id ) );
			$item = SMSS_Library::get( $item_id );
			self::log( $account_id, $paid, 'refund', $item_id, 'Hoàn điểm: ' . ( $item ? $item->name : '#' . $item_id ) );
		}
		return $paid;
	}

	public static function history( $account_id, $limit = 50 ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . SMSS_DB::ledger_table() . ' WHERE account_id = %d ORDER BY id DESC LIMIT %d',
			$account_id, $limit
		) );
	}

	/** Recent activity across all accounts, for the admin app home screen. */
	public static function recent( $limit = 20 ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			'SELECT l.*, a.username, a.kind FROM ' . SMSS_DB::ledger_table() . ' l LEFT JOIN ' . SMSS_DB::accounts_table() . ' a ON a.id = l.account_id
			 ORDER BY l.id DESC LIMIT %d',
			$limit
		) );
	}

	/** Totals for the admin home screen: points topped up / spent, this month and all time. */
	public static function totals() {
		global $wpdb;
		$t     = SMSS_DB::ledger_table();
		$month = gmdate( 'Y-m-01 00:00:00', current_time( 'timestamp' ) );
		return array(
			'topup_month'  => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(delta),0) FROM {$t} WHERE type = 'topup' AND created_at >= %s", $month ) ),
			'unlock_month' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE type = 'unlock' AND created_at >= %s", $month ) ),
			'topup_all'    => (int) $wpdb->get_var( "SELECT COALESCE(SUM(delta),0) FROM {$t} WHERE type = 'topup'" ),
			'balance_all'  => (int) $wpdb->get_var( 'SELECT COALESCE(SUM(points),0) FROM ' . SMSS_DB::accounts_table() ),
		);
	}
}
