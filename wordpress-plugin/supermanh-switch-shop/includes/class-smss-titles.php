<?php
defined( 'ABSPATH' ) || exit;

/**
 * Game names and cover-image links by Title ID, from the bundled public
 * title list in data/ (metadata only). Read on demand, one small file at a time.
 */
class SMSS_Titles {

	private static $shards = array();

	/** The base game's Title ID for a base, update (…800) or DLC id. */
	public static function base_id( $title_id ) {
		$id = strtoupper( (string) $title_id );
		if ( ! preg_match( '/^[0-9A-F]{16}$/', $id ) ) {
			return '';
		}
		$tail = substr( $id, 13 );
		if ( '000' === $tail || '800' === $tail ) {
			return substr( $id, 0, 13 ) . '000';
		}
		// DLC: base + 0x1000 + n.
		$n = hexdec( substr( $id, 9, 4 ) ) - 1;
		return $n < 0 ? '' : substr( $id, 0, 9 ) . sprintf( '%04X', $n ) . '000';
	}

	private static function shard( $key ) {
		if ( ! isset( self::$shards[ $key ] ) ) {
			$file = SMSS_DIR . 'data/titles-' . $key . '.json.gz';
			$data = array();
			if ( preg_match( '/^[0-9A-F]$/', $key ) && is_readable( $file ) ) {
				$json = gzdecode( (string) file_get_contents( $file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				$data = $json ? (array) json_decode( $json, true ) : array();
			}
			self::$shards[ $key ] = $data;
		}
		return self::$shards[ $key ];
	}

	/** @return array|null {name, icon} */
	public static function lookup( $title_id ) {
		$base = self::base_id( $title_id );
		if ( '' === $base ) {
			return null;
		}
		$shard = self::shard( $base[12] );
		if ( empty( $shard[ $base ] ) ) {
			return null;
		}
		$icon = (string) $shard[ $base ][1];
		return array(
			'name' => (string) $shard[ $base ][0],
			'icon' => 0 === strpos( $icon, 'https://' ) ? $icon : '',
		);
	}

	/**
	 * Fill game name, cover link and (when empty) the Tinfoil display name for
	 * items that have a Title ID and were not looked up yet.
	 *
	 * @param bool $all Re-check every item, not only new ones.
	 * @return int items that got a game name
	 */
	public static function enrich( $all = false ) {
		global $wpdb;
		$table = SMSS_DB::items_table();
		$rows  = $wpdb->get_results( "SELECT id, title_id, display_name FROM {$table} WHERE title_id IS NOT NULL" . ( $all ? '' : ' AND meta_checked = 0' ) . ' LIMIT 20000' );
		if ( ! $rows ) {
			return 0;
		}
		$found = 0;
		$wpdb->query( 'START TRANSACTION' );
		foreach ( $rows as $row ) {
			$hit  = self::lookup( $row->title_id );
			$data = array( 'meta_checked' => 1 );
			if ( $hit ) {
				$found++;
				$data['game_name'] = mb_substr( $hit['name'], 0, 200 );
				$data['icon_url']  = strlen( $hit['icon'] ) <= 255 ? $hit['icon'] : '';
				if ( '' === (string) $row->display_name ) {
					$data['display_name'] = mb_substr( $hit['name'], 0, 180 ) . ' (Việt hóa)';
				}
			}
			$wpdb->update( $table, $data, array( 'id' => (int) $row->id ) );
		}
		$wpdb->query( 'COMMIT' );
		self::$shards = array();
		return $found;
	}
}
