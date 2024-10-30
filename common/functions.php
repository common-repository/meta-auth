<?php

/**
 * Common functions
 */

use Sijad\LaravelEcrecover\EthSigRecover;

/**
 * Recover signed personal_sign message
 */
function meta_auth_recover_eth_personal_sign($message, $signature)
{
    $signed = false;
    $eth_sig = new EthSigRecover();

    try {
        $signed = $eth_sig->personal_ecRecover($message, $signature);
    } catch (Exception $err) {
    }

    return $signed;
}

/**
 * Check if a user already exists with a given wallet address.
 *
 * @return int
 */
function meta_auth_user_exists($wallet_address)
{
    global $wpdb;

    return (int) $wpdb->get_var(sprintf("SELECT ID FROM $wpdb->users WHERE user_login='%s' LIMIT 1;", sanitize_text_field($wallet_address)));
}

/**
 * Get client IP
 *
 * @return string
 */
function meta_auth_get_client_ip()
{
    // Equally untrustworthy.
    if (isset($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (isset($_SERVER['HTTP_X_CLUSTER_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif (isset($_SERVER['HTTP_X_FORWARDED'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED'];
    } elseif (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_FORWARDED_FOR'];
    } elseif (isset($_SERVER['HTTP_FORWARDED'])) {
        $ip = $_SERVER['HTTP_FORWARDED'];
    }

    // Maybe trustworthy.
    if (isset($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }

    return filter_var(trim($ip), FILTER_VALIDATE_IP) ?: '';
}

/**
 * Create a new customer on guest checkout programmatically
 *
 * @param string $wallet_address
 * @param array $meta User metadata
 * @throws Exception
 */
function meta_auth_add_user($wallet_address, $meta, $role = 'subscriber')
{
    global $wpdb;

    $username = sanitize_text_field($wallet_address);
    $user_id = meta_auth_user_exists($username);

    if (!$user_id) {
        $password = wp_generate_password();
        $inserted = $wpdb->insert($wpdb->users, [
            'user_pass' => wp_hash_password($password),
            'user_login' => $username,
            'display_name' => $username
        ]);
        if ($inserted) {
            $user_id = $wpdb->insert_id;
            $user = new WP_User($user_id);
            $user->set_role($role);
            if ($meta) {
                foreach ($meta as $key => $value) {
                    update_user_meta($user_id, $key, $value);
                }
            }
            do_action('user_register', $user_id);
            return $user;
        } else {
            throw new Exception(__('Failed to add new user!', AUTH_PLUGIN));
        }
    } else {
        if ($meta) {
            // unset($meta['locale']);
            foreach ($meta as $key => $value) {
                update_user_meta($user_id, $key, $value);
            }
        }
    }

    return get_user_by('ID', $user_id);
}

if (!function_exists('truncate')) {
    function truncate($string, $length, $dots = "...")
    {
        return (strlen($string) > $length) ? substr($string, 0, $length - strlen($dots)) . $dots : $string;
    }
}
;