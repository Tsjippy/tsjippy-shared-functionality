<?php
namespace SIM;

if ( ! defined( 'ABSPATH' ) ) exit;

// run on activation
add_action( 'activated_plugin', function ( $plugin ) {
    if( $plugin != PLUGIN ) {
        return;
    }

    // Create private upload folder
    $path   = wp_upload_dir()['basedir'].'/private';
    if (!is_dir($path)) {
        wp_mkdir_p($path);
    }
    
    $family = new FAMILY\Family();
    $family->createDbTables();

    //redirect after plugin activation
    exit( esc_url(wp_safe_redirect( admin_url( esc_url('admin.php?page=sim') ) ) ) );
} );