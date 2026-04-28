<?php
namespace TSJIPPY\ADMIN;
use TSJIPPY;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 *
 * Creates a default page if it does not exist yet
 * 
 * @param   string      $title      The title of the page
 * @param   string      $content    The page content
 * @param   array       $arg        Extra page creation arguments, default empty
 * 
 * @return  int                     The id of the created page
 */
function createDefaultPage($title, $content, $arg=[]){
    // Create the page
    $post = array(
        'post_type'		=> 'page',
        'post_title'    => $title,
        'post_content'  => $content,
        'post_status'   => "publish",
        'post_author'   => '1',
        'comment_status'=> 'closed'
    );

    if(!empty($arg)){
        $post   = array_merge($post, $arg);
    }
    $pageId 	= wp_insert_post( $post, true, false);


    // Do not require page updates
    update_post_meta($pageId, 'static_content', true);
    
    return $pageId;
}

/**
 * Checks if all pages are valid in the default pages option array and returns the first valid one as a link
 *
 * @param   string  $slug           The slug of the plugin
 * @param   string  $optionKey      The key in the plugin settings
 *
 * @return  string                  The url
 */
function getDefaultPageLink($slug, $optionKey){

    $url		= '';

    $settings   = get_option("tsjippy_{$slug}_settings");

	$pageIds	= $settings[$optionKey] ?? false;
	if(!$pageIds){
        return false;
    }

	if(is_array($pageIds)){
		foreach($pageIds as $key=>$pageId){
			if(get_post_status($pageId) != 'publish'){
				unset($pageIds[$key]);
			}
		}

        $pageIds    = array_values($pageIds);
		if(!empty($pageIds)){
			$url		= get_permalink($pageIds[0]);
		}

        if($settings[$optionKey] != $pageIds){
		    $settings[$optionKey]	= $pageIds;
		    update_option("tsjippy_{$slug}_settings", $settings);
        }
	}

    return $url;
}