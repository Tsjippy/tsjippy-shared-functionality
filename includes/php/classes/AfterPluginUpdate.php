<?php
namespace TSJIPPY;

if ( ! defined( 'ABSPATH' ) ) exit;

abstract class AfterPluginUpdate {
    public function __construct(){

    }

    public function upgradeSucces( $upgraderObject, $options ) {
        // If an update has taken place and the updated type is plugins and the plugins element exists
        if ( $options['action'] == 'update' && $options['type'] == 'plugin' && isset( $options['plugins'] ) ) {
            foreach( $options['plugins'] as $plugin ) {
                // Check to ensure it's a tsjippy plugin

                if( str_contains($plugin, 'tsjippy-')) {
                    // Include the necessary file for activate_plugin()
                    require_once( ABSPATH . 'wp-admin/includes/plugin.php' );

                    error_log("Updating '$plugin' plugin");

                    // Check if the plugin is not already active
                    if ( ! is_plugin_active( $plugin ) ) {
                        // Activate the plugin
                        activate_plugin( $plugin );
                    }

                    printArray('Scheduling update actions');
                    $oldVersion = $upgraderObject->skin->plugin_info['Version'];

                    wp_schedule_single_event(time() + 10, 'schedule_tsjippy_plugin_update_action', [ $plugin, $oldVersion ]);
                }
            }
        }
    }

    abstract public function afterPluginUpdate($oldVersion);
}