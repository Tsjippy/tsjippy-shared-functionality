<?php
namespace TSJIPPY\ADMIN;
use TSJIPPY;

if ( ! defined( 'ABSPATH' ) ) exit;


add_action( 'admin_menu', function(){
    new MainAdminMenu();
} );
