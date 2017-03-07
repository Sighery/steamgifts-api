<?php
require_once(__DIR__ . '/../utils/GiveawayHeader.php');
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

	// We start by checking against the DB
	$stmt = $db->prepare("SELECT COUNT(*) AS count, id, ended, deleted, deleted_reason, deleted_time, blacklisted, not_whitelisted, not_region, region, not_groups, not_wl_groups, usersgeneral_id, giv_type, level, copies, points, gamesinfo_id, not_steam_game, created_time, starting_time, ending_time, comments, entries, winners, unavailable, UNIX_TIMESTAMP(last_checked) AS last_checked FROM GiveawaysGeneral WHERE giv_id=:giv_id");
	$stmt->execute(array(
		':giv_id' => $giv_id
	));

	$giveaway_row = $stmt->fetch(PDO::FETCH_ASSOC);
	unset($stmt);

	// We check if there are duplicate rows in the DB first, if there are we need
	//to purge those before continuing
	if ($giveaway_row['count'] > 1) {
		$stmt = $db->prepare("SELECT id FROM GiveawaysGeneral WHERE giv_id=:giv_id ORDER BY id");
		$stmt->execute(array(
			':giv_id' => $giv_id
		));

		$count = 0;

		while ($duplicate_row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			if ($count !== 0) {
				$stmt2 = $db->prepare("DELETE FROM GiveawaysGeneral WHERE id=:id");
				$stmt2->execute(array(
					':id' => $duplicate_row['id']
				));
				unset($stmt2);

				$count++;
			} else {
				if ($giveaway_row['id'] !== $duplicate_row['id']) {
					$stmt2 = $db->prepare("SELECT COUNT(*) AS count, id, ended, deleted, deleted_reason, deleted_time, blacklisted, not_whitelisted, not_region, region, not_groups, not_wl_groups, usersgeneral_id, giv_type, level, copies, points, gamesinfo_id, not_steam_game, created_time, starting_time, ending_time, comments, entries, winners, unavailable, UNIX_TIMESTAMP(last_checked) AS last_checked FROM GiveawaysGeneral WHERE id=:id");
					$stmt2->execute(array(
						':id' => $duplicate_row['id']
					));

					$giveaway_row = $stmt2->fetch(PDO::FETCH_ASSOC);
					unset($stmt2);
				}

				$count++;
				continue;
			}
		}

		unset($count);
		unset($stmt);
	}


	$berror = false;
	if ($giveaway_row['deleted'] === 1 || $giveaway_row['not_whitelisted'] === 1 || $giveaway_row['not_region'] === 1 || $giveaway_row['not_groups'] === 1 || $giveaway_row['not_wl_groups'] === 1 || $giveaway_row['unavailable'] === 1) {
		$berror = true;
	}

	// Get description row
	if ($giveaway_row['count'] === 1 && $berror === false) {
		$stmt = $db->prepare("SELECT COUNT(*) AS count, description, UNIX_TIMESTAMP(last_checked) AS last_checked FROM GiveawaysDescriptions WHERE giveawaysgeneral_id=:giveawaysgeneral_id");
		$stmt->execute(array(
			':giveawaysgeneral_id' => $giveaway_row['id']
		));

		$description_row = $stmt->fetch(PDO::FETCH_ASSOC);
		unset($stmt);

		if ($description_row['count'] === 0) {
			$description_row['last_checked'] = 0;
		}
	} else {
		$description_row = array(
			'count' => 0,
			'last_checked' => 0
		);
	}


	$giveaway_obj = new GiveawayHeader($giv_id, $giveaway_row, $db);

	if ($giveaway_row['count'] === 1 && ($description_row['count'] === 1 || $berror) && $giveaway_row['unavailable'] === 0 && (($giveaway_row['ended'] === 0 && (((time() - $giveaway_row['last_checked']) < MAX_TIME_GIVEAWAY_CACHE) && ($berror || (time() - $description_row['last_checked']) < MAX_TIME_GIVEAWAY_CACHE))) || ($giveaway_row['ended'] === 1 && (((time() - $giveaway_row['last_checked']) < MAX_TIME_ENDED_GIVEAWAY_CACHE) && ($berror || (time() - $description_row['last_checked']) < MAX_TIME_ENDED_GIVEAWAY_CACHE))))) {
		try {
			// If this completes it means there was no restriction on the
			//data and that the successful response can be given back. END.
			$giveaway_obj->db_is_error();

			$data = array(
				'id' => $giv_id,
				'ended' => (bool)$giveaway_row['ended'],
				'user' => null,
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

			$stmt = $db->prepare("SELECT nickname FROM UsersGeneral WHERE id=:id");
			$stmt->execute(array(
				':id' => $giveaway_row['usersgeneral_id']
			));

			$usersgeneral_row = $stmt->fetch(PDO::FETCH_ASSOC);
			$data['user'] = $usersgeneral_row['nickname'];
			unset($stmt);
			unset($usersgeneral_row);

			if ($giveaway_row['gamesinfo_id'] === null) {
				$data['game_title'] = $giveaway_row['not_steam_game'];
			} else {
				$stmt = $db->prepare("SELECT game_id, game_type, game_title FROM GamesInfo WHERE id=:id");
				$stmt->execute(array(
					':id' => $giveaway_row['gamesinfo_id']
				));

				$gamesinfo_row = $stmt->fetch(PDO::FETCH_ASSOC);

				$data['game_id'] = $gamesinfo_row['game_id'];
				$data['game_type'] = $gamesinfo_row['game_type'];
				$data['game_title'] = $gamesinfo_row['game_title'];
			}

			return $response->withHeader('Access-Control-Allow-Origin', '*')
			->withHeader('Content-type', 'application/json')
			->withJson($data, 200);

		} catch (GeneralMsgException $exc) {
			// The DB data has some sort of restriction (like not being in
			//the required groups, not whitelisted, etc). Return that info
			//and END.

			$giveaway_obj->store_html_error($exc->getCode(), $exc->getDict());

			return $response->withHeader('Access-Control-Allow-Origin', '*')
			->withHeader('Content-type', 'application/json')
			->withJson($exc->getDict(), 500);
		}


	} else {
		$page_request = APIRequests::sg_generic_get_request("https://www.steamgifts.com/giveaway/" . $giv_id . "/", true);

		if ($page_request->status_code !== 200 && $page_request->status_code !== 301) {
			// SG is down. END.

			return $response->withHeader('Access-Control-Allow-Origin', '*')
			->withHeader('Content-type', 'application/json')
			->withJson(array(
			'errors' => array(
				'code' => 0,
				'description' => 'The request to SG was unsuccessful'
			)), 500);
		} elseif ($page_request->url === "https://www.steamgifts.com/" || $page_request->status_code === 301) {
			// Giveaway doesn't exist, store and exit. END.

			$stmt = $db->prepare("INSERT INTO GiveawaysGeneral (giv_id, unavailable) VALUES (:giv_id, :unavailable)");
			$stmt->execute(array(
				':giv_id' => $giv_id,
				':unavailable' => 1
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
			$html = str_get_html($page_request->body);

			try {
				$giveaway_obj->html_is_error($html);

				// All the parsing and storing went fine. END.
				$giveaway_obj->parse_giveaway($html);
				$inserted_id = $giveaway_obj->store_giveaway_data();

				$data = array(
					'id' => null,
					'ended' => null,
					'user' => null,
					'type' => null,
					'region' => null,
					'level' => null,
					'copies' => null,
					'points' => null,
					'comments' => null,
					'entries' => null,
					'winners' => null,
					'created_time' => null,
					'starting_time' => null,
					'ending_time' => null,
					'description' => null,
					'game_id' => null,
					'game_type' => null,
					'game_title' => null
				);
				$data = array_merge($data, $giveaway_obj->data);

				$description = $html->find('.page__description', 0);
				if (!is_null($description)) {
					$data['description'] = $description->lastChild()->children(0)->innertext;
				}
				unset($description);

				$stmt = $db->prepare("INSERT INTO GiveawaysDescriptions (giveawaysgeneral_id, description) VALUES (:giveawaysgeneral_id, :description) ON DUPLICATE KEY UPDATE description=:description2, last_checked=NULL");
				$stmt->execute(array(
					':giveawaysgeneral_id' => $inserted_id,
					':description' => $data['description'],
					':description2' => $data['description']
				));

				return $response->withHeader('Access-Control-Allow-Origin', '*')
				->withHeader('Content-type', 'application/json')
				->withJson($data, 200);

			} catch (BlacklistMsgException $exc) {
				// Catched the blacklisted message, repeat request as anon and
				//re-check further error codes.

				$page_request = APIRequests::sg_generic_get_request("https://www.steamgifts.com/giveaway/" . $giv_id . "/", true, false);

				if ($page_request->status_code !== 200 && $page_request->status_code !== 301) {
					// SG is down. END.

					return $response->withHeader('Access-Control-Allow-Origin', '*')
					->withHeader('Content-type', 'application/json')
					->withJson(array('errors' => array(
						'code' => 0,
						'description' => 'The request to SG was unsuccessful'
					)), 500);
				} elseif ($page_request->url === "https://www.steamgifts.com/" || $page_request->status_code === 301) {
					// Giveaway doesn't exist, store and exist. END.

					$stmt = $db->prepare("INSERT INTO GiveawaysGeneral (giv_id, unavailable) VALUES (:giv_id, :unavailable)");
					$stmt->execute(array(
						':giv_id' => $giv_id,
						':unavailable' => 1
					));

					unset($stmt);

					return $response->withHeader('Access-Control-Allow-Origin', '*')
					->withHeader('Content-type', 'application/json')
					->withJson(array('errors' => array(
						'code' => 2,
						'description' => 'The giveaway doesn\'t exist'
					)), 500);
				} else {
					$html = str_get_html($page_request->body);

					try {
						$giveaway_obj->html_is_error($html);

						// All the parsing and storing went fine. END.
						$giveaway_obj->parse_giveaway($html);
						$inserted_id = $giveaway_obj->store_giveaway_data();

						$data = array(
							'id' => null,
							'ended' => null,
							'user' => null,
							'type' => null,
							'region' => null,
							'level' => null,
							'copies' => null,
							'points' => null,
							'comments' => null,
							'entries' => null,
							'winners' => null,
							'created_time' => null,
							'starting_time' => null,
							'ending_time' => null,
							'description' => null,
							'game_id' => null,
							'game_type' => null,
							'game_title' => null
						);
						$data = array_merge($data, $giveaway_obj->data);

						$description = $html->find('.page__description', 0);
						if (!is_null($description)) {
							$data['description'] = $description->lastChild()->children(0)->innertext;
						}
						unset($description);

						$stmt = $db->prepare("INSERT INTO GiveawaysDescriptions (giveawaysgeneral_id, description) VALUES (:giveawaysgeneral_id, :description) ON DUPLICATE KEY UPDATE description=:description2, last_checked=NULL");
						$stmt->execute(array(
							':giveawaysgeneral_id' => $inserted_id,
							':description' => $data['description'],
							':description2' => $data['description']
						));

						return $response->withHeader('Access-Control-Allow-Origin', '*')
						->withHeader('Content-type', 'application/json')
						->withJson($data, 200);

					} catch (GeneralMsgException $exc) {
						// The DB data has some sort of restriction (like not being in
						//the required groups, not whitelisted, etc). Return that info
						//and END.

						$giveaway_obj->store_html_error($exc->getCode(), $exc->getDict());

						return $response->withHeader('Access-Control-Allow-Origin', '*')
						->withHeader('Content-type', 'application/json')
						->withJson($exc->getDict(), 500);
					}

				}

			} catch (GeneralMsgException $exc) {
				// The DB data has some sort of restriction (like not being in
				//the required groups, not whitelisted, etc). Return that info
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
