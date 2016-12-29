<?php
$private_data = parse_ini_file('private.ini');

function anon_get_sg_page($url, $debug_info = false) {
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_USERAGENT, "api.sighery.com/0.1");
	curl_setopt($ch, CURLOPT_COOKIEFILE, "");
	if ($debug_info) {
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLINFO_HEADER_OUT, true);
		curl_setopt($ch, CURLOPT_VERBOSE, true);
	}
	$data = curl_exec($ch);

	if (curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 200 || curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) == "https://www.steamgifts.com/") {
		return false;
	}

	if ($debug_info) {
		echo "HTTP Code:";
		var_dump(curl_getinfo($ch, CURLINFO_HTTP_CODE));
		echo "<br>\n";
		echo "Effective URL:";
		var_dump(curl_getinfo($ch, CURLINFO_EFFECTIVE_URL));
		echo "<br>\n";
	}

	curl_close($ch);

	if ($data === false) {
		if ($debug_info) {
			echo "Error curl! <br>\n";
		}
		return false;
	}
	return $data;
}

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

	if (curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 200 || curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) == "https://www.steamgifts.com/") {
		return false;
	}

	if ($debug_info) {
		echo "HTTP Code:";
		var_dump(curl_getinfo($ch, CURLINFO_HTTP_CODE));
		echo "<br>\n";
		echo "Effective URL:";
		var_dump(curl_getinfo($ch, CURLINFO_EFFECTIVE_URL));
		echo "<br>\n";
	}

	curl_close($ch);

	if ($data === false) {
		if ($debug_info) {
			echo "Error curl! <br>\n";
		}
		return false;
	}
	return $data;
}

function post_sg_page($url, $data, $sg_phpsessid = null, $debug_info = false) {
	if ($sg_phpsessid === null) {
		global $private_data;
		$sg_phpsessid = $private_data['sg_sighery_phpsessid'];
	}

	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_USERAGENT, "api.sighery.com/0.1");
	curl_setopt($ch, CURLOPT_COOKIE, "PHPSESSID=" . $sg_phpsessid);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
	if ($debug_info) {
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLINFO_HEADER_OUT, true);
		curl_setopt($ch, CURLOPT_VERBOSE, true);
	}
	$data = curl_exec($ch);

	if (curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 200) {
		return false;
	}

	if ($debug_info) {
		echo "HTTP Code:";
		var_dump(curl_getinfo($ch, CURLINFO_HTTP_CODE));
		echo "<br>\n";
		echo "Effective URL:";
		var_dump(curl_getinfo($ch, CURLINFO_EFFECTIVE_URL));
		echo "<br>\n";
	}

	curl_close($ch);

	if ($data === false) {
		if ($debug_info) {
			echo "Error curl! <br>\n";
		}
		return false;
	}
	return $data;
}

function get_page($url, $debug_info = false) {
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_USERAGENT, "api.sighery.com/0.1");
	curl_setopt($ch, CURLOPT_COOKIEJAR, "steam_cookies.txt");
	//curl_setopt($ch, CURLOPT_COOKIEFILE, "");
	if ($debug_info) {
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLINFO_HEADER_OUT, true);
		curl_setopt($ch, CURLOPT_VERBOSE, true);
	}
	$data = curl_exec($ch);

	if (curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 200) {
		return false;
	}

	if ($debug_info) {
		echo "HTTP Code:";
		var_dump(curl_getinfo($ch, CURLINFO_HTTP_CODE));
		echo "<br>\n";
		echo "Effective URL:";
		var_dump(curl_getinfo($ch, CURLINFO_EFFECTIVE_URL));
		echo "<br>\n";
	}

	curl_close($ch);

	if ($data === false) {
		if ($debug_info) {
			echo "Error curl! <br>\n";
		}
		return false;
	}
	return $data;
}
?>
