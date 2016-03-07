<?php
if(!empty($_GET)) {

    $date = new DateTime($_GET['matchDate'], new DateTimeZone($_GET['matchTimezone']));

    // Get cURL resource
    $curl = curl_init();
    $data = '{"date": "'.$date->format(DATE_ISO8601).'", "timezone": "'.$_GET['matchTimezone'].'"}';

    curl_setopt_array(
        $curl, [
        CURLOPT_URL             => 'https://api.toornament.com/v1/tournaments/'.$_GET['tournamentId'].'/matches/'.$_GET['matchId'],
        CURLOPT_RETURNTRANSFER  => true,
        CURLOPT_VERBOSE         => true,
        CURLOPT_HEADER          => true,
        CURLOPT_SSL_VERIFYPEER  => false,
        CURLOPT_HTTPHEADER      => [
            'X-Api-Key: '.trim($_GET['apiKey']),
            'Authorization: Bearer '.$_GET['accessToken'],
            'Content-Type: application/json'
        ],
        CURLOPT_CUSTOMREQUEST   => 'PATCH',
        CURLOPT_POSTFIELDS      => $data
    ]);

    $output         = curl_exec($curl);

    $header_size    = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    $header         = substr($output, 0, $header_size);
    $body           = substr($output, $header_size);

    // Close request to clear up some resources
    curl_close($curl);

    return json_decode($body);
}
