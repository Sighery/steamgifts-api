<?php
require_once(__DIR__ . '/../utils/GiveawayHeader.php');
require_once(__DIR__ . '/../utils/GiveawayWinners.php');
require_once(__DIR__ . '/../utils/utilities.php');
require_once(__DIR__ . '/../utils/dbconn.php');


$app->get('/SteamGifts/IGiveaways/GetGivWinners42/', function ($request, $response) {
	define('MAX_TIME_WINNERS_FRESH_CACHE', 7200); //2hours for less than a day
	define('MAX_TIME_WINNERS_RECENT_CACHE', 43200); //12hours for less than a week
	define('MAX_TIME_WINNERS_OLD_CACHE', 86400); //1day for more than a week

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


	// Get DB data of the giveaway
	$stmt = $db->prepare("SELECT COUNT(*) AS count, id, ended, deleted, deleted_reason, deleted_time, blacklisted, not_whitelisted, not_region, region, not_groups, not_wl_groups, ending_time, winners, unavailable, UNIX_TIMESTAMP(last_checked) AS last_checked FROM GiveawaysGeneral WHERE giv_id=:giv_id");
	$stmt->execute(array(
		':giv_id' => $giv_id
	));

	$giveaway_row = $stmt->fetch(PDO::FETCH_ASSOC);
	unset($stmt);


	$berror = false;
	if ($giveaway_row['deleted'] === 1 || $giveaway_row['not_whitelisted'] === 1 || $giveaway_row['not_region'] === 1 || $giveaway_row['not_groups'] === 1 || $giveaway_row['not_wl_groups'] === 1 || $giveaway_row['unavailable'] === 1) {
		$berror = true;
	}


	$winners_array = array();
	$winners_position = array();
	if ($giveaway_row['count'] === 1 && $giveaway_row['winners'] !== null && $giveaway_row['ended'] === 1) {
		$stmt = $db->prepare("SELECT GiveawaysWinners.id, GiveawaysWinners.usersgeneral_id, GiveawaysWinners.marked_status, UNIX_TIMESTAMP(GiveawaysWinners.last_checked) AS last_checked, UsersGeneral.nickname FROM GiveawaysWinners INNER JOIN UsersGeneral ON GiveawaysWinners.usersgeneral_id=UsersGeneral.id WHERE GiveawaysWinners.giveawaysgeneral_id=:giveawaysgeneral_id ORDER BY GiveawaysWinners.id");
		$stmt->execute(array(
			':giveawaysgeneral_id' => $giveaway_row['id']
		));

		$oldest_checked = time();
		$count = 0;

		while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			if ($row['last_checked'] < $oldest_checked) {
				$oldest_checked = $row['last_checked'];
			}

			array_push($winners_array, $row);

			$winners_position[$row['nickname']] = $count;
			$count++;
		}

		unset($stmt);
		unset($count);
	}


	if ($giveaway_row['count'] === 1 && $berror === false && time() < $giveaway_row['ending_time']) {
		$data = array(
			'id' => $giv_id,
			'winners_total' => null,
			'winners' => []
		);

		return $response->withHeader('Access-Control-Allow-Origin', '*')
		->withHeader('Content-type', 'application/json')
		->withJson($data, 200);


	} elseif ($berror && ((((time() - $giveaway_row['ending_time']) < 86400) && ((time() - $giveaway_row['last_checked']) < MAX_TIME_WINNERS_FRESH_CACHE)) || (((time() - $giveaway_row['ending_time']) <= 592200) && ((time() - $giveaway_row['last_checked']) < MAX_TIME_WINNERS_RECENT_CACHE)) || (((time() - $giveaway_row['ending_time']) > 592200) && ((time() - $giveaway_row['last_checked']) < MAX_TIME_WINNERS_OLD_CACHE)))) {
		$giveaway_obj = new GiveawayHeader($giv_id, $giveaway_row, $db);

		try {
			$giveaway_obj->db_is_error();
		} catch (GeneralMsgException $exc) {
			return $response->withHeader('Access-Control-Allow-Origin', '*')
			->withHeader('Content-type', 'application/json')
			->withJson($exc->getDict(), 500);
		}


	} elseif ($giveaway_row['count'] === 1 && $giveaway_row['ended'] === 1 && $giveaway_row['winners'] > 250) {
		// More than 250 winners, unavailable for now. END.

		return $response->withHeader('Access-Control-Allow-Origin', '*')
		->withHeader('Content-type', 'application/json')
		->withJson(array('errors' => array(
			'code' => 12,
			'description' => 'Fetching giveaways with more than 250 winners is currently unavailable to not stress the SteamGifts servers'
		)), 500);


	} elseif ($giveaway_row['count'] === 1 && $giveaway_row['ended'] === 1 && $giveaway_row['winners'] !== null && count($winners_array) !== 0 && ((((time() - $giveaway_row['ending_time']) < 86400) && ((time() - $oldest_checked) < MAX_TIME_WINNERS_FRESH_CACHE)) || (((time() - $giveaway_row['ending_time']) <= 592200) && ((time() - $oldest_checked) < MAX_TIME_WINNERS_RECENT_CACHE)) || (((time() - $giveaway_row['ending_time']) > 592200) && ((time() - $oldest_checked) < MAX_TIME_WINNERS_OLD_CACHE)))) {

		$data = array(
			'id' => $giv_id,
			'winners_total' => $giveaway_row['winners'],
			'winners' => []
		);

		forEach($winners_array as $winner) {
			$array = array(
				'nickname' => $winner['nickname'],
				'marked_status' => $winner['marked_status']
			);

			array_push($data['winners'], $array);
		}

		return $response->withHeader('Access-Control-Allow-Origin', '*')
		->withHeader('Content-type', 'application/json')
		->withJson($data, 200);


	} else {
		$title_string;
		$npages = 1;

		$page_req = APIRequests::sg_generic_get_request("https://www.steamgifts.com/giveaway/" . $giv_id . "/null/winners", true);

		if ($page_req->status_code !== 200) {
			// SG is down. END.

			return $response->withHeader('Access-Control-Allow-Origin', '*')
			->withHeader('Content-type', 'application/json')
			->withJson(array(
			'errors' => array(
				'code' => 0,
				'description' => 'The request to SG was unsuccessful'
			)), 500);
		} elseif ($page_req->url === "https://www.steamgifts.com/") {
			// Giveaway doesn't exist, store and exit. END.

			$stmt = $db->prepare("INSERT INTO GiveawaysGeneral (giv_id, unavailable) VALUES (:giv_id, :unavailable) ON DUPLICATE KEY UPDATE unavailable=:unavailable2, last_checked=:last_checked");
			$stmt->execute(array(
				':giv_id' => $giv_id,
				':unavailable' => 1,
				':unavailable2' => 1,
				':last_checked' => null
			));

			unset($stmt);

			return $response->withHeader('Access-Control-Allow-Origin', '*')
			->withHeader('Content-type', 'application/json')
			->withJson(array(
			'errors' => array(
				'code' => 2,
				'description' => 'The giveaway doesn\'t exist'
			)), 500);
		} else {
			preg_match('/https\:\/\/www\.steamgifts\.com\/giveaway\/[a-zA-Z0-9]{5}\/(.+?)\/winners/', $page_req->url, $title_match);

			$title_string = $title_match[1];
			unset($title_match);
		}


		try {
			$html = str_get_html($page_req->body);
			unset($page_req);

			$giveaway_obj = new GiveawayHeader($giv_id, $giveaway_row, $db);
			$giveaway_obj->html_is_error($html);

			$saved_already = false;
			if ($giveaway_row['count'] === 0 || $giveaway_row['winners'] === null) {
				$giveaway_obj->parse_giveaway($html);

				$giveaway_inserted_id = $giveaway_obj->store_giveaway_data();
				$saved_already = true;

				$stmt = $db->prepare("SELECT id, ended, ending_time, winners, UNIX_TIMESTAMP(last_checked) AS last_checked FROM GiveawaysGeneral WHERE id=:id");
				$stmt->execute(array(
					':id' => $giveaway_inserted_id
				));

				$giveaway_row = $stmt->fetch(PDO::FETCH_ASSOC);

				$nPages = (int)ceil($giveaway_row['winners']/25);
			} else {
				$nPages = (int)ceil($giveaway_row['winners']/25);
			}


			if ($giveaway_row['winners'] > 250) {
				// More than 250 winners, unavailable for now. END.

				return $response->withHeader('Access-Control-Allow-Origin', '*')
				->withHeader('Content-type', 'application/json')
				->withJson(array('errors' => array(
					'code' => 12,
					'description' => 'Fetching giveaways with more than 250 winners is currently unavailable to not stress the SteamGifts servers'
				)), 500);
			}


			$winners_array_html = GiveawayWinners::parse_winners($html);
			unset($giveaway_obj);


			for ($i = 2; $i <= $nPages; $i++) {
				$page_req = APIRequests::sg_generic_get_request("https://www.steamgifts.com/giveaway/" . $giv_id . "/" . $title_string . "/winners/search?page=" . $i);

				if ($page_req->status_code !== 200) {
					// SG is down. END.

					return $response->withHeader('Access-Control-Allow-Origin', '*')
					->withHeader('Content-type', 'application/json')
					->withJson(array(
					'errors' => array(
						'code' => 0,
						'description' => 'The request to SG was unsuccessful'
					)), 500);
				} elseif ($page_req->url === "https://www.steamgifts.com/") {
					// Giveaway doesn't exist, store and exit. END.

					$stmt = $db->prepare("INSERT INTO GiveawaysGeneral (giv_id, unavailable) VALUES (:giv_id, :unavailable) ON DUPLICATE KEY UPDATE unavailable=:unavailable2, last_checked=:last_checked");
					$stmt->execute(array(
						':giv_id' => $giv_id,
						':unavailable' => 1,
						':unavailable2' => 1,
						':last_checked' => null
					));

					unset($stmt);

					return $response->withHeader('Access-Control-Allow-Origin', '*')
					->withHeader('Content-type', 'application/json')
					->withJson(array(
					'errors' => array(
						'code' => 2,
						'description' => 'The giveaway doesn\'t exist'
					)), 500);
				}


				$html = str_get_html($page_req->body);

				$giveaway_obj = new GiveawayHeader($giv_id, $giveaway_row, $db);
				$giveaway_obj->html_is_error($html);

				$winners_array_html = array_merge($winners_array_html, GiveawayWinners::parse_winners($html));

				if ($i === $nPages && $saved_already === false) {
					$giveaway_obj->parse_giveaway($html);
					$giveaway_obj->store_giveaway_data();
				}
			}


			forEach($winners_array_html as $winner) {
				if (array_key_exists($winner['nickname'], $winners_position)) {
					GiveawayWinners::store_winner($winner, $db, $giveaway_row['id'], $winners_array[$winners_position[$winner['nickname']]]['id']);
				} else {
					GiveawayWinners::store_winner($winner, $db, $giveaway_row['id']);
				}
			}


			// Fetching and storing went fine. END.
			$data = array(
				'id' => $giv_id,
				'winners_total' => $giveaway_row['winners'],
				'winners' => $winners_array_html
			);

			return $response->withHeader('Access-Control-Allow-Origin', '*')
			->withHeader('Content-type', 'application/json')
			->withJson($data, 200);

		} catch (GeneralMsgException $exc) {
			// The DB or HTML data has some sort of restriction (like not being
			//in the required groups, not whitelisted, etc). Or there was some
			//error trying to parse/store the giveaway data. Return that info
			//and END.

			$giveaway_obj->store_html_error($exc->getCode(), $exc->getDict());

			return $response->withHeader('Access-Control-Allow-Origin', '*')
			->withHeader('Content-type', 'application/json')
			->withJson($exc->getDict(), 500);

		} catch (BlacklistMsgException $exc) {
			$page_req = APIRequests::sg_generic_get_request("https://www.steamgifts.com/giveaway/" . $giv_id . "/" . $title_string . "/winners", true, false);

			if ($page_req->status_code !== 200) {
				// SG is down. END.

				return $response->withHeader('Access-Control-Allow-Origin', '*')
				->withHeader('Content-type', 'application/json')
				->withJson(array(
				'errors' => array(
					'code' => 0,
					'description' => 'The request to SG was unsuccessful'
				)), 500);
			} elseif ($page_req->url === "https://www.steamgifts.com/") {
				// Giveaway doesn't exist, store and exit. END.

				$stmt = $db->prepare("INSERT INTO GiveawaysGeneral (giv_id, unavailable) VALUES (:giv_id, :unavailable) ON DUPLICATE KEY UPDATE unavailable=:unavailable2, last_checked=:last_checked");
				$stmt->execute(array(
					':giv_id' => $giv_id,
					':unavailable' => 1,
					':unavailable2' => 1,
					':last_checked' => null
				));

				unset($stmt);

				return $response->withHeader('Access-Control-Allow-Origin', '*')
				->withHeader('Content-type', 'application/json')
				->withJson(array(
				'errors' => array(
					'code' => 2,
					'description' => 'The giveaway doesn\'t exist'
				)), 500);
			}


			try {
				$html = str_get_html($page_req->body);
				unset($page_req);

				$giveaway_obj = new GiveawayHeader($giv_id, $giveaway_row, $db);
				$giveaway_obj->bBL = true;
				$giveaway_obj->html_is_error($html);

				$saved_already = false;
				if ($giveaway_row['count'] === 0 || $giveaway_row['winners'] === null) {
					$giveaway_obj->parse_giveaway($html);

					$giveaway_inserted_id = $giveaway_obj->store_giveaway_data();
					$saved_already = true;

					$stmt = $db->prepare("SELECT id, ended, ending_time, winners, UNIX_TIMESTAMP(last_checked) AS last_checked FROM GiveawaysGeneral WHERE id=:id");
					$stmt->execute(array(
						':id' => $giveaway_inserted_id
					));

					$giveaway_row = $stmt->fetch(PDO::FETCH_ASSOC);

					$nPages = (int)ceil($giveaway_row['winners']/25);
				} else {
					$nPages = (int)ceil($giveaway_row['winners']/25);
				}


				if ($giveaway_row['winners'] > 250) {
					// More than 250 winners, unavailable for now. END.

					return $response->withHeader('Access-Control-Allow-Origin', '*')
					->withHeader('Content-type', 'application/json')
					->withJson(array('errors' => array(
						'code' => 12,
						'description' => 'Fetching giveaways with more than 250 winners is currently unavailable to not stress the SteamGifts servers'
					)), 500);
				}


				$winners_array_html = GiveawayWinners::parse_winners($html);
				unset($giveaway_obj);


				for ($i = 2; $i <= $nPages; $i++) {
					$page_req = APIRequests::sg_generic_get_request("https://www.steamgifts.com/giveaway/" . $giv_id . "/" . $title_string . "/winners/search?page=" . $i);

					if ($page_req->status_code !== 200) {
						// SG is down. END.

						return $response->withHeader('Access-Control-Allow-Origin', '*')
						->withHeader('Content-type', 'application/json')
						->withJson(array(
						'errors' => array(
							'code' => 0,
							'description' => 'The request to SG was unsuccessful'
						)), 500);
					} elseif ($page_req->url === "https://www.steamgifts.com/") {
						// Giveaway doesn't exist, store and exit. END.

						$stmt = $db->prepare("INSERT INTO GiveawaysGeneral (giv_id, unavailable) VALUES (:giv_id, :unavailable) ON DUPLICATE KEY UPDATE unavailable=:unavailable2, last_checked=:last_checked");
						$stmt->execute(array(
							':giv_id' => $giv_id,
							':unavailable' => 1,
							':unavailable2' => 1,
							':last_checked' => null
						));

						unset($stmt);

						return $response->withHeader('Access-Control-Allow-Origin', '*')
						->withHeader('Content-type', 'application/json')
						->withJson(array(
						'errors' => array(
							'code' => 2,
							'description' => 'The giveaway doesn\'t exist'
						)), 500);
					}


					$html = str_get_html($page_req->body);

					$giveaway_obj = new GiveawayHeader($giv_id, $giveaway_row, $db);
					$giveaway_obj->bBL = true;
					$giveaway_obj->html_is_error($html);

					$winners_array_html = array_merge($winners_array_html, GiveawayWinners::parse_winners($html));

					if ($i === $nPages && $saved_already === false) {
						$giveaway_obj->parse_giveaway($html);
						$giveaway_obj->store_giveaway_data();
					}
				}


				forEach($winners_array_html as $winner) {
					if (array_key_exists($winner['nickname'], $winners_position)) {
						GiveawayWinners::store_winner($winner, $db, $giveaway_row['id'], $winners_array[$winners_position[$winner['nickname']]]['id']);
					} else {
						GiveawayWinners::store_winner($winner, $db, $giveaway_row['id']);
					}
				}


				// Fetching and storing went fine. END.
				$data = array(
					'id' => $giv_id,
					'winners_total' => $giveaway_row['winners'],
					'winners' => $winners_array_html
				);

				return $response->withHeader('Access-Control-Allow-Origin', '*')
				->withHeader('Content-type', 'application/json')
				->withJson($data, 200);

			} catch (GeneralMsgException $exc) {
				// The DB or HTML data has some sort of restriction (like not being
				//in the required groups, not whitelisted, etc). Or there was some
				//error trying to parse/store the giveaway data. Return that info
				//and END.

				$giveaway_obj->store_html_error($exc->getCode(), $exc->getDict());

				return $response->withHeader('Access-Control-Allow-Origin', '*')
				->withHeader('Content-type', 'application/json')
				->withJson($exc->getDict(), 500);

			}
		}
	}
});
?>
