<?php
require_once(__DIR__ . "/../../../vendor/Requests/library/Requests.php");
Requests::register_autoloader();

class APIRequests {
	private static $sgsid;

	private static function get_sgsid() {
		if (self::$sgsid === null) {
			$private_data = parse_ini_file('private.ini');
			self::$sgsid = $private_data['sg_phpsessid'];
			return self::$sgsid;
		} else {
			return self::$sgsid;
		}
	}

	private static function request($data) {
		if (!isset($data['headers'])) {
			$data['headers'] = array();
		}
		if (!isset($data['data'])) {
			$data['data'] = array();
		}
		if (!isset($data['options'])) {
			$data['options'] = array();
		}
		if (!isset($data['method'])) {
			$data['method'] = "GET";
		}

		$data['options']['useragent'] = "github.com/steamgifts-api";

		switch ($data['method']) {
			case 'GET':
				return Requests::get($data['url'], $data['headers'], $data['options']);
			case 'HEAD':
				return Requests::head($data['url'], $data['headers'], $data['options']);
			case 'POST':
				return Requests::post($data['url'], $data['headers'], $data['data'], $data['options']);
		}
	}

	public static function generic_get_request($url, $headers = array(), $options = array()) {
		// Function for generic GET requests, both the $headers and the $options
		//variables should be dictionaries with the parameters Requests accepts.
		$data = array(
			'url' => $url,
			'method' => 'GET',
			'headers' => $headers,
			'options' => $options
		);

		return self::request($data);
	}

	public static function sg_generic_get_request($url, $bredirect = false, $sgsid = null) {
		// Generic request for things such as the profile when searching by
		//nickname, giveaway general information, giveaway winners, giveaway
		//groups, etc.
		$data = array(
			'url' => $url,
			'method' => 'GET'
		);

		if ($bredirect === false) {
			// If we search by nickname any redirection would mean that the user
			//doesn't exist. If we search by ID it will either redirect to the
			//user profile if it exists or to the main page if it doesn't.
			$data['options'] = array(
				'follow_redirects' => false
			);
		}

		if ($sgsid !== false) {
			// If it's false it means we specifically want an anon get request.
			if ($sgsid === null) {
				$sgsid = self::get_sgsid();
			}

			$data['headers'] = array(
				'Cookie' => 'PHPSESSID=' . $sgsid . ';'
			);
		}

		return self::request($data);
	}

	public static function sg_post_request($url, $post_data, $sgsid = null) {
		// Method to make POST requests to SG, used for things like the IsFree
		//method of the API.
		$data = array(
			'url' => $url,
			'method' => 'POST',
			'data' => $post_data,
			'options' => array(
				'follow_redirects' => false
			)
		);

		if ($sgsid === null) {
			$sgsid = self::get_sgsid();
		}

		$data['headers'] = array(
			'Cookie' => 'PHPSESSID=' . $sgsid . ';'
		);

		return self::request($data);
	}
}
?>
