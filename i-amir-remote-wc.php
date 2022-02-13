<?php
/*
Plugin Name: آی امیر - افزونه دریافت اطلاعات
Plugin URI: https://iamir.net
Description: افزونه ای برای دریافت اطلاعات از طریق API
Author: Amirhossein Jahani
Version: 1.0.0
Author URI: https://iamir.net
*/
global $iamirapidbwc;

require_once("includes/functions.php");
require_once("includes/request.php");
require_once i_amir_remote_wc_path('classes/iAmirAPIWCDB.php');
$iamirapidbwc = new iAmirAPIWCDB();
require_once i_amir_remote_wc_path('classes/iAmirAPIWCFile.php');
require_once i_amir_remote_wc_path('classes/iAmirAPIWCProduct.php');
require_once i_amir_remote_wc_path('classes/iAmirAPIWCImport.php');

require_once("includes/pages/admin.php");


function i_amir_remote_wc_import_products_in_an_minute()
{

    iAmirAPIWCImport::importProducts();
}

function i_amir_remote_wc_dispatch_products_in_an_minute()
{

    iAmirAPIWCImport::dispatchProducts();
}

function i_amir_remote_wc_update_products_in_an_minute()
{

    iAmirAPIWCImport::updateProducts();
}

if (get_option("i_amir_remote_wc_status", 'inactive') == "active") {
    add_action('i_amir_remote_wc_update_products_event', 'i_amir_remote_wc_update_products_in_an_minute');
    wp_schedule_single_event(60, 'i_amir_remote_wc_update_products_event');
    add_action('i_amir_remote_wc_dispatch_products_event', 'i_amir_remote_wc_dispatch_products_in_an_minute');
    wp_schedule_single_event(60, 'i_amir_remote_wc_dispatch_products_event');
    add_action('i_amir_remote_wc_import_products_event', 'i_amir_remote_wc_import_products_in_an_minute');
    wp_schedule_single_event(60, 'i_amir_remote_wc_import_products_event');
}

add_filter('post_row_actions', 'i_amir_remote_wc_modify_list_row_actions', 10, 2);

function i_amir_remote_wc_modify_list_row_actions($actions, $post)
{
    if ($post->post_type == "product") {
        $url = admin_url('admin.php?page=i_amir_remote_wc&action=product_update&post=' . $post->ID);
        if (current_user_can('edit_product', $post->ID)) {
            $actions = array_merge($actions, array(
                'i_amir_remote_wc_update' => sprintf('<a href="%1$s">%2$s</a>',
                    esc_url($url),
                    'بروزسانی با API'
                )
            ));
        }
    }
    return $actions;
}
add_action( 'post_submitbox_minor_actions', 'i_amir_remote_wc_post_submitbox_minor_actions' , 30);

function i_amir_remote_wc_post_submitbox_minor_actions( $post ) {

    if ($post->post_type == "product") {
        $url = admin_url('admin.php?page=i_amir_remote_wc&action=product_update&post=' . $post->ID);
        if (current_user_can('edit_product', $post->ID)) {
            echo sprintf('<div id="update-api-action"><a href="%1$s" class="button button-success" style="margin-left: 5px">%2$s</a></div>',
                esc_url($url),
                'بروزسانی با API'
            );
        }
    }
}