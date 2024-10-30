<?php

namespace Januus\WP;

use WP_Error;
use Exception;
use WP_REST_Server;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use DateTimeImmutable;

/**
 * Api
 */
final class Api
{
    /**
     * @var string
     */
    const BASE_URL = 'https://metalocker.service.metaplugins.io';

    /**
     * Register REST routes
     *
     * @see https://developer.wordpress.org/reference/functions/register_rest_route/
     */
    static function registerRoutes($namespace)
    {
        register_rest_route($namespace, 'key', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => __CLASS__ . '::getPublicKey',
                'permission_callback' => '__return_true'
            ]
        ]);
    }

    /**
     * Get registration status
     *
     * @return bool
     */
    static function getActivationStatus($plugin)
    {
        $resp = self::request('/v1/plugin/status', 'GET', [], self::getAuthToken($plugin));

        if ($resp['status'] === 400 || !$resp['body']) {
            return false;
        } else {
            $body = json_decode($resp['body']);
            return isset($body->status)?$body->status:false;
        }
    }

    /**
     * Do registration
     *
     * @param string $plugin The slug of the plugin.
     * @param string $email The user email.
     * @return bool
     */
    static function registerSite($address, $plugin, $email, $ticker)
    {
        $resp = self::request('/v2/auth/register', 'POST', ['email' => $email, 'wallet' => ['address' => $address, 'ticker' => $ticker]], self::getAuthToken($plugin));

        if ($resp['status'] === 200) {
            $body = json_decode($resp['body']);
            return $body->status;
        }      
        else {
            return false;
        }
    }

    /**
     * Get the public key
     */
    static function getPublicKey($request)
    {
        $public_key = get_option('meta_public_key');

        if (!$public_key) {
            return new WP_Error('not_found', 'Public key not found!', ['status' => 404]);
        }
       
        return rest_ensure_response(['publicKey' => trim(preg_replace('/\s+/', ' ', $public_key))]);
    }

    /**
     * Setup keypair
     */
    static function setupKeypair($force = false)
    {
        if (get_option('meta_public_key') && get_option('meta_private_key') && !$force) {
            return;
        }

        if (!function_exists('openssl_pkey_new')) {
            throw new Exception('OpenSSL extension is not installed!');
        } else {
            $rsa_key = openssl_pkey_new([
                'digest_alg' => 'sha256',
                'private_key_bits' => 4096,
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
            ]);

            if (!$rsa_key) {
                throw new Exception(__('Unable to setup private key. Please try activating the plugin again!', AUTH_PLUGIN));
            }

            $public_key = openssl_pkey_get_details($rsa_key)['key'];

            openssl_pkey_export($rsa_key, $private_key);

            if (!update_option('meta_public_key', trim($public_key)) || !update_option('meta_private_key', trim($private_key))) {
                throw new Exception('Failed to setup SSL keypair!');
            }
        }
    }

    /**
     * Authorize the Bearer JWT
     */
    static function authorizeKey($key)
    {
        $site_url = get_site_url();
        $public_key = self::getServerPubKey();

        if (!$public_key) {
            return false;
        }

        try {
            $jwt_token = JWT::decode($key, new Key($public_key, 'RS256'));
        } catch (Exception $e) {
            $jwt_token = false;
        }

        if ($jwt_token->website !== $site_url) {
            return false;
        }

        return $jwt_token;
    }

    /**
     * Retrieve the JWT from the authorization header.
     *
     * @param array $request
     * @return string
     */
    static function getAuthKey($request)
    {
        preg_match('/Bearer\s(\S+)/', $_SERVER['HTTP_AUTHORIZATION'], $matches);

        return empty($matches[1]) ? '' : $matches[1];
    }

    /**
     * Retrieve public key of the central server
     *
     * @return bool|string
     */
    static function getServerPubKey()
    {
        $resp = self::request('/v1/key/public', 'GET', []);

        if ($resp['status'] !== 200) {
            return false;
        } else {
            $body = json_decode($resp['body']);
            return trim($body->publicKey);
        }
    }

    /**
     * Get generated private key
     *
     * @return string
     */
    static function getPrivateKey()
    {
        return wp_unslash(get_option('meta_private_key'));
    }

    /**
     * Do a CURL request
     *
     * @param string $endpoint Endpoint path. Relative to the BASE_URL.
     * @param string $method
     * @param array $params
     * @return array
     */
    static function request($endpoint, $method, array $params, $auth = false, $timeout = 60)
    {
        $curl = curl_init(self::BASE_URL . $endpoint);

        $headers = ['Accept: application/json', 'User-Agent: MetaPlugins'];

        if ($auth) {
            $headers[] = 'Authorization: Bearer ' . $auth;
        }

        curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        if (!empty($params)) {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($params));
        }

        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $resp = curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);
        return ['status' => $code, 'body' => $resp];
    }

    /**
     * Create a Bearer token for authorization
     *
     * @param string $plugin Slug of the plugin.
     * @return string
     */
    static function getAuthToken($plugin)
    {
        $transient = get_transient('meta_auth_token'); //Get token transient if exist
        if ($transient==false) {

            $time = new DateTimeImmutable();
            $claims = [             
                'iat' => $time->modify('-2 minutes')->getTimestamp(),
                'exp' => $time->modify('+2 hour')->getTimestamp(),
                'slug' => '/wp-json/' . $plugin,
                'website' => get_site_url(),
                'name' => $plugin,
                'ver' => META_AUTH_VER,
            ];
            $token = JWT::encode($claims, self::getPrivateKey(), 'RS256');
            set_transient('meta_auth_token', $token, 60 * MINUTE_IN_SECONDS); //Set 60 minute token transient
            return $token;
        } else {
            return $transient; //Return transient of token
        }
    }
}