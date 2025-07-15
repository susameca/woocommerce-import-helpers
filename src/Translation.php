<?php
namespace Woo_Import_Helpers;

class Translate {
	private const BASE_URL = 'https://api-free.deepl.com/v2/';
	private const CACHE_FOLDER = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'woo-import-helpers/translations' . DIRECTORY_SEPARATOR;

	private $auth_key = '';

	function __construct( $auth_key ) {
		$this->auth_key = $auth_key;
	}

	public function title( $text_to_translate ) {
		$cached = self::get_cached( $text_to_translate );

		if ( $cached ) {
			return $cached;
		}

		$translated = 'not translated';
		$base_url = 'https://api-free.deepl.com/v2/';
		$authKey = "b282002c-a51a-4ce1-a601-fe351c272dd8:fx";
		$headers = [
			'Authorization' => 'DeepL-Auth-Key ' . $this->auth_key,
			'Content-Type' => 'application/json',
		];

		$usage_request = wp_remote_get( self::BASE_URL . "usage", [
			'timeout'     => 60,
			'headers' => $headers,
		] );

		$usage = json_decode( wp_remote_retrieve_body( $usage_request ), 1 );

		if ( $usage['character_count'] + strlen( $text_to_translate ) >= $usage['character_limit'] ) {
			error_log( $text_to_translate );
			var_dump( $usage_request );
			error_log( wp_remote_retrieve_body( $usage_request ) );
			exit;
		}

		$translate_data = [
			'source_lang' => 'EN',
			'target_lang' => 'BG',
			'text' => [ $text_to_translate ],
		];

		$translate_request = wp_remote_post( self::BASE_URL . "translate", [
			'headers' => $headers,
			'body' => json_encode( $translate_data ),
		] );

		$translated_data = json_decode( wp_remote_retrieve_body( $translate_request ), 1 );

		foreach ( $translated_data['translations'] as $translation ) {
			if ( $translation['detected_source_language'] === 'EN' ) {
				$translated = $translation['text'];
				break;
			}
		}

		self::cache_text( $text_to_translate, $translated );

		return $translated;
	}

	public static function get_cached( $text ) {
		$data = false;
		$file = self::CACHE_FOLDER . md5( $text ) . '.txt';

		if ( file_exists( $file ) ) {
			global $wp_filesystem;

			if ( ! $wp_filesystem ) {
				require_once( ABSPATH . 'wp-admin/includes/file.php' );
				WP_Filesystem();
			}

			$data = $wp_filesystem->get_contents( $file );
		}

		return $data;
	}

	public static function cache_text( $text_to_translate, $translated ) {
		$file = self::CACHE_FOLDER . md5( $text_to_translate ) . '.txt';

		if ( ! is_dir( self::CACHE_FOLDER ) ) {
			wp_mkdir_p( self::CACHE_FOLDER );
		}

		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			require_once( ABSPATH . 'wp-admin/includes/file.php' );
			WP_Filesystem();
		}

		$wp_filesystem->put_contents(
			$file,
			$translated,
			FS_CHMOD_FILE
		);
	}
}