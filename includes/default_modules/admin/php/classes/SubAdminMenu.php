<?php
namespace TSJIPPY\ADMIN;
use TSJIPPY;

use function TSJIPPY\addElement;

if ( ! defined( 'ABSPATH' ) ) exit;

abstract class SubAdminMenu{

    public $settings;
    public $name;

    public function __construct($settings, $name){
        $this->settings	= $settings;
        $this->name		= $name;
    }

    /**
     * @param   object  $node   The DOM Document node to add html to
     * 
     * @return  bool            True if something was printed to screen false otherwise
     */
    abstract function settings($node);

    /**
     * @param   object  $node   The DOM Document node to add html to
     * 
     * @return  bool    True if something was printed to screen false otherwise
     */
    abstract function emails($node);

    /**
     * @param   object  $node   The DOM Document node to add html to
     * 
     * @return  bool    True if something was printed to screen false otherwise
     */
    abstract function data($node);

    /**
     * @param   object  $node   The DOM Document node to add html to
     * 
     * @return  bool    True if something was printed to screen false otherwise
     */
    abstract function functions($node);

    public function handlePost(){
        $message	= '';

        $message	= $this->postActions();
        
        // do some checks
        if(
            !isset($_POST['plugin']) ||
            !isset($_POST['nonce']) ||
            !wp_verify_nonce($_POST['nonce'], 'plugin-settings' )
        ){
            return $message;
        }

        if(isset($_POST['emails'])){
            $message	.= $this->saveEmails();
        }else{
            $message	.= $this->saveSettings();
        }
        
        // Build the message
        $plugin	= TSJIPPY\getFromTransient('plugin');
        if(isset($plugin)){
            if(isset($plugin['installed'])){
                $name		 = ucfirst($plugin['installed']);
                $message	.= "<br><br>Dependend plugin '$name' succesfully installed and activated";
            }elseif(isset($plugin['activated'])){
                $name		 = ucfirst($plugin['activated']);
                $message	.= "<br><br>Dependend plugin '$name' succesfully activated";
            }
            TSJIPPY\deleteFromTransient('plugin');
        }
        
        return $message;
    }

    /**
     * Function to do extra actions from $_POST data. Overwrite if needed
     */
    public function postActions(){
        return '';
    }

    /**
    * Saves plugins settings from $_POST
    */
    public function saveSettings(){
        $slug	    = sanitize_key(wp_unslash($_POST['plugin']));
        $options	= $_POST;
        unset($options['plugin']);
        unset($options['nonce']);

        foreach($options as &$option){
            $option = TSJIPPY\deslash($option);
        }

        $this->settings	= $options;

        $extraMessage   = $this->postSettingsSave();

        update_option("tsjippy_{$slug}_settings", $this->settings);

        return "<div class='success'>Settings succesfully saved $extraMessage</div>";
    }

    /**
     * Function to do extra actions after settings are saved
     */
    public function postSettingsSave(){
        return '';
    }

    /**
     * Save email settings
     */
    public function saveEmails(){
        $slug	        = sanitize_text_field($_POST['plugin']);
        $emailSettings	= $_POST['emails'];
        unset($emailSettings['plugin']);

        foreach($emailSettings as &$emailSetting){
            $emailSetting = wp_unslash($emailSetting);
        }

        update_option("tsjippy_{$slug}_emails", $emailSettings);

        return "<div class='success'>E-mail settings succesfully saved</div>";
    }

    /**
     * Get html to select an image
     * @param	string 		$key			the image key in the plugin settings
     * @param	string		$name			Human readable name of the picture
     * @param	DOMElement	$parent		    The parent node
     * @param	string		$type			The image type you allow
    */
    public function pictureSelector($key, $name, $parent, $type=''){
        wp_enqueue_media();
        wp_enqueue_script('tsjippy_picture_selector_script', TSJIPPY\INCLUDESURL.'/js/select_picture.min.js', array(), '7.0.0', true);
        wp_enqueue_style( 'tsjippy_picture_selector_style', TSJIPPY\INCLUDESURL.'/css/picture_select.min.css', array(), '7.0.0');

        if(empty($this->settings['picture-ids'][$key])){
            $hidden		= 'hidden';
            $src		= '';
            $id			= '';
            $text		= 'Select';
        }else{
            $id			= $this->settings['picture-ids'][$key];
            $src		= wp_get_attachment_image_url($id);
            $hidden		= '';
            $text		= 'Change';
        }

        $wrapper        = TSJIPPY\addElement('div', $parent, ['class' => 'picture-selector-wrapper']);

        $previewWrapper = TSJIPPY\addElement('div', $wrapper, ['class' => "image-preview-wrapper $hidden"]);

        TSJIPPY\addElement('img', $previewWrapper, ['loading' => 'lazy', 'class' => "image-preview", 'src' => $src, 'alt' => '']);

        $attributes     = [
            'type' => "button", 
            'value' => "$text picture for $name", 
            'class' => "button select-image-button"
        ];

        if(!empty($type)){
            $attributes['data-type'] = $type;
        }

        TSJIPPY\addElement('input', $wrapper, $attributes);
        
        $attributes     = [
            'type'  => "hidden", 
            'value' => $id, 
            'class' => "no-reset image-attachment-id",
            'name'  => "picture-ids[$key]"
        ];

        if(!empty($type)){
            $attributes['data-type'] = $type;
        }

        TSJIPPY\addElement('input', $wrapper, $attributes);
    }

    /**
     * Creates a dropdown to select a recurrence period
     * 
     * @param   string      $name           The selector name
     * @param   string      $selectedValue  The current selected value
     * @param   DOMElement  $parent         The element to append the selector to
     */
    public function recurrenceSelector($name, $selectedValue, $parent){
        $select     = addElement('select', $parent, ['name' => $name]);

        $options    = [
            'daily'         => 'Daily',
            'weekly'        => 'Weekly',
            'monthly'       => 'Monthly',
            'threemonthly'  => 'Every quarter',
            'sixmonthly'    => 'Every half a year',
            'yearly'        => 'Yearly'
        ];

        foreach($options as $value => $name){
            addElement(
                'option', 
                $select, 
                [
                    'value'     => $value,
                    'selected'  => $value == $selectedValue ? 'selected' : ''
                ],
                $name
            );
        }
    }
}