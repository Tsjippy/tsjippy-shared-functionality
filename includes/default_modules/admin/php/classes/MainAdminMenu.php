<?php
namespace TSJIPPY\ADMIN;
use TSJIPPY;

if ( ! defined( 'ABSPATH' ) ) exit;

class MainAdminMenu{
    public $tab;
    public $tabLinkButtonsWrapper;
    public $mainDiv;
    public $dom;
    public $settings;

    /**
     * Constructor
     */
    public function __construct() {
        $this->tab      = 'settings';
        if(isset($_GET['tab'])){
            $this->tab  = sanitize_key($_GET['tab']);
        }

        $this->dom		= new \DOMDocument();

        // Register a custom menu page.
        add_menu_page("Tsjippy Plugin Settings", "Tsjippy Settings", 'edit_others_posts', "tsjippy", [$this, "mainMenu"]);

        // Sub menu for Github
        add_submenu_page(
            'tsjippy', 
            'Github', 
            'Github', 
            "edit_others_posts", 
            'github', 
            function(){
                $this->buildSubMenu('Github', 'github');
            }
        );

        foreach(wp_get_active_and_valid_plugins() as $plugin){

            // Only add submenu for tsjippy plugins
            if( strpos($plugin, 'tsjippy-') !== false ){

                // Add plugin menu links
                add_filter("plugin_action_links_".plugin_basename($plugin), [$this, 'addExtraPluginLinks'], 10, 3);

                // But not for the shared functionality plugin)
                if(strpos($plugin, 'tsjippy-shared-functionality') !== false){
                    continue;
                }

                $menuSLug   = basename($plugin, '.php');

                $slug = str_replace('tsjippy-', '', $menuSLug);
                $name = ucwords(str_replace('-', ' ', $slug));
                $slug = str_replace('-', '', $slug);
    
                add_submenu_page(
                    'tsjippy', 
                    $name, 
                    $name, 
                    "edit_others_posts", 
                    $menuSLug, 
                    function() use ( $name, $slug ){
                        $this->buildSubMenu($name, $slug);
                    }
                );
            }
        }
    }
    
    public function mainMenu(){
        do_action('tsjippy_plugin_actions');
    
        ?>
        <div class="wrap">
            <h1>Tsjippy Plugin Settings</h1>
            <p>Welcome to the Tsjippy Plugin Settings page!</p>
        </div>
        <?php
    }
    
    /**
     * Tablink button for the submenu
     * 
     * @param   string  $slug   The slug one of settings, emails, data or functions
     * 
     * @return DOMElement       The DOm Document node
     */
    public function tabLinkButton($slug){
        $classString		= 'tablink';
        
        if($this->tab == $slug){
            $classString	.= ' active';
        }
        
        $attributes				= [
            'class' 		=> $classString, 
            'id' 			=> "show-$slug", 
            'data-target'	=> $slug
        ];

        if($slug == 'settings'){
            $position   = 'afterBegin';
        }else{
            $position   = 'beforeEnd';   
        }
        return TSJIPPY\addElement('button', $this->tabLinkButtonsWrapper, $attributes, ucfirst($slug), $position);
    }

    /**
    * Build the submenu container and tablink button
    * 
    * @param    string $slug    The slug of the submenu, used for the id and data-target of the button
    * @param    string $name    The name of the submenu
    *
    * @return   DOMElement      The domcontent node
    */
    public function mainNode($slug, $name){
        /**
         * Main container for the submenu
         */
        $attributes				= [
            'id'	=> $slug, 
            'class' => 'tabcontent'
        ];
        if($this->tab != $slug){
            $attributes['class'] .= ' hidden';
        }

        $node    = TSJIPPY\addElement('div', $this->mainDiv, $attributes);
        TSJIPPY\addElement('h2', $node, [], $name);

        return $node;
    }

    /**
     * Builds the submenu for each plugin
     */
    public function buildSubMenu($name, $slug){
        if(empty($_GET['page'])){
            return '';
        }

        $this->settings	= get_option("tsjippy_{$slug}_settings", []);

        $this->mainDiv	= TSJIPPY\addElement('div', $this->dom, ['class' => 'plugin-settings']);
        TSJIPPY\addElement('h1', $this->mainDiv, [], "$name plugin settings");
        
        $className          = "TSJIPPY\\" . strtoupper($slug) . "\\AdminMenu";

        if(class_exists($className)){
            $this->tabLinkButtonsWrapper	= TSJIPPY\addElement('div', $this->mainDiv, ['class' => 'tablink-wrapper']);

            $subMenu            = new $className($this->settings, $name);

            $message	        = $subMenu->handlePost();
                
            $settingsTab        = $this->settingsTab($subMenu, $slug, $name);
            $emailSettingsTab   = $this->emailSettingsTab($subMenu, $slug, $name);
            $dataTab            = $this->dataTab($subMenu, $slug, $name);
            $functionsTab       = $this->functionsTab($subMenu, $slug, $name);

            // Only add a tablink button for the settings if there is at least on other tab
            if($emailSettingsTab || $dataTab || $functionsTab){
                $this->tabLinkButton('settings');
            }

            if($this->tab == 'settings'){
                $parent = $settingsTab;
            }elseif($this->tab == 'emails'){
                $parent = $emailSettingsTab;
            }elseif($this->tab == 'data'){
                $parent = $dataTab;
            }elseif($this->tab == 'functions'){
                $parent = $functionsTab;
            }

            if(!empty($message)){
                TSJIPPY\addRawHtml($message, $parent, 'afterBegin');
            }
        }else{
            TSJIPPY\addElement('div', $this->mainDiv, [], 'No special settings needed for this plugin');
        }

        echo $this->dom->saveHtml();
    }

