<?php
// Trims an image then optionally adds padding around it.
// $im  = Image link resource
// $bg  = The background color to trim from the image
// $pad = Amount of padding to add to the trimmed image
//        (acts simlar to the "padding" CSS property: "top [right [bottom [left]]]")
function imagetrim(&$im, $bg, $pad=null, $scale=1, $removeTransp=false){

	// Calculate padding for each side.
	if (isset($pad)){
		$pp = explode(' ', $pad);
		if (isset($pp[3])){
			$p = array((int) $pp[0], (int) $pp[1], (int) $pp[2], (int) $pp[3]);
		}else if (isset($pp[2])){
			$p = array((int) $pp[0], (int) $pp[1], (int) $pp[2], (int) $pp[1]);
		}else if (isset($pp[1])){
			$p = array((int) $pp[0], (int) $pp[1], (int) $pp[0], (int) $pp[1]);
		}else{
			$p = array_fill(0, 4, (int) $pp[0]);
		}
	}else{
		$p = array_fill(0, 4, 0);
	}

	// Get the image width and height.
	$imw = imagesx($im);
	$imh = imagesy($im);

	// Set the X variables.
	$xmin = $imw;
	$xmax = 0;

	// Start scanning for the edges.
	for ($iy=0; $iy<$imh; $iy++){
		$first = true;
		for ($ix=0; $ix<$imw; $ix++){
			$ndx = imagecolorat($im, $ix, $iy);
			if ($ndx != $bg){
				if ($xmin > $ix){
					$xmin = $ix;
				}
				if ($xmax < $ix){
					$xmax = $ix;
				}
				if (!isset($ymin)){
					$ymin = $iy;
				}
				$ymax = $iy;
				if ($first){
					$ix = $xmax; $first = false;
				}
			}
		}
	}

	// The new width and height of the image. (not including padding)
	$imw = 1+$xmax-$xmin; // Image width in pixels
	$imh = 1+$ymax-$ymin; // Image height in pixels

	// Make another image to place the trimmed version in. Take into account resizing ratio.
	$new_width = $imw / $scale;
	$new_height = $imh / $scale;
	$im2 = imagecreatetruecolor($new_width+$p[1]+$p[3], $new_height+$p[0]+$p[2]);

	//Make the background of the new image the same as the background of the old one.
	//Removes transparency replacing it with white.
	if ($removeTransp) {
		$bg2 = imagecolorallocate ($im2, 255, 255, 255);

		//Preserves transparency.
	} else {
		$bg2 = imagecolorallocatealpha ($im2, ($bg >> 16) & 0xFF, ($bg >> 8) & 0xFF, $bg & 0xFF, ($bg >> 24) & 0xFF);
		imagealphablending ($im2, false);
		imagesavealpha ($im2, true);
	}
	imagefill($im2, 0, 0, $bg2);

	// Copy it over to the new image. Resizes if ratio > 1.
	imagecopyresampled ($im2, $im, $p[3], $p[0], $xmin, $ymin, $new_width, $new_height, $imw, $imh);

	// To finish up, we replace the old image which is referenced.
	$im = $im2;
}

/* Crops and converts a PNG image to 8-bit colour space
 * Call: pngOptim (sys_get_temp_dir().'/bars.png', 64, 9); */
function pngOptim ($filePath, $ncolors, $compress, $resize_ratio=1) {
	$image = imagecreatefrompng ($filePath);
	$bgColor = imagecolorat ($image, 0, 0);
	imagetrim ($image, $bgColor, '2', $resize_ratio, true);
	imagetruecolortopalette ($image, false, $ncolors);
	imagepng ($image, substr ($filePath, 0, -4) . "2.png", $compress);
	ImageDestroy ($image);
}

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

/* Direct retrieval of XML file */
function getXml($string) {
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $string);
	curl_setopt($ch, CURLOPT_HEADER, false);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$xml = curl_exec($ch);
	curl_close($ch);
	$xml = new SimpleXMLElement($xml);
	return $xml;
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
