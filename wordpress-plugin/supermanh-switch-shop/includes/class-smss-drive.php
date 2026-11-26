<?php
defined( 'ABSPATH' ) || exit;

/**
 * Google Drive API v3 with an API key. Works for files and folders shared as
 * "Anyone with the link" (files inside a shared folder inherit its sharing).
 */
class SMSS_Drive {

	const API = 'https://www.googleapis.com/drive/v3/files';

	const FOLDER_MIME = 'application/vnd.google-apps.folder';

	/** Safety net against a folder tree that never ends. */
	const MAX_FILES = 20000;

	private static function key() {
		return trim( (string) SMSS_Settings::get( 'api_key' ) );
	}

	public static function has_key() {
		return '' !== self::key();
	}

	/**
	 * @return array|WP_Error Decoded JSON.
	 */
	private static function request( $url, array $query ) {
		if ( ! self::has_key() ) {
			return new WP_Error( 'smss_no_key', 'Chưa nhập Google API key trong phần Cài đặt.' );
		}
		$query['key']               = self::key();
		$query['supportsAllDrives'] = 'true';
		$res = wp_remote_get( add_query_arg( array_map( 'rawurlencode', $query ), $url ), array( 'timeout' => 20 ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( 200 !== $code ) {
			$msg = isset( $body['error']['message'] ) ? $body['error']['message'] : 'HTTP ' . $code;
			if ( 404 === $code ) {
				$msg = 'Không tìm thấy hoặc chưa chia sẻ "Bất kỳ ai có đường liên kết". (' . $msg . ')';
			}
			return new WP_Error( 'smss_drive_' . $code, 'Google Drive: ' . $msg );
		}
		return is_array( $body ) ? $body : new WP_Error( 'smss_drive_json', 'Google Drive trả về dữ liệu lạ.' );
	}

	/**
	 * @return array|WP_Error {id, name, size, mimeType}
	 */
	public static function get_file( $id ) {
		return self::request( self::API . '/' . rawurlencode( $id ), array(
			'fields' => 'id,name,size,mimeType',
		) );
	}

	/**
	 * Every file below a folder, sub-folders included.
	 *
	 * @return array|WP_Error list of {id, name, size, mimeType}
	 */
	public static function list_folder_recursive( $folder_id ) {
		$files   = array();
		$queue   = array( $folder_id );
		$visited = array();

		while ( $queue ) {
			$current = array_shift( $queue );
			if ( isset( $visited[ $current ] ) ) {
				continue;
			}
			$visited[ $current ] = true;
			$page_token = '';

			do {
				$query = array(
					'q'                         => "'" . $current . "' in parents and trashed = false",
					'fields'                    => 'nextPageToken,files(id,name,size,mimeType)',
					'pageSize'                  => '1000',
					'includeItemsFromAllDrives' => 'true',
				);
				if ( $page_token ) {
					$query['pageToken'] = $page_token;
				}
				$body = self::request( self::API, $query );
				if ( is_wp_error( $body ) ) {
					return $body;
				}
				foreach ( (array) ( $body['files'] ?? array() ) as $f ) {
					if ( self::FOLDER_MIME === ( $f['mimeType'] ?? '' ) ) {
						$queue[] = $f['id'];
					} else {
						$files[] = $f;
					}
				}
				if ( count( $files ) > self::MAX_FILES ) {
					return new WP_Error( 'smss_too_many', 'Thư mục có hơn ' . self::MAX_FILES . ' file, hãy chia nhỏ.' );
				}
				$page_token = $body['nextPageToken'] ?? '';
			} while ( $page_token );
		}
		return $files;
	}

	/** Folder name, for display. */
	public static function folder_name( $folder_id ) {
		$body = self::get_file( $folder_id );
		if ( is_wp_error( $body ) ) {
			return $body;
		}
		if ( self::FOLDER_MIME !== ( $body['mimeType'] ?? '' ) ) {
			return new WP_Error( 'smss_not_folder', 'Link này là file, không phải thư mục.' );
		}
		return $body['name'];
	}
}
