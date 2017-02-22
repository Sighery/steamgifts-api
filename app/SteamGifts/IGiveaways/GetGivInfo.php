<?php
require_once(__DIR__ . '/../utils/utilities.php');
require_once(__DIR__ . '/../utils/dbconn.php');

$app->get('/SteamGifts/IGiveaways/GetGivInfo/', function ($request, $response) {
	define('MAX_TIME_GIVEAWAY_CACHE', 10800); //3hours
	define('MAX_TIME_ENDED_GIVEAWAY_CACHE', 259200); //3days

	global $db;

	$params = $request->getQueryParams();

	// Get id of the giveaway
	if (isset($params['id']) && preg_match('/^[A-Za-z0-9]{5}$/', $params['id']) === 1) {
		$giv_id = $params['id'];
	} else {
		return $response->withHeader('Access-Control-Allow-Origin', '*')
		->withHeader('Content-type', 'application/json')->withJson(array(
		"errors" => array(
			"code" => 0,
			"message" => "Required id argument missing or invalid"
		)), 400);
	}

	// Get the PHPSESSID cookie if given (unavailable for now)
	if (isset($params['sgsid']) && false) {
		if (preg_match('/^[A-Za-z0-9]+$/', $params['sgsid']) === 1) {
			$sg_phpsessid = $params['sgsid'];
		} else {
			return $response->withHeader('Access-Control-Allow-Origin', '*')
			->withHeader('Content-type', 'application/json')
			->withJson(array("errors" => array(
				"code" => 1,
				"message" => "Optional sgsid argument invalid"
			)), 400);
		}
	}

	$regions_translation = array(
		'China' => 0,
		'Germany' => 1,
		'Hong Kong + Taiwan' => 2,
		'India' => 3,
		'North America' => 4,
		'Poland' => 5,
		'RU + CIS' => 6,
		'Saudi Arabia + United Arab Emirates' => 7,
		'SE Asia' => 8,
		'South America' => 9,
		'Turkey' => 10
	);

	// Get local data if any
	$stmt = $db->prepare("SELECT COUNT(*) AS count, id, ended, deleted, deleted_reason, deleted_time, blacklisted, not_whitelisted, not_region, region, not_groups, not_wl_groups, usersgeneral_id, giv_type, level, copies, points, gamesinfo_id, not_steam_game, created_time, starting_time, ending_time, comments, entries, winners, unavailable, UNIX_TIMESTAMP(last_checked) AS last_checked FROM GiveawaysGeneral WHERE giv_id=:giv_id");
	$stmt->execute(array(
		':giv_id' => $giv_id
	));

	$giveaway_row = $stmt->fetch(PDO::FETCH_ASSOC);
	unset($stmt);


	//Get description data if any
	if ($giveaway_row['count'] === 1) {
		$stmt = $db->prepare("SELECT COUNT(*) AS count, description, UNIX_TIMESTAMP(last_checked) AS last_checked FROM GiveawaysDescriptions WHERE giveawaysgeneral_id=:giveawaysgeneral_id");
		$stmt->execute(array(
			':giveawaysgeneral_id' => $giveaway_row['id']
		));

		$description_row = $stmt->fetch(PDO::FETCH_ASSOC);
		unset($stmt);
	} else {
		$description_row = array(
			'count' => 0
		);
	}

	/*
	//TODO: FIGURE THIS OUT
	if ($giveaway_row['ended'] === null) {
		// Row was created by a method like GetGivWinners but it just contains
		//the ID of the giveaway and nothing else
		$page_req = APIRequests::sg_generic_get_request('https://www.steamgifts.com/giveaway/' . $giv_id . '/', true);
	}
	*/

	if ($giveaway_row['count'] === 1 && $description_row['count'] === 1 && $giveaway_row['unavailable'] === 0 && $giveaway_row['ended'] === 0 && (time() - $giveaway_row['last_checked']) <= MAX_TIME_GIVEAWAY_CACHE) {
		// Data is in the DB. It's not outdated, and not unavailable.
		if ($giveaway_row['deleted'] === 1) {
			$stmt = $db->query("SELECT nickname FROM UsersGeneral WHERE id=" . $giveaway_row['usersgeneral_id']);

			$usersgeneral_row = $stmt->fetch(PDO::FETCH_ASSOC);
			unset($stmt);

			return $response->withHeader('Access-Control-Allow-Origin', '*')
			->withHeader('Content-type', 'application/json')
			->withJson(array('errors' => array(
				'code' => 3,
				'description' => 'Giveaway deleted',
				'id' => $giv_id,
				'user' => $usersgeneral_row['nickname'],
				'reason' => $giveaway_row['deleted_reason'],
				'deleted_time' => $giveaway_row['deleted_time']
			)), 500);
		} elseif ($giveaway_row['not_region'] === 1) {
			if ($giveaway_row['blacklisted'] === 1) {
				return $response->withHeader('Access-Control-Allow-Origin', '*')
				->withHeader('Content-type', 'application/json')
				->withJson(array('errors' => array(
					'code' => 4,
					'description' => 'Blacklisted by the creator and not in the proper region',
					'id' => $giv_id,
					'region' => $giveaway_row['region']
				)), 500);
			} else {
				return $response->withHeader('Access-Control-Allow-Origin', '*')
				->withHeader('Content-type', 'application/json')
				->withJson(array('errors' => array(
					'code' => 5,
					'description' => 'Not in the proper region',
					'id' => $giv_id,
					'region' => $giveaway_row['region']
				)), 500);
			}
		} elseif ($giveaway_row['not_wl_groups'] === 1) {
			if ($giveaway_row['blacklisted'] === 1) {
				return $response->withHeader('Access-Control-Allow-Origin', '*')
				->withHeader('Content-type', 'application/json')
				->withJson(array('errors' => array(
					'code' => 6,
					'description' => 'Blacklisted by the creator and not in the whitelist or required groups',
					'id' => $giv_id
				)), 500);
			} else {
				return $response->withHeader('Access-Control-Allow-Origin', '*')
				->withHeader('Content-type', 'application/json')
				->withJson(array('errors' => array(
					'code' => 7,
					'description' => 'Not in the whitelist or required groups',
					'id' => $giv_id
				)), 500);
			}
		} elseif ($giveaway_row['not_whitelisted'] === 1) {
			if ($giveaway_row['blacklisted'] === 1) {
				return $response->withHeader('Access-Control-Allow-Origin', '*')
				->withHeader('Content-type', 'application/json')
				->withJson(array('errors' => array(
					'code' => 8,
					'description' => 'Blacklisted by the creator and not in the whitelist',
					'id' => $giv_id
				)), 500);
			} else {
				return $response->withHeader('Access-Control-Allow-Origin', '*')
				->withHeader('Content-type', 'application/json')
				->withJson(array('errors' => array(
					'code' => 9,
					'description' => 'Not in the whitelist',
					'id' => $giv_id
				)), 500);
			}
		} elseif ($giveaway_row['not_groups'] === 1) {
			if ($giveaway_row['blacklisted'] === 1) {
				return $response->withHeader('Access-Control-Allow-Origin', '*')
				->withHeader('Content-type', 'application/json')
				->withJson(array('errors' => array(
					'code' => 10,
					'description' => 'Blacklisted by the creator and not in the required groups',
					'id' => $giv_id
				)), 500);
			} else {
				return $response->withHeader('Access-Control-Allow-Origin', '*')
				->withHeader('Content-type', 'application/json')
				->withJson(array('errors' => array(
					'code' => 11,
					'description' => 'Not in the required groups',
					'id' => $giv_id
				)), 500);
			}
		} else {
			$stmt = $db->query("SELECT nickname FROM UsersGeneral WHERE id=" . $giveaway_row['usersgeneral_id']);

			$usersgeneral_row = $stmt->fetch(PDO::FETCH_ASSOC);
			unset($stmt);

			$data = array(
				'id' => $giv_id,
				'ended' => (bool)$giveaway_row['ended'],
				'user' => $usersgeneral_row['nickname'],
				'type' => $giveaway_row['giv_type'],
				'region' => $giveaway_row['region'],
				'level' => $giveaway_row['level'],
				'copies' => $giveaway_row['copies'],
				'points' => $giveaway_row['points'],
				'comments' => $giveaway_row['comments'],
				'entries' => $giveaway_row['entries'],
				'winners' => $giveaway_row['winners'],
				'created_time' => $giveaway_row['created_time'],
				'starting_time' => $giveaway_row['starting_time'],
				'ending_time' => $giveaway_row['ending_time'],
				'description' => $description_row['description'],
				'game_id' => null,
				'game_type' => null,
				'game_title' => null
			);

			if (isset($giveaway_row['not_steam_game'])) {
				$data['game_title'] = $giveaway_row['not_steam_game'];
			} else {
				$stmt = $db->query("SELECT game_id, game_type, game_title FROM GamesInfo WHERE id=" . $giveaway_row['gamesinfo_id']);
				$gamesinfo_row = $stmt->fetch(PDO::FETCH_ASSOC);
				unset($stmt);

				$data['game_id'] = $gamesinfo_row['game_id'];
				$data['game_type'] = $gamesinfo_row['game_type'];
				$data['game_title'] = $gamesinfo_row['game_title'];
			}

			return $response->withHeader('Access-Control-Allow-Origin', '*')
			->withHeader('Content-type', 'application/json')
			->withJson($data, 200);
		}

	} elseif ($giveaway_row['count'] === 1 && $giveaway_row['unavailable'] === 1 && (time() - $giveaway_row['last_checked']) <= MAX_TIME_GIVEAWAY_CACHE) {
		// Data was in the DB. Giveaway doesn't exist but data isn't outdated.
		return $response->withHeader('Access-Control-Allow-Origin', '*')
		->withHeader('Content-type', 'application/json')
		->withJson(array(
		'errors' => array(
			'code' => 2,
			'description' => 'The giveaway doesn\'t exist'
		)), 500);

	} elseif ($giveaway_row['count'] === 1 && $description_row['count'] === 1 && $giveaway_row['ended'] === 1 && (time() - $giveaway_row['last_checked']) <= MAX_TIME_ENDED_GIVEAWAY_CACHE) {
		// Data was in the DB. Giveaway ended but it isn't outdated.
		$stmt = $db->query("SELECT nickname FROM UsersGeneral WHERE id=" . $giveaway_row['usersgeneral_id']);

		$usersgeneral_row = $stmt->fetch(PDO::FETCH_ASSOC);
		unset($stmt);

		$data = array(
			'id' => $giv_id,
			'ended' => (bool)$giveaway_row['ended'],
			'user' => $usersgeneral_row['nickname'],
			'type' => $giveaway_row['giv_type'],
			'region' => $giveaway_row['region'],
			'level' => $giveaway_row['level'],
			'copies' => $giveaway_row['copies'],
			'points' => $giveaway_row['points'],
			'comments' => $giveaway_row['comments'],
			'entries' => $giveaway_row['entries'],
			'winners' => $giveaway_row['winners'],
			'created_time' => $giveaway_row['created_time'],
			'starting_time' => $giveaway_row['starting_time'],
			'ending_time' => $giveaway_row['ending_time'],
			'description' => $description_row['description'],
			'game_id' => null,
			'game_type' => null,
			'game_title' => null
		);

		if (isset($giveaway_row['not_steam_game'])) {
			$data['game_title'] = $giveaway_row['not_steam_game'];
		} else {
			$stmt = $db->query("SELECT game_id, game_type, game_title FROM GamesInfo WHERE id=" . $giveaway_row['gamesinfo_id']);
			$gamesinfo_row = $stmt->fetch(PDO::FETCH_ASSOC);
			unset($stmt);

			$data['game_id'] = $gamesinfo_row['game_id'];
			$data['game_type'] = $gamesinfo_row['game_type'];
			$data['game_title'] = $gamesinfo_row['game_title'];
		}

		return $response->withHeader('Access-Control-Allow-Origin', '*')
		->withHeader('Content-type', 'application/json')
		->withJson($data, 200);

	} elseif ($giveaway_row['count'] === 1 && $giveaway_row['ended'] === 1 && (time() - $giveaway_row['last_checked']) >= MAX_TIME_ENDED_GIVEAWAY_CACHE) {
		// Request the page. Data was in the DB, but it was outdated (giveaway
		//ended and it passed the maximum time limit for ended giveaways).
		if ($giveaway_row['blacklisted'] === 1) {
			// If the API detected being blacklisted in the last request make
			//an anon GET request by default.
			$page_req = APIRequests::sg_generic_get_request('https://www.steamgifts.com/giveaway/' . $giv_id . '/', true, false);
		} else {
			// Make a normal signed GET request
			$page_req = APIRequests::sg_generic_get_request('https://www.steamgifts.com/giveaway/' . $giv_id . '/', true);
		}
	} elseif ($giveaway_row['count'] === 1 && $giveaway_row['blacklisted'] === 1 && (time() - $giveaway_row['last_checked']) >= MAX_TIME_GIVEAWAY_CACHE) {
		// Request the page. Data was in the DB, but it was outdated. The API
		//detected that it is blacklisted in a previous request, so make an
		//anon GET request by default.
		$page_req = APIRequests::sg_generic_get_request('https://www.steamgifts.com/giveaway/' . $giv_id . '/', true, false);
	} else {
		// Request the page if there was no info whatsoever in the DB.
		if (isset($sg_phpsessid)) {
			// Custom PHPSESSID cookie was given.
			$page_req = APIRequests::sg_generic_get_request('https://www.steamgifts.com/giveaway/' . $giv_id . '/', true, $sg_phpsessid);
		} else {
			$page_req = APIRequests::sg_generic_get_request('https://www.steamgifts.com/giveaway/' . $giv_id . '/', true);
		}
	}


	// If SG is down or giveaway unavailable stop and exit with a 500
	if ($page_req->status_code !== 200 && $page_req->status_code !== 301) {
		return $response->withHeader('Access-Control-Allow-Origin', '*')
		->withHeader('Content-type', 'application/json')
		->withJson(array(
		'errors' => array(
			'code' => 0,
			'description' => 'The request to SG was unsuccessful'
		)), 500);
	} else if ($page_req->url === "https://www.steamgifts.com/" || $page_req->status_code === 301) {
		if ($giveaway_row['count'] === 0) {
			$stmt = $db->query("INSERT INTO GiveawaysGeneral (giv_id, unavailable) VALUES (:giv_id, :unavailable)");
			$stmt->execute(array(
				':giv_id' => $giv_id,
				':unavailable' => 1
			));

			unset($stmt);

			$inserted_id = $db->query("SELECT LAST_INSERT_ID() AS inserted_id");
			$inserted_id = $inserted_id->fetch(PDO::FETCH_ASSOC);
			$inserted_id = $inserted_id['inserted_id'];

			$stmt = $db->query("INSERT INTO GiveawaysDescriptions (giveawaysgeneral_id) VALUES (:giveawaysgeneral_id)");
			$stmt->execute(array(
				':giveawaysgeneral_id' => $inserted_id
			));

		} else {
			$stmt = $db->prepare("UPDATE GiveawaysGeneral SET last_ckeched=NULL, unavailable=1 WHERE id=:id");
			$stmt->execute(array(
				':id' => $giveaway_row['id']
			));

			unset($stmt);
		}

		return $response->withHeader('Access-Control-Allow-Origin', '*')
		->withHeader('Content-type', 'application/json')
		->withJson(array(
		'errors' => array(
			'code' => 2,
			'description' => 'The giveaway doesn\'t exist'
		)), 500);
	}


	$html = str_get_html($page_req->body);

	// Check if the response got back the blacklisted message, and make an anon
	//request if so.
	$bBL = false;
	$title = $html->find('.page__heading__breadcrumbs', 0);
	if (!is_null($title) && empty($title->children()) && $title->innertext == "Error") {
		$response_rows = $html->find('.table__row-outer-wrap');

		if (count($response_rows) == 2) {
			$message = $response_rows[1]->children(0)->children(1)->plaintext;

			if (strpos($message, "blacklisted") !== false) {
				$page_req = APIRequests::sg_generic_get_request('https://www.steamgifts.com/giveaway/' . $giv_id .'/', true, false);
				$html = str_get_html($page_req->body);
				$title = $html->find('.page__heading__breadcrumbs', 0);

				$bBL = true;
			}
		}
	}

	// The blacklisted message could not appear if it was already stored in the
	//DB as being blacklisted and such the API made an anon request by default.
	//If that's the case we set $bBL as true anyway for proper error reporting.
	if ($giveaway_row['blacklisted'] === 1) {
		$bBL = true;
	}


	if (!is_null($title) && empty($title->children()) && $title->innertext == "Error") {
		$response_rows = $html->find('.table__row-outer-wrap');

		if (count($response_rows) == 2) {
			$message = $response_rows[1]->children(0)->children(1)->plaintext;

			if (strpos($message, "This giveaway is restricted to the following region:") !== false) {

				$initial_index = strpos($message, ":") + 2;
				$end_index = strpos($message, "(");

				if ($end_index === false) {
					$region = substr($message, $initial_index);
				} else {
					$end_index = $end_index - 1;
					$region = substr($message, $initial_index, $end_index - $initial_index);
				}

				if (array_key_exists($region, $regions_translation) === false) {
					$region = 99;
				} else {
					$region = $regions_translation[$region];
				}

				unset($initial_index);
				unset($end_index);

				// Store this data on the DB
				if ($giveaway_row['count'] === 0) {
					$stmt = $db->prepare("INSERT INTO GiveawaysGeneral (giv_id, blacklisted, not_region, region) VALUES (:giv_id, :blacklisted, :not_region, :region)");
					$stmt->execute(array(
						':giv_id' => $giv_id,
						':blacklisted' => (int)$bBL,
						':not_region' => 1,
						':region' => $region
					));
				} else {
					$stmt = $db->prepare("UPDATE GiveawaysGeneral SET blacklisted=:blacklisted, not_region=:not_region, region=:region, unavailable=:unavailable, last_checked=NULL WHERE id=:id");
					$stmt->execute(array(
						':blacklisted' => (int)$bBL,
						':not_region' => 1,
						':region' => $region,
						':unavailable' => 0,
						':id' => $giveaway_row['id']
					));
				}

				unset($stmt);

				if ($bBL) {
					return $response->withHeader('Access-Control-Allow-Origin', '*')
					->withHeader('Content-type', 'application/json')
					->withJson(array('errors' => array(
						'code' => 4,
						'description' => 'Blacklisted by the creator and not in the proper region',
						'id' => $giv_id,
						'region' => $giveaway_row['region']
					)), 500);
				} else {
					return $response->withHeader('Access-Control-Allow-Origin', '*')
					->withHeader('Content-type', 'application/json')
					->withJson(array('errors' => array(
						'code' => 5,
						'description' => 'Not in the proper region',
						'id' => $giv_id,
						'region' => $region
					)), 500);
				}

			} elseif (strpos($message, "whitelist, or the required Steam groups") !== false) {

				if ($giveaway_row['count'] === 0) {
					$stmt = $db->prepare("INSERT INTO GiveawaysGeneral (giv_id, blacklisted, not_wl_groups) VALUES (:giv_id, :blacklisted, :not_wl_groups)");
					$stmt->execute(array(
						':giv_id' => $giv_id,
						':blacklisted' => (int)$bBL,
						':not_wl_groups' => 1
					));
				} else {
					$stmt = $db->prepare("UPDATE GiveawaysGeneral SET blacklisted=:blacklisted, not_wl_groups=:not_wl_groups, unavailable=:unavailable, last_checked=NULL WHERE id=:id");
					$stmt->execute(array(
						':blacklisted' => (int)$bBL,
						':not_wl_groups' => 1,
						':unavailable' => 0,
						':id' => $giveaway_row['id']
					));
				}

				unset($stmt);

				if ($bBL) {
					return $response->withHeader('Access-Control-Allow-Origin', '*')
					->withHeader('Content-type', 'application/json')
					->withJson(array('errors' => array(
						'code' => 6,
						'description' => 'Blacklisted by the creator and not in the whitelist or required groups',
						'id' => $giv_id
					)), 500);
				} else {
					return $response->withHeader('Access-Control-Allow-Origin', '*')
					->withHeader('Content-type', 'application/json')
					->withJson(array('errors' => array(
						'code' => 7,
						'description' => 'Not in the whitelist or required groups',
						'id' => $giv_id
					)), 500);
				}

			} elseif (strpos($message, "whitelist") !== false) {

				if ($giveaway_row['count'] === 0) {
					$stmt = $db->prepare("INSERT INTO GiveawaysGeneral (giv_id, blacklisted, not_whitelisted) VALUES (:giv_id, :blacklisted, :not_whitelisted)");
					$stmt->execute(array(
						':giv_id' => $giv_id,
						':blacklisted' => (int)$bBL,
						':not_whitelisted' => 1
					));
				} else {
					$stmt = $db->prepare("UPDATE GiveawaysGeneral SET blacklisted=:blacklisted, not_whitelisted=:not_whitelisted, unavailable=:unavailable, last_checked=NULL WHERE id=:id");
					$stmt->execute(array(
						':blacklisted' => (int)$bBL,
						':not_whitelisted' => 1,
						':unavailable' => 0,
						':id' => $giveaway_row['id']
					));
				}

				unset($stmt);

				if ($bBL) {
					return $response->withHeader('Access-Control-Allow-Origin', '*')
					->withHeader('Content-type', 'application/json')
					->withJson(array('errors' => array(
						'code' => 8,
						'description' => 'Blacklisted by the creator and not in the whitelist',
						'id' => $giv_id
					)), 500);
				} else {
					return $response->withHeader('Access-Control-Allow-Origin', '*')
					->withHeader('Content-type', 'application/json')
					->withJson(array('errors' => array(
						'code' => 9,
						'description' => 'Not in the whitelist',
						'id' => $giv_id
					)), 500);
				}

			} elseif (strpos($message, "Steam groups") !== false) {

				if ($giveaway_row['count'] === 0) {
					$stmt = $db->prepare("INSERT INTO GiveawaysGeneral (giv_id, blacklisted, not_groups) VALUES (:giv_id, :blacklisted, :not_groups)");
					$stmt->execute(array(
						':giv_id' => $giv_id,
						':blacklisted' => (int)$bBL,
						':not_groups' => 1
					));
				} else {
					$stmt = $db->prepare("UPDATE GiveawaysGeneral SET blacklisted=:blacklisted, not_groups=:not_groups, unavailable=:unavailable, last_checked=NULL WHERE id=:id");
					$stmt->execute(array(
						':blacklisted' => (int)$bBL,
						':not_groups' => 1,
						':unavailable' => 0,
						':id' => $giveaway_row['id']
					));
				}

				unset($stmt);

				if ($bBL) {
					return $response->withHeader('Access-Control-Allow-Origin', '*')
					->withHeader('Content-type', 'application/json')
					->withJson(array('errors' => array(
						'code' => 10,
						'description' => 'Blacklisted by the creator and not in the required groups',
						'id' => $giv_id
					)), 500);
				} else {
					return $response->withHeader('Access-Control-Allow-Origin', '*')
					->withHeader('Content-type', 'application/json')
					->withJson(array('errors' => array(
						'code' => 11,
						'description' => 'Not in the required groups',
						'id' => $giv_id
					)), 500);
				}
			}


		} elseif (count($response_rows) == 4) {
			$deleted_reasons_translation = array(
				'Accident' => 0,
				'Beta Key, Guest Pass, or Free Game' => 1,
				'Did Not Understand How the Site Works' => 2,
				'Gift Not Steam Redeemable' => 3,
				'Gift or Key No Longer Available' => 4,
				'Leaked Giveaway (Invite Only)' => 5,
				'Regifting a Previous Win' => 6,
				'Region Restricted Gift' => 7,
				'Selected Incorrect Game or Information' => 8
			);

			forEach($response_rows as $elem) {
				switch($elem->children(0)->children(0)->plaintext) {
					case 'Error':
						$deleted_time = intval($elem->find('span', 0)->getAttribute('data-timestamp'));
						$user = $elem->find('.table__column__secondary-link', 0)->innertext;
						break;
					case 'Reason':
						$deleted_reason = $elem->children(0)->children(1)->innertext;
						break;
				}
			}

			if (array_key_exists($deleted_reason, $deleted_reasons_translation) === false) {
				$deleted_reason = 99;
			} else {
				$deleted_reason = $deleted_reasons_translation[$deleted_reason];
			}



			if ($giveaway_row['count'] === 0) {
				// Code to get id of user, or insert it if inexistant
				$stmt = $db->prepare("SELECT COUNT(*) AS count, id, nickname FROM UsersGeneral WHERE nickname=:nickname");
				$stmt->execute(array(
					':nickname' => $user
				));

				$usersgeneral_row = $stmt->fetch(PDO::FETCH_ASSOC);
				unset($stmt);

				if ($usersgeneral_row['count'] === 0) {
					$stmt = $db->prepare("INSERT INTO UsersGeneral (nickname) VALUES (:nickname)");
					$stmt->execute(array(
						':nickname' => $user
					));

					unset($stmt);
					$inserted_id = $db->query("SELECT LAST_INSERT_ID() AS inserted_id");
					$inserted_id = $inserted_id->fetch(PDO::FETCH_ASSOC);
					$inserted_id = $inserted_id['inserted_id'];
				} else {
					$inserted_id = $usersgeneral_row['id'];
				}

				unset($usersgeneral_row);

				$stmt = $db->prepare("INSERT INTO GiveawaysGeneral (giv_id, usersgeneral_id, deleted, deleted_reason, deleted_time) VALUES (:giv_id, :usersgeneral_id, :deleted, :deleted_reason, :deleted_time)");
				$stmt->execute(array(
					':giv_id' => $giv_id,
					':usersgeneral_id' => $inserted_id,
					':deleted' => 1,
					':deleted_reason' => $deleted_reason,
					':deleted_time' => $deleted_time
				));

				unset($stmt);
			} else {
				$stmt = $db->prepare("UPDATE GiveawaysGeneral SET unavailable=:unavailable, last_checked=NULL WHERE id=:id");
				$stmt->execute(array(
					':unavailable' => 0,
					':id' => $giveaway_row['id']
				));

				unset($stmt);
			}


			return $response->withHeader('Access-Control-Allow-Origin', '*')
			->withHeader('Content-type', 'application/json')
			->withJson(array('errors' => array(
				'code' => 1,
				'description' => 'Giveaway deleted',
				'id' => $giv_id,
				'user' => $user,
				'reason' => $deleted_reason,
				'deleted_time' => $deleted_time
			)), 500);
		}
	}


	$data = array(
		'id' => null,
		'ended' => false,
		'user' => null,
		'type' => null,
		'region' => null,
		'level' => 0,
		'copies' => 1,
		'points' => null,
		'comments' => 0,
		'entries' => 0,
		'winners' => null,
		'created_time' => null,
		'starting_time' => null,
		'ending_time' => null,
		'description' => null,
		'game_id' => null,
		'game_type' => null,
		'game_title' => null
	);

	$data['id'] = $giv_id;

	$store_link = $html->find("a[href*='store.steampowered.com'], a[class*='global__image-outer-wrap--game-large']", 0);

	if (!is_null($store_link)) {
		$type_id_matches;
		preg_match("/http:\/\/store\.steampowered\.com\/(app|sub)\/([0-9]+)/", $store_link->href, $type_id_matches);

		if (!empty($type_id_matches)) {
			// Game types translation
			$game_type_numbers = array(
				"app" => 0,
				"sub" => 1
			);

			$data['game_type'] = $game_type_numbers[$type_id_matches[1]];
			$data['game_id'] = intval($type_id_matches[2]);
		}
	}

	$game_title = $html->find("div[class*='featured__heading__medium']", 0)->innertext;

	if (is_null($store_link)) {
		$data['game_title'] = $game_title;
	}

	$headings_small = $html->find('.featured__heading__small');
	if (!empty($headings_small) && count($headings_small) === 2) {
		$copies;
		preg_match("/(\d+)/", str_replace(",", "", $headings_small[0]->innertext), $copies);
		$data['copies'] = intval($copies[1]);

		$points;
		preg_match("/(\d+)/", $headings_small[1]->innertext, $points);
		$data['points'] = intval($points[1]);

		unset($copies);
		unset($points);

	} elseif (!empty($headings_small) && count($headings_small) === 1) {
		$data['copies'] = 1;

		$points;
		preg_match("/(\d+)/", $headings_small[0]->innertext, $points);
		$data['points'] = intval($points[1]);

		unset($points);
	}
	unset($headings_small);

	$bTypes = array(
		'private' => false,
		'region' => false,
		'whitelist' => false,
		'group' => false
	);

	// Get all info on the featured columns: level, user, giv_type, etc
	forEach($html->find('.featured__column') as $column) {
		$column_class = $column->class;

		if ($column_class == "featured__column") {
			$start_end_time = $column->find('span', 0);
			$start_end_time = intval($start_end_time->getAttribute('data-timestamp'));

			if (strpos($column->plaintext, "Begins in") !== false) {
				$data['starting_time'] = $start_end_time;
			} else {
				$data['ending_time'] = $start_end_time;
				if (time() >= $data['ending_time']) {
					$data['ended'] = true;
				}
			}

			unset($start_end_time);

		} elseif (strpos($column_class, 'featured__column--width-fill') !== false) {
			$created_time = $column->find('span', 0);
			$data['created_time'] = intval($created_time->getAttribute('data-timestamp'));

			$user = $column->find("a[href*='/user/']", 0);
			$data['user'] = $user->innertext;

			unset($created_time);
			unset($user);

		} elseif (strpos($column_class, 'featured__column--invite-only') !== false) {
			$bTypes['private'] = true;

		} elseif (strpos($column_class, 'featured__column--region-restricted') !== false) {
			$bTypes['region'] = true;

			if (array_key_exists(trim($column->plaintext), $regions_translation) !== false) {
				$data['region'] = 99;
			} else {
				$data['region'] = $regions_translation[trim($column->plaintext)];
			}

		} elseif (strpos($column_class, 'featured__column--whitelist') !== false) {
			$bTypes['whitelist'] = true;

		} elseif (strpos($column_class, 'featured__column--group') !== false) {
			$bTypes['group'] = true;

		} elseif (strpos($column_class, 'featured__column--contributor-level') !== false) {
			$level;
			preg_match("/(\d+)/", $column->innertext, $level);

			$data['level'] = intval($level[1]);

			unset($level);
		}
	}


	// Generate the giv_type int
	if ($bTypes['region'] && $bTypes['whitelist'] && $btypes['group']) {
		$data['type'] = 9;
	} elseif ($bTypes['region'] && $bTypes['whitelist']) {
		$data['type'] = 8;
	} elseif ($bTypes['region'] && $bTypes['group']) {
		$data['type'] = 7;
	} elseif ($bTypes['region'] && $bTypes['private']) {
		$data['type'] = 6;
	} elseif ($bTypes['whitelist'] && $bTypes['group']) {
		$data['type'] = 5;
	} elseif ($bTypes['group']) {
		$data['type'] = 4;
	} elseif ($bTypes['whitelist']) {
		$data['type'] = 3;
	} elseif ($bTypes['region']) {
		$data['type'] = 2;
	} elseif ($bTypes['private']) {
		$data['type'] = 1;
	} else {
		$data['type'] = 0;
	}

	$description = $html->find('.page__description', 0);
	if (!is_null($description)) {
		$data['description'] = $description->lastChild()->children(0)->innertext;
	}
	unset($description);

	$sidebar_numbers = $html->find('.sidebar__navigation__item');
	forEach($sidebar_numbers as $row) {
		switch ($row->find('.sidebar__navigation__item__name', 0)->innertext) {
			case 'Comments':
				$data['comments'] = intval(str_replace(",", "", $row->find('.sidebar__navigation__item__count', 0)->innertext));
				break;
			case 'Entries':
				$data['entries'] = intval(str_replace(",", "", $row->find('.sidebar__navigation__item__count', 0)->innertext));
				break;
			case 'Winners':
				$data['winners'] = intval(str_replace(",", "", $row->find('.sidebar__navigation__item__count', 0)->innertext));
				break;
		}
	}
	unset($sidebar_numbers);

	// Check if user exists on UsersGeneral
	$stmt = $db->prepare("SELECT COUNT(*) AS count, id FROM UsersGeneral WHERE nickname=:nickname");
	$stmt->execute(array(
		':nickname' => $data['user']
	));

	$usersgeneral_row = $stmt->fetch(PDO::FETCH_ASSOC);
	unset($stmt);

	if ($usersgeneral_row['count'] === 0) {
		$stmt = $db->prepare("INSERT INTO UsersGeneral (nickname) VALUES (:nickname)");
		$stmt->execute(array(
			':nickname' => $data['user']
		));

		unset($stmt);

		$usersgeneral_inserted_id = $db->query("SELECT LAST_INSERT_ID() AS inserted_id");
		$usersgeneral_inserted_id = $usersgeneral_inserted_id->fetch(PDO::FETCH_ASSOC);
		$usersgeneral_inserted_id = $usersgeneral_inserted_id['inserted_id'];
	} else {
		$usersgeneral_inserted_id = $usersgeneral_row['id'];
	}
	unset($usersgeneral_row);

	// Check if game exists on GamesInfo
	if (!is_null($data['game_id']) && !is_null($data['game_type'])) {
		$stmt = $db->prepare("SELECT COUNT(*) AS count, id, game_title FROM GamesInfo WHERE game_id=:game_id AND game_type=:game_type");
		$stmt->execute(array(
			':game_id' => $data['game_id'],
			':game_type' => $data['game_type']
		));

		$gamesinfo_row = $stmt->fetch(PDO::FETCH_ASSOC);
		unset($stmt);

		if ($gamesinfo_row['count'] === 0 && strlen($game_title) <= 40) {
			$stmt = $db->prepare("INSERT INTO GamesInfo (game_id, game_type, game_title) VALUES (:game_id, :game_type, :game_title)");
			$stmt->execute(array(
				':game_id' => $data['game_id'],
				':game_type' => $data['game_type'],
				':game_title' => $game_title
			));

			unset($stmt);

			$data['game_title'] = $game_title;

			$gamesinfo_inserted_id = $db->query("SELECT LAST_INSERT_ID() AS inserted_id");
			$gamesinfo_inserted_id = $gamesinfo_inserted_id->fetch(PDO::FETCH_ASSOC);
			$gamesinfo_inserted_id = $gamesinfo_inserted_id['inserted_id'];
		} elseif ($gamesinfo_row['count'] === 0 && strlen($game_title) > 40) {
			$api_request = APIRequests::generic_get_request('http://api.sighery.com/SteamGifts/Interactions/GetGameTitle/?id=' . $data['game_id'] . "&type=" . $data['game_type']);

			if ($api_request->status_code !== 200) {
				return $response->withHeader('Access-Control-Allow-Origin', '*')
				->withHeader('Content-type', 'application/json')
				->withJson(array(
					'errors' => array(
						'code' => 1,
						'description' => 'The request to Steam was unsuccessful'
					)), 500);
			}


			$api_request = json_decode($api_request->body, true);

			$data['game_title'] = $api_request['game_title'];
			$gamesinfo_inserted_id = $api_request['id'];
		} elseif ($gamesinfo_row['count'] === 1 && strlen($game_title) > 40) {
			$data['game_title'] = $gamesinfo_row['game_title'];
			$gamesinfo_inserted_id = $gamesinfo_row['id'];
		} else {
			$data['game_title'] = $game_title;
			$gamesinfo_inserted_id = $gamesinfo_row['id'];
		}
		unset($gamesinfo_row);
	}

	if ($giveaway_row['count'] === 0) {
		if (!is_null($store_link)) {
			$stmt = $db->prepare("INSERT INTO GiveawaysGeneral (blacklisted, ended, region, giv_id, usersgeneral_id, giv_type, level, copies, points, gamesinfo_id, created_time, starting_time, ending_time, comments, entries, winners) VALUES (:blacklisted, :ended, :region, :giv_id, :usersgeneral_id, :giv_type, :level, :copies, :points, :gamesinfo_id, :created_time, :starting_time, :ending_time, :comments, :entries, :winners)");
			$stmt->execute(array(
				':blacklisted' => (int)$bBL,
				':ended' => (int)$data['ended'],
				':region' => $data['region'],
				':giv_id' => $data['id'],
				':usersgeneral_id' => $usersgeneral_inserted_id,
				':giv_type' => $data['type'],
				':level' => $data['level'],
				':copies' => $data['copies'],
				':points' => $data['points'],
				':gamesinfo_id' => $gamesinfo_inserted_id,
				':created_time' => $data['created_time'],
				':starting_time' => $data['starting_time'],
				':ending_time' => $data['ending_time'],
				':comments' => $data['comments'],
				':entries' => $data['entries'],
				':winners' => $data['winners']
			));

			unset($stmt);
		} else {
			$stmt = $db->prepare("INSERT INTO GiveawaysGeneral (blacklisted, ended, region, giv_id, usersgeneral_id, giv_type, level, copies, points, not_steam_game, created_time, starting_time, ending_time, comments, entries, winners) VALUES (:blacklisted, :ended, :region, :giv_id, :usersgeneral_id, :giv_type, :level, :copies, :points, :not_steam_game, :created_time, :starting_time, :ending_time, :comments, :entries, :winners)");
			$stmt->execute(array(
				':blacklisted' => (int)$bBL,
				':ended' => (int)$data['ended'],
				':region' => $data['region'],
				':giv_id' => $data['id'],
				':usersgeneral_id' => $usersgeneral_inserted_id,
				':giv_type' => $data['type'],
				':level' => $data['level'],
				':copies' => $data['copies'],
				':points' => $data['points'],
				':not_steam_game' => $data['game_title'],
				':created_time' => $data['created_time'],
				':starting_time' => $data['starting_time'],
				':ending_time' => $data['ending_time'],
				':comments' => $data['comments'],
				':entries' => $data['entries'],
				':winners' => $data['winners']
			));

			unset($stmt);
		}

		$inserted_id = $db->query("SELECT LAST_INSERT_ID() AS inserted_id");
		$inserted_id = $inserted_id->fetch(PDO::FETCH_ASSOC);
		$inserted_id = $inserted_id['inserted_id'];
	} else {
		if (!is_null($store_link)) {
			$stmt = $db->prepare("UPDATE GiveawaysGeneral SET blacklisted=:blacklisted, ended=:ended, region=:region, giv_type=:giv_type, level=:level, copies=:copies, points=:points, gamesinfo_id=:gamesinfo_id, ending_time=:ending_time, comments=:comments, entries=:entries, winners=:winners WHERE giv_id=:giv_id");
			$stmt->execute(array(
				':blacklisted' => (int)$bBL,
				':ended' => (int)$data['ended'],
				':region' => $data['region'],
				':giv_type' => $data['type'],
				':level' => $data['level'],
				':copies' => $data['copies'],
				':points' => $data['points'],
				':gamesinfo_id' => $gamesinfo_inserted_id,
				':ending_time' => $data['ending_time'],
				':comments' => $data['comments'],
				':entries' => $data['entries'],
				':winners' => $data['winners'],
				':giv_id' => $data['id']
			));

			unset($stmt);
		} else {
			$stmt = $db->prepare("UPDATE GiveawaysGeneral SET blacklisted=:blacklisted, ended=:ended, region=:region, giv_type=:giv_type, level=:level, copies=:copies, points=:points, not_steam_game=:not_steam_game, ending_time=:ending_time, comments=:comments, entries=:entries, winners=:winners WHERE giv_id=:giv_id");
			$stmt->execute(array(
				':blacklisted' => (int)$bBL,
				':ended' => (int)$data['ended'],
				':region' => $data['region'],
				':giv_type' => $data['type'],
				':level' => $data['level'],
				':copies' => $data['copies'],
				':points' => $data['points'],
				':not_steam_game' => $data['game_title'],
				':ending_time' => $data['ending_time'],
				':comments' => $data['comments'],
				':entries' => $data['entries'],
				':winners' => $data['winners'],
				':giv_id' => $data['id']
			));

			unset($stmt);
		}

		$inserted_id = $giveaway_row['id'];
	}

	if ($description_row['count'] === 0) {
		$stmt = $db->prepare("INSERT INTO GiveawaysDescriptions (giveawaysgeneral_id, description) VALUES (:giveawaysgeneral_id, :description)");
		$stmt->execute(array(
			':giveawaysgeneral_id' => $inserted_id,
			':description' => $data['description']
		));

		unset($stmt);
	} else {
		if ($description_row['description'] !== $data['description']) {
			$stmt = $db->prepare("UPDATE GiveawaysDescriptions SET description=:description WHERE giveawaysgeneral_id=:giveawaysgeneral_id");
			$stmt->execute(array(
				':description' => $data['description'],
				':giveawaysgeneral_id' => $inserted_id
			));

			unset($stmt);
		}
	}

	return $response->withHeader('Access-Control-Allow-Origin', '*')
	->withHeader('Content-type', 'application/json')
	->withJson($data, 200);
});
?>
