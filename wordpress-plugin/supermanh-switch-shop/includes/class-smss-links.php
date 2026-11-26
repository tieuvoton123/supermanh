<?php
defined( 'ABSPATH' ) || exit;

/**
 * Understanding the links the admin types, and building the URLs Tinfoil reads.
 */
class SMSS_Links {

	const NSW_EXT = array( 'nsp', 'nsz', 'xci', 'xcz' );

	/**
	 * Classify one link.
	 *
	 * @return array{type:string,id?:string,url?:string} type is drive_file, drive_folder, http or invalid.
	 */
	public static function parse( $link ) {
		$link = trim( $link );
		if ( '' === $link ) {
			return array( 'type' => 'invalid' );
		}

		// Already in Tinfoil form: gdrive:ID or gdrive:/ID, with an optional #name.
		if ( preg_match( '~^gdrive:/?([A-Za-z0-9_-]{10,})~', $link, $m ) ) {
			return array( 'type' => 'drive_file', 'id' => $m[1] );
		}

		$host = strtolower( (string) wp_parse_url( $link, PHP_URL_HOST ) );
		$is_drive = in_array( $host, array( 'drive.google.com', 'docs.google.com', 'drive.usercontent.google.com' ), true );

		if ( $is_drive ) {
			if ( preg_match( '~/(?:drive/(?:u/\d+/)?)?folders/([A-Za-z0-9_-]{10,})~', $link, $m ) ) {
				return array( 'type' => 'drive_folder', 'id' => $m[1] );
			}
			if ( preg_match( '~/file/d/([A-Za-z0-9_-]{10,})~', $link, $m ) ) {
				return array( 'type' => 'drive_file', 'id' => $m[1] );
			}
			$query = array();
			wp_parse_str( (string) wp_parse_url( $link, PHP_URL_QUERY ), $query );
			if ( ! empty( $query['id'] ) && preg_match( '~^[A-Za-z0-9_-]{10,}$~', $query['id'] ) ) {
				return array( 'type' => 'drive_file', 'id' => $query['id'] );
			}
			return array( 'type' => 'invalid' );
		}

		// A bare Drive ID (no scheme): treat as a file.
		if ( preg_match( '~^[A-Za-z0-9_-]{25,}$~', $link ) ) {
			return array( 'type' => 'drive_file', 'id' => $link );
		}

		// Syntax only: wp_http_validate_url() would resolve DNS here. Fetches go
		// through wp_safe_remote_*(), which blocks private and local addresses.
		// Spaces and Vietnamese characters pasted raw are percent-encoded first.
		$link = preg_replace_callback( '/[^\x21-\x7E]+/u', function ( $m ) {
			return rawurlencode( $m[0] );
		}, $link );
		$bare = preg_replace( '~#.*$~', '', $link );
		if ( preg_match( '~^https?://[^/\s]+~i', $bare ) && false !== filter_var( $bare, FILTER_VALIDATE_URL ) ) {
			return array( 'type' => 'http', 'url' => $link );
		}

		return array( 'type' => 'invalid' );
	}

	/**
	 * One line of the "add links" box: "link" or "link | file name" or "link | file name | size".
	 */
	public static function parse_line( $line ) {
		$parts = array_map( 'trim', explode( '|', $line ) );
		return array(
			'link' => $parts[0],
			'name' => isset( $parts[1] ) ? self::clean_name( $parts[1] ) : '',
			'size' => isset( $parts[2] ) ? self::parse_size( $parts[2] ) : 0,
		);
	}

	public static function clean_name( $name ) {
		$name = wp_strip_all_tags( (string) $name );
		$name = str_replace( array( '/', '\\', "\0", "\r", "\n", '#' ), ' ', $name );
		return trim( preg_replace( '/\s+/u', ' ', $name ) );
	}

