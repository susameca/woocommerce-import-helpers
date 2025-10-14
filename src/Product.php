<?php
namespace Woo_Import_Helpers;

class Product {
	private $wc_product, $sku, $name, $long_desc, $short_desc, $images, $category, $brand, $attributes, $price, $sale_price;

	function __construct() {
		
	}

	public static function import_images( $all_images ) {
        $this->wc_product = wc_get_product( $product_id );
        $custom_folder = '/products/mts/' . $this->wc_product->get_sku();

        $gallery = [];

        if ( ! $this->wc_product ) {
            return;
        }

        $all_images = array_filter( array_unique( $all_images ) );

        $primary_image = array_shift( $all_images );
        $featured_image_id = Image_Import::return_image_id_by_dir( $primary_image, $this->wc_product->get_id(), $custom_folder );

        if ( $featured_image_id ) {
            $this->wc_product->set_image_id( $featured_image_id );
        }

        if ( !empty( $all_images ) ) {
            foreach ( $all_images as $image ) {
                $image = preg_replace('/\s+/', '', $image);

                if ( $image_id = Image_Import::return_image_id_by_dir( $image, $this->wc_product->get_id(), $custom_folder ) ) {
                    $gallery[] = $image_id;
                }
            }
        }

        $this->wc_product->set_gallery_image_ids( $gallery );
        $this->wc_product->save();
    }
}