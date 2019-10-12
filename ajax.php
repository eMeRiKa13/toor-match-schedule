<?php
if(!empty($_GET)) {

    // Get Access Token
    include_once('oauth.php');

    // set scope for access token to organizer:result per the API v2.0 authorization Scopes spec
    $scope = "organizer:result";

    $access_token = getAccessTokenScope(trim($_GET['api_client_id']), trim($_GET['api_client_secret']), trim($_GET['api_key']), $scope);


     $dataDate = explode('/', $_GET['date']);
    $dataTime = explode(':', $_GET['hour']);

    $date = new DateTime('now', new DateTimeZone($_GET['timezone']));
    $date->setDate($dataDate[2], $dataDate[1], $dataDate[0]);
    $date->setTime($dataTime[0], $dataTime[1]);

    // Get cURL resource
    $curl = curl_init();

    curl_setopt($curl, CURLINFO_HEADER_OUT, true); // enable tracking
    
    $data = '{"scheduled_datetime": "'.$date->format(DATE_ISO8601).'"}';
    echo $data;

    curl_setopt_array(
        $curl, array(
        CURLOPT_URL             => 'https://api.toornament.com/organizer/v2/tournaments/'.trim($_GET['toor_id']).'/matches/'.trim($_GET['match_id']),
        CURLOPT_RETURNTRANSFER  => true,
        CURLOPT_VERBOSE         => true,
        CURLOPT_HEADER          => true,
        CURLOPT_SSL_VERIFYPEER  => false,
        CURLOPT_HTTPHEADER      => array(
            'X-Api-Key: '.trim($_GET['api_key']),
            'Authorization: Bearer '.$access_token,
            'organizer:result',
            'Content-Type: application/json'

        ),
        CURLOPT_CUSTOMREQUEST   => 'PATCH',
        CURLOPT_POSTFIELDS      => $data
    ));

    $output         = curl_exec($curl);

    $header_size    = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    $header         = substr($output, 0, $header_size);
    $body           = substr($output, $header_size);




  
$headerSent = curl_getinfo($curl, CURLINFO_HEADER_OUT ); // request headers
echo $headerSent;
echo $output;
    // Close request to clear up some resources
    curl_close($curl);

    //return json_decode($body);
}
?>

