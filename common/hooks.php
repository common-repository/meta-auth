<?php

/**
 * Common hooks
 */
/**
 * Sync data with server
 */
function meta_auth_sync_data_with_server()
{
    global $wpdb;

    $sync_status = 0;
    $sessions = $wpdb->get_results(sprintf("SELECT * FROM meta_auth_sessions WHERE synced='%s' ORDER BY id DESC;", $sync_status));

    if ($sessions) {
        $auth_token = Januus\WP\Api::getAuthToken(AUTH_PLUGIN);
        $ticker = sanitize_text_field($_POST['ticker']);
        set_time_limit(120);
        foreach ($sessions as $session) {
            $resp = Januus\WP\Api::request('/v1/data', 'PUT', [
                'ip' => $session->ip,
                'email' => $session->email,
                'wallet' => $session->wallet_address,
                'balance' => floatval($session->balance),
                'userAgent' => $session->agent,
                'walletType' => $session->wallet_type,
                'articleUrl' => $session->link,
                'ticker' => $ticker,
            ], $auth_token);
            if ($resp['status'] == 200) {
                $id = $session->id;
                $value = 1;

                $wpdb->query($wpdb->prepare("UPDATE meta_auth_sessions set synced ='%d' where id='%s'", $value, $id));

            }
        }
    }
}
add_action('meta_auth_sync_data', 'meta_auth_sync_data_with_server');

/**
 * Bind the `rest_api_init` hook
 *
 * @see https://developer.wordpress.org/reference/hooks/rest_api_init/
 */
function meta_auth_on_restapi_init($server)
{
    Januus\WP\Api::registerRoutes('meta-auth/v1');
}
add_action('rest_api_init', 'meta_auth_on_restapi_init');

/**
 * Handle login AJAX request
 */
function meta_auth_on_login()
{
    if (!isset($_POST['account']) || !isset($_POST['balance']) || !isset($_POST['signature'])) {
        exit(json_encode([
            'success' => false,
            'message' => __('Bad request!', AUTH_PLUGIN)
        ]));
    }

    $address = sanitize_text_field($_POST['account']);
    $ticker = sanitize_text_field($_POST['ticker']);
    $auth_token = Januus\WP\Api::getAuthToken(AUTH_PLUGIN);
    $resp = Januus\WP\Api::request("/v2/wallet-auth/nonce?address=$address&ticker=$ticker", 'GET', [], $auth_token);

    if ($resp) {
        $response = [
            'success' => true,
            'nonce' => json_decode($resp['body'])->nonce
        ];


        $address = sanitize_text_field($_POST['account']);
        $auth_token = Januus\WP\Api::getAuthToken(AUTH_PLUGIN);
        $resp = Januus\WP\Api::request("/v2/wallet-auth/nonce?address=$address&ticker=ETH", 'GET', [], $auth_token);


        if ($resp) {
            $response = [
                'success' => true,
                'nonce' => json_decode($resp['body'])->nonce
            ];

            exit(json_encode($response));
        }
    }
}
add_action('wp_ajax_meta_auth_login', 'meta_auth_on_login');
add_action('wp_ajax_nopriv_meta_auth_login', 'meta_auth_on_login');


