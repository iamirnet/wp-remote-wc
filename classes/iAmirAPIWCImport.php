<?php

class iAmirAPIWCImport
{
    public static function importProducts($limit = 100) {
        global $iamirapidbwc;
        set_time_limit(58);
        $time = time();
        $items = $iamirapidbwc->getProducts(get_option("i_amir_remote_wc_products_import_last_id", 0), $limit);
        if ($items && is_array($items) && count($items)) {
            foreach ($items as $index => $item) {
                if ((time() - $time) > 50) return;
                foreach (["product_group", "product", "variants", "images"] as $name) {
                    $item->$name = json_decode($item->$name);
                }
                update_option("i_amir_remote_wc_products_import_last_id", $item->product->id);
                $iProduct = new iAmirAPIWCProduct($item);
                $iProduct->save();
                if($index > 10) return;
            }
            self::importProducts();
        }
    }

    public static function updateProducts($limit = 100) {
        global $iamirapidbwc;
        set_time_limit(58);
        $time = time();
        $skus =  $iamirapidbwc->getSkus(get_option("i_amir_remote_wc_products_update_last_id", 0), $limit);
        foreach ($skus as $index => $sku) {
            if ((time() - $time) > 50) return;
            $item = $iamirapidbwc->productBySku($sku);
            if ($item) {
                update_option("i_amir_remote_wc_products_update_last_id", iAmirAPIWCProduct::findBySKU($sku));
                foreach (["product_group", "product", "variants", "images"] as $name) {
                    $item->$name = json_decode($item->$name);
                }
                $iProduct = new iAmirAPIWCProduct($item);
                $iProduct->save();
            }
            break;
        }
    }

    public static function dispatchProducts() {
        global $iamirapidbwc;
        $lastTask = $iamirapidbwc->lastTask();
        if (!$lastTask || ($lastTask && $lastTask->state == "done")) {
            if ($lastTask && $lastTask->state == "done" && get_option("i_amir_remote_wc_products_dispatch_task_id", 0) != $lastTask->id) {
                if (isset($lastTask->extra->product_skus) && is_array($lastTask->extra->product_skus) && count($lastTask->extra->product_skus) == 1){
                    $post_id = iAmirAPIWCProduct::findBySKU($lastTask->extra->product_skus[0]);
                    if ($post_id && get_post_meta($post_id, "i_amir_remote_wc_product_update_task_id", true) != $lastTask->id) {
                        update_post_meta($post_id, "i_amir_remote_wc_product_update_task_id", $lastTask->id);
                        $item = $iamirapidbwc->productBySku($lastTask->extra->product_skus[0]);
                        if ($item) {
                            foreach (["product_group", "product", "variants", "images"] as $name) {
                                $item->$name = json_decode($item->$name);
                            }
                            $iProduct = new iAmirAPIWCProduct($item);
                            $iProduct->save();
                        }
                    }
                }else {
                    update_option("i_amir_remote_wc_products_update_last_id",0);
                    update_option("i_amir_remote_wc_products_dispatch_task_id",$lastTask->id);
                }
            }else {
                $iamirapidbwc->createTask(["product_skus" => $iamirapidbwc->getSkus()]);
            }
        }
    }

    public static function dispatchProduct($post_id) {
        global $iamirapidbwc;
        $lastTask = $iamirapidbwc->lastTask();
        $product = wc_get_product($post_id);
        $sku = $product->get_sku();

        if ($lastTask && isset($lastTask->extra) && isset($lastTask->extra->product_skus) && is_array($lastTask->extra->product_skus) && count($lastTask->extra->product_skus) == 1 && $lastTask->extra->product_skus[0] == $sku) {
            if ($lastTask->state == "done" && get_post_meta($post_id, "i_amir_remote_wc_product_update_task_id", true) != $lastTask->id) {
                update_post_meta($post_id, "i_amir_remote_wc_product_update_task_id", $lastTask->id);
                $item = $iamirapidbwc->productBySku($sku);
                if ($item) {
                    foreach (["product_group", "product", "variants", "images"] as $name) {
                        $item->$name = json_decode($item->$name);
                    }
                    $iProduct = new iAmirAPIWCProduct($item);
                    $iProduct->save();
                }
            }
            return $product;
        }
        $iamirapidbwc->createTask(["product_skus" => [(string)$sku]]);
    }
}

