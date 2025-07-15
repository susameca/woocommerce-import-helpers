<?php
namespace Woo_Import_Helpers;

class Helpers {
	public static function import_images( $all_images, $product_id, $custom_folder = '' ) {
		$wc_product = wc_get_product( $product_id );
		$gallery = [];

		if ( ! $wc_product || empty( $all_images ) ) {
			return;
		}

		foreach ( $all_images as $image ) {
			if ( wp_http_validate_url( $image ) ) {
				$image_id = Image_Import::return_image_id_by_url( $image, $wc_product->get_id(), $custom_folder );
			} else {
				$image_id = Image_Import::return_image_id_by_dir( $image, $wc_product->get_id(), $custom_folder );
			}

			if ( $image_id ) {
				$gallery[] = $image_id;
			}
		}

		if ( !empty( $gallery ) ) {
			$featured_image_id = array_shift( $gallery );

			$wc_product->set_image_id( $featured_image_id );

			if ( !empty( $gallery ) ) {
				$wc_product->set_gallery_image_ids( $gallery );
			}

			$wc_product->save();
		}
	}

	public static function get_attribute_taxonomy_id( $raw_name, $raw_slug ) {
		global $wpdb, $wc_product_attributes;

		// These are exported as labels, so convert the label to a name if possible first.
		$attribute_labels = wp_list_pluck( wc_get_attribute_taxonomies(), 'attribute_label', 'attribute_name' );
		$attribute_name   = array_search( $raw_slug, $attribute_labels, true );

		if ( ! $attribute_name ) {
			$attribute_name = wc_sanitize_taxonomy_name( $raw_slug );
		}

		$attribute_id = wc_attribute_taxonomy_id_by_name( $attribute_name );

		// Get the ID from the name.
		if ( $attribute_id ) {
			return $attribute_id;
		}

		// If the attribute does not exist, create it.
		$attribute_id = wc_create_attribute(
			array(
				'name'         => $raw_name,
				'slug'         => $attribute_name,
				'type'         => 'select',
				'order_by'     => 'menu_order',
				'has_archives' => false,
			)
		);

		if ( is_wp_error( $attribute_id ) ) {
			throw new \Exception( $attribute_id->get_error_message(), 400 );
		}

		// Register as taxonomy while importing.
		$taxonomy_name = wc_attribute_taxonomy_name( $attribute_name );
		register_taxonomy(
			$taxonomy_name,
			apply_filters( 'woocommerce_taxonomy_objects_' . $taxonomy_name, array( 'product' ) ),
			apply_filters(
				'woocommerce_taxonomy_args_' . $taxonomy_name,
				array(
					'labels'       => array(
						'name' => $raw_name,
					),
					'hierarchical' => true,
					'show_ui'      => false,
					'query_var'    => true,
					'rewrite'      => false,
				)
			)
		);

		// Set product attributes global.
		$wc_product_attributes = array();

		foreach ( wc_get_attribute_taxonomies() as $taxonomy ) {
			$wc_product_attributes[ wc_attribute_taxonomy_name( $taxonomy->attribute_name ) ] = $taxonomy;
		}

		return $attribute_id;
	}

	public static function get_term_id( $category, $taxonomy = 'product_cat' ) {
		$slug = sanitize_title_with_dashes( self::to_latin( $category ) );

		if ( ! term_exists( $slug, $taxonomy ) ) {
			$category_id = wp_insert_term( $category, $taxonomy, array( 'slug' => $slug ) );

			if ( is_array( $category_id ) ) {
                $category_id = $category_id['term_id'];
            }
		} else {
			$category_id = get_term_by( 'slug', $slug, $taxonomy )->term_id;
		}

		return $category_id;
	}

	public static function to_latin( $string ) {
	    $gost = [
	        "а" => "a", "б" => "b", "в" => "v", "г" => "g", "д" => "d",
	        "е" => "e", "ё" => "yo", "ж" => "j", "з" => "z", "и" => "i",
	        "й" => "ji", "к" => "c", "л" => "l", "м" => "m", "н" => "n",
	        "о" => "o", "п" => "p", "р" => "r", "с" => "s", "т" => "t",
	        "у" => "y", "ф" => "f", "х" => "h", "ц" => "c", "ч" => "ch",
	        "ш" => "sh", "щ" => "sch", "ы" => "ie", "э" => "e", "ю" => "u",
	        "я" => "ya",
	        "А" => "A", "Б" => "B", "В" => "V", "Г" => "G", "Д" => "D",
	        "Е" => "E", "Ё" => "Yo", "Ж" => "J", "З" => "Z", "И" => "I",
	        "Й" => "Ji", "К" => "C", "Л" => "L", "М" => "M", "Н" => "N",
	        "О" => "O", "П" => "P", "Р" => "R", "С" => "S", "Т" => "T",
	        "У" => "U", "Ф" => "F", "Х" => "H", "Ц" => "Ts", "Ч" => "Ch",
	        "Ш" => "Sh", "Щ" => "Sht", "Ы" => "Ie", "Э" => "E", "Ю" => "IY",
	        "Я" => "Ya",
	        "ь" => "'", "Ь" => "_'", "ъ" => "''", "Ъ" => "_''",
	        "ї" => "yi",
	        "і" => "i",
	        "ґ" => "ge",
	        "є" => "ye",
	        "Ї" => "Yi",
	        "І" => "I",
	        "Ґ" => "Ge",
	        "Є" => "YE",
	    ];

	    return strtr( $string, $gost );
	}

	public static function reserved_taxonomies() {
		return [
			'action',
			'attachment',
			'attachment_id',
			'author',
			'author_name',
			'calendar',
			'cat',
			'category',
			'category__and',
			'category__in',
			'category__not_in',
			'category_name',
			'comments_per_page',
			'comments_popup',
			'cpage',
			'custom',
			'customize_messenger_channel',
			'customized',
			'date',
			'day',
			'debug',
			'embed',
			'error',
			'exact',
			'feed',
			'fields',
			'hour',
			'include',
			'link_category',
			'm',
			'minute',
			'monthnum',
			'more',
			'name',
			'nav_menu',
			'nonce',
			'nopaging',
			'offset',
			'order',
			'orderby',
			'output',
			'p',
			'page',
			'page_id',
			'paged',
			'pagename',
			'pb',
			'perm',
			'post',
			'post__in',
			'post__not_in',
			'post_format',
			'post_mime_type',
			'post_status',
			'post_tag',
			'post_type',
			'posts',
			'posts_per_archive_page',
			'posts_per_page',
			'preview',
			'robots',
			's',
			'search',
			'search_terms',
			'second',
			'sentence',
			'showposts',
			'static',
			'status',
			'subpost',
			'subpost_id',
			'tag',
			'tag__and',
			'tag__in',
			'tag__not_in',
			'tag_id',
			'tag_slug__and',
			'tag_slug__in',
			'taxonomy',
			'tb',
			'term',
			'terms',
			'theme',
			'themes',
			'title',
			'type',
			'types',
			'w',
			'withcomments',
			'withoutcomments',
			'year',
		];
	}
}