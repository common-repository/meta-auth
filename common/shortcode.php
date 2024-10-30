<?php

/**
 * Shortcode
 */

/**
 * Main shortcode
 */
function meta_auth_render_shortcode($atts)
{
    if (is_user_logged_in()) {
        return;
    }

    $settings = get_option('meta_auth_settings');

    $symbols = require META_AUTH_DIR . 'assets/symbols.php';
    $testnets = require META_AUTH_DIR . 'assets/testnets.php';
    wp_enqueue_style('meta-auth-login', META_AUTH_URI . 'assets/css/login.min.css', [], META_AUTH_VER);
    wp_enqueue_script('meta-auth-login', META_AUTH_URI . 'assets/js/components/LoginButton.js', [], META_AUTH_VER, true);
    wp_enqueue_script('meta-auth-login', META_AUTH_URI . 'assets/js/components/LazyScriptsLoader.js', [], META_AUTH_VER, true);
    wp_enqueue_script('meta-auth-login', META_AUTH_URI . 'assets/js/admin.min.js', [], META_AUTH_VER, true);
    wp_enqueue_script('meta-auth-login', META_AUTH_URI . 'assets/js/login.min.js', [], META_AUTH_VER, true);
    wp_localize_script('meta-auth-login', 'networkInfo', array('symbols' => $symbols, 'testnets' => $testnets)); // Localize the first script

    wp_localize_script('meta-auth-login', 'metaAuth', [
        'settings' => array_merge([
            'ajaxURL' => admin_url('admin-ajax.php'),
            'pluginURI' => META_AUTH_URI,
            'signMessage' => 'Please confirm that you own the wallet by signing this message!'
        ], (array) $settings),

        'i18n' => [
            'verifying' => __('Connecting wallet...', AUTH_PLUGIN),
            'failedConnect' => __('Failed to connect your wallet. Please try again!', AUTH_PLUGIN)
        ]


    ]);


    ob_start();

    wp_login_form();

    require META_AUTH_DIR . 'common/templates/login-modal.php';

    return ob_get_clean();
}
add_shortcode(AUTH_PLUGIN, 'meta_auth_render_shortcode');