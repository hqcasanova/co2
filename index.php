<?php

/* Retrieval of XML file with cache */
function FN_CacheEventPage($sourceURL, $destinationFile){
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $sourceURL);
	$fp = fopen($destinationFile, 'w');
	curl_setopt($ch, CURLOPT_FILE, $fp);
	curl_exec ($ch);
	curl_close ($ch);
	fclose($fp);
	$ReturnFileContent = file_get_contents($destinationFile, FILE_USE_INCLUDE_PATH);
	return $ReturnFileContent;
}

/* Get human-readable description of data */
function getHuman ($cacheEntry) {
	$cacheEntry = preg_replace ('/\.?\s*(\r?\n|<br>)\s*/i', "\n", $cacheEntry);
	return preg_replace ('/( )+/i', ' ', $cacheEntry);
}

/* Get percentage difference between historical averages */
function getDelta ($co2, $withPercentage=true) {
	$value = round ((substr($co2[1], 0, 5) - substr($co2[10], 0, 5)) / substr($co2[10], 0, 5) * 100, 2);
	if ($withPercentage) {
		$value = $value.' %';
	}
	return $value;
}

function getCache () {
	//Set maximum age of cache file before refreshing it (1 day in seconds)
	$cacheLife = 86400;
	
	//Set remote URL
	$remoteURL = 'https://www.esrl.noaa.gov/gmd/webdata/ccgg/trends/rss.xml';
	
	//Set cacheName (assuming it's going to reside in the same directory as the caller script).
	$cacheFileName = './cache.xml';
	
	//If the cache file isn't there, or is of 0 bytes of size or is too old, build a new one!   
	if (!file_exists($cacheFileName) or (filesize($cacheFileName) == 0) or (time() - filemtime($cacheFileName) >= $cacheLife)) {
	   $cacheFileContent = FN_CacheEventPage($remoteURL, $cacheFileName);

	//File exists and is still current => use it
	} else {
	   $cacheFileContent = file_get_contents($cacheFileName);
	} 
	return new SimpleXMLElement($cacheFileContent);	
}

/* Prevent invalid requests */
if (($_SERVER['REQUEST_METHOD'] != 'GET')) {    
   die ('Invalid.');
}

/* Digest URL */
$requestURI = explode('/', $_SERVER['REQUEST_URI']); 
$scriptName = explode('/',$_SERVER['SCRIPT_NAME']);
for ($i= 0; $i < sizeof ($scriptName); $i++) {
    if ($requestURI[$i] == $scriptName[$i]) {
       unset($requestURI[$i]);
    }
}

/* There must be at least one command or GET parameter (the JSONP callback) */
$command = array_values ($requestURI);
$cmd_length = count($command);
if ($cmd_length != 1) {
	die ('Invalid.');
} else {
	$isJSONP = (preg_match('/\?callback=(\w|&|=)+$/i', $command[0]) == 1);
	$isTEXT = (preg_match('/^(#|0#|1#|10#|all#|help#|delta#)$/i', $command[0].'#') == 1); 
}

/* If URL valid => retrieve data from RSS and serve it */
if ($isTEXT || $isJSONP) {
   
   /*Set content type appropriately*/
   if ($isTEXT) {
   		header('Content-Type: text/plain'); 
   } else {
   	    header('Content-Type: application/javascript');
   	    $command[0] = 'callback';
   }	

   /*Download XML and look for weekly data*/
   $xml = getCache() or die("Could not retrieve data");
   
   /*Get the latest post on weekly data */
   $i = 0;  
   while (($i < 10) && (false === stripos($xml->channel->item[$i]->title, 'weekly')))
         $i++;		 
   $full = trim ($xml->channel->item[$i]->description); 

   /*Output appropriate data in plain text format*/ 
   if ($command[0] == 'all') {
   	  echo getHuman($full);	      	  
   } else if ($command[0] == 'help') {
		  echo file_get_contents('help.txt', FILE_USE_INCLUDE_PATH);			 
   } else {
	    $full_split = preg_split("/:|(<br>)*\r?\n/i", $full);  
		  $co2 = array (0 => trim ($full_split[2]), 
		                1 => trim ($full_split[4]), 
						10 => trim ($full_split[6]));
		  if ($command[0] == 'delta') {
		     echo getDelta($co2);
		  } else if ($command[0] == 'callback') {
		  	 $firstCo2 = explode (' ', $co2[0]);
		  	 $co2[0] = $firstCo2[0];
		  	 $co2[1] = substr ($co2[1], 0, -4);
		  	 $co2[10] = substr ($co2[10], 0, -4);
		  	 $co2['units'] = $firstCo2[1];
		  	 $co2['date'] = date('c', strtotime(trim($xml->channel->item[$i]->pubDate)));
		  	 $co2['delta'] = getDelta($co2, false);
		  	 $co2['all'] = getHuman($full);
		  	 echo $_GET['callback']."(".json_encode($co2).")";	
		  } else {
			 if ($command[0] == '') {
	            $command[0] = '0';
	         }
        	 echo $co2[$command[0]];
		  }
   }   
} else echo "Invalid parameter";
?>
