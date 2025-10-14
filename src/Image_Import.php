<?php
namespace Woo_Import_Helpers;

class Image_Import {
	//By URL functions
	public static function return_image_id_by_url( $img_url, $post_id = null, $custom_folder = '' ) {
		if ( !$img_url ) {
			return;
		}

		if ( $custom_folder ) {
			global $custom_upload_dir;
			$custom_upload_dir = $custom_folder;

			add_filter( 'upload_dir', array( __CLASS__, 'change_upload_dir' ) );
		}

		if ( $img_id = attachment_url_to_postid( $img_url ) ) {
			return $img_id;
		}

		if ( $img_id = self::get_attachment_by_url( $img_url ) ) {
			return $img_id;
		}

		$attachment_id = self::upload_image_from_url( $img_url, $post_id );

		if ( $custom_folder ) {
			$custom_upload_dir = null;

			remove_filter( 'upload_dir', array( __CLASS__, 'change_upload_dir' ), $custom_folder );
		}

		return $attachment_id;
	}

	public static function upload_image_from_url( $url, $post_id = null ) {
		require_once( ABSPATH . 'wp-admin/includes/file.php' );
		require_once( ABSPATH . 'wp-admin/includes/image.php');

		$upload_dir = wp_upload_dir();
		$timeout_seconds = 5;

		// Download file to temp dir
		$temp_file = download_url( $url, $timeout_seconds );

		if ( is_wp_error( $temp_file ) ) {
			return;
		}

		$name = sanitize_file_name( basename( $url ) );
		$file = array(
			'name'     => $name,
			'tmp_name' => $temp_file,
			'error'    => 0,
			'size'     => filesize($temp_file),
		);

		$overrides = array(
			'test_form' => false,
			'test_size' => true,
		);

		// Move the temporary file into the uploads directory
		$file_return = wp_handle_sideload( $file, $overrides );

		if ( !empty( $file_return['error'] ) ) {
			return;
		}

		$attachment = array(
			'post_mime_type' => $file_return['type'],
			'post_title' => preg_replace('/\.[^.]+$/', '', basename($file_return['file'])),
			'post_content' => '',
			'post_status' => 'inherit',
			'guid' => $upload_dir['url'] . '/' . basename($file_return['file'])
		);

		$attach_id = wp_insert_attachment( $attachment, $file_return['file'], $post_id );

		$attach_data = wp_generate_attachment_metadata( $attach_id, $file_return['file'] );
		wp_update_attachment_metadata( $attach_id, $attach_data );
		update_post_meta( $attach_id, '_woo_tuning_original_url', esc_url( $url ) );

		return $attach_id;
	}

