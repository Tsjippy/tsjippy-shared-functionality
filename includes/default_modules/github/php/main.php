<?php
namespace TSJIPPY\GITHUB;
use TSJIPPY;
use Github\Exception\ApiLimitExceedException;
use Github\Client;

if ( ! defined( 'ABSPATH' ) ) exit;

require( TSJIPPY\PLUGINPATH  . '/includes/default_modules/github/lib/vendor/autoload.php');

// https://github.com/KnpLabs/php-github-api 	-- github api
// https://github.com/michelf/php-markdown		-- convert markdown to html

/**
 * Adds a custom description to the plugin in the plugin page
 */
add_filter( 'plugins_api', __NAMESPACE__.'\customDescription', 10, 3);
function customDescription( $res, $action, $args ) {
	// do nothing if you're not getting plugin information or this is not our plugin
	if( 'plugin_information' !== $action || TSJIPPY\PLUGINSLUG !== $args->slug) {
		return $res;
	}

	$github 	    		= new Github();
	return $github->pluginData(TSJIPPY\PLUGINPATH, 'Tsjippy', 'tsjippy-shared-functionality', [
		'active_installs'	=> 2, 
		'donate_link'		=> 'harmseninnigeria.nl', 
		'rating'			=> 5, 
		'ratings'			=> [4,5,5,5,5,5], 
		'banners'			=> [
			'high'	=> TSJIPPY\PICTURESURL."/banner-1544x500.jpg",
			'low'	=> TSJIPPY\PICTURESURL."/banner-772x250.jpg"
		], 
		'tested'			=> '6.6.2'		
	]);
}

/**
 * Checks and shows plugin updates from github
 */
add_filter( 'pre_set_site_transient_update_plugins', __NAMESPACE__.'\showPluginUpdate');
/**
 * Adds updates to the site transient for all Tsjippy plugins
 * @param mixed $transient  Transient name. Expected to not be SQL-escaped. Must be 167 characters or fewer in length.
 */
function showPluginUpdate($transient){
	$github			= new Github();

	/**
	 * Check for plugin updates for each of the tsjippy plugins
	 */
	foreach(wp_get_active_and_valid_plugins() as $plugin){
		// Only add submenu for tsjippy plugins
		if( strpos($plugin, 'tsjippy-') === false ){
			continue;
		}

		$item			= $github->getVersionInfo($plugin);

		if(!is_object($item)){
			return $transient;
		}

		// Git has a newer version
		if(isset($item->new_version)){
			$transient->response[plugin_basename($plugin)]	= $item;
		}else{
			$transient->no_update[plugin_basename($plugin)]	= $item;
		}
	}

	return $transient;
}

define(__NAMESPACE__ .'\SETTINGS', get_option('tsjippy_github_settings', []));