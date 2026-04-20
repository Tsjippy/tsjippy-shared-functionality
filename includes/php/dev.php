<?php
namespace SIM;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Prints something to the log file and optional to the screen
 * @param 	string		$message	 			The message to be printed
 * @param	bool		$display				Whether to print the message to the screen or not
 * @param	bool|int	$printFunctionHiearchy	Whether to print the full backtrace, false for not printing, true for all, number for max depth
*/
function printArray($message, $display=false, $printFunctionHiearchy=false, $error=false){
	$bt		= debug_backtrace();

	if($error){
		$type 			= 0;
		$destination 	= null;
	}else{
		$type 			= 3;
		$destination	= WP_CONTENT_DIR.'/notice.log';
	}

	if($printFunctionHiearchy){
		error_log("Called from:", $type, $destination);
		foreach($bt as $index => $trace){
			// stop if we have reached the max depth
			if(is_numeric($printFunctionHiearchy) && $index == $printFunctionHiearchy){
				break;
			}
			
			$path	= str_replace(MODULESPATH, '', $trace['file']);

			error_log("$index\n", $type, $destination);
			error_log( "    File: $path\n", $type, $destination);
			error_log( "    Line {$trace['line']}\n", $type, $destination);
			error_log( "    Function: {$trace['function']}\n", $type, $destination);
			error_log( "    Args:\n", $type, $destination);
			error_log(print_r($trace['args'], true), $type, $destination);
		}
	}else{
		$caller = array_shift($bt);
		$path	= str_replace(MODULESPATH, '', $caller['file']);
		error_log("Called from file $path line {$caller['line']}\n", $type, $destination);
	}

	if(is_array($message) || is_object($message)){
		error_log(print_r($message, true), $type, $destination);
	}else{
		error_log(gmdate(DATEFORMAT.' '.TIMEFORMAT, time()).' - '.$message."\n", $type, $destination);
	}
	
	if($display){
		?>
		<pre>
			Called from file <?php echo esc_html($caller['file']);?> line <?php echo esc_html($caller['line']);?>
			<br>
			<br>
			<?php 
			echo wp_kses_post(print_r($message));
			?>
		</pre>
		<?php
	}
}

/**
 * Prints html properly outlined for easy debugging
 */
function printHtml($html){
	$tabs	= 0;

	// Split on the < symbol to get a list of opening and closing tags
	$html		= explode('>', $html);
	$newHtml	= '';

	// loop over the elements
	foreach($html as $index => $el){
		$el = trim($el);

		if(empty($el)){
			continue;
		}

		// Split the line on a closing character </
		$lines	= explode('</', $el);

		if(!empty($lines[0])){
			$newHtml	.= "\n";
			
			// write as many tabs as need
			for ($x = 0; $x <= $tabs; $x++) {
				$newHtml	.= "\t";
			}

			// then write the first element
			$newHtml	.= $lines[0];
		}

		if(
			substr($el, 0, 1) == '<' && 						// Element start with an opening symbol
			substr($el, 0, 2) != '</' && 						// It does not start with a closing symbol
			substr($el, 0, 6) != '<input' && 					// It does not start with <input (as that one does not have a closing />)
			(
				substr($el, 0, 7) != '<option' || 				// It does not start with <option (as that one does not have a closing />)
				str_contains( $html[$index+1], '</option') 		// or the next element contains a closing option
			) &&
			$el != '<br'
		){
			$tabs++;
		}
		
		if(isset($lines[1])){
			$tabs--;

			$newHtml	.= "\n";

			for ($x = 0; $x <= $tabs; $x++) {
				$newHtml	.= "\t";
			}
			$newHtml	.= '</'.$lines[1].'>';
		}else{
			$newHtml	.= '>';
		}
	}

	printArray($newHtml);
}

// disable auto updates for this plugin on localhost
add_filter( 'auto_update_plugin', __NAMESPACE__.'\disableAutoUpdate', 10, 2 );
function disableAutoUpdate( $value, $item ) {
    if ( 'tsjippy-shared-functionality' === $item->slug && ( $_SERVER['HTTP_HOST'] == 'localhost' || str_contains($_SERVER['HTTP_HOST'], '.local'))) {
        return false; // disable auto-updates for the specified plugin
    }

    return $value; // Preserve auto-update status for other plugins
}

/**
 * Module settings
 */
// Blocks are assumed to be in the plugins folder.
// So adjust the urls for the ones in the sim-modules folder
add_filter( 'plugins_url', __NAMESPACE__.'\fixBlockUrls', 10, 3);
function fixBlockUrls($url, $path, $plugin ){
	if(str_contains($url, MODULESPATH)){
		$url	= pathToUrl(MODULESPATH.explode(MODULESPATH, $url)[1]);
	}
	return $url;
}

//Shortcode for testing
add_shortcode("test", function ($atts){
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    require_once ABSPATH . 'wp-admin/install-helper.php';

    global $wpdb;
    global $Modules;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    require_once ABSPATH . 'wp-admin/install-helper.php';

    $args = array(
        'post_type'      => 'attachment',
        'post_mime_type' => 'image/jpeg', // Uses a wildcard internally (image/*)
        'numberposts'    => -1,
        'post_status'    => 'any',
    );

    $images = get_posts( $args );

    foreach( $images as $image){
        if(strpos($image->guid, '.jpe') === false){
            continue;
        }
        $path = get_attached_file( $image->ID, true);

        if(!file_exists($path)){
            $ext    = pathinfo($path, PATHINFO_EXTENSION);

            $path   = str_replace( '.'.$ext, '.jpg', $path );

            if(!file_exists($path)){
                $path = str_replace( '.jpg', '.jpeg', $path );
            }

            if(!file_exists($path)){
                continue;
            }
        }

        update_attached_file( $image->ID, $path );
    }

});

// turn off incorrect error on localhost
add_filter( 'wp_mail_smtp_core_wp_mail_function_incorrect_location_notice', '__return_false' );