    public function settingsTab($subMenu, $slug, $name){
        $node   = $this->mainNode('settings', 'Settings');

        $form   = TSJIPPY\addElement('form', $node, ['method' => "post"]);
        TSJIPPY\addElement('input', $form, ['type' => "hidden", 'name' => "plugin", 'value' => $slug,  'class' => 'no-reset']);
        TSJIPPY\addElement('input', $form, ['type' => "hidden", 'class' => 'no-reset', 'name' => "nonce", 'value' => wp_create_nonce('plugin-settings')]);

        $wrapper    = TSJIPPY\addElement('div', $form, ['class' => 'options']);

        $hasSettings    = $subMenu->settings($wrapper);

        if($hasSettings){
            TSJIPPY\addElement('br', $form);
            TSJIPPY\addElement('input', $form, ['type' => "submit", 'value' => "Save $name settings"]);
        }else{
            TSJIPPY\addElement('div', $wrapper, [], 'No special settings needed for this plugin');
        }

        return $node;
    }

    public function emailSettingsTab($subMenu, $slug, $name){
        $node    = $this->mainNode('emails', 'E-mail Settings');

        $form   = TSJIPPY\addElement('form', $node, ['method' => "post"]);
        TSJIPPY\addElement('input', $form, ['type' => "hidden", 'name' => "plugin", 'value' => $slug,  'class' => 'no-reset']);

        $hasEmails  = $subMenu->emails($form);

        if($hasEmails){
            TSJIPPY\addElement('br', $form);
            
            TSJIPPY\addElement('input', $form, ['type' => "submit", 'value' => "Save $name e-mail settings"]);

            $this->tabLinkButton('emails');

            return $node;
        }
        
        $node->remove();

        return false;
    }

    public function dataTab($subMenu, $slug, $name){
        $node    = $this->mainNode('data', 'Data Settings');

        if(!$subMenu->data($node)){
            $node->remove();

            return false;
        }

        $this->tabLinkButton('data');

        return $node;
    }

    public function functionsTab($subMenu, $slug, $name){
        $node    = $this->mainNode('functions', 'Functions');

        if(!$subMenu->functions($node)){
            $node->remove();

            return false;
        }

        $this->tabLinkButton('functions');

        return $node;
    }

    //Add setting link to plugin page
    function addExtraPluginLinks($links, $plugin, $data) {
        //http://plugin-prepare.local/wp-admin/admin.php?page=tsjippy
        //http://plugin-prepare.local/wp-admin/admin.php?page=tsjippy_bookings
        
        // Settings Link
        $slug           = basename($plugin, '.php');

        if($slug == 'tsjippy-shared-functionality'){
            $page   = 'tsjippy';
        }else{
            $page   = basename($plugin, '.php');
        }

        $url            = admin_url( "admin.php?page=$page" );
        $link           = "<a href='$url'>Settings</a>";
        array_unshift($links, $link);

        // Details link
        $url            = admin_url( "plugin-install.php?tab=plugin-information&plugin=$slug&section=changelog" );
        $link           = "<a href='$url'>Details</a>";
        array_unshift($links, $link);

        //TO DO: implement Pro
        $pro = false;
        if($pro){

            // Update links
            if(isset($_GET['update']) && $_GET['update'] == 'check'){
                // Reset updates cache
                delete_site_transient( 'update_plugins' );
                delete_transient('tsjippy-git-release');

                wp_update_plugins();

                $updates    = get_site_transient( 'update_plugins' );
                if(is_wp_error($updates)){
                    $link = "<div class='error'>".$updates->get_error_message()."</div>";
                }elseif(isset($updates->response[$plugin])){
                    $url    = self_admin_url( 'update.php?action=update-selected&amp;plugin=' . urlencode( $plugin ) );
                    $url    = wp_nonce_url( $url, 'bulk-update-plugins' );
                    $link   = "<a href='$url' class='update-link'>Update to ".$updates->response[$plugin]->new_version."</a>";
                }else{
                    $url   = admin_url( 'plugins.php?update=check' );
                    $link  = "Up to date <a href='$url'>Check again</a>";
                }
            }else{
                $url   = admin_url( 'plugins.php?update=check' );
                $link  = "<a href='$url'>Check for update</a>";
            }
            array_unshift($links, $link);
        }

        return $links;
    }
}