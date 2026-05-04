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

            if(isset($modules['contentfilter'])){
                $modules['content-filter']  = $modules['contentfilter'];
                unset($modules['contentfilter']);
            }
            if(isset($modules['defaultpictures'])){
                $modules['default-pictures']  = $modules['defaultpictures'];
                unset($modules['defaultpictures']);
            }
            if(isset($modules['embedpage'])){
                $modules['embed-page']  = $modules['embedpage'];
                unset($modules['embedpage']);
            }
            if(isset($modules['fancyemail'])){
                $modules['html-email']  = $modules['fancyemail'];
                unset($modules['fancyemail']);
            }
            if(isset($modules['frontendposting'])){
                $modules['frontend-posting']  = $modules['frontendposting'];
                unset($modules['frontendposting']);
            }
            if(isset($modules['heictojepeg'])){
                $modules['heic-to-jpeg']  = $modules['heictojepeg'];
                unset($modules['heictojepeg']);
            }
            if(isset($modules['mediagallery'])){
                $modules['media-gallery']  = $modules['mediagallery'];
                unset($modules['mediagallery']);
            }
            if(isset($modules['pagegallery'])){
                $modules['page-gallery']  = $modules['pagegallery'];
                unset($modules['pagegallery']);
            }
            if(isset($modules['pdftoexcel'])){
                $modules['pdf-to-excel']  = $modules['pdftoexcel'];
                unset($modules['pdftoexcel']);
            }
            if(isset($modules['positionalaccounts'])){
                $modules['positional-accounts']  = $modules['positionalaccounts'];
                unset($modules['positionalaccounts']);
            }
            if(isset($modules['simnigeria'])){
                $modules['sim-nigeria']  = $modules['simnigeria'];
                unset($modules['simnigeria']);
            }
            if(isset($modules['usermanagement'])){
                $modules['user-management']  = $modules['usermanagement'];
                unset($modules['usermanagement']);
            }
            if(isset($modules['userpages'])){
                $modules['user-pages']  = $modules['userpages'];
                unset($modules['userpages']);
            }

            if(isset($modules['bulkchange'])){
                unset($modules['bulkchange']);
            }

            if(isset($modules['welcomemessage'])){
                $modules['welcome-message']  = $modules['welcomemessage'];
                unset($modules['welcomemessage']);
            }

            if(isset($modules['banking'])){
                unset($modules['banking']);
            }

            if(isset($modules['mailposting'])){
                unset($modules['mailposting']);
            }

            if(isset($modules['login'])){
                $modules['login']['login-menu'] = $settings['loginmenu'] ?? [];
                $modules['login']['logout-menu'] = $settings['logoutmenu'] ?? [];
                $modules['login']['visibilty-login-menu'] = $settings['visibiltyloginmenu'] ?? [];
                $modules['login']['visibilty-logout-menu'] = $settings['visibiltylogoutmenu'] ?? [];

                unset($settings['loginmenu'] );
                unset($settings['logoutmenu'] );
                unset($settings['visibiltyloginmenu'] );
                unset($settings['visibiltylogoutmenu'] );
            }

            $github = new GITHUB\Github($modules['github']['token'] ?? '');

            $retryActivate  = [];

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
                $result = activate_plugin("tsjippy-$module/tsjippy-$module.php");

                if(is_wp_error($result)){
                    printArray($result->get_error_message());
                    $retryActivate[] = $module;
                }
            }

            $retryActivate2  = [];
            foreach($retryActivate as $module){
                // Activate
                error_log("Activating $module plugin - Attempt 2");
                $result = activate_plugin("tsjippy-$module/tsjippy-$module.php");

                if(is_wp_error($result)){
                    printArray($result->get_error_message());
                    $retryActivate2[] = $module;
                }
            }

            foreach($retryActivate2 as $module){
                // Activate
                error_log("Activating $module plugin - Attempt 3");
                $result = activate_plugin("tsjippy-$module/tsjippy-$module.php");

                if(is_wp_error($result)){
                    printArray($result->get_error_message());
                }
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
