<?php
defined( 'ABSPATH' ) || exit;

/**
 * Two tables: the games (items) and the shop accounts.
 */
class SMSS_DB {

	public static function items_table() {
		global $wpdb;
		return $wpdb->prefix . 'smss_items';
	}

	public static function accounts_table() {
		global $wpdb;
		return $wpdb->prefix . 'smss_accounts';
	}

	public static function grants_table() {
		global $wpdb;
		return $wpdb->prefix . 'smss_grants';
	}

	public static function ledger_table() {
		global $wpdb;
		return $wpdb->prefix . 'smss_ledger';
	}

	public static function install() {
		global $wpdb;
		$previous = get_option( 'smss_db_version' );
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$items   = self::items_table();
		$acc     = self::accounts_table();

		// source: manual = link typed by the admin, folder = imported from a Drive folder.
		// gdrive_id is set for every Google Drive file, url for every other link.
		dbDelta( "CREATE TABLE {$items} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(400) NOT NULL DEFAULT '',
			url text NULL,
			gdrive_id varchar(128) NULL,
			size bigint(20) unsigned NOT NULL DEFAULT 0,
			title_id varchar(16) NULL,
			display_name varchar(200) NOT NULL DEFAULT '',
			game_name varchar(200) NOT NULL DEFAULT '',
			icon_url varchar(255) NOT NULL DEFAULT '',
			meta_checked tinyint(1) NOT NULL DEFAULT 0,
			price bigint(20) unsigned NOT NULL DEFAULT 0,
			is_free tinyint(1) NOT NULL DEFAULT 0,
			source varchar(10) NOT NULL DEFAULT 'manual',
			folder_id varchar(128) NULL,
			enabled tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY gdrive_id (gdrive_id),
			KEY folder_id (folder_id),
			KEY enabled_name (enabled,name(120)),
			KEY source_folder (source,folder_id)
		) {$charset};" );

		// devices: JSON list of Tinfoil UIDs that used this account.
		dbDelta( "CREATE TABLE {$acc} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			username varchar(60) NOT NULL,
			pass_hash varchar(255) NOT NULL DEFAULT '',
			access_key varchar(40) NOT NULL DEFAULT '',
			note varchar(255) NOT NULL DEFAULT '',
			expires_at datetime NULL,
			max_devices int(11) NOT NULL DEFAULT 0,
			all_access tinyint(1) NOT NULL DEFAULT 0,
			points bigint(20) NOT NULL DEFAULT 0,
			kind varchar(8) NOT NULL DEFAULT 'user',
			uid_hash varchar(64) NOT NULL DEFAULT '',
			sig_hash varchar(64) NOT NULL DEFAULT '',
			sig_state tinyint(1) NOT NULL DEFAULT 0,
			sig_bad int(11) NOT NULL DEFAULT 0,
			sig_bad_at datetime NULL,
			pin varchar(8) NOT NULL DEFAULT '',
			pin_until datetime NULL,
			client_ver varchar(20) NOT NULL DEFAULT '',
			devices text NULL,
			enabled tinyint(1) NOT NULL DEFAULT 1,
			last_seen datetime NULL,
			last_ip varchar(64) NOT NULL DEFAULT '',
			hits bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY username (username),
			KEY uid_hash (uid_hash),
			KEY access_key (access_key)
		) {$charset};" );

		// Which games an account bought.
		$grants = self::grants_table();
		dbDelta( "CREATE TABLE {$grants} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			account_id bigint(20) unsigned NOT NULL,
			item_id bigint(20) unsigned NOT NULL,
			ledger_id bigint(20) unsigned NULL,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY account_item (account_id,item_id),
			KEY item_id (item_id)
		) {$charset};" );

		// Points history: top-ups by the admin, unlocks by the customer, adjustments.
		// type: topup | unlock | adjust | refund
		$ledger = self::ledger_table();
		dbDelta( "CREATE TABLE {$ledger} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			account_id bigint(20) unsigned NOT NULL,
			delta bigint(20) NOT NULL DEFAULT 0,
			balance bigint(20) NOT NULL DEFAULT 0,
			type varchar(10) NOT NULL DEFAULT 'adjust',
			item_id bigint(20) unsigned NULL,
			note varchar(255) NOT NULL DEFAULT '',
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY account_id (account_id),
			KEY type_created (type,created_at)
		) {$charset};" );

		// v1 accounts saw every game: keep it that way after the upgrade.
		if ( '1' === (string) $previous ) {
			$wpdb->query( "UPDATE {$acc} SET all_access = 1" );
		}

		// Settings ride along with WordPress's autoloaded options from 1.3 on.
		if ( function_exists( 'wp_set_option_autoload' ) ) {
			wp_set_option_autoload( SMSS_Settings::OPTION, true );
		}

		update_option( 'smss_db_version', SMSS_DB_VERSION, true );
	}
}
