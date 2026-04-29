<?php
namespace TSJIPPY;

if ( ! defined( 'ABSPATH' ) ) exit;

// Runs after a succesfull update of a plugin
add_action( 'upgrader_process_complete', function($upgraderObject, $options){
    $afterUpdate    = new AfterUpdate();
    $afterUpdate->upgradeSucces( $upgraderObject, $options );
}, 10, 2 );

// Runs 10 seconds after a succesfull update of a tsjippy- plugin to be able to use the new files
add_action( 'schedule_tsjippy_plugin_update_action', function($slug, $oldVersion){
    $className  = "TSJIPPY\\" . strtoupper($slug) . "\\AfterUpdate";
    
    // Run update actions for this plugin if it exists
    if(class_exists($className)){
        $afterUpdate            = new $className();
        $afterUpdate->afterPluginUpdate( $oldVersion );
    }
}, 10, 2 );
