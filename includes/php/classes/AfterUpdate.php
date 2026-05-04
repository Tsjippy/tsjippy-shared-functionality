<?php
namespace TSJIPPY;

if ( ! defined( 'ABSPATH' ) ) exit;

class AfterUpdate extends AfterPluginUpdate {

    public function afterPluginUpdate($oldVersion){
        global $wpdb;

        printArray('Running update actions');

        error_log("Old Version is $oldVersion");

        if(version_compare('10.0.0', $oldVersion) === 1 || get_option('sim_modules')){
            /**
             * transfer module settings to option er plugin
             */
            $modules     = get_option('sim_modules', []);

            $modules['content-filter']  = $modules['contentfilter'];
            $modules['default-pictures']  = $modules['defaultpictures'];
            $modules['embed-page']  = $modules['embedpage'];
            $modules['html-email']  = $modules['fancyemail'];
            $modules['frontend-posting']  = $modules['frontendposting'];
            $modules['heic-to-jepeg']  = $modules['heictojepeg'];
            $modules['media-gallery']  = $modules['mediagallery'];
            $modules['page-gallery']  = $modules['pagegallery'];
            $modules['pdf-to-excel']  = $modules['pdftoexcel'];
            $modules['positional-accounts']  = $modules['positionalaccounts'];
            $modules['sim-nigeria']  = $modules['simnigeria'];
            $modules['user-management']  = $modules['usermanagement'];
            $modules['user-pages']  = $modules['userpages'];

            $github = new GITHUB\Github($modules['github']['token'] ?? '');

            foreach($modules as $module => $settings){
                error_log("Processing $module");

                if(isset($settings['emails'])){
                    update_option("tsjippy_{$module}_emails", $settings);

                    unset($settings['emails']);
                }

                if(isset($settings['enable'])){
                    unset($settings['enable']);
                } 
                
                if(isset($settings['nonce'])){
                    unset($settings['nonce']);
                }
                
                update_option("tsjippy_{$module}_settings", $settings);

                if(in_array($module, ['admin', 'family', 'fileupload', 'github'] )){
                    continue;
                }

                error_log("Installing $module as plugin");
                
                /**
                 * Download the the module as plugin
                 */
                $result = $github->downloadFromGithub('Tsjippy', $module, WP_PLUGIN_DIR."/tsjippy-$module");
                if(is_wp_error($result)){
                    printArray($result->get_error_message());
                    return;
                }

                // Activate
                error_log("Activating $module plugin");
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
