<?php

/**
 * Handle AJAX activation request
 */
function meta_auth_activate_site()
{
    if (empty($_POST['email']) || empty($_POST['plugin'])) {
        exit(json_encode([
            'success' => false,
            'message' => __('Please enter your email address!', AUTH_PLUGIN)
        ]));
    }

    $email = sanitize_email($_POST['email']);
    $plugin = sanitize_title($_POST['plugin']);
    $address = sanitize_text_field($_POST['address']);
    $ticker = sanitize_text_field($_POST['ticker']);
    $status = "";
    $status = Januus\WP\Api::getActivationStatus($plugin);


    if (!$status) {
        $status = Januus\WP\Api::registerSite($address, $plugin, $email, $ticker);
        sleep(1);
        if (!$status) {
            exit(json_encode([
                'success' => false,
                'message' => __('Failed to register your site. Please try again!', AUTH_PLUGIN)
            ]));
        } else {
            if ($status === 'registered') {
                exit(json_encode([
                    'success' => true,
                    'message' => __('The plugin has been activated successfully!', AUTH_PLUGIN)
                ]));
            }
            //wip no authentication email being sent
            // else {

            //     exit(json_encode([
            //         'success' => true,
            //         'message' => __('Please check your email for activation link!', AUTH_PLUGIN)
            //     ]));
            // }
        }
    } else {
        if ($status === 'registered') {
            exit(json_encode([
                'success' => true,
                'message' => __('The plugin has been activated successfully!', AUTH_PLUGIN)
            ]));
        }
        //wip no authentication email being sent
        // else {

        //     exit(json_encode([
        //         'success' => true,
        //         'message' => __('Please check your email for activation link!', AUTH_PLUGIN)
        //     ]));
        // }
    }
}
add_action('wp_ajax_meta_auth_activate_site', 'meta_auth_activate_site');