<?php
error_reporting(E_ERROR | E_WARNING | E_PARSE);
ini_set('display_errors', 1);
session_start();

// Import Matches
if((!empty($_POST) && $_POST['import_matches'] == 1) || isset($_GET['page'])) {

    if(isset($_POST['import_matches'])) {
        $_SESSION['form_data'] = $_POST;
    }

    // Get Access Token
    include_once('oauth.php');
    $access_token = getAccessToken(trim($_SESSION['form_data']['api_client_id']), trim($_SESSION['form_data']['api_client_secret']), trim($_SESSION['form_data']['api_key']));

    // Get cURL resource
    $curl = curl_init();

    curl_setopt_array(
        $curl, array(
        CURLOPT_URL             => 'https://api.toornament.com/v1/tournaments/'.trim($_SESSION['form_data']['toor_id']),
        CURLOPT_RETURNTRANSFER  => true,
        CURLOPT_VERBOSE         => true,
        CURLOPT_HEADER          => true,
        CURLOPT_SSL_VERIFYPEER  => false,
        CURLOPT_HTTPHEADER      => array(
            'X-Api-Key: '.trim($_SESSION['form_data']['api_key']),
            'Authorization: Bearer '.$access_token,
            'Content-Type: application/json'
        )
    ));
    $output         = curl_exec($curl);
    $header_size    = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    $header         = substr($output, 0, $header_size);
    $body           = substr($output, $header_size);

    $tournament = json_decode($body);

    curl_setopt_array(
        $curl, array(
        CURLOPT_URL             => 'https://api.toornament.com/viewer/v2/tournaments/'.trim($_SESSION['form_data']['toor_id']).'/stages',
        CURLOPT_RETURNTRANSFER  => true,
        CURLOPT_VERBOSE         => true,
        CURLOPT_HEADER          => true,
        CURLOPT_SSL_VERIFYPEER  => false,
        CURLOPT_HTTPHEADER      => array(
            'X-Api-Key: '.trim($_SESSION['form_data']['api_key']),
            'Authorization: Bearer '.$access_token,
            'Content-Type: application/json'
        )
    ));
    $output         = curl_exec($curl);
    $header_size    = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    $header         = substr($output, 0, $header_size);
    $body           = substr($output, $header_size);

    $temp = json_decode($body);
    $stages = array();
    foreach($temp as $stage) {
        $stages[$stage->id] = $stage;
    }

    curl_setopt_array(
        $curl, array(
        CURLOPT_URL             => 'https://api.toornament.com/viewer/v2/tournaments/'.trim($_SESSION['form_data']['toor_id']).'/groups',
        CURLOPT_RETURNTRANSFER  => true,
        CURLOPT_VERBOSE         => true,
        CURLOPT_HEADER          => true,
        CURLOPT_SSL_VERIFYPEER  => false,
        CURLOPT_HTTPHEADER      => array(
            'X-Api-Key: '.trim($_SESSION['form_data']['api_key']),
            'Authorization: Bearer '.$access_token,
            'Content-Type: application/json',
            'Range: groups=0-49'
        )
    ));
    $output         = curl_exec($curl);
    $header_size    = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    $header         = substr($output, 0, $header_size);
    $body           = substr($output, $header_size);

    $temp = json_decode($body);
    $groups = array();
    foreach($temp as $group) {
        $groups[$group->id] = $group;
    }

    if(isset($_GET['page'])) {
        $range = (($_GET['page']-1)*100).'-'.(($_GET['page']-1)*100+99);
    } else {
        $range = '0-99';
    }

    curl_setopt_array(
        $curl, array(
        CURLOPT_URL             => 'https://api.toornament.com/viewer/v2/tournaments/'.trim($_SESSION['form_data']['toor_id']).'/matches',
        CURLOPT_RETURNTRANSFER  => true,
        CURLOPT_VERBOSE         => true,
        CURLOPT_HEADER          => true,
        CURLOPT_SSL_VERIFYPEER  => false,
        CURLOPT_HTTPHEADER      => array(
            'X-Api-Key: '.trim($_SESSION['form_data']['api_key']),
            'Authorization: Bearer '.$access_token,
            'Content-Type: application/json',
            'Range: matches='.$range
        )
    ));
    $output         = curl_exec($curl);
    $message        = parse_message($output);

    // Close request to clear up some resources
    curl_close($curl);

    $matches = json_decode($message['body']);

    preg_match('^\w+ (\d+)-(\d+)\/(\d+)$^', $message['headers']['Content-Range'][0], $pagination);

    $nb_pages = round($pagination[3] / 100);
}

/**
 * Parses an HTTP message into an associative array.
 *
 * The array contains the "start-line" key containing the start line of
 * the message, "headers" key containing an associative array of header
 * array values, and a "body" key containing the body of the message.
 *
 * @param string $message HTTP request or response to parse.
 *
 * @return array
 * @internal
 */
