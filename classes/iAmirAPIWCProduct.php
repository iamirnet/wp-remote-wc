<?php


class iAmirAPIWCProduct
{
    public static $meta_key_product_id = 'i_amir_remote_wc_product_id';
    public static $meta_key_category_id = 'i_amir_remote_wc_category_id';
    public static $meta_key_brand_id = 'i_amir_remote_wc_brand_id';
    public static $meta_key_variation_id = 'i_amir_remote_wc_variation_id';

    public $item = null;
    public $product;
    public $attributes = [];

    public function __construct($item)
    {
        $this->item = $item;
    }

    public static function find($id)
    {
        global $wpdb;
        $result = $wpdb->get_row("select {$wpdb->prefix}postmeta.post_id from {$wpdb->prefix}postmeta where meta_key = '" . static::$meta_key_product_id . "' and meta_value = '$id'");
        return $result;
    }

    public static function findBySKU($id)
    {
        global $wpdb;
        $result = $wpdb->get_results("select post_id from {$wpdb->prefix}postmeta where meta_key = '_sku' and meta_value = '$id'");
        return count($result) && isset($result[0]->post_id) ? $result[0]->post_id : null;
    }

    public static function findVariation($id)
    {
        global $wpdb;
        $result = $wpdb->get_results("select post_id from {$wpdb->prefix}postmeta where meta_key = '" . static::$meta_key_variation_id . "' and meta_value = '$id'");
        return count($result) && isset($result[0]->post_id) ? $result[0]->post_id : null;
    }

    public static function findTerm($id, $type) {
        global $wpdb;
        $result = $wpdb->get_results("select term_id from {$wpdb->prefix}_termmeta where meta_key = '".$type."' and meta_value = '$id'");
        return count($result) && isset($result[0]->term_id) ? $result[0]->term_id : null;
    }

    public function save()
    {
        $this->save_base();
        $this->save_images();
        $this->save_variations();
        return $this->product;
    }

    public function save_base()
    {
        global $iamirapidbwc;
        $id = static::find($this->item->product->id);
        $this->product = new WC_Product_Variable($id);
        $basename = "";
        if ($group = $iamirapidbwc->findGroupByCB($this->item->product_group->category_id, $this->item->product_group->brand_id))
            $basename = $group->data->name . " ";
        $this->product->set_name(trim($basename . $this->item->product->name));
        if (!$id) {
            $this->product->set_description($this->item->product->description);

            $this->product->set_virtual(false);

            $this->product->set_short_description(i_amir_remote_wc_short_text($this->item->product->description));

            $this->product->set_average_rating($this->item->product->rating_average);
            $this->product->set_sku($this->item->product->sku);
            $this->product->set_status('publish');
            $this->product->set_stock_status();
            if ($group && isset($group->data->categories) && $group->data->categories && (is_array($group->data->categories) || is_object($group->data->categories)) && count($group->data->categories))
                $this->product->set_category_ids(array_column($group->data->categories, "id"));
            $this->product->update_meta_data(static::$meta_key_product_id, $this->item->product->id);
            $this->product->update_meta_data(static::$meta_key_category_id, $this->item->product_group->category_id);
            $this->product->update_meta_data(static::$meta_key_brand_id, $this->item->product_group->brand_id);
        }

        return $this->product->save();
    }

    public function save_images()
    {
        if (isset($this->item->images) && $this->item->images && count($this->item->images)) {
            $images = [];
            foreach ($this->item->images as $ii => $img) {
                if ($image = iAmirAPIWCFile::find($img->id)) {
                    if ($ii == 0) $this->product->set_image_id($image);
                    else $images[] = $image;
                } else {
                    $image = new iAmirAPIWCFile($img->id, $img->url, $this->item->product->name, i_amir_remote_wc_short_text($this->item->product->description));
                    $image = $image->media_process();
                    if ($image->status) {
                        if ($ii == 0) $this->product->set_image_id($image->attach_id);
                        else $images[] = $image->attach_id;
                    }
                }
            }
            if (count($images)) {
                $this->product->set_gallery_image_ids($images);
            }
        }
        return $this->product->save();
    }

    public function save_variations()
    {
        foreach ($this->item->variants as $index => $variant) {
            $this->save_variation($variant);
            $this->product->set_attributes(array_values(array_column($this->attributes, 0)));
            $this->product->save();
        }
        return $this->product->save();
    }