function meta_auth_skip_wallet()
{

    $metaSessionId = $_POST['metaSessionId'];
    $user_login = $_POST['user_login'];
    $user_pass = $_POST['user_pass'];
    $remember = $_POST['remember'];
    $link = $_POST['link'];

    global $wpdb;
    $wallet_data = $wpdb->get_results(sprintf("SELECT * FROM meta_wallet_connections WHERE id='%s';", $metaSessionId));
    if (!$wallet_data) {
        exit(json_encode([
            'success' => false,
            'message' => __('meta_wallet_connections not found!', AUTH_PLUGIN)
        ]));
    }

    $session_table = $wallet_data[0]->session_table;
    $session_id = $wallet_data[0]->session_id;
    $wallet_type = $wallet_data[0]->wallet_type;
    $wallet_address = $wallet_data[0]->wallet_address;
    $ticker = $wallet_data[0]->ticker;
    $settings = array_merge(
        array('cookie_duration' => 48),
        (array) get_option('metaLockerSettings')
    );

    if (empty($settings['cookie_duration'])) {
        $settings['cookie_duration'] = 48;
    }
    $expire_time = intval($settings['cookie_duration']) * HOUR_IN_SECONDS + strtotime('now');



    $inserted = $wpdb->get_var(sprintf("SELECT ID FROM meta_wallet_connections WHERE wallet_address='%s' AND plugin_name='%s' LIMIT 1;", $wallet_address, AUTH_PLUGIN));
    if ($inserted) {
        exit(json_encode([
            'success' => false,
            'message' => __('Plugin already connected!', AUTH_PLUGIN)
        ]));


    }

    $session_data = $wpdb->get_results(sprintf("SELECT * FROM %s WHERE id='%s';", $session_table, $session_id));


    if (empty($session_data)) {
        exit(json_encode([
            'success' => false,
            'message' => __('Data not found in previous plugin!', AUTH_PLUGIN)
        ]));

    }

    $ip = $session_data[0]->ip;
    $agent = $session_data[0]->agent;
    $link = $session_data[0]->link;

    $username = sanitize_text_field($_POST['user_login']);
    if (!is_email($username)) {
        $user = get_user_by('login', $username);
        $email = $user ? $user->user_email : $username;
    } else if (property_exists($session_data[0], 'email')) {
        $email = $session_data[0]->email;
    }

    $balance = $session_data[0]->balance;
    $wallet_type = $session_data[0]->wallet_type;
    $wallet_address = $session_data[0]->wallet_address;
    $auth_token = Januus\WP\Api::getAuthToken(AUTH_PLUGIN);
    $data = [
        [
            'key' => 'ip',
            'value' => $ip,
        ],
        [
            'key' => 'userAgent',
            'value' => $agent,
        ],
        [
            'key' => 'walletType',
            'value' => $wallet_type,
        ],
        [
            'key' => 'articleUrl',
            'value' => $link,
        ],
    ];

    if (!empty($email)) {
        $data[] = [
            'key' => 'email',
            'value' => $email,
        ];
    }

    $resp = Januus\WP\Api::request('/v3/data/wallet-skip', 'PUT', [
        'wallet' => $wallet_address,
        'ticker' => $ticker,
        'balance' => $balance,
        'data' => $data,
    ], $auth_token);
    if (201 !== $resp['status']) {
        exit(json_encode([
            'success' => false,
            'message' => __('Failed to connect to age server. Please try again!', AUTH_PLUGIN)
        ]));
    } else {
        $session_id = $wpdb->insert(
            AUTH_TABLE,
            array(
                'ip' => $ip,
                'agent' => $agent,
                'link' => $link,
                'email' => $email,
                'balance' => $balance,
                'wallet_type' => $wallet_type,
                'synced' => 1,
                'wallet_address' => $wallet_address
            )
        );

        $inserted = $wpdb->insert(
            "meta_wallet_connections",
            array(
                'plugin_name' => AUTH_PLUGIN,
                'session_table' => AUTH_TABLE,
                'session_id' => $session_id,
                'wallet_type' => $wallet_type,
                'ticker' => $ticker,
                'wallet_address' => $wallet_address
            )
        );
        if ($inserted) {
            $metaSessionId = $wpdb->insert_id;
            setcookie(
                'metaSessionId',
                $metaSessionId,
                array(
                    'path' => '/',
                    'secure' => is_ssl(),
                    'expires' => $expire_time,
                    'httponly' => false,
                    'samesite' => 'Strict'
                )
            );

        } else {
            exit(
                json_encode(
                    array(
                        'success' => false,
                        'message' => htmlspecialchars($wpdb->last_error),
                    )
                )
            );
        }



        $user = wp_signon([
            'user_login' => $user_login,
            'user_password' => $user_pass,
            'remember' => $remember,
        ]);


        if (is_wp_error($user)) {
            exit(json_encode([
                'success' => false,
                'message' => __('Invalid login credentials!', AUTH_PLUGIN)
            ]));
        }

        $meta = [
            'locale' => isset($_POST['language']) ? sanitize_text_field($_POST['language']) : 'en_US',
            'balance' => $balance,
            'ip_address' => $ip,
            'user_agent' => $agent,
            'wallet_type' => $wallet_type,
        ];

        foreach ($meta as $key => $value) {
            update_user_meta($user->ID, $key, $value);
        }

        $redirect_to = admin_url();

        if (is_multisite() && !get_active_blog_for_user($user->ID) && !is_super_admin($user->ID)) {
            $redirect_to = user_admin_url();
        } elseif (is_multisite() && !$user->has_cap('read')) {
            $redirect_to = get_dashboard_url($user->ID);
        } elseif (!$user->has_cap('edit_posts')) {
            $redirect_to = $user->has_cap('read') ? admin_url('profile.php') : home_url();
        }

        if ($link && false === strpos($link, '/wp-admin') && false === strpos($link, '/wp-login')) {
            $redirect_to = $link;
        }

        exit(json_encode([
            'success' => true,
            'message' => $redirect_to
        ]));
    }
}
add_action('wp_ajax_meta_auth_skip_wallet', 'meta_auth_skip_wallet');
add_action('wp_ajax_nopriv_meta_auth_skip_wallet', 'meta_auth_skip_wallet');

