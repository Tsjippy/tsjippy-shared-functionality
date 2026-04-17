<?php
namespace SIM;


// Runs after a succesfull update of the plugin
add_action( 'upgrader_process_complete', __NAMESPACE__.'\upgradeSucces', 10, 2 );
function upgradeSucces( $upgraderObject, $options ) {
    // If an update has taken place and the updated type is plugins and the plugins element exists
    if ( $options['action'] == 'update' && $options['type'] == 'plugin' && isset( $options['plugins'] ) ) {
        foreach( $options['plugins'] as $plugin ) {
            // Check to ensure it's my plugin
            if( $plugin == PLUGIN ) {
                // Include the necessary file for activate_plugin()
                require_once( ABSPATH . 'wp-admin/includes/plugin.php' );

                // Define the path to the plugin's main file relative to wp-content/plugins/
                $pluginPath = 'tsjippy-shared-functionality/tsjippy-shared-functionality.php';

                // Check if the plugin is not already active
                if ( ! is_plugin_active( $pluginPath ) ) {
                    // Activate the plugin
                    activate_plugin( $pluginPath );
                }

                printArray('Scheduling update actions');
                $oldVersion = $upgraderObject->skin->plugin_info['Version'];

                wp_schedule_single_event(time() + 10, 'schedule_sim_plugin_update_action', [ $oldVersion ]);
            }
        }
    }
}


// Runs 10 seconds after a succesfull update of the plugin to be able to use the new files
add_action( 'schedule_sim_plugin_update_action', __NAMESPACE__.'\afterPluginUpdate');
function afterPluginUpdate($oldVersion){
    global $Modules;
    global $moduleDirs;

    printArray('Running update actions');
    do_action('sim_plugin_update', $oldVersion);

    $github = new GITHUB\Github();

    // Reinstall any missing modules
    foreach(array_keys($Modules) as $module){
        if(!in_array($module, array_keys($moduleDirs))){
            $result = $github->downloadFromGithub('Tsjippy', $module, MODULESPATH.$module);

            if($result && !is_wp_error($result)){
                printArray("Succesfully installed module $module");
            }else{
                printArray($result);
            }

        }
    }

    if($oldVersion < '5.5.9'){
        foreach($Modules as $moduleName => &$settings){
            foreach($settings as $setting => $value){
                if(is_array($value)){
                    foreach($value as $i => $v){
                        $newIndex   = str_replace('_', '-', $i, $c);

                        if($c > 0){
                            $value[$newIndex]   = $v;

                            unset($value[$i]);
                        }
                    }
                }

                unset($settings[$setting]);

                $newIndex   = str_replace('_', '-', $setting);

                $settings[$newIndex]   = $value;
            }
        }

        update_option('sim_modules', $Modules);
    }

    if($oldVersion < '5.6.9'){
        $familyObject = new FAMILY\Family();
        $familyObject->createDbTables();

        $users  = get_users([
            'meta_key'      => 'family',
            'meta_compare'  => 'EXISTS'
        ]);

        $familyMetaKeys = apply_filters('sim-family-meta-keys', []);

        foreach($users as $user){
            $family = get_user_meta($user->ID, 'family', true);

            // Only process adults
            if(is_array($family) && !isset($family["father"]) && !isset($family["mother"])){
                foreach($family as $key => $value){
                    if(empty($value)){
                        continue;
                    }

                    switch($key){
                        case 'partner':
                            $familyObject->storeRelationship($user->ID, $value, $key, $family['weddingdate']);
                            break;
                        case 'children':
                            foreach($value as $childId){
                                $familyObject->storeRelationship($user->ID, $childId, 'child');
                            }
                            break;
                        case 'picture':
                            if(is_array($value) && !empty($value[0])){
                                $familyObject->updateFamilyMeta($user->ID, 'family_picture', $value[0]);
                            }
                            break;
                        case 'name':
                            $familyObject->updateFamilyMeta($user->ID, 'family_name', $value);
                            break;
                        case 'siblings':
                            foreach($value as $siblingId){
                                $familyObject->storeRelationship($user->ID, $siblingId, 'sibling');
                            }
                            break;
                    }
                }
        
                foreach($familyMetaKeys as $key){
                    $value   = get_user_meta($user->ID, $key, true);

                    if(empty($value)){
                        continue;
                    }

                    // Delete before updating otherwise it will be deleted again
                    delete_user_meta($user->ID, $key);

                    if($key == 'location' && is_array($value)){
                        $value   = array_values($value)[0];
                    }
                    if(!empty($value)){  
                        $familyObject->updateFamilyMeta($user->ID, $key, $value);
                    }
                }
            }else{
                foreach($familyMetaKeys as $key){
                    delete_user_meta($user->ID, $key);
                }
            }

            delete_user_meta($user->ID, 'family');
        }
    }

    if($oldVersion < '5.7.1'){
        $users = get_users([
            'meta_key'      => 'profile_picture',
            'meta_compare'  => 'EXISTS'
        ]);

        foreach($users as $user){
            $profilePicture = get_user_meta($user->ID, 'profile_picture', true);

            if(is_array($profilePicture) && isset($profilePicture[0])){
                $profilePicture = $profilePicture[0];
            }

            if(is_numeric($profilePicture) && wp_get_attachment_image_url($profilePicture)){
                update_user_meta($user->ID, 'profile_picture', $profilePicture);
            }else{
                delete_user_meta($user->ID, 'profile_picture');
            }
        }
    }

    if($oldVersion < '6.0.3'){
        $args = array(
            'post_type'      => 'attachment',
            'post_mime_type' => 'image/jpeg', // Uses a wildcard internally (image/*)
            'numberposts'    => -1
        );

        $images = get_posts( $args );

        foreach($images as $image){
            $meta       = get_post_meta($image->ID, '_wp_attachment_metadata', true);

            if(empty($meta['file'])){
                continue;
            }

            $old        = $meta['file'];

            if(pathinfo($old, PATHINFO_EXTENSION) != 'jpe'){
                continue;
            }

            $new        = str_replace('.jpe', '.jpeg', $old);

            $meta['file']    = $new;

            foreach($meta['sizes'] as &$size){
                $size['file']   = str_replace('.jpe', '.jpeg', $size['file']);
            }

            update_post_meta($image->ID, '_wp_attachment_metadata', $meta);

            $paths    = get_post_meta($image->ID, '_wp_attached_file');
            foreach($paths as &$path){
                $path   = str_replace('.jpe', '.jpeg', $path);
            }

            update_post_meta($image->id, '_wp_attached_file', $paths);
        }
    }

    if(version_compare('6.0.7', $oldVersion)){
        $activatedPlugins = get_option('active_plugins');

        $activatedPlugins[] = 'tsjippy-shared-functionality/tsjippy-shared-functionality.php';

        update_option('active_plugins', $activatedPlugins);
    }
}