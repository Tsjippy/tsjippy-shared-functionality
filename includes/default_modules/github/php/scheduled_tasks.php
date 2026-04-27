<?php
namespace TSJIPPY\GITHUB;
use TSJIPPY;

if ( ! defined( 'ABSPATH' ) ) exit;

add_action('init', __NAMESPACE__.'\init');
function init(){
	//add action for use in scheduled task
	add_action( 'update_plugin_action', __NAMESPACE__.'\checkForPluginUpdates' );
}

function scheduleTasks(){
    TSJIPPY\scheduleTask('update_plugin_action', 'daily');
}

function checkForPluginUpdates(){

	// Do not run on localhost
	if(wp_get_environment_type() === 'local'){
		return;
	}

	// update the plugin first
	$url    = self_admin_url( 'update.php?action=upgrade-plugin&plugin=' . urlencode( TSJIPPY\PLUGINNAME ) );
    $url    = wp_nonce_url( $url, 'bulk-update-plugins' );
	file_get_contents($url);

	// Now check for module updates
	$github	= new Github();
	foreach(wp_get_active_and_valid_plugins() as $plugin){

		if(strpos($plugin, 'tsjippy-') === false ){
			continue;
        }

		$slug   	= str_replace('tsjippy-', '', basename($plugin, '.php'));
		$nameSpace	= strtoupper($slug);

		// inactive module
		if( ! defined("TSJIPPY\\$nameSpace\\PLUGINVERSION") ){
			TSJIPPY\printArray("Constant does not exist for $slug ");
			continue;
		}

		$oldVersion	= false;

		$oldVersion	= constant("TSJIPPY\\$nameSpace\\PLUGINVERSION");
		
		$release	= $github->getLatestRelease('Tsjippy', $slug, true);

		if(is_wp_error($release)){
			TSJIPPY\printArray("Error checking for update for plugin $slug: ");
			TSJIPPY\printArray($release);
			continue;
		}

		$newVersion	= $release['tag_name'];

		// Download the new version
		//TSJIPPY\printArray("Name: $module. Current Version $oldVersion, new version $newVersion. ");
		if(version_compare($newVersion, $oldVersion)){
			TSJIPPY\printArray("Updating $slug");
			
            $github->downloadFromGithub('Tsjippy', $slug);
        }
	}
}

add_action('init', function(){
	checkForPluginUpdates();
});