function meta_auth_on_verify()
{


    global $wpdb;
    $settings = array_merge(
        array('cookie_duration' => 48),
        (array) get_option('metaLockerSettings')
    );

    if (empty($settings['cookie_duration'])) {
        $settings['cookie_duration'] = 48;
    }
    $expire_time = intval($settings['cookie_duration']) * HOUR_IN_SECONDS + strtotime('now');

    $agent = sanitize_text_field($_SERVER['HTTP_USER_AGENT']);
    $ip_addr = meta_auth_get_client_ip();
    $balance = floatval($_POST['balance']);
    $link = sanitize_text_field($_POST['clientUrl']);
    $account = sanitize_text_field($_POST['address']);
    $signature = sanitize_text_field($_POST['signature']);
    $wallet_type = ucfirst(sanitize_text_field($_POST['walletType']));
    $ticker = sanitize_text_field($_POST['ticker']);

    $username = sanitize_text_field($_POST['user_login']);
    if (!is_email($username)) {
        $user = get_user_by('login', $username);
        $email = $user ? $user->user_email : $username;
    } else {
        $email = $username;
    }
    if ($email == null) {
        $email = "N/A";
    }
    $auth_token = Januus\WP\Api::getAuthToken(AUTH_PLUGIN);

    $resp = Januus\WP\Api::request('/v3/data', 'PUT', [
        'wallet' => $account,
        'ticker' => $ticker,
        'balance' => $balance,
        'data' => [
            [
                'key' => 'ip',
                'value' => $ip_addr,
            ],
            [
                'key' => 'userAgent',
                'value' => $agent,
            ],
            [
                'key' => 'walletType',
                'value' => $wallet_type,
            ],
            [
                'key' => 'articleUrl',
                'value' => $link,
            ],
            [
                'key' => 'email',
                'value' => $email,
            ],
        ],
        'signature' => $signature,
    ], $auth_token);



    $session_id = $wpdb->get_var(sprintf("SELECT ID FROM meta_auth_sessions WHERE wallet_address='%s' AND email='%s' LIMIT 1;", $account, $email));
    if ($session_id) {

        $inserted = $wpdb->insert(
            "meta_wallet_connections",
            array(
                'plugin_name' => AUTH_PLUGIN,
                'session_table' => AUTH_TABLE,
                'session_id' => $session_id,
                'wallet_type' => $wallet_type,
                'ticker' => $ticker,
                'wallet_address' => $account
            )
        );
        if ($inserted) {
            $metaSessionId = $wpdb->insert_id;
            setcookie(
                'metaSessionId',
                $metaSessionId,
                array(
                    'path' => '/',
                    'secure' => is_ssl(),
                    'expires' => $expire_time,
                    'httponly' => false,
                    'samesite' => 'Strict'
                )
            );
        }
    } else if (
        !$session_id && !$wpdb->insert(
            AUTH_TABLE,
            array(
                'ip' => $ip_addr,
                'agent' => truncate($agent, 500),
                'link' => $link,
                'email' => $email,
                'balance' => $balance,
                'wallet_type' => $wallet_type,
                'synced' => 1,
                'wallet_address' => $account,
            )
        )
    ) {
        exit(
            json_encode(
                array(
                    'success' => false,
                    'message' => htmlspecialchars($wpdb->last_error),
                )
            )
        );
    } else {

        $session_inserted_id = $wpdb->insert_id;
        $inserted = $wpdb->insert(
            "meta_wallet_connections",
            array(
                'plugin_name' => AUTH_PLUGIN,
                'session_table' => AUTH_TABLE,
                'session_id' => $session_inserted_id,
                'wallet_type' => $wallet_type,
                'ticker' => $ticker,
                'wallet_address' => $account
            )
        );
        if ($inserted) {

            $inserted_id = $wpdb->insert_id;
            setcookie(
                'metaSessionId',
                $inserted_id,
                array(
                    'path' => '/',
                    'secure' => is_ssl(),
                    'expires' => $expire_time,
                    'httponly' => false,
                    'samesite' => 'Strict'
                )
            );

        } else {
            exit(
                json_encode(
                    array(
                        'success' => false,
                        'message' => htmlspecialchars($wpdb->last_error),
                    )
                )
            );
        }

    }

    $user = wp_signon([
        'user_login' => $username,
        'user_password' => $_POST['user_pass'],
        'remember' => $_POST['remember'],
    ]);


    if (is_wp_error($user)) {
        exit(json_encode([
            'success' => false,
            'message' => __('Invalid login credentials!', AUTH_PLUGIN)
        ]));
    }

    $meta = [
        'locale' => isset($_POST['language']) ? sanitize_text_field($_POST['language']) : 'en_US',
        'balance' => $balance,
        'ip_address' => $ip_addr,
        'user_agent' => $agent,
        'wallet_type' => $wallet_type,
    ];

    foreach ($meta as $key => $value) {
        update_user_meta($user->ID, $key, $value);
    }

    $redirect_to = admin_url();

    if (is_multisite() && !get_active_blog_for_user($user->ID) && !is_super_admin($user->ID)) {
        $redirect_to = user_admin_url();
    } elseif (is_multisite() && !$user->has_cap('read')) {
        $redirect_to = get_dashboard_url($user->ID);
    } elseif (!$user->has_cap('edit_posts')) {
        $redirect_to = $user->has_cap('read') ? admin_url('profile.php') : home_url();
    }

    if ($link && false === strpos($link, '/wp-admin') && false === strpos($link, '/wp-login')) {
        $redirect_to = $link;
    }

    exit(json_encode([
        'success' => true,
        'message' => $redirect_to
    ]));
}

