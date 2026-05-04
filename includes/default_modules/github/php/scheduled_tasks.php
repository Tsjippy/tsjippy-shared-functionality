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

	// update the base plugin first
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

		if($nameSpace == 'SHARED-FUNCTIONALITY'){
			$oldVersion	= constant("TSJIPPY\\PLUGINVERSION");
		}else{
			$oldVersion	= constant("TSJIPPY\\$nameSpace\\PLUGINVERSION");
		}
		
		$release	= $github->getLatestRelease('Tsjippy', $slug, true);

		if(is_wp_error($release)){
			$errorMessage	= $release->get_error_message();
			TSJIPPY\printArray("Error checking for update for plugin $slug: $errorMessage");
			TSJIPPY\printArray($errorMessage);
			TSJIPPY\printArray($release);

			if(
				$errorMessage == 'You have triggered an abuse detection mechanism. Please wait a few minutes before you try again.' ||
				str_contains($errorMessage, 'You have reached GitHub hourly limit!')
			){
				return;
			}
			continue;
		}

		$newVersion	= $release['tag_name'];

		// Download the new version
		TSJIPPY\printArray("Name: $slug. Current Version $oldVersion, new version $newVersion. ");
		if(version_compare($newVersion, $oldVersion)){
			TSJIPPY\printArray("Updating $slug");
			
            $github->downloadFromGithub('Tsjippy', $slug);
        }
	}
}