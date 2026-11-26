<?php
defined( 'ABSPATH' ) || exit;

/**
 * The game list: adding links, syncing Drive folders, building the Tinfoil index.
 */
class SMSS_Library {

	/**
	 * Add the lines of the "add links" box.
	 *
	 * @return array{added:int,updated:int,folders:int,errors:string[]}
	 */
	public static function add_lines( $text ) {
		$report = array( 'added' => 0, 'updated' => 0, 'folders' => 0, 'errors' => array() );
		$lines  = preg_split( '/\r\n|\r|\n/', (string) $text );

		foreach ( $lines as $n => $raw ) {
			$raw = trim( $raw );
			if ( '' === $raw || 0 === strpos( $raw, '//' ) ) {
				continue;
			}
			$line   = SMSS_Links::parse_line( $raw );
			$parsed = SMSS_Links::parse( $line['link'] );
			$label  = 'Dòng ' . ( $n + 1 ) . ': ';

			switch ( $parsed['type'] ) {
				case 'drive_folder':
					$res = self::add_folder( $parsed['id'] );
					if ( is_wp_error( $res ) ) {
						$report['errors'][] = $label . $res->get_error_message();
					} else {
						$report['folders']++;
						$report['added'] += $res['added'];
						$report['updated'] += $res['updated'];
					}
					break;

				case 'drive_file':
					$res = self::add_drive_file( $parsed['id'], $line['name'], $line['size'] );
					if ( is_wp_error( $res ) ) {
						$report['errors'][] = $label . $res->get_error_message();
					} else {
						$report[ $res ]++;
					}
					break;

				case 'http':
					$res = self::add_http( $parsed['url'], $line['name'], $line['size'] );
					if ( is_wp_error( $res ) ) {
						$report['errors'][] = $label . $res->get_error_message();
					} else {
						$report[ $res ]++;
					}
					break;

				default:
					$report['errors'][] = $label . 'Không nhận ra link "' . esc_html( $line['link'] ) . '".';
			}
		}
		SMSS_Titles::enrich();
		return $report;
	}

	/** @return string|WP_Error "added" or "updated" */
	public static function add_drive_file( $id, $name = '', $size = 0 ) {
		if ( SMSS_Drive::has_key() ) {
			$meta = SMSS_Drive::get_file( $id );
			if ( is_wp_error( $meta ) ) {
				if ( '' === $name ) {
					return $meta;
				}
			} else {
				if ( SMSS_Drive::FOLDER_MIME === ( $meta['mimeType'] ?? '' ) ) {
					$res = self::add_folder( $id );
					return is_wp_error( $res ) ? $res : 'added';
				}
				$name = '' !== $name ? $name : SMSS_Links::clean_name( $meta['name'] );
				$size = $size > 0 ? $size : (int) ( $meta['size'] ?? 0 );
			}
		} elseif ( '' === $name ) {
			return new WP_Error( 'smss_need_name', 'Chưa có Google API key nên phải ghi tên file: link | Tên game [TitleID][v0].nsz | dung lượng' );
		}

		$err = self::check_name( $name );
		if ( $err ) {
			return $err;
		}
		return self::upsert( array(
			'name'      => $name,
			'gdrive_id' => $id,
			'url'       => null,
			'size'      => $size,
			'source'    => 'manual',
			'folder_id' => null,
		) );
	}

	/** @return string|WP_Error */
	public static function add_http( $url, $name = '', $size = 0 ) {
		if ( '' === $name ) {
			$name = SMSS_Links::name_from_url( $url );
		}
		$err = self::check_name( $name );
		if ( $err ) {
			return $err;
		}
		if ( $size <= 0 ) {
			$size = SMSS_Links::remote_size( $url );
		}
		return self::upsert( array(
			'name'      => $name,
			'gdrive_id' => null,
			'url'       => esc_url_raw( preg_replace( '~#.*$~', '', $url ) ),
			'size'      => $size,
			'source'    => 'manual',
			'folder_id' => null,
		) );
	}

	/** @return WP_Error|null */
	private static function check_name( $name ) {
		if ( '' === $name ) {
			return new WP_Error( 'smss_no_name', 'Không xác định được tên file, hãy ghi thêm: link | Tên file.nsz' );
		}
		if ( SMSS_Settings::get( 'only_nsw' ) && ! SMSS_Links::is_nsw( $name ) ) {
			return new WP_Error( 'smss_ext', '"' . esc_html( $name ) . '" không phải nsp/nsz/xci/xcz.' );
		}
		return null;
	}

