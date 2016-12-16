<?php
$private_data = parse_ini_file('private.ini');

function get_sg_page($url, $sg_phpsessid = null, $debug_info = false) {
	if ($sg_phpsessid === null) {
		global $private_data;
		$sg_phpsessid = $private_data['sg_sighery_phpsessid'];
	}

	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_USERAGENT, "api.sighery.com/0.1");
	curl_setopt($ch, CURLOPT_COOKIE, "PHPSESSID=" . $sg_phpsessid);
	if ($debug_info) {
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLINFO_HEADER_OUT, true);
		curl_setopt($ch, CURLOPT_VERBOSE, true);
	}
	$data = curl_exec($ch);
	curl_close($ch);

	if ($data === false) {
		echo "Error!";
	}
	return $data;
}
?>
