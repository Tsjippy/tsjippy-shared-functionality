<?php
namespace TSJIPPY;

/**
 * Plugin Name:  		Shared Functionality For Others 
 * Description:  		Shared functionality for a bundle of 34 plugins to add AJAX login, forms and other functionality
 * Version:      		10.2.1
 * Author:       		Ewald Harmsen
 * Author URI:			https://harmseninnigeria.nl
 * Requires at least:	6.3
 * Requires PHP: 		8.3
 * Tested up to: 		6.9
 * Plugin URI:			https://github.com/Tsjippy/shared-functionality/
 * Tested:				6.9	
 * TextDomain:			tsjippy
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 *
 * @author Ewald Harmsen
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

//do not run for other stuff then documents
if(!isset($_SERVER['HTTP_SEC_FETCH_DEST'])){
	//error_log(print_r($_SERVER,true));
}elseif(!in_array($_SERVER['HTTP_SEC_FETCH_DEST'], ['document','empty','iframe'])){
	// Do not run plugin when requesting an image
	exit;
}else{
	//error_log(print_r($_SERVER,true));
}

//only call it once
//remove_action( 'wp_head', 'adjacent_posts_rel_link');
define(__NAMESPACE__ .'\PLUGIN', plugin_basename(__FILE__));
define(__NAMESPACE__ .'\PLUGINPATH', __DIR__.'/');
define(__NAMESPACE__ .'\PLUGINSLUG', str_replace('tsjippy-', '', basename(__FILE__, '.php')));
define(__NAMESPACE__ .'\SETTINGS', get_option('tsjippy_settings', []));

$files = glob(__DIR__  . '/*.php');
foreach ($files as $file) {
    require_once($file);
}

// run before activation
register_activation_hook( __FILE__, function(){
    // Create private upload folder
    $path   = wp_upload_dir()['basedir'].'/private';
    if (!is_dir($path)) {
        wp_mkdir_p($path);
    }
    
    require_once(PLUGINPATH.'/includes/default_modules/family/php/classes/Family.php');

    $family = new FAMILY\Family();
    $family->createDbTables();
} );

//Register a function to run on plugin deactivation
register_deactivation_hook( __FILE__, __NAMESPACE__.'\onDeactivation');
function onDeactivation() {
	wp_clear_scheduled_hook( 'update_plugin_action' );
}

// Run after activation
add_action( 'activated_plugin', function($plugin){
    /**
     * Redirect to settings page after plugin activation
     * If it is activated from the plugins page and not in bulk
     */ 
    if(
        str_contains($plugin, 'tsjippy') &&
        (
            !isset($_REQUEST['bulk_action'] ) ||
            $_REQUEST['bulk_action'] != 'Apply'
        ) &&
        $_REQUEST['action'] == 'activate'
    ){
        $page   = basename($plugin, '.php');

        if($plugin == PLUGIN){
            $page = 'tsjippy';
        }
        exit( wp_safe_redirect( esc_url(admin_url("admin.php?page=$page") )  ) );
    }
});