	/**
	 * Insert, or update the row with the same Drive ID / URL.
	 *
	 * @return string "added" or "updated"
	 */
	/** Row id written by the last upsert(). */
	public static $last_id = 0;

	public static function upsert( array $data ) {
		global $wpdb;
		$table = SMSS_DB::items_table();
		$now   = current_time( 'mysql' );

		if ( ! empty( $data['gdrive_id'] ) ) {
			$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE gdrive_id = %s LIMIT 1", $data['gdrive_id'] ) );
		} else {
			$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE gdrive_id IS NULL AND url = %s LIMIT 1", $data['url'] ) );
		}

		$data['title_id']   = SMSS_Links::title_id( $data['name'] );
		$data['updated_at'] = $now;

		if ( $existing ) {
			// A manual link keeps its "manual" source even if a folder sync sees it too.
			if ( 'folder' === $data['source'] ) {
				$current = $wpdb->get_var( $wpdb->prepare( "SELECT source FROM {$table} WHERE id = %d", $existing ) );
				if ( 'manual' === $current ) {
					unset( $data['source'], $data['folder_id'] );
				}
			}
			$wpdb->update( $table, $data, array( 'id' => (int) $existing ) );
			self::$last_id = (int) $existing;
			return 'updated';
		}

		$data['enabled']    = 1;
		$data['created_at'] = $now;
		$wpdb->insert( $table, $data );
		self::$last_id = (int) $wpdb->insert_id;
		return 'added';
	}

	/* ------------------------------------------------------------------ */
	/* Bulk import / export                                                */
	/* ------------------------------------------------------------------ */

	private static function field( array $row, array $names ) {
		foreach ( $names as $n ) {
			if ( isset( $row[ $n ] ) && '' !== trim( (string) $row[ $n ] ) ) {
				return trim( (string) $row[ $n ] );
			}
		}
		return '';
	}

	/** JSON (list of objects, or {"items": [...]}) or CSV with a header row -> list of rows. */
	public static function parse_import( $text ) {
		$text = trim( preg_replace( '/^\xEF\xBB\xBF/', '', (string) $text ) );
		if ( '' === $text ) {
			return new WP_Error( 'smss_import', 'File trống.' );
		}
		if ( '[' === $text[0] || '{' === $text[0] ) {
			$data = json_decode( $text, true );
			if ( ! is_array( $data ) ) {
				return new WP_Error( 'smss_import', 'JSON không hợp lệ: ' . json_last_error_msg() );
			}
			if ( isset( $data['items'] ) && is_array( $data['items'] ) ) {
				$data = $data['items'];
			}
			return array_values( array_filter( $data, 'is_array' ) );
		}
		$lines = preg_split( '/\r\n|\r|\n/', $text );
		$head  = array_shift( $lines );
		$delim = substr_count( $head, ';' ) > substr_count( $head, ',' ) ? ';' : ( substr_count( $head, "\t" ) > substr_count( $head, ',' ) ? "\t" : ',' );
		$cols  = array_map( function ( $c ) {
			return strtolower( trim( $c ) );
		}, str_getcsv( $head, $delim ) );
		$rows = array();
		foreach ( $lines as $line ) {
			if ( '' === trim( $line ) ) {
				continue;
			}
			$vals = str_getcsv( $line, $delim );
			$row  = array();
			foreach ( $cols as $i => $c ) {
				$row[ $c ] = isset( $vals[ $i ] ) ? $vals[ $i ] : '';
			}
			$rows[] = $row;
		}
		return $rows;
	}

