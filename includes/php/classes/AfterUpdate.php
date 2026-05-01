<?php
namespace TSJIPPY;

if ( ! defined( 'ABSPATH' ) ) exit;

class AfterUpdate extends AfterPluginUpdate {

    public function afterPluginUpdate($oldVersion){
        global $wpdb;

        printArray('Running update actions');

        error_log("Old Version is $oldVersion");

        if(version_compare('10.0.0', $oldVersion) === 1 || get_option('sim_modules')){
            $github = new GITHUB\Github();

            /**
             * transfer module settings to option er plugin
             */
            $modules     = get_option('sim_modules', []);

            foreach($modules as $module => $settings){
                error_log("Processing $module");

                if(isset($settings['emails'])){
                    update_option("tsjippy_{$module}_emails", $settings);

                    unset($settings['emails']);
                }
                
                update_option("tsjippy_{$module}_settings", $settings);

                if(in_array($module, ['admin', 'family', 'fileupload', 'github'] )){
                    continue;
                }

                error_log("Installing $module as plugin");
                
                /**
                 * Download the the module as plugin
                 */
                $github->downloadFromGithub('Tsjippy', $module, WP_PLUGIN_DIR."/tsjippy-$module");

                // Activate
                activate_plugin("tsjippy-$module/tsjippy-$module.php");
            }

            /**
             * Rename tables to tsjippy_
             */
            $tables = $wpdb->get_col("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = 'local' and TABLE_TYPE = 'BASE TABLE' and TABLE_NAME like '%_tsjippy_%'");
            
            foreach($tables as $table){
                $newName    = str_replace('_sim_', '_tsjippy_', $table);
                $wpdb->query("ALTER TABLE $table RENAME TO $newName");
            }

            delete_option('sim_modules');
        }
    }
}
