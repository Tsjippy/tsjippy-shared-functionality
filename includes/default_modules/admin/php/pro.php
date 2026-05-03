<?php
namespace TSJIPPY\ADMIN;
use TSJIPPY;

/**
 * Download new plugins or delete them
 */
function mainMenuActions(){
	if(!empty($_GET['update'])){
		if($_GET['update'] == 'all'){
			TSJIPPY\GITHUB\checkForPluginUpdates();
	
			?>
			<div class='success'>All plugins updated successfully</div>
			<?php

			return;
		}

		$slug		= sanitize_text_field($_GET['update']);

		if(updateOrDownloadPlugin($slug)){
			?>
			<div class="success">
				Plugin <?php echo esc_attr($slug);?> succesfully updated.
			</div>
			<?php
		}
	}

	if(!empty($_GET['download'])){
		$slug		= sanitize_text_field($_GET['download']);

		if(updateOrDownloadPlugin($slug)){
			?>
			<div class="success">
				Plugin <?php echo esc_attr($slug);?> succesfully downloaded
			</div>
			<?php
		}
	}

	if(!empty($_GET['remove'])){
		$slug		= sanitize_text_field($_GET['remove']);

		delete_option("tsjippy_{$slug}_settings");
	}
}

/**
 * Downloads a plugin from github and displays the error messages if any
 * 
 * @param	string	$slug	The plugin slug
 * 
 * @return	bool			true on succes, false on failure
 */
function updateOrDownloadPlugin($slug){
	$github		= new TSJIPPY\GITHUB\Github();

	$result		= $github->downloadFromGithub('Tsjippy', $slug, WP_PLUGIN_DIR.'/tsjippy-'.$slug, true);
	
	if(is_wp_error($result)){
		echo "<div class='error'>".esc_attr($result->get_error_message())."</div>";

		return false;
	}elseif($result){
		return true;
	}else{
		?>
		<div class="error">
			Plugin <?php echo esc_attr($slug);?> not found on github.<br><br>
			<?php
			if(!$github->authenticated){
				$url            = admin_url( "admin.php?page=tsjippy_github&main-tab=settings" );
				?> maybe you <a href='<?php echo esc_url($url);?>'>should supply a github token</a> so I can try again while logged in.
				<?php
			}
			?>
		</div>
		<?php

		return false;
	}
}