	/** "12345", "1.5G", "700 MB" -> bytes. */
	public static function parse_size( $text ) {
		$text = strtoupper( str_replace( array( ' ', ',' ), array( '', '.' ), (string) $text ) );
		if ( ! preg_match( '/^([0-9.]+)([KMGT]?)I?B?$/', $text, $m ) ) {
			return 0;
		}
		$mult = array( '' => 1, 'K' => 1024, 'M' => 1048576, 'G' => 1073741824, 'T' => 1099511627776 );
		return (int) round( (float) $m[1] * $mult[ $m[2] ] );
	}

	public static function name_from_url( $url ) {
		$fragment = wp_parse_url( $url, PHP_URL_FRAGMENT );
		if ( $fragment ) {
			return self::clean_name( rawurldecode( $fragment ) );
		}
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		return self::clean_name( rawurldecode( basename( $path ) ) );
	}

	public static function extension( $name ) {
		return strtolower( pathinfo( (string) $name, PATHINFO_EXTENSION ) );
	}

	public static function is_nsw( $name ) {
		return in_array( self::extension( $name ), self::NSW_EXT, true );
	}

	/** The [0100XXXXXXXXXXXX] Title ID Tinfoil reads from the file name. */
	public static function title_id( $name ) {
		if ( preg_match( '/\[([0-9A-Fa-f]{16})\]/', (string) $name, $m ) ) {
			return strtoupper( $m[1] );
		}
		return null;
	}

	/**
	 * Percent-encode a file name for the URL fragment, the way Tinfoil index
	 * generators do: controls, space, " < > ` and non-ASCII bytes, plus # and %.
	 */
	public static function encode_fragment( $name ) {
		$out = '';
		$len = strlen( $name );
		for ( $i = 0; $i < $len; $i++ ) {
			$c   = $name[ $i ];
			$ord = ord( $c );
			if ( $ord < 0x21 || $ord > 0x7E || strpos( '"<>`#%', $c ) !== false ) {
				$out .= sprintf( '%%%02X', $ord );
			} else {
				$out .= $c;
			}
		}
		return $out;
	}

	/** URL Tinfoil downloads for an item row. */
	public static function item_url( $item, $drive_mode ) {
		$name = self::encode_fragment( $item->name );
		if ( ! empty( $item->gdrive_id ) ) {
			if ( 'https' === $drive_mode ) {
				return 'https://drive.usercontent.google.com/download?id=' . rawurlencode( $item->gdrive_id ) . '&export=download&confirm=t#' . $name;
			}
			return 'gdrive:' . $item->gdrive_id . '#' . $name;
		}
		$url = preg_replace( '~#.*$~', '', (string) $item->url );
		return $url . '#' . $name;
	}

	/**
	 * File size of a plain HTTP link, from a HEAD request, falling back to a
	 * one-byte range GET for servers that do not answer HEAD.
	 */
	public static function remote_size( $url ) {
		$url  = preg_replace( '~#.*$~', '', $url );
		$args = array( 'timeout' => 15, 'redirection' => 5 );

		$res = wp_safe_remote_head( $url, $args );
		if ( ! is_wp_error( $res ) && 200 === (int) wp_remote_retrieve_response_code( $res ) ) {
			$len = (int) wp_remote_retrieve_header( $res, 'content-length' );
			if ( $len > 0 ) {
				return $len;
			}
		}

		$args['headers'] = array( 'Range' => 'bytes=0-0' );
		$args['limit_response_size'] = 1024;
		$res = wp_safe_remote_get( $url, $args );
		if ( ! is_wp_error( $res ) ) {
			$range = (string) wp_remote_retrieve_header( $res, 'content-range' );
			if ( preg_match( '~/(\d+)$~', $range, $m ) ) {
				return (int) $m[1];
			}
		}
		return 0;
	}

	public static function human_size( $bytes ) {
		$bytes = (float) $bytes;
		if ( $bytes <= 0 ) {
			return '—';
		}
		$units = array( 'B', 'KB', 'MB', 'GB', 'TB' );
		$i     = (int) floor( log( $bytes, 1024 ) );
		$i     = min( $i, count( $units ) - 1 );
		return round( $bytes / pow( 1024, $i ), $i >= 3 ? 2 : 1 ) . ' ' . $units[ $i ];
	}
}
