<?php
namespace SIM\ADMIN;
use SIM;

if ( ! defined( 'ABSPATH' ) ) exit;

class AdminMenu{
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
        add_menu_page("SIM Plugin Settings", "SIM Settings", 'edit_others_posts', "sim", [$this, "mainMenu"]);

        foreach(wp_get_active_and_valid_plugins() as $plugin){
            if(
                strpos($plugin, 'tsjippy-') !== false &&                    // Only add submenu for tsjippy plugins
                strpos($plugin, 'tsjippy-shared-functionality') === false   // But not for the shared functionality plugin
            ){
                $slug = str_replace('tsjippy-', '', basename($plugin, '.php'));
                $name = ucwords(str_replace('-', ' ', $slug));
    
                add_submenu_page(
                    'sim', 
                    $name, 
                    $name, 
                    "edit_others_posts", 
                    "sim_$slug", 
                    function() use ( $name, $slug ){
                        $this->buildSubMenu($name, $slug);
                    }
                );
            }
        }
    }
    
    public function mainMenu(){
        do_action('sim_plugin_actions');
    
        ?>
        <div class="wrap">
            <h1>SIM Plugin Settings</h1>
            <p>Welcome to the SIM Plugin Settings page!</p>
        </div>
        <?php
    }

     /**
    * Build the submenu container and tablink button
    * 
    * @param    string $slug    The slug of the submenu, used for the id and data-target of the button
    * @param    string $name    The name of the submenu
    * @return   string          The domcontent node
    */
    public function mainNode($slug, $name){
        /**
         * Tablink button for the submenu
         */
        $classString		= 'tablink';
        
        if($this->tab == $slug){
            $classString	.= ' active';
        }
        
        $attributes				= [
            'class' 		=> $classString, 
            'id' 			=> "show-$slug", 
            'data-target'	=> $slug
        ];

        addElement('button', $this->tabLinkButtonsWrapper, $attributes, ucfirst($slug), $this->dom);

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

        $node    = addElement('div', $this->mainDiv, $attributes, "", $this->dom);
        addElement('h2', $node, [], $name, $this->dom);

        return $node;
    }

    /**
     * Builds the submenu for each plugin
     */
    public function buildSubMenu($name, $slug){
        if(empty($_GET['page'])){
            return '';
        }

        $this->settings	= get_option("sim_{$slug}_settings", []);

        $this->mainDiv	= addElement('div', $this->dom, ['class' => 'plugin-settings'], '', $this->dom);
        addElement('h1', $this->mainDiv, [], "$name plugin settings", $this->dom);

        $this->tabLinkButtonsWrapper	= addElement('div', $this->mainDiv, ['class' => 'tablink-wrapper'], '', $this->dom);            
            
        $settingsTab        = $this->settingsTab($slug, $name);
        $emailSettingsTab   = $this->emailSettingsTab($slug, $name);
        $dataTab            = $this->dataTab($slug, $name);
        $functionsTab       = $this->functionsTab($slug, $name);

        $message	        = $this->handlePost();
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
            addRawHtml($message, $parent, 'afterBegin');
        }

        echo $this->dom->saveHtml();
    }

    public function settingsTab($slug, $name){
        $node    = $this->mainNode('settings', 'Settings');

        ob_start();
    
        ?>
        <form action="" method="post">
            <input type='hidden' class='no-reset' name='plugin' value='<?php echo esc_html($slug);?>'>
            <input type='hidden' class='no-reset' name='nonce' value='<?php echo esc_html(wp_create_nonce('plugin-settings'));?>'>

            <div class='options'>
                <?php
                $options	= apply_filters("sim_submenu_{$slug}_options", '', $this->settings, $name);
                if(empty($options)){
                    ?>
                    <div>
                        No special settings needed for this plugin
                    </div>
                    <?php
                }else{
                    echo $options;
                }
                
                ?>
            </div>

            <?php
            // Only show submit button if there is something to submit
            if(!empty($options)){
                ?>
                <br>
                <br>
                <input type="submit" value="Save <?php echo esc_html($name);?> settings">
                <?php
            }
            ?>
        </form>
        <?php
        addRawHtml(ob_get_clean(), $node);

        return $node;
    }

    public function emailSettingsTab($slug, $name){
        $html	= apply_filters("sim_email_{$slug}_settings", '', $this->settings, $name);

        if(empty($html)){
            return '';
        }

        $node    = $this->mainNode('emails', 'E-mail Settings');

        ob_start();

        ?>
        <form action="" method="post">
            <input type='hidden' class='no-reset' name='plugin' value='<?php echo esc_html($slug);?>'>
            <?php
            echo $html;
            ?>
            <br>
            <br>
            <input type="submit" name="save-email-settings" value="Save <?php echo esc_html($name);?> e-mail settings">
        </form>
        <?php

        addRawHtml(ob_get_clean(), $node);

        return $node;
    }

    public function dataTab($slug, $name){
        $html	= apply_filters("sim_plugin_{$slug}_data", '', $this->settings, $name);

        if(empty($html)){
            return '';
        }

        $node    = $this->mainNode('data', 'Data Settings');

        addRawHtml($html, $node);

        return $node;
    }

    public function functionsTab($slug, $name){
        $html	= apply_filters("sim_plugin_{$slug}_functions", '', $this->settings, $name);

        if(empty($html)){
            return '';
        }

        $node    = $this->mainNode('functions', 'Functions');

        addRawHtml($html, $node);

        return $node;
    }

    public function handlePost(){
        $message	= apply_filters('sim-admin-settings-post', '', $this->settings);
        
        // do some checks
        if(
            !isset($_POST['plugin']) ||
            !isset($_POST['nonce']) ||
            !wp_verify_nonce($_POST['nonce'], 'plugin-settings' )
        ){
            return '';
        }

        if(isset($_POST['emails'])){
            $message	.= "<div class='success'>E-mail settings succesfully saved</div>";
            $this->saveEmails();
        }else{
            $message	.= "<div class='success'>Settings succesfully saved</div>";
            $this->saveSettings();
        }
        
        // Build the message
        $plugin	= SIM\getFromTransient('plugin');
        if(isset($plugin)){
            if(isset($plugin['installed'])){
                $name		 = ucfirst($plugin['installed']);
                $message	.= "<br><br>Dependend plugin '$name' succesfully installed and activated";
            }elseif(isset($plugin['activated'])){
                $name		 = ucfirst($plugin['activated']);
                $message	.= "<br><br>Dependend plugin '$name' succesfully activated";
            }
            SIM\deleteFromTransient('plugin');
        }
        
        return $message;
    }

    /**
    * Saves plugins settings from $_POST
    */
    public function saveSettings(){
        if(
            !isset($_POST['plugin']) ||
            !isset($_POST['nonce']) ||
            !wp_verify_nonce($_POST['nonce'], 'plugin-settings' )
        ){
            return '';
        }

        $slug	    = sanitize_key(wp_unslash($_POST['plugin']));
        $options	= $_POST;
        unset($options['plugin']);
        unset($options['nonce']);

        foreach($options as &$option){
            $option = SIM\deslash($option);
        }

        /**
         * Filters the settings of this sub-plugin
         * @param   array   $options    The options to save, after being sanitized
         * @param   array   $settings   The current saved settings, before saving the new ones
         * @return  array                The options to save, after being processed by the filter
         */
        $settings	= apply_filters("sim_plugin_{$slug}_after_save", $options, get_option("sim_{$slug}_settings", []));

        update_option("sim_{$slug}_settings", $settings);
    }

    public function saveEmails(){
        if(
            !isset($_POST['plugin']) ||
            !isset($_POST['nonce']) ||
            !isset($_POST['emails']) ||
            !wp_verify_nonce($_POST['nonce'], 'plugin-settings' )
        ){
            return '';
        }

        $slug	        = sanitize_text_field($_POST['plugin']);
        $emailSettings	= $_POST['emails'];
        unset($emailSettings['plugin']);

        foreach($emailSettings as &$emailSetting){
            $emailSetting = SIM\deslash($emailSetting);
        }

        update_option("sim_{$slug}_emails", $emailSettings);
    }
}