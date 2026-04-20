<?php
namespace SIM;

if ( ! defined( 'ABSPATH' ) ) exit;

if(PLUGINVERSION < '7.0.0'){
    return;
}

// Find all class files in all tsjippy- plugins
$classPaths = glob(__DIR__."/../tsjippy-*{,/includes,/includes/default_modules/*}/php/classes/*.php", GLOB_BRACE);

$classFiles = [];
foreach ($classPaths as $file) {
    $className  = basename($file, '.php');

    $nameSpace  = strtoupper(str_replace('tsjippy-', '', basename(dirname(dirname(dirname($file))))));

    if($nameSpace == 'includes'){
        $nameSpace = 'SIM';
    }

    // Store the file path for the class name in an array in case there are multiple classes with the same name in different namespaces
    if(!isset($classFiles[$className])){
        $classFiles[$className] = [];
    }

    $classFiles[$className][$nameSpace] = $file;
}

// Class loader function
spl_autoload_register(function ($classname) {
    global $classFiles;
    
    $path       = explode('\\', $classname);

     if($path[0] != 'SIM' || count($path) < 1){
        return;
    }

    $className  = array_pop($path);
    
    if(count($path) > 1){
        $nameSpace  = array_pop($path);
    }else{
        $nameSpace = 'SIM';
    }
    
    $filePaths	= $classFiles[$className] ?? [];
    $classFile	= $filePaths[$nameSpace] ?? null;
    if(file_exists($classFile)){
		require_once($classFile);
        return;
	}else{
        // If the class file does not exist, throw an error
        trigger_error(esc_html("Class $classname not found in file $classFile"), E_USER_ERROR);
    }
});

//Load all main files
$files = glob(__DIR__."/../tsjippy-*{,/includes,/includes/default_modules/*}/php/*.php", GLOB_BRACE);
foreach ($files as $file) {
    if(str_contains($file, '-dev/')){
        continue;
    }
    $result = require_once($file);

    if(is_wp_error($result)){
        ?>
        <div class='error' style='background-color:white;'>
            <?php echo esc_html($result->get_error_message());?>
        </div>
        <?php
    }
}