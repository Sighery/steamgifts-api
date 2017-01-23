<?php
require_once("utilities.php");
require_once("dbconn.php");

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
	$stmt = $db->prepare("SELECT COUNT(*) AS count, id, ended, deleted, deleted_reason, deleted_time, blacklisted, not_whitelisted, not_region, region, not_groups, not_wl_groups, iusers_id, giv_type, level, copies, points, gametitles_id, not_steam_game, created_time, starting_time, ending_time, comments, entries, description, unavailable, UNIX_TIMESTAMP(last_checked) AS last_checked FROM IGiveaways WHERE giv_id=:giv_id");
	$stmt->execute(array(
		':giv_id' => $giv_id
	));

	$giveaway_row = $stmt->fetch(PDO::FETCH_ASSOC);
	unset($stmt);

	if ($giveaway_row['count'] === 1 && $giveaway_row['unavailable'] === 0 && $giveaway_row['ended'] === 0 && (time() - $giveaway_row['last_checked']) <= MAX_TIME_GIVEAWAY_CACHE) {
		if ($giveaway_row['deleted'] === 1) {
			$stmt = $db->query("SELECT nickname FROM IUsers WHERE id=" . $giveaway_row['iusers_id']);

			$iusers_row = $stmt->fetch(PDO::FETCH_ASSOC);
			unset($stmt);

			return $response->withHeader('Access-Control-Allow-Origin', '*')
			->withHeader('Content-type', 'application/json')
			->withJson(array('errors' => array(
				'code' => 1,
				'description' => 'Giveaway deleted',
				'id' => $giv_id,
				'user' => $iusers_row['nickname'],
				'reason' => $giveaway_row['deleted_reason'],
				'deleted_time' => $giveaway_row['deleted_time']
			)), 500);
		} elseif ($giveaway_row['not_region'] === 1) {
			return $response->withHeader('Access-Control-Allow-Origin', '*')
			->withHeader('Content-type', 'application/json')
			->withJson(array('errors' => array(
				'code' => 2,
				'description' => 'Not in the proper region',
				'id' => $giv_id,
				'region' => $giveaway_row['region']
			)), 500);
		} elseif ($giveaway_row['not_wl_groups'] === 1) {
			return $response->withHeader('Access-Control-Allow-Origin', '*')
			->withHeader('Content-type', 'application/json')
			->withJson(array('errors' => array(
				'code' => 3,
				'description' => 'Not in the whitelist and/or required groups',
				'id' => $giv_id
			)), 500);
		} elseif ($giveaway_row['not_whitelisted'] === 1) {
			return $response->withHeader('Access-Control-Allow-Origin', '*')
			->withHeader('Content-type', 'application/json')
			->withJson(array('errors' => array(
				'code' => 4,
				'description' => 'Not in the whitelist',
				'id' => $giv_id
			)), 500);
		} elseif ($giveaway_row['not_groups'] === 1) {
			return $response->withHeader('Access-Control-Allow-Origin', '*')
			->withHeader('Content-type', 'application/json')
			->withJson(array('errors' => array(
				'code' => 5,
				'description' => 'Not in the required groups',
				'id' => $giv_id
			)), 500);
		} else {
			$stmt = $db->query("SELECT nickname FROM IUsers WHERE id=" . $giveaway_row['iusers_id']);

			$iusers_row = $stmt->fetch(PDO::FETCH_ASSOC);
			unset($stmt);

			$data = array(
				'id' => $giv_id,
				'ended' => (bool)$giveaway_row['ended'],
				'user' => $iusers_row['nickname'],
				'type' => $giveaway_row['giv_type'],
				'region' => $giveaway_row['region'],
				'level' => $giveaway_row['level'],
				'copies' => $giveaway_row['copies'],
				'points' => $giveaway_row['points'],
				'comments' => $giveaway_row['comments'],
				'entries' => $giveaway_row['entries'],
				'created_time' => $giveaway_row['created_time'],
				'starting_time' => $giveaway_row['starting_time'],
				'ending_time' => $giveaway_row['ending_time'],
				'description' => $giveaway_row['description'],
				'game_id' => null,
				'game_type' => null,
				'game_title' => null
			);

			if (isset($giveaway_row['not_steam_game'])) {
				$data['game_title'] = $giveaway_row['not_steam_game'];
			} else {
				$stmt = $db->query("SELECT game_id, game_type, game_title FROM GameTitles WHERE id=" . $giveaway_row['gametitles_id']);
				$gametitles_row = $stmt->fetch(PDO::FETCH_ASSOC);
				unset($stmt);

				$data['game_id'] = $gametitles_row['game_id'];
				$data['game_type'] = $gametitles_row['game_type'];
				$data['game_title'] = $gametitles_row['game_title'];
			}

			return $response->withHeader('Access-Control-Allow-Origin', '*')
			->withHeader('Content-type', 'application/json')
			->withJson($data, 200);
		}

	} elseif ($giveaway_row['count'] === 1 && $giveaway_row['unavailable'] === 1 && (time() - $giveaway_row['last_checked']) <= MAX_TIME_GIVEAWAY_CACHE) {
		return $response->withHeader('Access-Control-Allow-Origin', '*')
		->withHeader('Content-type', 'application/json')
		->withJson(array(
		'errors' => array(
			'code' => 0,
			'description' => 'There was some error with the request to SG or the giveaway doesn\'t exist'
		)), 500);

	} elseif ($giveaway_row['count'] === 1 && $giveaway_row['ended'] === 1 && (time() - $giveaway_row['last_checked']) <= MAX_TIME_ENDED_GIVEAWAY_CACHE) {
		$stmt = $db->query("SELECT nickname FROM IUsers WHERE id=" . $giveaway_row['iusers_id']);

		$iusers_row = $stmt->fetch(PDO::FETCH_ASSOC);
		unset($stmt);

		$data = array(
			'id' => $giv_id,
			'ended' => (bool)$giveaway_row['ended'],
			'user' => $iusers_row['nickname'],
			'type' => $giveaway_row['giv_type'],
			'region' => $giveaway_row['region'],
			'level' => $giveaway_row['level'],
			'copies' => $giveaway_row['copies'],
			'points' => $giveaway_row['points'],
			'comments' => $giveaway_row['comments'],
			'entries' => $giveaway_row['entries'],
			'created_time' => $giveaway_row['created_time'],
			'starting_time' => $giveaway_row['starting_time'],
			'ending_time' => $giveaway_row['ending_time'],
			'description' => $giveaway_row['description'],
			'game_id' => null,
			'game_type' => null,
			'game_title' => null
		);

		if (isset($giveaway_row['not_steam_game'])) {
			$data['game_title'] = $giveaway_row['not_steam_game'];
		} else {
			$stmt = $db->query("SELECT game_id, game_type, game_title FROM GameTitles WHERE id=" . $giveaway_row['gametitles_id']);
			$gametitles_row = $stmt->fetch(PDO::FETCH_ASSOC);
			unset($stmt);

			$data['game_id'] = $gametitles_row['game_id'];
			$data['game_type'] = $gametitles_row['game_type'];
			$data['game_title'] = $gametitles_row['game_title'];
		}

		return $response->withHeader('Access-Control-Allow-Origin', '*')
		->withHeader('Content-type', 'application/json')
		->withJson($data, 200);

	} elseif ($giveaway_row['count'] === 1 && $giveaway_row['ended'] === 1 && (time() - $giveaway_row['last_checked']) >= MAX_TIME_ENDED_GIVEAWAY_CACHE) {
		if ($giveaway_row['blacklisted'] === 1) {
			$page_req = anon_get_sg_page('https://www.steamgifts.com/giveaway/' . $giv_id . '/');
		} else {
			$page_req = get_sg_page('https://www.steamgifts.com/giveaway/' . $giv_id . '/');
		}
	} elseif ($giveaway_row['count'] === 1 && $giveaway_row['blacklisted'] === 1 && (time() - $giveaway_row['last_checked']) >= MAX_TIME_GIVEAWAY_CACHE) {
		$page_req = anon_get_sg_page('https://www.steamgifts.com/giveaway/' . $giv_id . '/');
	} else {
		// Request the page
		if (isset($sg_phpsessid)) {
			$page_req = get_sg_page('https://www.steamgifts.com/giveaway/' . $giv_id . '/', $sg_phpsessid);
		} else {
			$page_req = get_sg_page('https://www.steamgifts.com/giveaway/' . $giv_id . '/');
		}
	}


	// If SG is down or giveaway unavailable stop and exit with a 500
	if ($page_req === false) {
		if ($giveaway_row['count'] === 0) {
			$stmt = $db->query("INSERT INTO IGiveaways (giv_id, unavailable) VALUES (:giv_id, :unavailable)");
			$stmt->execute(array(
				':giv_id' => $giv_id,
				':unavailable' => 1
			));

			unset($stmt);
		} else {
			$stmt = $db->prepare("UPDATE IGiveaways SET last_ckeched=NULL, unavailable=1 WHERE id=:id");
			$stmt->execute(array(
				':id' => $giveaway_row['id']
			));

			unset($stmt);
		}

		return $response->withHeader('Access-Control-Allow-Origin', '*')
		->withHeader('Content-type', 'application/json')
		->withJson(array(
		'errors' => array(
			'code' => 0,
			'description' => 'There was some error with the request to SG or the giveaway doesn\'t exist'
		)), 500);
	}


	$html = str_get_html($page_req);

	$bBL = false;
	$title = $html->find('.page__heading__breadcrumbs', 0);
	if (!is_null($title) && empty($title->children()) && $title->innertext == "Error") {
		//echo "1 If.";
		//echo "\n";
		$response_rows = $html->find('.table__row-outer-wrap');

		if (count($response_rows) == 2) {
			//echo "1 If, 1 If.";
			//echo "\n";
			$message = $response_rows[1]->children(0)->children(1)->plaintext;

			if (strpos($message, "blacklisted") !== false) {
				//echo "1 If, 1 If, 1 If.";
				//echo "\n";
				$page_req = anon_get_sg_page('https://www.steamgifts.com/giveaway/' . $giv_id .'/');
				$html = str_get_html($page_req);
				$title = $html->find('.page__heading__breadcrumbs', 0);

				$bBL = true;
			}
		}
	}


	if (!is_null($title) && empty($title->children()) && $title->innertext == "Error") {
		//echo "1 If.";
		//echo "\n";
		$response_rows = $html->find('.table__row-outer-wrap');

		if (count($response_rows) == 2) {
			//echo "1 If, 1 If.";
			//echo "\n";
			$message = $response_rows[1]->children(0)->children(1)->plaintext;

			if (strpos($message, "This giveaway is restricted to the following region:") !== false) {
				//echo "1 If, 1 If, 2 If.";
				//echo "\n";

				$initial_index = strpos($message, ":") + 2;
				$end_index = strpos($message, "(");

				if ($end_index === false) {
					//echo "1 If, 1 If, 1 ElseIf, 1 If.";
					//echo "\n";
					$region = substr($message, $initial_index);
				} else {
					//echo "1 If, 1 If, 1 ElseIf, 1 Else.";
					//echo "\n";
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
					$stmt = $db->prepare("INSERT INTO IGiveaways (giv_id, blacklisted, not_region, region) VALUES (:giv_id, :blacklisted, :not_region, :region)");
					$stmt->execute(array(
						':giv_id' => $giv_id,
						':blacklisted' => (int)$bBL,
						':not_region' => 1,
						':region' => $region
					));

					unset($stmt);
				} else {
					$stmt = $db->prepare("UPDATE IGiveaways SET blacklisted=:blacklisted, not_region=:not_region, region=:region, unavailable=:unavailable, last_checked=NULL WHERE id=:id");
					$stmt->execute(array(
						':blacklisted' => (int)$bBL,
						':not_region' => 1,
						':region' => $region,
						':unavailable' => 0,
						':id' => $giveaway_row['id']
					));

					unset($stmt);
				}


				return $response->withHeader('Access-Control-Allow-Origin', '*')
				->withHeader('Content-type', 'application/json')
				->withJson(array('errors' => array(
					'code' => 2,
					'description' => 'Not in the proper region',
					'id' => $giv_id,
					'region' => $region
				)), 500);

			} elseif (strpos($message, "whitelist, or the required Steam groups") !== false) {
				//echo "1 If, 1 If, 1 ElseIf.";
				//echo "\n";

				if ($giveaway_row['count'] === 0) {
					$stmt = $db->prepare("INSERT INTO IGiveaways (giv_id, blacklisted, not_wl_groups) VALUES (:giv_id, :blacklisted, :not_wl_groups)");
					$stmt->execute(array(
						':giv_id' => $giv_id,
						':blacklisted' => (int)$bBL,
						':not_wl_groups' => 1
					));

					unset($stmt);
				} else {
					$stmt = $db->prepare("UPDATE IGiveaways SET blacklisted=:blacklisted, not_wl_groups=:not_wl_groups, unavailable=:unavailable, last_checked=NULL WHERE id=:id");
					$stmt->execute(array(
						':blacklisted' => (int)$bBL,
						':not_wl_groups' => 1,
						':unavailable' => 0,
						':id' => $giveaway_row['id']
					));

					unset($stmt);
				}


				return $response->withHeader('Access-Control-Allow-Origin', '*')
				->withHeader('Content-type', 'application/json')
				->withJson(array('errors' => array(
					'code' => 3,
					'description' => 'Not in the whitelist and/or required groups',
					'id' => $giv_id
				)), 500);

			} elseif (strpos($message, "whitelist") !== false) {
				//echo "1 If, 1 If, 2 ElseIf.";
				//echo "\n";

				if ($giveaway_row['count'] === 0) {
					$stmt = $db->prepare("INSERT INTO IGiveaways (giv_id, blacklisted, not_whitelisted) VALUES (:giv_id, :blacklisted, :not_whitelisted)");
					$stmt->execute(array(
						':giv_id' => $giv_id,
						':blacklisted' => (int)$bBL,
						':not_whitelisted' => 1
					));

					unset($stmt);
				} else {
					$stmt = $db->prepare("UPDATE IGiveaways SET blacklisted=:blacklisted, not_whitelisted=:not_whitelisted, unavailable=:unavailable, last_checked=NULL WHERE id=:id");
					$stmt->execute(array(
						':blacklisted' => (int)$bBL,
						':not_whitelisted' => 1,
						':unavailable' => 0,
						':id' => $giveaway_row['id']
					));

					unset($stmt);
				}


				return $response->withHeader('Access-Control-Allow-Origin', '*')
				->withHeader('Content-type', 'application/json')
				->withJson(array('errors' => array(
					'code' => 4,
					'description' => 'Not in the whitelist',
					'id' => $giv_id
				)), 500);

			} elseif (strpos($message, "Steam groups") !== false) {
				//echo "1 If, 1 If, 3 ElseIf.";
				//echo "\n";

				if ($giveaway_row['count'] === 0) {
					$stmt = $db->prepare("INSERT INTO IGiveaways (giv_id, blacklisted, not_groups) VALUES (:giv_id, :blacklisted, :not_groups)");
					$stmt->execute(array(
						':giv_id' => $giv_id,
						':blacklisted' => (int)$bBL,
						':not_groups' => 1
					));

					unset($stmt);
				} else {
					$stmt = $db->prepare("UPDATE IGiveaways SET blacklisted=:blacklisted, not_groups=:not_groups, unavailable=:unavailable, last_checked=NULL WHERE id=:id");
					$stmt->execute(array(
						':blacklisted' => (int)$bBL,
						':not_groups' => 1,
						':unavailable' => 0,
						':id' => $giveaway_row['id']
					));
				}


				return $response->withHeader('Access-Control-Allow-Origin', '*')
				->withHeader('Content-type', 'application/json')
				->withJson(array('errors' => array(
					'code' => 5,
					'description' => 'Not in the required groups',
					'id' => $giv_id
				)), 500);
			}


		} elseif (count($response_rows) == 4) {
			//echo "1 If, 1 ElseIf.";
			//echo "\n";
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
				$stmt = $db->prepare("SELECT COUNT(*) AS count, id, nickname FROM IUsers WHERE nickname=:nickname");
				$stmt->execute(array(
					':nickname' => $user
				));

				$iusers_row = $stmt->fetch(PDO::FETCH_ASSOC);
				unset($stmt);

				if ($iusers_row['count'] === 0) {
					$stmt = $db->prepare("INSERT INTO IUsers (nickname) VALUES (:nickname)");
					$stmt->execute(array(
						':nickname' => $user
					));

					unset($stmt);
					$inserted_id = $db->query("SELECT LAST_INSERT_ID() AS inserted_id");
					$inserted_id = $inserted_id->fetch(PDO::FETCH_ASSOC);
					$inserted_id = $inserted_id['inserted_id'];
				} else {
					$inserted_id = $iusers_row['id'];
				}

				unset($iusers_row);

				$stmt = $db->prepare("INSERT INTO IGiveaways (giv_id, iusers_id, deleted, deleted_reason, deleted_time) VALUES (:giv_id, :iusers_id, :deleted, :deleted_reason, :deleted_time)");
				$stmt->execute(array(
					':giv_id' => $giv_id,
					':iusers_id' => $inserted_id,
					':deleted' => 1,
					':deleted_reason' => $deleted_reason,
					':deleted_time' => $deleted_time
				));

				unset($stmt);
			} else {
				$stmt = $db->prepare("UPDATE IGiveaways SET unavailable=:unavailable, last_checked=NULL WHERE id=:id");
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
		'comments' => null,
		'entries' => 0,
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
	//echo "store link is: ";
	//var_dump($store_link->href);
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
	//var_dump($game_title);
	//echo strlen($game_title);

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
				if (time() >= $ending_time) {
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
		}
	}
	unset($sidebar_numbers);

	// Check if user exists on IUsers
	$stmt = $db->prepare("SELECT COUNT(*) AS count, id FROM IUsers WHERE nickname=:nickname");
	$stmt->execute(array(
		':nickname' => $data['user']
	));

	$iusers_row = $stmt->fetch(PDO::FETCH_ASSOC);
	unset($stmt);

	if ($iusers_row['count'] === 0) {
		$stmt = $db->prepare("INSERT INTO IUsers (nickname) VALUES (:nickname)");
		$stmt->execute(array(
			':nickname' => $data['user']
		));

		unset($stmt);

		$iusers_inserted_id = $db->query("SELECT LAST_INSERT_ID() AS inserted_id");
		$iusers_inserted_id = $iusers_inserted_id->fetch(PDO::FETCH_ASSOC);
		$iusers_inserted_id = $iusers_inserted_id['inserted_id'];
	} else {
		$iusers_inserted_id = $iusers_row['id'];
		//var_dump($iusers_row['id']);
	}
	unset($iusers_row);

	// Check if game exists on GameTitles
	if (!is_null($data['game_id']) && !is_null($data['game_type'])) {
		$stmt = $db->prepare("SELECT COUNT(*) AS count, id, game_title FROM GameTitles WHERE game_id=:game_id AND game_type=:game_type");
		$stmt->execute(array(
			':game_id' => $data['game_id'],
			':game_type' => $data['game_type']
		));

		$gametitles_row = $stmt->fetch(PDO::FETCH_ASSOC);
		unset($stmt);

		if ($gametitles_row['count'] === 0 && strlen($game_title) <= 40) {
			$stmt = $db->prepare("INSERT INTO GameTitles (game_id, game_type, game_title) VALUES (:game_id, :game_type, :game_title)");
			$stmt->execute(array(
				':game_id' => $data['game_id'],
				':game_type' => $data['game_type'],
				':game_title' => $game_title
			));

			unset($stmt);

			$data['game_title'] = $game_title;

			$gametitles_inserted_id = $db->query("SELECT LAST_INSERT_ID() AS inserted_id");
			$gametitles_inserted_id = $gametitles_inserted_id->fetch(PDO::FETCH_ASSOC);
			$gametitles_inserted_id = $gametitles_inserted_id['inserted_id'];
		} elseif ($gametitles_row['count'] === 0 && strlen($game_title) > 40) {
			$api_request = get_page('http://api.sighery.com/SteamGifts/Interactions/GetGameTitle/?id=' . $data['game_id'] . "&type=" . $data['game_type']);

			if ($api_request === false) {
				return $response->withHeader('Access-Control-Allow-Origin', '*')
				->withHeader('Content-type', 'application/json')
				->withJson(json_decode($api_request, true), 500);
			}

			//var_dump($api_request);

			$api_request = json_decode($api_request, true);

			$data['game_title'] = $api_request['game_title'];
			$gametitles_inserted_id = $api_request['id'];
		} elseif ($gametitles_row['count'] === 1 && strlen($game_title) > 40) {
			$data['game_title'] = $gametitles_row['game_title'];
			$gametitles_inserted_id = $gametitles_row['id'];
		} else {
			$data['game_title'] = $game_title;
			$gametitles_inserted_id = $gametitles_row['id'];
		}
		unset($gametitles_row);
	}

	if ($giveaway_row['count'] === 0) {
		if (!is_null($store_link)) {
			$stmt = $db->prepare("INSERT INTO IGiveaways (blacklisted, ended, region, giv_id, iusers_id, giv_type, level, copies, points, gametitles_id, created_time, starting_time, ending_time, comments, entries, description) VALUES (:blacklisted, :ended, :region, :giv_id, :iusers_id, :giv_type, :level, :copies, :points, :gametitles_id, :created_time, :starting_time, :ending_time, :comments, :entries, :description)");
			$stmt->execute(array(
				':blacklisted' => (int)$bBL,
				':ended' => (int)$data['ended'],
				':region' => $data['region'],
				':giv_id' => $data['id'],
				':iusers_id' => $iusers_inserted_id,
				':giv_type' => $data['type'],
				':level' => $data['level'],
				':copies' => $data['copies'],
				':points' => $data['points'],
				':gametitles_id' => $gametitles_inserted_id,
				':created_time' => $data['created_time'],
				':starting_time' => $data['starting_time'],
				':ending_time' => $data['ending_time'],
				':comments' => $data['comments'],
				':entries' => $data['entries'],
				':description' => $data['description']
			));

			unset($stmt);
		} else {
			$stmt = $db->prepare("INSERT INTO IGiveaways (blacklisted, ended, region, giv_id, iusers_id, giv_type, level, copies, points, not_steam_game, created_time, starting_time, ending_time, comments, entries, description) VALUES (:blacklisted, :ended, :region, :giv_id, :iusers_id, :giv_type, :level, :copies, :points, :not_steam_game, :created_time, :starting_time, :ending_time, :comments, :entries, :description)");
			$stmt->execute(array(
				':blacklisted' => (int)$bBL,
				':ended' => (int)$data['ended'],
				':region' => $data['region'],
				':giv_id' => $data['id'],
				':iusers_id' => $iusers_inserted_id,
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
				':description' => $data['description']
			));
		}
	} else {
		if (!is_null($store_link)) {
			$stmt = $db->prepare("UPDATE IGiveaways SET blacklisted=:blacklisted, ended=:ended, region=:region, giv_type=:giv_type, level=:level, copies=:copies, points=:points, gametitles_id=:gametitles_id, ending_time=:ending_time, comments=:comments, entries=:entries, description=:description WHERE giv_id=:giv_id");
			$stmt->execute(array(
				':blacklisted' => (int)$bBL,
				':ended' => (int)$data['ended'],
				':region' => $data['region'],
				':giv_type' => $data['type'],
				':level' => $data['level'],
				':copies' => $data['copies'],
				':points' => $data['points'],
				':gametitles_id' => $gametitles_inserted_id,
				':ending_time' => $data['ending_time'],
				':comments' => $data['comments'],
				':entries' => $data['entries'],
				':description' => $data['description'],
				':giv_id' => $data['id']
			));

			unset($stmt);
		} else {
			$stmt = $db->prepare("UPDATE IGiveaways SET blacklisted=:blacklisted, ended=:ended, region=:region, giv_type=:giv_type, level=:level, copies=:copies, points=:points, not_steam_game=:not_steam_game, ending_time=:ending_time, comments=:comments, entries=:entries, description=:description WHERE giv_id=:giv_id");
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
				':description' => $data['description'],
				':giv_id' => $data['id']
			));
		}
	}

	return $response->withHeader('Access-Control-Allow-Origin', '*')
	->withHeader('Content-type', 'application/json')
	->withJson($data, 200);
});
?>