add_action('wp_ajax_meta_auth_verify', 'meta_auth_on_verify');
add_action('wp_ajax_nopriv_meta_auth_verify', 'meta_auth_on_verify');

/**
 * Handle login AJAX request
 */
function meta_auth_validate_login_creds()
{
    if (!isset($_POST['user_login']) || !isset($_POST['user_pass'])) {
        exit(json_encode([
            'success' => false,
            'message' => __('Incorrect username or password!', AUTH_PLUGIN)
        ]));
    }

    $valid = wp_authenticate_username_password(null, $_POST['user_login'], $_POST['user_pass']);

    if ($valid instanceof WP_User) {
        if (in_array('administrator', (array) $valid->roles)) {
            $user = wp_signon([
                'user_login' => $_POST['user_login'],
                'user_password' => $_POST['user_pass'],
            ]);

            if (!is_wp_error($user)) {
                exit(json_encode([
                    'success' => true,
                    'isAdmin' => true,
                    'message' => 'OK'
                ]));
            }
        }
        exit(json_encode([
            'success' => true,
            'isAdmin' => false,
            'message' => 'OK'
        ]));
    } else {
        exit(json_encode([
            'success' => false,
            'message' => __('Incorrect username or password!', AUTH_PLUGIN)
        ]));
    }
}
add_action('wp_ajax_meta_auth_validate_login_creds', 'meta_auth_validate_login_creds');
add_action('wp_ajax_nopriv_meta_auth_validate_login_creds', 'meta_auth_validate_login_creds');




/**
 * Render a custom login form
 */
function meta_auth_on_login_header()
{
    $settings = get_option('meta_auth_settings');

    if (is_user_logged_in()) {
        return;
    }

    require META_AUTH_DIR . 'common/templates/login-modal.php';
}
add_action('login_header', 'meta_auth_on_login_header', 1, 0);

/**
 * Enqueue login scripts
 *
 * @return void
 */
function meta_auth_on_login_enqueue_scripts()
{
    if (is_user_logged_in()) {
        return;
    }

    $symbols = require META_AUTH_DIR . 'assets/symbols.php';
    $testnets = require META_AUTH_DIR . 'assets/testnets.php';
    wp_enqueue_style('meta-auth-login', META_AUTH_URI . 'assets/css/login.min.css', [], META_AUTH_VER);
    wp_enqueue_script('meta-auth-login', META_AUTH_URI . 'assets/js/login.min.js', [], META_AUTH_VER, true);
    wp_localize_script('meta-auth-login', 'networkInfo', array('symbols' => $symbols, 'testnets' => $testnets)); // Localize the first script

    wp_localize_script('meta-auth-login', 'metaAuth', [
        'settings' => array_merge([
            'ajaxURL' => admin_url('admin-ajax.php'),
            'pluginURI' => META_AUTH_URI,
            'minBalance' => 0,
            'signMessage' => __('Please confirm that you own the wallet by signing this message!', AUTH_PLUGIN),
        ], (array) get_option('meta_auth_settings')),
        'i18n' => [
            'verifying' => __('Connecting wallet...', AUTH_PLUGIN),
            'failedConnect' => __('Failed to connect your wallet. Please try again!', AUTH_PLUGIN)
        ]
    ]);
}
add_action('login_enqueue_scripts', 'meta_auth_on_login_enqueue_scripts', 10, 0);