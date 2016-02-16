<?php
if(!empty($_GET)) {

    // Get Access Token
    include_once('oauth.php');
    $access_token = getAccessToken(trim($_GET['api_client_id']), trim($_GET['api_client_secret']), trim($_GET['api_key']));

    $dataDate = explode('/', $_GET['date']);
    $dataTime = explode(':', $_GET['hour']);

    $date = new DateTime('now', new DateTimeZone($_GET['timezone']));
    $date->setDate($dataDate[2], $dataDate[1], $dataDate[0]);
    $date->setTime($dataTime[0], $dataTime[1]);

    // Get cURL resource
    $curl = curl_init();
    $data = '{"date": "'.$date->format(DATE_ISO8601).'", "timezone": "'.$_GET['timezone'].'"}';
    echo $data;

    curl_setopt_array(
        $curl, array(
        CURLOPT_URL             => 'https://api.toornament.com/v1/tournaments/'.trim($_GET['toor_id']).'/matches/'.trim($_GET['match_id']),
        CURLOPT_RETURNTRANSFER  => true,
        CURLOPT_VERBOSE         => true,
        CURLOPT_HEADER          => true,
        CURLOPT_SSL_VERIFYPEER  => false,
        CURLOPT_HTTPHEADER      => array(
            'X-Api-Key: '.trim($_GET['api_key']),
            'Authorization: Bearer '.$access_token,
            'Content-Type: application/json'
        ),
        CURLOPT_CUSTOMREQUEST   => 'PATCH',
        CURLOPT_POSTFIELDS      => $data
    ));

    $output         = curl_exec($curl);

    $header_size    = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    $header         = substr($output, 0, $header_size);
    $body           = substr($output, $header_size);

    // Close request to clear up some resources
    curl_close($curl);

    return json_decode($body);
}
?>