function parse_message($message)
{
    if (!$message) {
        throw new \InvalidArgumentException('Invalid message');
    }
    // Iterate over each line in the message, accounting for line endings
    $lines = preg_split('/(\\r?\\n)/', $message, -1, PREG_SPLIT_DELIM_CAPTURE);
    $headers = array('start-line' => array_shift($lines), 'headers' => array(), 'body' => '');
    array_shift($lines);
    for ($i = 0, $totalLines = count($lines); $i < $totalLines; $i += 2) {
        $line = $lines[$i];
        // If two line breaks were encountered, then this is the end of body
        if (empty($line)) {
            if ($i < $totalLines - 1) {
                $result['body'] = implode('', array_slice($lines, $i + 2));
            }
            break;
        }
        if (strpos($line, ':')) {
            $parts = explode(':', $line, 2);
            $key = trim($parts[0]);
            $value = isset($parts[1]) ? trim($parts[1]) : '';
            $result['headers'][$key][] = $value;
        }
    }
    return $result;
}

?>

<!doctype html>
<html>
<head>
    <title>Matches Scheduling Tool for Toornament.com</title>
    <meta charset="utf-8">
    <link rel="stylesheet" href="pure-min.css">
    <style type="text/css">
        header {
            width: 100%;
            background-color: #262626;
        }
        header h1 {
            width: 1400px;
            padding: 20px 0 0;
            margin: 0 auto;
            color: #ffffff;
        }
        header h2 {
            width: 1400px;
            padding: 10px 0 20px;
            margin: 0 auto;
            color: #ffffff;
        }
        #content {
            width: 1400px;
            margin: 50px auto;
        }
        ul {
            list-style-type: none;
            margin: 0 0 40px;
        }
        ul li {
            padding: 5px 0;
        }
        ul .name {
            display: inline-block;
            width: 300px;
            float: left;
            text-align: right;
            margin-right: 30px;
            margin-top: 8px;
            font-size: 24px;
        }
        h3 {
            font-size: 26px;
            line-height: 30px;
            background-color: #E5E5E5;
            color: #3B3B3B;
            padding: 5px 10px;
        }
        h4 {
            font-size: 22px;
            line-height: 30px;
            border-bottom: 1px solid #E5E5E5;
            padding: 5px 10px;
            margin: 0 0 15px;
        }
        h4 img {
            height: 30px;
            vertical-align: middle;
            margin-right: 5px;
        }

        .pure-table td, .pure-table th {
            text-align: center;
            padding: 15px;
        }

        th, td {
            text-align: left;
        }

        #list_matchs {
            float: left;
            width: 972px;
        }

        #config {
            float: right;
            width: 400px;
        }

        .clearfix {
            overflow: auto;
            zoom: 1;
        }

        label {
            display: block;
            font-size: 16px;
            line-height: 20px;
        }

        #config input {
            width: 388px;
        }

        #config select {
            width: 388px;
        }

        .button-success {
            background: rgb(28, 184, 65); /* this is a green */
        }

        .hide {
            display: none;
        }

        .pagination {
            text-align: center;
            padding: 40px 0;
            margin: 0;
        }

        .pagination a {
            display: inline-block;
            padding: 8px 16px;
            color: #FFFFFF;
            background-color: #009AFF;
            text-decoration: none;
            font-size: 20px;
            margin: 0 8px;
        }

        .pagination a:hover {
            background-color: #0073D8;
        }

        .pagination a.current {
            background-color: #0073D8;
        }
    </style>
</head>
<body>

<header>
    <h1>Matches Scheduling Tool</h1>
    <h2>For Toornament.com</h2>
</header>