	public static function get_attachment_by_url( $image_url ) {
		$basename = explode( '.', sanitize_file_name( basename( $image_url ) ) );

		if ( $id = self::get_attachment_url_by_title( $basename[0] ) ) {
			return $id;
		}
		global $wpdb;

		$attachments =  $wpdb->get_results( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_woo_tuning_original_url' AND meta_value = \"" . esc_url( $image_url ) . "\"", OBJECT );

		if ( $attachments ){
			return $attachments[0]->post_id;
		}

		return;
	}

	public static function get_attachment_url_by_title( $title ) {
		global $wpdb;

		$attachments = $wpdb->get_results( "SELECT * FROM $wpdb->posts WHERE post_title = '$title' AND post_type = 'attachment' ", OBJECT );

		if ( $attachments ){
			return $attachments[0]->ID;
		}

		return;
	}

	public static function change_upload_dir( $args ) {
		global $custom_upload_dir;

		$args[ 'path' ] = $args['basedir'] . $custom_upload_dir;
		$args[ 'url' ] = $args['baseurl'] . $custom_upload_dir;
		$args[ 'subdir' ] = $custom_upload_dir;

		return $args;
	}

	//By PATH functions
	public static function return_image_id_by_dir( $image, $post_id = null, $custom_folder = '' ) {
		if ( !$image || !file_exists( $image ) ) {
			return;
		}

		if ( $custom_folder ) {
			global $custom_upload_dir;
			$custom_upload_dir = $custom_folder;

			add_filter( 'upload_dir', array( __CLASS__, 'change_upload_dir' ) );
		}

		$basename = explode( '.', sanitize_file_name( basename( $image ) ) );
		
		if ( $id = self::get_attachment_url_by_title( $basename[0] ) ) {
			return $id;
		}

		$attachment_id = self::upload_image_from_dir( $image, $post_id );

		if ( $custom_folder ) {
			$custom_upload_dir = null;

			remove_filter( 'upload_dir', array( __CLASS__, 'change_upload_dir' ), $custom_folder );
		}

		return $attachment_id;
	}

	public static function upload_image_from_dir( $temp_file, $post_id = null ) {
		$name = sanitize_file_name( basename( $temp_file ) );
		$file = array(
			'name'     => $name,
			'tmp_name' => $temp_file,
			'error'    => 0,
			'size'     => filesize($temp_file),
		);
		$overrides = array(
			'test_form' => false,
			'test_size' => true,
		);

		// Move the temporary file into the uploads directory
		$file_return = wp_handle_sideload( $file, $overrides );

		if ( !empty( $file_return['error'] ) ) {
			return;
		}

		$attachment = array(
			'post_mime_type' => $file_return['type'],
			'post_title' => preg_replace('/\.[^.]+$/', '', basename($file_return['file'])),
			'post_content' => '',
			'post_status' => 'inherit',
			'guid' => $upload_dir['url'] . '/' . basename($file_return['file'])
		);

		$attach_id = wp_insert_attachment( $attachment, $file_return['file'], $post_id );

		$attach_data = wp_generate_attachment_metadata( $attach_id, $file_return['file'] );
		wp_update_attachment_metadata( $attach_id, $attach_data );

		return $attach_id;
	}

	public static function upload_image_from_base64( $image, $name = 'attachment', $parent = null ) {
		if ( empty( $image[ 'data' ] ) ) {
			return false;
		}

		$upload_dir = wp_upload_dir();
		$upload_path = str_replace( '/', DIRECTORY_SEPARATOR, $upload_dir['path'] ) . DIRECTORY_SEPARATOR;

		$base64 = $image[ 'data' ];

		if ( !is_string($base64) ) {
			return;
		}
		
		$base64 = str_replace(array(
			'data:image/jpeg;base64,',
			'data:image/jpg;base64,',
			'data:image/png;base64,',
		), '', $base64);
		$base64 = str_replace(' ', '+', $base64);

		$decoded = base64_decode($base64);
		$extension = "." . str_replace( 'image/', '', $image[ 'type' ] );
		$filename = $name . $extension;
		$hashed_filename = md5( $filename . microtime() ) . $extension;

		$image_upload = file_put_contents( $upload_path . $hashed_filename, $decoded );

		if( !function_exists( 'wp_handle_sideload' ) ) {
		  require_once( ABSPATH . 'wp-admin/includes/file.php' );
		}

		$file             = array();
		$file['error']    = '';
		$file['tmp_name'] = $upload_path . $hashed_filename;
		$file['name']     = $hashed_filename;
		$file['type']     = $image[ 'type' ];
		$file['size']     = filesize( $upload_path . $hashed_filename );

		// upload file to server
		// @new use $file instead of $image_upload
		$file_return = wp_handle_sideload( $file, array( 'test_form' => false ) );

		$attachment = array(
			'post_mime_type' => $file_return['type'],
			'post_title' => preg_replace('/\.[^.]+$/', '', basename($file_return['file'])),
			'post_content' => '',
			'post_status' => 'inherit',
			'guid' => $upload_dir['url'] . DIRECTORY_SEPARATOR . basename($file_return['file'])
		 );

		$attach_id = wp_insert_attachment( $attachment, $file_return['file'], $parent );

		require_once( ABSPATH . 'wp-admin/includes/image.php');

		$attach_data = wp_generate_attachment_metadata( $attach_id, $file_return['file'] );

		wp_update_attachment_metadata( $attach_id, $attach_data );

		return $attach_id;
	}
}