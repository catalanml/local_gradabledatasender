<?php

/**
 *
 * @package   local_gradabledatasender
 * @author    Lucas Catalan <catalan.munoz.l@gmail.com>
 */

define('UDLALOGINWS', '/api/v1.0/login');

require_once($CFG->dirroot . '/local/gradabledatasender/classes/local_gradabledatasender_curl_manager.php');

defined('MOODLE_INTERNAL') || die;


/**
 * Get the current token from the destiny endpoint
 * Return the token or false if the request fails
 * @return string|bool
 */


function refresh_token()
{

    $curl = new \local_gradabledatasender_curl_manager();

    $destiny_endpoint = get_config('gradabledatasender', 'destiny_endpoint');
    $endpoint_username = get_config('gradabledatasender', 'endpoint_username');
    $endpoint_password = get_config('gradabledatasender', 'endpoint_password');


    $data = [
        'username' => $endpoint_username,
        'password' => $endpoint_password,
    ];

    $headers[] = 'Content-Type: application/json';

    try {
        $wsresult  = $curl->make_request(
            $destiny_endpoint . UDLALOGINWS,
            'POST',
            $data,
            $headers
        );

        if ($wsresult->remote_endpoint_status === 200) {
            return $wsresult->remote_endpoint_response->data->accessToken;; //$remote_response->token;
        } else {
            return false;
        }
    } catch (\Throwable $th) {
        return false;
    }
}