	/**
	 * Import rows: link (file or folder on Drive, or a direct link), optional
	 * name, title_id, price, free, display, size.
	 */
	public static function import( $text, $default_price = 0 ) {
		global $wpdb;
		$rows = self::parse_import( $text );
		if ( is_wp_error( $rows ) ) {
			return $rows;
		}
		$report = array( 'added' => 0, 'updated' => 0, 'folders' => 0, 'errors' => array() );
		foreach ( array_slice( $rows, 0, 5000 ) as $n => $row ) {
			$row   = array_change_key_case( $row, CASE_LOWER );
			$label = 'Dòng ' . ( $n + 1 ) . ': ';
			$link  = self::field( $row, array( 'link', 'url', 'drive', 'gdrive' ) );
			$name  = SMSS_Links::clean_name( self::field( $row, array( 'name', 'file', 'filename', 'ten', 'tên' ) ) );
			$tid   = strtoupper( self::field( $row, array( 'title_id', 'titleid', 'id' ) ) );
			$size  = SMSS_Links::parse_size( self::field( $row, array( 'size', 'dung_luong' ) ) );
			// A name without [TitleID] gets it from the title_id column.
			if ( '' !== $name && preg_match( '/^[0-9A-F]{16}$/', $tid ) && ! SMSS_Links::title_id( $name ) ) {
				$ext  = SMSS_Links::extension( $name );
				$stem = '' !== $ext ? substr( $name, 0, -strlen( $ext ) - 1 ) : $name;
				$name = $stem . ' [' . $tid . '][v0]' . ( '' !== $ext ? '.' . $ext : '' );
			}
			$parsed = SMSS_Links::parse( $link );
			self::$last_id = 0;
			if ( 'drive_folder' === $parsed['type'] ) {
				$res = self::add_folder( $parsed['id'] );
				if ( is_wp_error( $res ) ) {
					$report['errors'][] = $label . $res->get_error_message();
				} else {
					$report['folders']++;
					$report['added']   += $res['added'];
					$report['updated'] += $res['updated'];
				}
				continue;
			}
			if ( 'drive_file' === $parsed['type'] ) {
				$res = self::add_drive_file( $parsed['id'], $name, $size );
			} elseif ( 'http' === $parsed['type'] ) {
				$res = self::add_http( $parsed['url'], $name, $size );
			} else {
				$res = new WP_Error( 'smss_import', 'Thiếu hoặc không nhận ra link.' );
			}
			if ( is_wp_error( $res ) ) {
				$report['errors'][] = $label . $res->get_error_message();
				continue;
			}
			$report[ $res ]++;
			if ( self::$last_id ) {
				$price   = self::field( $row, array( 'price', 'gia', 'giá', 'points', 'diem' ) );
				$free    = strtolower( self::field( $row, array( 'free', 'mien_phi', 'is_free' ) ) );
				$display = self::field( $row, array( 'display', 'display_name', 'ten_hien' ) );
				$data    = array();
				if ( '' !== $price ) {
					$data['price'] = max( 0, (int) $price );
				} elseif ( 'added' === $res && $default_price > 0 ) {
					$data['price'] = (int) $default_price;
				}
				if ( '' !== $free ) {
					$data['is_free'] = in_array( $free, array( '1', 'true', 'yes', 'x', 'co', 'có' ), true ) ? 1 : 0;
				}
				if ( '' !== $display ) {
					$data['display_name'] = mb_substr( sanitize_text_field( $display ), 0, 200 );
				}
				if ( $data ) {
					$wpdb->update( SMSS_DB::items_table(), $data, array( 'id' => self::$last_id ) );
				}
			}
		}
		$report['named'] = SMSS_Titles::enrich();
		return $report;
	}

	public static function export() {
		global $wpdb;
		$out = array();
		foreach ( $wpdb->get_results( 'SELECT * FROM ' . SMSS_DB::items_table() . ' ORDER BY name ASC' ) as $i ) {
			$out[] = array(
				'name'     => $i->name,
				'title_id' => (string) $i->title_id,
				'link'     => $i->gdrive_id ? 'https://drive.google.com/file/d/' . $i->gdrive_id . '/view' : $i->url,
				'size'     => (int) $i->size,
				'price'    => (int) $i->price,
				'free'     => (int) $i->is_free,
				'display'  => $i->display_name,
				'game'     => $i->game_name,
				'icon'     => $i->icon_url,
			);
		}
		return $out;
	}

	/* ------------------------------------------------------------------ */
	/* Google Drive folders                                                */
	/* ------------------------------------------------------------------ */

	/** @return array|WP_Error sync report */
	public static function add_folder( $folder_id ) {
		$name = SMSS_Drive::folder_name( $folder_id );
		if ( is_wp_error( $name ) ) {
			return $name;
		}
		$folders               = (array) SMSS_Settings::get( 'folders' );
		$folders[ $folder_id ] = array_merge(
			array( 'id' => $folder_id, 'name' => $name, 'last_sync' => '', 'count' => 0, 'error' => '' ),
			isset( $folders[ $folder_id ] ) ? $folders[ $folder_id ] : array(),
			array( 'name' => $name )
		);
		SMSS_Settings::update( array( 'folders' => $folders ) );
		return self::sync_folder( $folder_id );
	}