<div id="content" class="clearfix">

    <form method="post" action="" class="pure-form">

        <div id="list_matchs">

            <h4>Matches List</h4>

            <table class="pure-table pure-table-horizontal">
                <thead>
                    <tr>
                        <th style="text-align: left; width: 350px;">Info</th>
                        <th style="text-align: left;">Date</th>
                        <th style="text-align: left;">Hour (24 clock format)</th>
                        <th style="text-align: left; width: 115px;"></th>
                    </tr>
                </thead>

                <tbody>
                <?php if(isset($matches)) : foreach($matches as $match) : ?>

                    <?php
                    if(isset($match->scheduled_datetime) AND $match->scheduled_datetime != null AND isset($tournament->timezone) AND $tournament->timezone != null) {
                        $date = new DateTime($match->scheduled_datetime);
                        $date->setTimezone(new DateTimeZone($tournament->timezone));

                    } else {
                        $date = null;
                    } ?>

                    <tr>
                        <td style="text-align: left;">
                            <span style="font-size: 12px;">Stage <?php echo $stages[$match->stage_id]->name; ?> / Group <?php echo $groups[$match->group_id]->name; ?></span><br />
                            <strong>
                                <?php if(isset($match->opponents[0]->participant)) echo $match->opponents[0]->participant->name ?>
                            </strong>
                                vs
                            <strong>
                                <?php if(isset($match->opponents[1]->participant)) echo $match->opponents[1]->participant->name ?>
                            </strong>
                        </td>
                        <td><input type="text" name="match_date" value="<?php if($date != null) echo $date->format('d/m/Y'); ?>" placeholder="JJ/MM/AAAA" autocomplete="off" /></td>
                        <td><input type="text" name="match_hour" value="<?php if($date != null) echo $date->format('H:i'); ?>" placeholder="HH:MM" autocomplete="off" /></td>
                        <td style="text-align: right;">
                            <img class="hide" style="float: left; margin: 10px 10px;" src="loading.gif" alt="" />
                            <button id="<?php echo $match->id; ?>" class="pure-button pure-button-primary button-success" type="button" name="update_match" value="1">Save</button>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>

            </table>

            <p class="pagination">
            <?php for($i=1;$i<=$nb_pages;$i++) :?>
                <a <?php if($_GET['page'] == $i) echo 'class="current"'; ?> href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
            <?php endfor;?>
            </p>

        </div>

        <div id="config">

            <h4>Configuration</h4>

            <p style="color: dimgrey; font-size: 14px;">
                Create an <a target="_blank" href="https://developer.toornament.com/applications">application for the Toornament API</a> and copy your API IDs:
            </p>

            <p>
                <label>API Key</label>
                <input type="text" name="api_key" value="<?php if(isset($_SESSION['form_data']['api_key'])) echo $_SESSION['form_data']['api_key']; ?>" autocomplete="off" />
            </p>

            <p>
                <label>API Client ID</label>
                <input type="text" name="api_client_id" value="<?php if(isset($_SESSION['form_data']['api_client_id'])) echo $_SESSION['form_data']['api_client_id']; ?>" autocomplete="off" />
            </p>

            <p>
                <label>API Client Secret</label>
                <input type="text" name="api_client_secret" value="<?php if(isset($_SESSION['form_data']['api_client_secret'])) echo $_SESSION['form_data']['api_client_secret']; ?>" autocomplete="off" />
            </p>

            <p>
                <label>Toornament ID</label>
                <input type="text" name="toor_id" value="<?php if(isset($_SESSION['form_data']['toor_id'])) echo $_SESSION['form_data']['toor_id']; ?>" autocomplete="off" />
                <br />
                <span style="color: dimgrey; font-size: 14px;">
                    Copy your tournament ID, example https://organizer.toornament.com/tournaments/441680454962233344/matches/ id is 441680454962233344
                </span>
            </p>

            <p>
                <label>Timezone</label>
                <select name="timezone">
                    <?php if(isset($_SESSION['form_data']['timezone'])) : ?>
                        <option selected="selected" value="<?php echo $_SESSION['form_data']['timezone']; ?>"><?php echo $_SESSION['form_data']['timezone']; ?></option>
                    <?php endif; ?>
                    <option value="">No timezone</option><option value="Africa/Abidjan">Africa/Abidjan</option><option value="Africa/Accra">Africa/Accra</option><option value="Africa/Addis_Ababa">Africa/Addis Ababa</option><option value="Africa/Algiers">Africa/Algiers</option><option value="Africa/Asmara">Africa/Asmara</option><option value="Africa/Bamako">Africa/Bamako</option><option value="Africa/Bangui">Africa/Bangui</option><option value="Africa/Banjul">Africa/Banjul</option><option value="Africa/Bissau">Africa/Bissau</option><option value="Africa/Blantyre">Africa/Blantyre</option><option value="Africa/Brazzaville">Africa/Brazzaville</option><option value="Africa/Bujumbura">Africa/Bujumbura</option><option value="Africa/Cairo">Africa/Cairo</option><option value="Africa/Casablanca">Africa/Casablanca</option><option value="Africa/Ceuta">Africa/Ceuta</option><option value="Africa/Conakry">Africa/Conakry</option><option value="Africa/Dakar">Africa/Dakar</option><option value="Africa/Dar_es_Salaam">Africa/Dar es Salaam</option><option value="Africa/Djibouti">Africa/Djibouti</option><option value="Africa/Douala">Africa/Douala</option><option value="Africa/El_Aaiun">Africa/El Aaiun</option><option value="Africa/Freetown">Africa/Freetown</option><option value="Africa/Gaborone">Africa/Gaborone</option><option value="Africa/Harare">Africa/Harare</option><option value="Africa/Johannesburg">Africa/Johannesburg</option><option value="Africa/Juba">Africa/Juba</option><option value="Africa/Kampala">Africa/Kampala</option><option value="Africa/Khartoum">Africa/Khartoum</option><option value="Africa/Kigali">Africa/Kigali</option><option value="Africa/Kinshasa">Africa/Kinshasa</option><option value="Africa/Lagos">Africa/Lagos</option><option value="Africa/Libreville">Africa/Libreville</option><option value="Africa/Lome">Africa/Lome</option><option value="Africa/Luanda">Africa/Luanda</option><option value="Africa/Lubumbashi">Africa/Lubumbashi</option><option value="Africa/Lusaka">Africa/Lusaka</option><option value="Africa/Malabo">Africa/Malabo</option><option value="Africa/Maputo">Africa/Maputo</option><option value="Africa/Maseru">Africa/Maseru</option><option value="Africa/Mbabane">Africa/Mbabane</option><option value="Africa/Mogadishu">Africa/Mogadishu</option><option value="Africa/Monrovia">Africa/Monrovia</option><option value="Africa/Nairobi">Africa/Nairobi</option><option value="Africa/Ndjamena">Africa/Ndjamena</option><option value="Africa/Niamey">Africa/Niamey</option><option value="Africa/Nouakchott">Africa/Nouakchott</option><option value="Africa/Ouagadougou">Africa/Ouagadougou</option><option value="Africa/Porto-Novo">Africa/Porto-Novo</option><option value="Africa/Sao_Tome">Africa/Sao Tome</option><option value="Africa/Tripoli">Africa/Tripoli</option><option value="Africa/Tunis">Africa/Tunis</option><option value="Africa/Windhoek">Africa/Windhoek</option><option value="America/Adak">America/Adak</option><option value="America/Anchorage">America/Anchorage</option><option value="America/Anguilla">America/Anguilla</option><option value="America/Antigua">America/Antigua</option><option value="America/Araguaina">America/Araguaina</option><option value="America/Argentina/Buenos_Aires">America/Argentina/Buenos Aires</option><option value="America/Argentina/Catamarca">America/Argentina/Catamarca</option><option value="America/Argentina/Cordoba">America/Argentina/Cordoba</option><option value="America/Argentina/Jujuy">America/Argentina/Jujuy</option><option value="America/Argentina/La_Rioja">America/Argentina/La Rioja</option><option value="America/Argentina/Mendoza">America/Argentina/Mendoza</option><option value="America/Argentina/Rio_Gallegos">America/Argentina/Rio Gallegos</option><option value="America/Argentina/Salta">America/Argentina/Salta</option><option value="America/Argentina/San_Juan">America/Argentina/San Juan</option><option value="America/Argentina/San_Luis">America/Argentina/San Luis</option><option value="America/Argentina/Tucuman">America/Argentina/Tucuman</option><option value="America/Argentina/Ushuaia">America/Argentina/Ushuaia</option><option value="America/Aruba">America/Aruba</option><option value="America/Asuncion">America/Asuncion</option><option value="America/Atikokan">America/Atikokan</option><option value="America/Bahia">America/Bahia</option><option value="America/Bahia_Banderas">America/Bahia Banderas</option><option value="America/Barbados">America/Barbados</option><option value="America/Belem">America/Belem</option><option value="America/Belize">America/Belize</option><option value="America/Blanc-Sablon">America/Blanc-Sablon</option><option value="America/Boa_Vista">America/Boa Vista</option><option value="America/Bogota">America/Bogota</option><option value="America/Boise">America/Boise</option><option value="America/Cambridge_Bay">America/Cambridge Bay</option><option value="America/Campo_Grande">America/Campo Grande</option><option value="America/Cancun">America/Cancun</option><option value="America/Caracas">America/Caracas</option><option value="America/Cayenne">America/Cayenne</option><option value="America/Cayman">America/Cayman</option><option value="America/Chicago">America/Chicago</option><option value="America/Chihuahua">America/Chihuahua</option><option value="America/Costa_Rica">America/Costa Rica</option><option value="America/Creston">America/Creston</option><option value="America/Cuiaba">America/Cuiaba</option><option value="America/Curacao">America/Curacao</option><option value="America/Danmarkshavn">America/Danmarkshavn</option><option value="America/Dawson">America/Dawson</option><option value="America/Dawson_Creek">America/Dawson Creek</option><option value="America/Denver">America/Denver</option><option value="America/Detroit">America/Detroit</option><option value="America/Dominica">America/Dominica</option><option value="America/Edmonton">America/Edmonton</option><option value="America/Eirunepe">America/Eirunepe</option><option value="America/El_Salvador">America/El Salvador</option><option value="America/Fortaleza">America/Fortaleza</option><option value="America/Glace_Bay">America/Glace Bay</option><option value="America/Godthab">America/Godthab</option><option value="America/Goose_Bay">America/Goose Bay</option><option value="America/Grand_Turk">America/Grand Turk</option><option value="America/Grenada">America/Grenada</option><option value="America/Guadeloupe">America/Guadeloupe</option><option value="America/Guatemala">America/Guatemala</option><option value="America/Guayaquil">America/Guayaquil</option><option value="America/Guyana">America/Guyana</option><option value="America/Halifax">America/Halifax</option><option value="America/Havana">America/Havana</option><option value="America/Hermosillo">America/Hermosillo</option><option value="America/Indiana/Indianapolis">America/Indiana/Indianapolis</option><option value="America/Indiana/Knox">America/Indiana/Knox</option><option value="America/Indiana/Marengo">America/Indiana/Marengo</option><option value="America/Indiana/Petersburg">America/Indiana/Petersburg</option><option value="America/Indiana/Tell_City">America/Indiana/Tell City</option><option value="America/Indiana/Vevay">America/Indiana/Vevay</option><option value="America/Indiana/Vincennes">America/Indiana/Vincennes</option><option value="America/Indiana/Winamac">America/Indiana/Winamac</option><option value="America/Inuvik">America/Inuvik</option><option value="America/Iqaluit">America/Iqaluit</option><option value="America/Jamaica">America/Jamaica</option><option value="America/Juneau">America/Juneau</option><option value="America/Kentucky/Louisville">America/Kentucky/Louisville</option><option value="America/Kentucky/Monticello">America/Kentucky/Monticello</option><option value="America/Kralendijk">America/Kralendijk</option><option value="America/La_Paz">America/La Paz</option><option value="America/Lima">America/Lima</option><option value="America/Los_Angeles">America/Los Angeles</option><option value="America/Lower_Princes">America/Lower Princes</option><option value="America/Maceio">America/Maceio</option><option value="America/Managua">America/Managua</option><option value="America/Manaus">America/Manaus</option><option value="America/Marigot">America/Marigot</option><option value="America/Martinique">America/Martinique</option><option value="America/Matamoros">America/Matamoros</option><option value="America/Mazatlan">America/Mazatlan</option><option value="America/Menominee">America/Menominee</option><option value="America/Merida">America/Merida</option><option value="America/Metlakatla">America/Metlakatla</option><option value="America/Mexico_City">America/Mexico City</option><option value="America/Miquelon">America/Miquelon</option><option value="America/Moncton">America/Moncton</option><option value="America/Monterrey">America/Monterrey</option><option value="America/Montevideo">America/Montevideo</option><option value="America/Montserrat">America/Montserrat</option><option value="America/Nassau">America/Nassau</option><option value="America/New_York">America/New York</option><option value="America/Nipigon">America/Nipigon</option><option value="America/Nome">America/Nome</option><option value="America/Noronha">America/Noronha</option><option value="America/North_Dakota/Beulah">America/North Dakota/Beulah</option><option value="America/North_Dakota/Center">America/North Dakota/Center</option><option value="America/North_Dakota/New_Salem">America/North Dakota/New Salem</option><option value="America/Ojinaga">America/Ojinaga</option><option value="America/Panama">America/Panama</option><option value="America/Pangnirtung">America/Pangnirtung</option><option value="America/Paramaribo">America/Paramaribo</option><option value="America/Phoenix">America/Phoenix</option><option value="America/Port-au-Prince">America/Port-au-Prince</option><option value="America/Port_of_Spain">America/Port of Spain</option><option value="America/Porto_Velho">America/Porto Velho</option><option value="America/Puerto_Rico">America/Puerto Rico</option><option value="America/Rainy_River">America/Rainy River</option><option value="America/Rankin_Inlet">America/Rankin Inlet</option><option value="America/Recife">America/Recife</option><option value="America/Regina">America/Regina</option><option value="America/Resolute">America/Resolute</option><option value="America/Rio_Branco">America/Rio Branco</option><option value="America/Santa_Isabel">America/Santa Isabel</option><option value="America/Santarem">America/Santarem</option><option value="America/Santiago">America/Santiago</option><option value="America/Santo_Domingo">America/Santo Domingo</option><option value="America/Sao_Paulo">America/Sao Paulo</option><option value="America/Scoresbysund">America/Scoresbysund</option><option value="America/Sitka">America/Sitka</option><option value="America/St_Barthelemy">America/St Barthelemy</option><option value="America/St_Johns">America/St Johns</option><option value="America/St_Kitts">America/St Kitts</option><option value="America/St_Lucia">America/St Lucia</option><option value="America/St_Thomas">America/St Thomas</option><option value="America/St_Vincent">America/St Vincent</option><option value="America/Swift_Current">America/Swift Current</option><option value="America/Tegucigalpa">America/Tegucigalpa</option><option value="America/Thule">America/Thule</option><option value="America/Thunder_Bay">America/Thunder Bay</option><option value="America/Tijuana">America/Tijuana</option><option value="America/Toronto">America/Toronto</option><option value="America/Tortola">America/Tortola</option><option value="America/Vancouver">America/Vancouver</option><option value="America/Whitehorse">America/Whitehorse</option><option value="America/Winnipeg">America/Winnipeg</option><option value="America/Yakutat">America/Yakutat</option><option value="America/Yellowknife">America/Yellowknife</option><option value="Antarctica/Casey">Antarctica/Casey</option><option value="Antarctica/Davis">Antarctica/Davis</option><option value="Antarctica/DumontDUrville">Antarctica/DumontDUrville</option><option value="Antarctica/Macquarie">Antarctica/Macquarie</option><option value="Antarctica/Mawson">Antarctica/Mawson</option><option value="Antarctica/McMurdo">Antarctica/McMurdo</option><option value="Antarctica/Palmer">Antarctica/Palmer</option><option value="Antarctica/Rothera">Antarctica/Rothera</option><option value="Antarctica/Syowa">Antarctica/Syowa</option><option value="Antarctica/Troll">Antarctica/Troll</option><option value="Antarctica/Vostok">Antarctica/Vostok</option><option value="Arctic/Longyearbyen">Arctic/Longyearbyen</option><option value="Asia/Aden">Asia/Aden</option><option value="Asia/Almaty">Asia/Almaty</option><option value="Asia/Amman">Asia/Amman</option><option value="Asia/Anadyr">Asia/Anadyr</option><option value="Asia/Aqtau">Asia/Aqtau</option><option value="Asia/Aqtobe">Asia/Aqtobe</option><option value="Asia/Ashgabat">Asia/Ashgabat</option><option value="Asia/Baghdad">Asia/Baghdad</option><option value="Asia/Bahrain">Asia/Bahrain</option><option value="Asia/Baku">Asia/Baku</option><option value="Asia/Bangkok">Asia/Bangkok</option><option value="Asia/Beirut">Asia/Beirut</option><option value="Asia/Bishkek">Asia/Bishkek</option><option value="Asia/Brunei">Asia/Brunei</option><option value="Asia/Chita">Asia/Chita</option><option value="Asia/Choibalsan">Asia/Choibalsan</option><option value="Asia/Colombo">Asia/Colombo</option><option value="Asia/Damascus">Asia/Damascus</option><option value="Asia/Dhaka">Asia/Dhaka</option><option value="Asia/Dili">Asia/Dili</option><option value="Asia/Dubai">Asia/Dubai</option><option value="Asia/Dushanbe">Asia/Dushanbe</option><option value="Asia/Gaza">Asia/Gaza</option><option value="Asia/Hebron">Asia/Hebron</option><option value="Asia/Ho_Chi_Minh">Asia/Ho Chi Minh</option><option value="Asia/Hong_Kong">Asia/Hong Kong</option><option value="Asia/Hovd">Asia/Hovd</option><option value="Asia/Irkutsk">Asia/Irkutsk</option><option value="Asia/Jakarta">Asia/Jakarta</option><option value="Asia/Jayapura">Asia/Jayapura</option><option value="Asia/Jerusalem">Asia/Jerusalem</option><option value="Asia/Kabul">Asia/Kabul</option><option value="Asia/Kamchatka">Asia/Kamchatka</option><option value="Asia/Karachi">Asia/Karachi</option><option value="Asia/Kathmandu">Asia/Kathmandu</option><option value="Asia/Khandyga">Asia/Khandyga</option><option value="Asia/Kolkata">Asia/Kolkata</option><option value="Asia/Krasnoyarsk">Asia/Krasnoyarsk</option><option value="Asia/Kuala_Lumpur">Asia/Kuala Lumpur</option><option value="Asia/Kuching">Asia/Kuching</option><option value="Asia/Kuwait">Asia/Kuwait</option><option value="Asia/Macau">Asia/Macau</option><option value="Asia/Magadan">Asia/Magadan</option><option value="Asia/Makassar">Asia/Makassar</option><option value="Asia/Manila">Asia/Manila</option><option value="Asia/Muscat">Asia/Muscat</option><option value="Asia/Nicosia">Asia/Nicosia</option><option value="Asia/Novokuznetsk">Asia/Novokuznetsk</option><option value="Asia/Novosibirsk">Asia/Novosibirsk</option><option value="Asia/Omsk">Asia/Omsk</option><option value="Asia/Oral">Asia/Oral</option><option value="Asia/Phnom_Penh">Asia/Phnom Penh</option><option value="Asia/Pontianak">Asia/Pontianak</option><option value="Asia/Pyongyang">Asia/Pyongyang</option><option value="Asia/Qatar">Asia/Qatar</option><option value="Asia/Qyzylorda">Asia/Qyzylorda</option><option value="Asia/Rangoon">Asia/Rangoon</option><option value="Asia/Riyadh">Asia/Riyadh</option><option value="Asia/Sakhalin">Asia/Sakhalin</option><option value="Asia/Samarkand">Asia/Samarkand</option><option value="Asia/Seoul">Asia/Seoul</option><option value="Asia/Shanghai">Asia/Shanghai</option><option value="Asia/Singapore">Asia/Singapore</option><option value="Asia/Srednekolymsk">Asia/Srednekolymsk</option><option value="Asia/Taipei">Asia/Taipei</option><option value="Asia/Tashkent">Asia/Tashkent</option><option value="Asia/Tbilisi">Asia/Tbilisi</option><option value="Asia/Tehran">Asia/Tehran</option><option value="Asia/Thimphu">Asia/Thimphu</option><option value="Asia/Tokyo">Asia/Tokyo</option><option value="Asia/Ulaanbaatar">Asia/Ulaanbaatar</option><option value="Asia/Urumqi">Asia/Urumqi</option><option value="Asia/Ust-Nera">Asia/Ust-Nera</option><option value="Asia/Vientiane">Asia/Vientiane</option><option value="Asia/Vladivostok">Asia/Vladivostok</option><option value="Asia/Yakutsk">Asia/Yakutsk</option><option value="Asia/Yekaterinburg">Asia/Yekaterinburg</option><option value="Asia/Yerevan">Asia/Yerevan</option><option value="Atlantic/Azores">Atlantic/Azores</option><option value="Atlantic/Bermuda">Atlantic/Bermuda</option><option value="Atlantic/Canary">Atlantic/Canary</option><option value="Atlantic/Cape_Verde">Atlantic/Cape Verde</option><option value="Atlantic/Faroe">Atlantic/Faroe</option><option value="Atlantic/Madeira">Atlantic/Madeira</option><option value="Atlantic/Reykjavik">Atlantic/Reykjavik</option><option value="Atlantic/South_Georgia">Atlantic/South Georgia</option><option value="Atlantic/St_Helena">Atlantic/St Helena</option><option value="Atlantic/Stanley">Atlantic/Stanley</option><option value="Australia/Adelaide">Australia/Adelaide</option><option value="Australia/Brisbane">Australia/Brisbane</option><option value="Australia/Broken_Hill">Australia/Broken Hill</option><option value="Australia/Currie">Australia/Currie</option><option value="Australia/Darwin">Australia/Darwin</option><option value="Australia/Eucla">Australia/Eucla</option><option value="Australia/Hobart">Australia/Hobart</option><option value="Australia/Lindeman">Australia/Lindeman</option><option value="Australia/Lord_Howe">Australia/Lord Howe</option><option value="Australia/Melbourne">Australia/Melbourne</option><option value="Australia/Perth">Australia/Perth</option><option value="Australia/Sydney">Australia/Sydney</option><option value="Europe/Amsterdam">Europe/Amsterdam</option><option value="Europe/Andorra">Europe/Andorra</option><option value="Europe/Athens">Europe/Athens</option><option value="Europe/Belgrade">Europe/Belgrade</option><option value="Europe/Berlin">Europe/Berlin</option><option value="Europe/Bratislava">Europe/Bratislava</option><option value="Europe/Brussels">Europe/Brussels</option><option value="Europe/Bucharest">Europe/Bucharest</option><option value="Europe/Budapest">Europe/Budapest</option><option value="Europe/Busingen">Europe/Busingen</option><option value="Europe/Chisinau">Europe/Chisinau</option><option value="Europe/Copenhagen">Europe/Copenhagen</option><option value="Europe/Dublin">Europe/Dublin</option><option value="Europe/Gibraltar">Europe/Gibraltar</option><option value="Europe/Guernsey">Europe/Guernsey</option><option value="Europe/Helsinki">Europe/Helsinki</option><option value="Europe/Isle_of_Man">Europe/Isle of Man</option><option value="Europe/Istanbul">Europe/Istanbul</option><option value="Europe/Jersey">Europe/Jersey</option><option value="Europe/Kaliningrad">Europe/Kaliningrad</option><option value="Europe/Kiev">Europe/Kiev</option><option value="Europe/Lisbon">Europe/Lisbon</option><option value="Europe/Ljubljana">Europe/Ljubljana</option><option value="Europe/London">Europe/London</option><option value="Europe/Luxembourg">Europe/Luxembourg</option><option value="Europe/Madrid">Europe/Madrid</option><option value="Europe/Malta">Europe/Malta</option><option value="Europe/Mariehamn">Europe/Mariehamn</option><option value="Europe/Minsk">Europe/Minsk</option><option value="Europe/Monaco">Europe/Monaco</option><option value="Europe/Moscow">Europe/Moscow</option><option value="Europe/Oslo">Europe/Oslo</option><option value="Europe/Paris">Europe/Paris</option><option value="Europe/Podgorica">Europe/Podgorica</option><option value="Europe/Prague">Europe/Prague</option><option value="Europe/Riga">Europe/Riga</option><option value="Europe/Rome">Europe/Rome</option><option value="Europe/Samara">Europe/Samara</option><option value="Europe/San_Marino">Europe/San Marino</option><option value="Europe/Sarajevo">Europe/Sarajevo</option><option value="Europe/Simferopol">Europe/Simferopol</option><option value="Europe/Skopje">Europe/Skopje</option><option value="Europe/Sofia">Europe/Sofia</option><option value="Europe/Stockholm">Europe/Stockholm</option><option value="Europe/Tallinn">Europe/Tallinn</option><option value="Europe/Tirane">Europe/Tirane</option><option value="Europe/Uzhgorod">Europe/Uzhgorod</option><option value="Europe/Vaduz">Europe/Vaduz</option><option value="Europe/Vatican">Europe/Vatican</option><option value="Europe/Vienna">Europe/Vienna</option><option value="Europe/Vilnius">Europe/Vilnius</option><option value="Europe/Volgograd">Europe/Volgograd</option><option value="Europe/Warsaw">Europe/Warsaw</option><option value="Europe/Zagreb">Europe/Zagreb</option><option value="Europe/Zaporozhye">Europe/Zaporozhye</option><option value="Europe/Zurich">Europe/Zurich</option><option value="Indian/Antananarivo">Indian/Antananarivo</option><option value="Indian/Chagos">Indian/Chagos</option><option value="Indian/Christmas">Indian/Christmas</option><option value="Indian/Cocos">Indian/Cocos</option><option value="Indian/Comoro">Indian/Comoro</option><option value="Indian/Kerguelen">Indian/Kerguelen</option><option value="Indian/Mahe">Indian/Mahe</option><option value="Indian/Maldives">Indian/Maldives</option><option value="Indian/Mauritius">Indian/Mauritius</option><option value="Indian/Mayotte">Indian/Mayotte</option><option value="Indian/Reunion">Indian/Reunion</option><option value="Pacific/Apia">Pacific/Apia</option><option value="Pacific/Auckland">Pacific/Auckland</option><option value="Pacific/Bougainville">Pacific/Bougainville</option><option value="Pacific/Chatham">Pacific/Chatham</option><option value="Pacific/Chuuk">Pacific/Chuuk</option><option value="Pacific/Easter">Pacific/Easter</option><option value="Pacific/Efate">Pacific/Efate</option><option value="Pacific/Enderbury">Pacific/Enderbury</option><option value="Pacific/Fakaofo">Pacific/Fakaofo</option><option value="Pacific/Fiji">Pacific/Fiji</option><option value="Pacific/Funafuti">Pacific/Funafuti</option><option value="Pacific/Galapagos">Pacific/Galapagos</option><option value="Pacific/Gambier">Pacific/Gambier</option><option value="Pacific/Guadalcanal">Pacific/Guadalcanal</option><option value="Pacific/Guam">Pacific/Guam</option><option value="Pacific/Honolulu">Pacific/Honolulu</option><option value="Pacific/Johnston">Pacific/Johnston</option><option value="Pacific/Kiritimati">Pacific/Kiritimati</option><option value="Pacific/Kosrae">Pacific/Kosrae</option><option value="Pacific/Kwajalein">Pacific/Kwajalein</option><option value="Pacific/Majuro">Pacific/Majuro</option><option value="Pacific/Marquesas">Pacific/Marquesas</option><option value="Pacific/Midway">Pacific/Midway</option><option value="Pacific/Nauru">Pacific/Nauru</option><option value="Pacific/Niue">Pacific/Niue</option><option value="Pacific/Norfolk">Pacific/Norfolk</option><option value="Pacific/Noumea">Pacific/Noumea</option><option value="Pacific/Pago_Pago">Pacific/Pago Pago</option><option value="Pacific/Palau">Pacific/Palau</option><option value="Pacific/Pitcairn">Pacific/Pitcairn</option><option value="Pacific/Pohnpei">Pacific/Pohnpei</option><option value="Pacific/Port_Moresby">Pacific/Port Moresby</option><option value="Pacific/Rarotonga">Pacific/Rarotonga</option><option value="Pacific/Saipan">Pacific/Saipan</option><option value="Pacific/Tahiti">Pacific/Tahiti</option><option value="Pacific/Tarawa">Pacific/Tarawa</option><option value="Pacific/Tongatapu">Pacific/Tongatapu</option><option value="Pacific/Wake">Pacific/Wake</option><option value="Pacific/Wallis">Pacific/Wallis</option><option value="UTC">UTC</option>
                </select>
            </p>

            <p style="text-align: center;">
                <button class="pure-button pure-button-primary" type="submit" name="import_matches" value="1">Import Matches</button>
            </p>

            <p style="text-align: center;"><a target="_blank" href="http://www.toornament.com"><img src="poweredByToornament-dark.png" alt="" /></a></p>

        </div>

    </form>

</div>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.0/jquery.min.js"></script>

<script type="text/javascript">
    $(document).ready(function() {

        $('.button-success').click(function() {

            var api_key             = $('input[name=api_key]').val();
            var api_client_id       = $('input[name=api_client_id]').val();
            var api_client_secret   = $('input[name=api_client_secret]').val();
            var toor_id             = $('input[name=toor_id]').val();
            var match_id            = $(this).attr('id');
            var hour                = $(this).parent().prev().find('input').val();
            var date                = $(this).parent().prev().prev().find('input').val();
            var timezone            = $('select[name=timezone]').val();

            var loader = $(this).prev();

            loader.show();

            $.ajax({
                type : 'GET',
                url : 'ajax.php',
                data : 'api_key='+api_key+'&api_client_id='+api_client_id+'&api_client_secret='+api_client_secret+'&toor_id='+toor_id+'&match_id='+match_id+'&hour='+hour+'&date='+date+'&timezone='+timezone,
                error : function() {},
                success : function(data) {
                    loader.hide();
                }
            });

        });

    });
</script>

</body>
</html>


