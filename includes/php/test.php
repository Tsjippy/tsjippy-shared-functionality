<?php
namespace SIM;

//Shortcode for testing
add_shortcode("test", function ($atts){
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    require_once ABSPATH . 'wp-admin/install-helper.php';

    global $wpdb;
    global $Modules;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    require_once ABSPATH . 'wp-admin/install-helper.php';

    $args = array(
        'post_type'      => 'attachment',
        'post_mime_type' => 'image/jpeg', // Uses a wildcard internally (image/*)
        'numberposts'    => -1,
        'post_status'    => 'any',
    );

    $images = get_posts( $args );

    foreach( $images as $image){
        if(strpos($image->guid, '.jpe') === false){
            continue;
        }
        $path = get_attached_file( $image->ID, true);

        if(!file_exists($path)){
            $ext    = pathinfo($path, PATHINFO_EXTENSION);

            $path   = str_replace( '.'.$ext, '.jpg', $path );

            if(!file_exists($path)){
                $path = str_replace( '.jpg', '.jpeg', $path );
            }

            if(!file_exists($path)){
                continue;
            }
        }

        update_attached_file( $image->ID, $path );
    }

});

// turn off incorrect error on localhost
add_filter( 'wp_mail_smtp_core_wp_mail_function_incorrect_location_notice', '__return_false' );