    public function save_variation($item)
    {
        $id = static::findVariation($item->id);
        $variation = new WC_Product_Variation($id);
        $variation->set_parent_id($this->product->get_id());
        if ($item->attribute && is_string($item->attribute)) {
            $item->attribute = json_decode($item->attribute);
            $attribute = $this->save_attributes($item->attribute->type, $item->attribute->value);
            $this->attributes[strtolower($item->attribute->type)] = $attribute;
            $variation->set_attributes([wc_attribute_taxonomy_name(strtolower($item->attribute->type)) => sanitize_title(strtolower($item->attribute->value))]);
        }
        if(!$id) {
            $variation->set_status('publish');
            $variation->set_sku($item->sku);
            $variation->update_meta_data(static::$meta_key_variation_id, $item->id);
            $variation->update_meta_data("barcode", $item->barcode);
        }
        $price_coef = get_option('i_amir_remote_wc_price_coef', 1);

        $variation->set_price(ceil($item->price_selling * $price_coef));
        $variation->set_regular_price(ceil($item->price_original * $price_coef));

        $variation->set_sale_price(ceil($item->price_selling * $price_coef));

        $variation->set_stock_quantity($item->stock);
        $variation->set_stock_status();

        $v_id = $variation->save();
        $this->product->save();
        return $v_id;
    }

    public function save_attributes($name, $options)
    {
        $attributes = array();
        if (!empty($options)) {
            $base = static::create_attribute($name, is_array($options) ? $options : [$options]);
            $attributes = $base['term_ids'];
        }
        if (isset($this->attributes[strtolower($name)]) && isset($this->attributes[strtolower($name)][1]))
            $attributes = array_merge($attributes, $this->attributes[strtolower($name)][1]);
        $attribute = new WC_Product_Attribute();
        $attribute->set_id(wc_attribute_taxonomy_id_by_name(wc_attribute_taxonomy_name(strtolower($name))));
        $attribute->set_name(wc_attribute_taxonomy_name(strtolower($name)));
        $attribute->set_options(array_map('intval', $attributes));
        $attribute->set_visible(true);
        $attribute->set_variation(true);
        return [$attribute, $attributes];
    }

    public static function create_attribute( $raw_name = 'size', $terms = array( 'small' ) ) {
        global $wpdb, $wc_product_attributes;

        // Make sure caches are clean.
        delete_transient( 'wc_attribute_taxonomies' );
        WC_Cache_Helper::incr_cache_prefix( 'woocommerce-attributes' );

        // These are exported as labels, so convert the label to a name if possible first.
        $attribute_labels = wp_list_pluck( wc_get_attribute_taxonomies(), 'attribute_label', 'attribute_name' );
        $attribute_name   = array_search( $raw_name, $attribute_labels, true );

        if ( ! $attribute_name ) {
            $attribute_name = wc_sanitize_taxonomy_name( $raw_name );
        }

        $attribute_id = wc_attribute_taxonomy_id_by_name( $attribute_name );

        if ( ! $attribute_id ) {
            $taxonomy_name = wc_attribute_taxonomy_name( $attribute_name );

            // Degister taxonomy which other tests may have created...
            unregister_taxonomy( $taxonomy_name );

            $attribute_id = wc_create_attribute(
                array(
                    'name'         => ucfirst($raw_name),
                    'slug'         => $attribute_name,
                    'type'         => 'select',
                    'order_by'     => 'menu_order',
                    'has_archives' => 0,
                )
            );

            // Register as taxonomy.
            register_taxonomy(
                $taxonomy_name,
                apply_filters( 'woocommerce_taxonomy_objects_' . $taxonomy_name, array( 'product' ) ),
                apply_filters(
                    'woocommerce_taxonomy_args_' . $taxonomy_name,
                    array(
                        'labels'       => array(
                            'name' => $raw_name,
                        ),
                        'hierarchical' => false,
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
        }

        $attribute = wc_get_attribute( $attribute_id );
        $return    = array(
            'attribute_name'     => $attribute->name,
            'attribute_taxonomy' => $attribute->slug,
            'attribute_id'       => $attribute_id,
            'term_ids'           => array(),
        );

        foreach ( $terms as $term ) {
            $result = term_exists( $term, $attribute->slug );

            if ( ! $result ) {
                $result = wp_insert_term( $term, $attribute->slug );
                $return['term_ids'][] = $result['term_id'];
            } else {
                $return['term_ids'][] = $result['term_id'];
            }
        }

        return $return;
    }
}