	/**
	 * Mirror one Drive folder: add new files, update renamed ones, remove the
	 * ones that disappeared from Drive.
	 *
	 * @return array|WP_Error {added, updated, removed}
	 */
	public static function sync_folder( $folder_id ) {
		global $wpdb;
		$table   = SMSS_DB::items_table();
		$folders = (array) SMSS_Settings::get( 'folders' );
		$files   = SMSS_Drive::list_folder_recursive( $folder_id );

		if ( is_wp_error( $files ) ) {
			if ( isset( $folders[ $folder_id ] ) ) {
				$folders[ $folder_id ]['error'] = $files->get_error_message();
				SMSS_Settings::update( array( 'folders' => $folders ) );
			}
			return $files;
		}

		$report   = array( 'added' => 0, 'updated' => 0, 'removed' => 0 );
		$seen_ids = array();
		$only_nsw = SMSS_Settings::get( 'only_nsw' );

		// Everything already known, in one query: unchanged files cost nothing.
		$known_rows = array();
		foreach ( $wpdb->get_results( "SELECT gdrive_id, name, size, source, folder_id FROM {$table} WHERE gdrive_id IS NOT NULL" ) as $r ) {
			$known_rows[ $r->gdrive_id ] = $r;
		}

		$wpdb->query( 'START TRANSACTION' );
		foreach ( $files as $f ) {
			$name = SMSS_Links::clean_name( $f['name'] ?? '' );
			if ( '' === $name || ( $only_nsw && ! SMSS_Links::is_nsw( $name ) ) ) {
				continue;
			}
			$seen_ids[] = $f['id'];
			$old        = $known_rows[ $f['id'] ] ?? null;
			if ( $old && $old->name === $name && (int) $old->size === (int) ( $f['size'] ?? 0 ) && ( 'manual' === $old->source || $old->folder_id === $folder_id ) ) {
				continue;
			}
			$res        = self::upsert( array(
				'name'      => $name,
				'gdrive_id' => $f['id'],
				'url'       => null,
				'size'      => (int) ( $f['size'] ?? 0 ),
				'source'    => 'folder',
				'folder_id' => $folder_id,
			) );
			$report[ $res ]++;
		}
		$wpdb->query( 'COMMIT' );
		if ( $report['added'] || $report['updated'] ) {
			SMSS_Titles::enrich();
		}

		// Files deleted or moved out of the folder on Drive.
		$known = $wpdb->get_col( $wpdb->prepare( "SELECT gdrive_id FROM {$table} WHERE source = 'folder' AND folder_id = %s", $folder_id ) );
		$gone  = array_diff( $known, $seen_ids );
		foreach ( array_chunk( $gone, 200 ) as $chunk ) {
			$in = implode( ',', array_fill( 0, count( $chunk ), '%s' ) );
			$report['removed'] += (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE source = 'folder' AND gdrive_id IN ({$in})", $chunk ) );
		}

		if ( isset( $folders[ $folder_id ] ) ) {
			$folders[ $folder_id ]['last_sync'] = current_time( 'mysql' );
			$folders[ $folder_id ]['count']     = count( $seen_ids );
			$folders[ $folder_id ]['error']     = '';
			SMSS_Settings::update( array( 'folders' => $folders ) );
		}
		return $report;
	}

	public static function sync_all_folders() {
		foreach ( array_keys( (array) SMSS_Settings::get( 'folders' ) ) as $folder_id ) {
			self::sync_folder( $folder_id );
		}
	}

	public static function remove_folder( $folder_id, $delete_items = true ) {
		global $wpdb;
		$folders = (array) SMSS_Settings::get( 'folders' );
		unset( $folders[ $folder_id ] );
		SMSS_Settings::update( array( 'folders' => $folders ) );
		if ( $delete_items ) {
			$wpdb->delete( SMSS_DB::items_table(), array( 'source' => 'folder', 'folder_id' => $folder_id ) );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Reading                                                             */
	/* ------------------------------------------------------------------ */

	public static function count( $search = '', $only_enabled = false ) {
		global $wpdb;
		list( $where, $args ) = self::where( $search, $only_enabled );
		$sql = "SELECT COUNT(*) FROM " . SMSS_DB::items_table() . " {$where}";
		return (int) ( $args ? $wpdb->get_var( $wpdb->prepare( $sql, $args ) ) : $wpdb->get_var( $sql ) );
	}

	public static function total_size() {
		global $wpdb;
		return (float) $wpdb->get_var( "SELECT COALESCE(SUM(size),0) FROM " . SMSS_DB::items_table() . " WHERE enabled = 1" );
	}

	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . SMSS_DB::items_table() . ' WHERE id = %d', $id ) );
	}

