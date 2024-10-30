<?php

/**
 * Frontend hooks
 */

/**
 * Enqueue CSS for the login form shortcode
 */
function meta_auth_enqueue_scripts()
{
    $symbols = require META_AUTH_DIR . 'assets/symbols.php';
    $testnets = require META_AUTH_DIR . 'assets/testnets.php';
    wp_enqueue_style('meta-auth-login', META_AUTH_URI . 'assets/css/login.min.css', [], META_AUTH_VER);
    wp_enqueue_script('meta-auth-login', META_AUTH_URI . 'assets/js/login.min.js', [], META_AUTH_VER, true);
    wp_localize_script('meta-auth-login', 'networkInfo', array('symbols' => $symbols, 'testnets' => $testnets));  // Localize the first script
}
add_action('wp_enqueue_scripts', 'meta_auth_enqueue_scripts');