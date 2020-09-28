<?php 
/*Direct retrieval of URL output*/
function getURL($string) {
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $string);
	curl_setopt($ch, CURLOPT_HEADER, false);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$output = curl_exec($ch);
	curl_close($ch);
	return $output;
}
?>