	public static function query( $search = '', $page = 1, $per_page = 50 ) {
		global $wpdb;
		list( $where, $args ) = self::where( $search, false );
		$args[] = (int) $per_page;
		$args[] = (int) max( 0, ( $page - 1 ) * $per_page );
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM " . SMSS_DB::items_table() . " {$where} ORDER BY name ASC LIMIT %d OFFSET %d",
			$args
		) );
	}

	private static function where( $search, $only_enabled ) {
		global $wpdb;
		$clauses = array();
		$args    = array();
		if ( $only_enabled ) {
			$clauses[] = 'enabled = 1';
		}
		if ( '' !== $search ) {
			$like      = '%' . $wpdb->esc_like( $search ) . '%';
			$clauses[] = '(name LIKE %s OR title_id LIKE %s)';
			$args[]    = $like;
			$args[]    = $like;
		}
		return array( $clauses ? 'WHERE ' . implode( ' AND ', $clauses ) : '', $args );
	}

	/**
	 * The JSON document Tinfoil loads.
	 *
	 * @param object|null $account null = public shop (no login): every enabled item.
	 *                             Otherwise: free items + what this account bought,
	 *                             or everything for an "all access" account.
	 */
	public static function build_index( $account = null ) {
		global $wpdb;
		$settings = SMSS_Settings::all();
		$items    = SMSS_DB::items_table();
		if ( null === $account || (int) $account->all_access ) {
			$rows = $wpdb->get_results( "SELECT name, url, gdrive_id, size, title_id, display_name FROM {$items} WHERE enabled = 1 ORDER BY name ASC" );
		} else {
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT i.name, i.url, i.gdrive_id, i.size, i.title_id, i.display_name FROM {$items} i
				 WHERE i.enabled = 1 AND ( i.is_free = 1 OR i.id IN ( SELECT g.item_id FROM " . SMSS_DB::grants_table() . " g WHERE g.account_id = %d ) )
				 ORDER BY i.name ASC",
				$account->id
			) );
		}

		$files   = array();
		$titledb = array();
		foreach ( $rows as $row ) {
			// Custom title entry (as in TinShop): the name Tinfoil shows for this Title ID.
			if ( ! empty( $row->title_id ) && '' !== (string) $row->display_name ) {
				$titledb[ $row->title_id ] = array( 'id' => $row->title_id, 'name' => $row->display_name );
			}
			$files[] = array(
				'url'  => SMSS_Links::item_url( $row, $settings['drive_mode'] ),
				'size' => (int) $row->size,
			);
		}

		$index = array( 'files' => $files );
		if ( $titledb ) {
			$index['titledb'] = $titledb;
		}
		$shop = wp_parse_url( SMSS_Settings::shop_url() );
		if ( $settings['host_lock'] && 'https' === ( $shop['scheme'] ?? '' ) ) {
			$index['referrer'] = 'https://' . $shop['host'];
		}
		if ( ! $files && null !== $account ) {
			// Shown by Tinfoil: tells the buyer what to do instead of an empty screen.
			$index['success'] = 'Tai khoan ' . $account->username . ' chua co ban Viet hoa nao. Vao ' . preg_replace( '~^https?://~', '', SMSS_App::portal_url() ) . ' de mo khoa, lien he ' . $settings['contact'] . ' de duoc cong diem.';
		} elseif ( '' !== trim( $settings['motd'] ) ) {
			$index['success'] = $settings['motd'];
		}
		// Lets Tinfoil read public gdrive: files without the player signing in to Google.
		if ( 'gdrive' === $settings['drive_mode'] && '' !== trim( $settings['api_key'] ) ) {
			$index['googleApiKey'] = trim( $settings['api_key'] );
		}
		return $index;
	}
}
