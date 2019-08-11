<?php
$domain = ""; //your root domain, e.g. domain.com
$name = "peerseed"; //subdomain e.g. name.domain.com 
$number_of_records = 10; //maximum n A records with $name... 10 recommended 
$user = ""; //user name
$key = ""; //key for cloudflare api found in account settings
$seed_dump = "/home/someone/peercoin-seeder/dnsseed.dump"; //absolute path to dnsseed.dump in the peercoin-seeder root directory 
$good_port = "9901";

///THE MAGIC HAPPENS BELOW DON'T CHANGE UNLESS YOU KNOW WHAT YOU'RE DOING

require_once('vendor/autoload.php');
$key     = new Cloudflare\API\Auth\APIKey($user, $key);
$adapter = new Cloudflare\API\Adapter\Guzzle($key);
$user    = new Cloudflare\API\Endpoints\User($adapter);
$zones = new \Cloudflare\API\Endpoints\Zones($adapter);
$zoneID = $zones->getZoneID($domain);

//write IPs into array 
$ip_raw = file($seed_dump); //read seed_dump into array
array_shift($ip_raw);
$ip_numbers_in_file = count($ip_raw);

$i = 0;
$ip_array = array();

while ($i < $ip_numbers_in_file) {
    $ip_array_line = $ip_raw[$i]; //read line 
    $ip_array_split = explode(":", $ip_array_line); //explode string at ":"
    $ip = $ip_array_split[0]; // [0] is the ip address of the line

    $pos = strpos($ip_array_split[1], $good_port); //position of $good_port
    $bool_pos = is_numeric($pos); //custom ports (!= $good_port) not allowed.

    if ($bool_pos && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $good = 0;
        $ip_array_split = str_replace($good_port, "", $ip_array_split[1]); //remove port
        $ip_array_split = trim($ip_array_split); //remove spaces at the start
        $ip_array_split = substr($ip_array_split, 0, 1); //get first character == the GOOD parameter in the dump file /
        $good =  $ip_array_split;

        if ($good == 1) {
            array_push(
                $ip_array,
                array(
                    'ip' => $ip
                )
            );
        }
    }
    $i++;
}

//go through ips in zone
$current_entry_array = array();
$dns = new \Cloudflare\API\Endpoints\DNS($adapter);
$zoneResult = $dns->listRecords($zoneID, "", "", "", 1, 100)->result;
foreach ($zoneResult as $record) {
    if ($name === explode('.', $record->name)[0]) {
        //check if they're still good
        $search_result = array_search($record->content, array_column($ip_array, 'ip'));
        if (!is_numeric($search_result)) {
            echo "did not find $record->content - $record->id \n";
            $dns->deleteRecord($zoneID, $record->id);
        } else {
            array_push($current_entry_array, $record->content);
        }
    }
}

//add new ones
$i2 = 0;
while (count($current_entry_array) < $number_of_records) {
    $search_result_existing = array_search($ip_array[$i2]["ip"], $current_entry_array);
    if (!is_numeric($search_result_existing)) {
        $dns->addRecord($zoneID, "A", $name, $ip_array[$i2]["ip"], 1, false);
        array_push($current_entry_array, $ip_array[$i2]["ip"]);
    }
    $i2++;
}
