<?php

function getAccessToken($api_client_id, $api_client_secret, $api_key) {

    $json       = file_get_contents("access_token.json");
    $token_data = json_decode($json, true);

    if($token_data['date'] < (time() - (24 * 60 * 59))) {

        // Get cURL resource
        $curl = curl_init();
        curl_setopt_array(
            $curl, array(
            CURLOPT_URL             => 'https://api.toornament.com/oauth/v2/token?grant_type=client_credentials&client_id='.$api_client_id.'&client_secret='.$api_client_secret,
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_VERBOSE         => true,
            CURLOPT_HEADER          => true,
            CURLOPT_SSL_VERIFYPEER  => false,
            CURLOPT_HTTPHEADER      => array(
                'X-Api-Key: '.$api_key,
                'Content-Type: application/json'
            )
        ));

        $output         = curl_exec($curl);

        $header_size    = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $header         = substr($output, 0, $header_size);
        $body           = substr($output, $header_size);
        $body           = json_decode($body);

        if(isset($body->access_token)) {
            $access_token   = $body->access_token;
            file_put_contents("access_token.json", '{"access_token": "'.$access_token.'", "date": '.time().'}');
        }

    } else {
        $access_token = $token_data['access_token'];
    }

    return $access_token;
}