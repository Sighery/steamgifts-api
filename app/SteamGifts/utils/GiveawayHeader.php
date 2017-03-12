<?php
class BlacklistMsgException extends Exception {}

class GeneralMsgException extends Exception {
	private $error_dict;

	public function __construct($error_dict, $code) {
		$this->error_dict = $error_dict;

		parent::__construct($message = null, $code, $previous = null);
	}

	public function getDict() {
		return $this->error_dict;
	}
}

class GiveawayHeader {
	private $regions_translation = array(
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

	private $deleted_reasons_translation = array(
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

	private $game_types_translation = array(
		'app' => 0,
		'sub' => 1
	);

	private $giveaway_row;
	private $db;
	private $giv_id;

	public $data = array(
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
		'game_id' => null,
		'game_type' => null,
		'game_title' => null
	);

	public $bBL = false;

	public $giveaway_inserted_id;


	public function __construct($giv_id, $giveaway_row = null, $db) {
		$this->giv_id = $giv_id;
		$this->db = $db;
		$this->data['id'] = $giv_id;
		$this->giveaway_row = $giveaway_row;
	}

	private function retrieve_db_data() {
		$stmt = $this->db->prepare("SELECT COUNT(*) AS count, id, ended, deleted, deleted_reason, deleted_time, blacklisted, not_whitelisted, not_region, region, not_groups, not_wl_groups, usersgeneral_id, giv_type, level, copies, points, gamesinfo_id, not_steam_game, created_time, starting_time, ending_time, comments, entries, unavailable, UNIX_TIMESTAMP(last_checked) AS last_checked FROM GiveawaysGeneral WHERE giv_id=:giv_id");
		$stmt->execute(array(
			':giv_id' => $this->giv_id
		));

		return $stmt->fetch(PDO::FETCH_ASSOC);
	}

	public function html_is_error($html) {
		$title = $html->find('.page__heading__breadcrumbs', 0);

		if (!is_null($title) && empty($title->children()) && $title->innertext == "Error") {
			$response_rows = $html->find('.table__row-outer-wrap');

			if (count($response_rows) === 2) {
				$message = $response_rows[1]->children(0)->children(1)->plaintext;

				if (strpos($message, "blacklisted") !== false) {
					$this->bBL = true;

					throw new BlacklistMsgException();

				} elseif (strpos($message, "This giveaway is restricted to the following region:") !== false) {
					$initial_index = strpos($message, ":") + 2;

					$end_index = strpos($message, "(") - 1;

					if ($end_index === false) {
						$region = substr($message, $initial_index);
					} else {
						$end_index = $end_index - 1;
						$region = substr($message, $initial_index, $end_index - $initial_index);
					}

					if (array_key_exists($region, $this->regions_translation) === false) {
						$region = 99;
					} else {
						$region = $this->regions_translation[$region];
					}

					unset($initial_index);
					unset($end_index);

					if ($this->bBL) {
						throw new GeneralMsgException(array('errors' => array(
							'code' => 4,
							'description' => 'Blacklisted by the creator and not in the proper region',
							'id' => $this->giv_id,
							'region' => $region
						)), 4);
					} else {
						throw new GeneralMsgException(array('errors' => array(
							'code' => 5,
							'description' => 'Not in the proper region',
							'id' => $this->giv_id,
							'region' => $region
						)), 5);
					}

				} elseif (strpos($message, "whitelist, or the required Steam groups") !== false) {
					if ($this->bBL) {
						throw new GeneralMsgException(array('errors' => array(
							'code' => 6,
							'description' => 'Blacklisted by the creator and not in the whitelist nor required groups',
							'id' => $this->giv_id
						)), 6);
					} else {
						throw new GeneralMsgException(array('errors' => array(
							'code' => 7,
							'description' => 'Not in the whitelist nor required groups',
							'id' => $this->giv_id
						)), 7);
					}

				} elseif (strpos($message, "whitelist") !== false) {
					if ($this->bBL) {
						throw new GeneralMsgException(array('errors' => array(
							'code' => 8,
							'description' => 'Blacklisted by the creator and not in the whitelist',
							'id' => $this->giv_id
						)), 8);
					} else {
						throw new GeneralMsgException(array('errors' => array(
							'code' => 9,
							'description' => 'Not in the whitelist',
							'id' => $this->giv_id
						)), 9);
					}

				} elseif (strpos($message, "Steam groups") !== false) {
					if ($this->bBL) {
						throw new GeneralMsgException(array('errors' => array(
							'code' => 10,
							'description' => 'Blacklisted by the creator and not in the required groups',
							'id' => $this->giv_id
						)), 10);
					} else {
						throw new GeneralMsgException(array('errors' => array(
							'code' => 11,
							'description' => 'Not in the required groups',
							'id' => $this->giv_id
						)), 11);
					}
				}

			} elseif (count($response_rows) === 4) {
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

				if (array_key_exists($deleted_reason, $this->deleted_reasons_translation) === false) {
					$deleted_reason = 99;
				} else {
					$deleted_reason = $this->deleted_reasons_translation[$deleted_reason];
				}

				throw new GeneralMsgException(array('errors' => array(
					'code' => 3,
					'description' => 'Giveaway deleted',
					'id' => $this->giv_id,
					'user' => $user,
					'reason' => $deleted_reason,
					'deleted_time' => $deleted_time
				)), 3);
			}
		}
	}

	public function store_html_error($error_code, $error_dict) {
		if ($this->giveaway_row === null) {
			$stmt = $this->db->prepare("SELECT COUNT(*) AS count, id, ended, deleted, deleted_reason, deleted_time, blacklisted, not_whitelisted, not_region, region, not_groups, not_wl_groups, usersgeneral_id, giv_type, level, copies, points, gamesinfo_id, not_steam_game, created_time, starting_time, ending_time, comments, entries, winners, unavailable, UNIX_TIMESTAMP(last_checked) AS last_checked FROM GiveawaysGeneral WHERE giv_id=:giv_id");
			$stmt->execute(array(
				':giv_id' => $this->giv_id
			));

			$giveaway_row = $stmt->fetch(PDO::FETCH_ASSOC);
			unset($stmt);
		} else {
			$giveaway_row = $this->giveaway_row;
		}

		if ($giveaway_row['count'] === 0) {
			switch($error_code) {
				case 3:
					$stmt = $this->db->prepare("SELECT COUNT(*) AS count, id FROM UsersGeneral WHERE nickname=:nickname");
					$stmt->execute(array(
						':nickname' => $error_dict['errors']['user']
					));

					$usersgeneral_row = $stmt->fetch(PDO::FETCH_ASSOC);
					unset($stmt);

					if ($usersgeneral_row['count'] === 0 || $usersgeneral_row['count'] > 1) {
						$user_api_request = APIRequests::generic_get_request("http://api.sighery.com/SteamGifts/IUsers/GetUserInfo/?user=" . $error_dict['errors']['user']);

						if ($user_api_request->status_code !== 200) {
							if ($usersgeneral_row['count'] === 0) {
								$stmt->$this->db->prepare("INSERT INTO UsersGeneral (nickname) VALUES (:nickname)");
								$stmt->execute(array(
									':nickname' => $error_dict['errors']['user']
								));

								unset($stmt);

								$stmt = $this->db->query("SELECT LAST_INSERT_ID() AS id");
								$usersgeneral_row = $stmt->fetch(PDO::FETCH_ASSOC);
								unset($stmt);
							} else {
								// The API method was down for some reason, we'll
								//have to manually purge the DB
								$stmt = $this->db->prepare("SELECT id FROM UsersGeneral WHERE nickname=:nickname ORDER BY id");
								$stmt->execute(array(
									':nickname' => $error_dict['errors']['user']
								));

								$count = 0;

								while($duplicate_row = $stmt->fetch(PDO::FETCH_ASSOC)) {
									if ($count !== 0) {
										$stmt2 = $this->db->prepare("DELETE FROM UsersGeneral WHERE id=:id");
										$stmt2->execute(array(
											':id' => $duplicate_row['id']
										));
										unset($stmt2);

										$count++;
									} else {
										$usersgeneral_row = $duplicate_row;
										$count++;
										continue;
									}
								}

								unset($stmt);
								unset($count);
							}
						} else {
							$stmt = $this->db->prepare("SELECT id FROM UsersGeneral WHERE nickname=:nickname");
							$stmt->execute(array(
								':nickname' => $error_dict['errors']['user']
							));

							$usersgeneral_row = $stmt->fetch(PDO::FETCH_ASSOC);
						}
					}

					$stmt = $this->db->prepare("INSERT INTO GiveawaysGeneral (giv_id, deleted, deleted_reason, deleted_time, usersgeneral_id) VALUES (:giv_id, :deleted, :deleted_reason, :deleted_time, :usersgeneral_id)");
					$stmt->execute(array(
						':giv_id' => $this->giv_id,
						':deleted' => 1,
						':deleted_reason' => $error_dict['errors']['reason'],
						':deleted_time' => $error_dict['errors']['deleted_time'],
						':usersgeneral_id' => $usersgeneral_row['id']
					));

					unset($stmt);
					break;
				case 4:
					$stmt = $this->db->prepare("INSERT INTO GiveawaysGeneral (giv_id, blacklisted, not_region, region) VALUES (:giv_id, :blacklisted, :not_region, :region)");
					$stmt->execute(array(
						':giv_id' => $this->giv_id,
						':blacklisted' => 1,
						':not_region' => 1,
						':region' => $error_dict['errors']['region']
					));

					unset($stmt);
					break;
				case 5:
					$stmt = $this->db->prepare("INSERT INTO GiveawaysGeneral (giv_id, not_region, region) VALUES (:giv_id, :not_region, :region)");
					$stmt->execute(array(
						':giv_id' => $this->giv_id,
						':not_region' => 1,
						':region' => $error_dict['errors']['region']
					));

					unset($stmt);
					break;
				case 6:
					$stmt = $this->db->prepare("INSERT INTO GiveawaysGeneral (giv_id, blacklisted, not_wl_groups) VALUES (:giv_id, :blacklisted, :not_wl_groups)");
					$stmt->execute(array(
						':giv_id' => $this->giv_id,
						':blacklisted' => 1,
						':not_wl_groups' => 1
					));

					unset($stmt);
					break;
				case 7:
					$stmt = $this->db->prepare("INSERT INTO GiveawaysGeneral (giv_id, not_wl_groups) VALUES (:giv_id, :not_wl_groups)");
					$stmt->execute(array(
						':giv_id' => $this->giv_id,
						':not_wl_groups' => 1
					));

					unset($stmt);
					break;
				case 8:
					$stmt = $this->db->prepare("INSERT INTO GiveawaysGeneral (giv_id, blacklisted, not_whitelisted) VALUES (:giv_id, :blacklisted, :not_whitelisted)");
					$stmt->execute(array(
						':giv_id' => $this->giv_id,
						':blacklisted' => 1,
						':not_whitelisted' => 1
					));

					unset($stmt);
					break;
				case 9:
					$stmt = $this->db->prepare("INSERT INTO GiveawaysGeneral (giv_id, not_whitelisted) VALUES (:giv_id, :not_whitelisted)");
					$stmt->execute(array(
						':giv_id' => $this->giv_id,
						':not_whitelisted' => 1
					));

					unset($stmt);
					break;
				case 10:
					$stmt = $this->db->prepare("INSERT INTO GiveawaysGeneral (giv_id, blacklisted, not_groups) VALUES (:giv_id, :blacklisted, :not_groups)");
					$stmt->execute(array(
						':giv_id' => $this->giv_id,
						':blacklisted' => 1,
						':not_groups' => 1
					));

					unset($stmt);
					break;
				case 11:
				$stmt = $this->db->prepare("INSERT INTO GiveawaysGeneral (giv_id, not_groups) VALUES (:giv_id, :not_groups)");
				$stmt->execute(array(
					':giv_id' => $this->giv_id,
					':not_groups' => 1
				));

				unset($stmt);
				break;
			}

		} elseif ($giveaway_row['count'] === 1) {
			switch($error_code) {
				case 3:
					$stmt = $this->db->prepare("UPDATE GiveawaysGeneral SET deleted=:deleted, deleted_reason=:deleted_reason, deleted_time=:deleted_time, blacklisted=:blacklisted, not_whitelisted=:not_whitelisted, not_region=:not_region, not_groups=:not_groups, not_wl_groups=:not_wl_groups, last_checked=:last_checked, unavailable=:unavailable WHERE id=:id");
					$stmt->execute(array(
						':deleted' => 1,
						':deleted_reason' => $error_dict['errors']['reason'],
						':deleted_time' => $error_dict['errors']['deleted_time'],
						':blacklisted' => 0,
						':not_whitelisted' => 0,
						':not_region' => 0,
						':not_groups' => 0,
						':not_wl_groups' => 0,
						':last_checked' => null,
						':unavailable' => 0,
						':id' => $giveaway_row['id']
					));

					unset($stmt);
					break;
				case 4:
					$stmt = $this->db->prepare("UPDATE GiveawaysGeneral SET deleted=:deleted, blacklisted=:blacklisted, not_whitelisted=:not_whitelisted, not_region=:not_region, region=:region, not_groups=:not_groups, not_wl_groups=:not_wl_groups, last_checked=:last_checked, unavailable=:unavailable WHERE id=:id");
					$stmt->execute(array(
						':deleted' => 0,
						':blacklisted' => 1,
						':not_whitelisted' => 0,
						':not_region' => 1,
						':region' => $error_dic['errors']['region'],
						':not_groups' => 0,
						':not_wl_groups' => 0,
						':last_checked' => null,
						':unavailable' => 0,
						':id' => $giveaway_row['id']
					));

					unset($stmt);
					break;
				case 5:
					$stmt = $this->db->prepare("UPDATE GiveawaysGeneral SET deleted=:deleted, blacklisted=:blacklisted, not_whitelisted=:not_whitelisted, not_region=:not_region, region=:region, not_groups=:not_groups, not_wl_groups=:not_wl_groups, last_checked=:last_checked, unavailable=:unavailable WHERE id=:id");
					$stmt->execute(array(
						':deleted' => 0,
						':blacklisted' => 0,
						':not_whitelisted' => 0,
						':not_region' => 1,
						':region' => $error_dic['errors']['region'],
						':not_groups' => 0,
						':not_wl_groups' => 0,
						':last_checked' => null,
						':unavailable' => 0,
						':id' => $giveaway_row['id']
					));

					unset($stmt);
					break;
				case 6:
					$stmt = $this->db->prepare("UPDATE GiveawaysGeneral SET deleted=:deleted, blacklisted=:blacklisted, not_whitelisted=:not_whitelisted, not_region=:not_region, not_groups=:not_groups, not_wl_groups=:not_wl_groups, last_checked=:last_checked, unavailable=:unavailable WHERE id=:id");
					$stmt->execute(array(
						':deleted' => 0,
						':blacklisted' => 1,
						':not_whitelisted' => 0,
						':not_region' => 0,
						':not_groups' => 0,
						':not_wl_groups' => 1,
						':last_checked' => null,
						':unavailable' => 0,
						':id' => $giveaway_row['id']
					));

					unset($stmt);
					break;
				case 7:
					$stmt = $this->db->prepare("UPDATE GiveawaysGeneral SET deleted=:deleted, blacklisted=:blacklisted, not_whitelisted=:not_whitelisted, not_region=:not_region, not_groups=:not_groups, not_wl_groups=:not_wl_groups, last_checked=:last_checked, unavailable=:unavailable WHERE id=:id");
					$stmt->execute(array(
						':deleted' => 0,
						':blacklisted' => 0,
						':not_whitelisted' => 0,
						':not_region' => 0,
						':not_groups' => 0,
						':not_wl_groups' => 1,
						':last_checked' => null,
						':unavailable' => 0,
						':id' => $giveaway_row['id']
					));

					unset($stmt);
					break;
				case 8:
					$stmt = $this->db->prepare("UPDATE GiveawaysGeneral SET deleted=:deleted, blacklisted=:blacklisted, not_whitelisted=:not_whitelisted, not_region=:not_region, not_groups=:not_groups, not_wl_groups=:not_wl_groups, last_checked=:last_checked, unavailable=:unavailable WHERE id=:id");
					$stmt->execute(array(
						':deleted' => 0,
						':blacklisted' => 1,
						':not_whitelisted' => 1,
						':not_region' => 0,
						':not_groups' => 0,
						':not_wl_groups' => 0,
						':last_checked' => null,
						':unavailable' => 0,
						':id' => $giveaway_row['id']
					));

					unset($stmt);
					break;
				case 9:
					$stmt = $this->db->prepare("UPDATE GiveawaysGeneral SET deleted=:deleted, blacklisted=:blacklisted, not_whitelisted=:not_whitelisted, not_region=:not_region, not_groups=:not_groups, not_wl_groups=:not_wl_groups, last_checked=:last_checked, unavailable=:unavailable WHERE id=:id");
					$stmt->execute(array(
						':deleted' => 0,
						':blacklisted' => 0,
						':not_whitelisted' => 1,
						':not_region' => 0,
						':not_groups' => 0,
						':not_wl_groups' => 0,
						':last_checked' => null,
						':unavailable' => 0,
						':id' => $giveaway_row['id']
					));

					unset($stmt);
					break;
				case 10:
					$stmt = $this->db->prepare("UPDATE GiveawaysGeneral SET deleted=:deleted, blacklisted=:blacklisted, not_whitelisted=:not_whitelisted, not_region=:not_region, not_groups=:not_groups, not_wl_groups=:not_wl_groups, last_checked=:last_checked, unavailable=:unavailable WHERE id=:id");
					$stmt->execute(array(
						':deleted' => 0,
						':blacklisted' => 1,
						':not_whitelisted' => 0,
						':not_region' => 0,
						':not_groups' => 1,
						':not_wl_groups' => 0,
						':last_checked' => null,
						':unavailable' => 0,
						':id' => $giveaway_row['id']
					));

					unset($stmt);
					break;
				case 11:
					$stmt = $this->db->prepare("UPDATE GiveawaysGeneral SET deleted=:deleted, blacklisted=:blacklisted, not_whitelisted=:not_whitelisted, not_region=:not_region, not_groups=:not_groups, not_wl_groups=:not_wl_groups, last_checked=:last_checked, unavailable=:unavailable WHERE id=:id");
					$stmt->execute(array(
						':deleted' => 0,
						':blacklisted' => 0,
						':not_whitelisted' => 0,
						':not_region' => 0,
						':not_groups' => 1,
						':not_wl_groups' => 0,
						':last_checked' => null,
						':unavailable' => 0,
						':id' => $giveaway_row['id']
					));

					unset($stmt);
					break;
			}

		} elseif ($giveaway_row['count'] > 1) {
			// First purge the DB and then UPDATE
			$stmt = $this->db->prepare("SELECT id FROM GiveawaysGeneral WHERE giv_id=:giv_id ORDER BY id");
			$stmt->execute(array(
				':giv_id' => $this->giv_id
			));

			$count = 0;
			$lowest_id;

			while($duplicate_row = $stmt->fetch(PDO::FETCH_ASSOC)) {
				if ($count !== 0) {
					$stmt2 = $this->db->prepare("DELETE FROM GiveawaysGeneral WHERE id=:id");
					$stmt2->execute(array(
						':id' => $duplicate_row['id']
					));
					unset($stmt2);

					$count++;
				} else {
					$lowest_id = $duplicate_row['id'];
					$count++;
					continue;
				}
			}

			unset($stmt);
			unset($count);

			switch($error_code) {
				case 3:
					$stmt = $this->db->prepare("UPDATE GiveawaysGeneral SET deleted=:deleted, deleted_reason=:deleted_reason, deleted_time=:deleted_time, blacklisted=:blacklisted, not_whitelisted=:not_whitelisted, not_region=:not_region, not_groups=:not_groups, not_wl_groups=:not_wl_groups, last_checked=:last_checked, unavailable=:unavailable WHERE id=:id");
					$stmt->execute(array(
						':deleted' => 1,
						':deleted_reason' => $error_dict['errors']['reason'],
						':deleted_time' => $error_dict['errors']['deleted_time'],
						':blacklisted' => 0,
						':not_whitelisted' => 0,
						':not_region' => 0,
						':not_groups' => 0,
						':not_wl_groups' => 0,
						':last_checked' => null,
						':unavailable' => 0,
						':id' => $lowest_id
					));

					unset($stmt);
					break;
				case 4:
					$stmt = $this->db->prepare("UPDATE GiveawaysGeneral SET deleted=:deleted, blacklisted=:blacklisted, not_whitelisted=:not_whitelisted, not_region=:not_region, region=:region, not_groups=:not_groups, not_wl_groups=:not_wl_groups, last_checked=:last_checked, unavailable=:unavailable WHERE id=:id");
					$stmt->execute(array(
						':deleted' => 0,
						':blacklisted' => 1,
						':not_whitelisted' => 0,
						':not_region' => 1,
						':region' => $error_dic['errors']['region'],
						':not_groups' => 0,
						':not_wl_groups' => 0,
						':last_checked' => null,
						':unavailable' => 0,
						':id' => $lowest_id
					));

					unset($stmt);
					break;
				case 5:
					$stmt = $this->db->prepare("UPDATE GiveawaysGeneral SET deleted=:deleted, blacklisted=:blacklisted, not_whitelisted=:not_whitelisted, not_region=:not_region, region=:region, not_groups=:not_groups, not_wl_groups=:not_wl_groups, last_checked=:last_checked, unavailable=:unavailable WHERE id=:id");
					$stmt->execute(array(
						':deleted' => 0,
						':blacklisted' => 0,
						':not_whitelisted' => 0,
						':not_region' => 1,
						':region' => $error_dic['errors']['region'],
						':not_groups' => 0,
						':not_wl_groups' => 0,
						':last_checked' => null,
						':unavailable' => 0,
						':id' => $lowest_id
					));

					unset($stmt);
					break;
				case 6:
					$stmt = $this->db->prepare("UPDATE GiveawaysGeneral SET deleted=:deleted, blacklisted=:blacklisted, not_whitelisted=:not_whitelisted, not_region=:not_region, not_groups=:not_groups, not_wl_groups=:not_wl_groups, last_checked=:last_checked, unavailable=:unavailable WHERE id=:id");
					$stmt->execute(array(
						':deleted' => 0,
						':blacklisted' => 1,
						':not_whitelisted' => 0,
						':not_region' => 0,
						':not_groups' => 0,
						':not_wl_groups' => 1,
						':last_checked' => null,
						':unavailable' => 0,
						':id' => $lowest_id
					));

					unset($stmt);
					break;
				case 7:
					$stmt = $this->db->prepare("UPDATE GiveawaysGeneral SET deleted=:deleted, blacklisted=:blacklisted, not_whitelisted=:not_whitelisted, not_region=:not_region, not_groups=:not_groups, not_wl_groups=:not_wl_groups, last_checked=:last_checked, unavailable=:unavailable WHERE id=:id");
					$stmt->execute(array(
						':deleted' => 0,
						':blacklisted' => 0,
						':not_whitelisted' => 0,
						':not_region' => 0,
						':not_groups' => 0,
						':not_wl_groups' => 1,
						':last_checked' => null,
						':unavailable' => 0,
						':id' => $lowest_id
					));

					unset($stmt);
					break;
				case 8:
					$stmt = $this->db->prepare("UPDATE GiveawaysGeneral SET deleted=:deleted, blacklisted=:blacklisted, not_whitelisted=:not_whitelisted, not_region=:not_region, not_groups=:not_groups, not_wl_groups=:not_wl_groups, last_checked=:last_checked, unavailable=:unavailable WHERE id=:id");
					$stmt->execute(array(
						':deleted' => 0,
						':blacklisted' => 1,
						':not_whitelisted' => 1,
						':not_region' => 0,
						':not_groups' => 0,
						':not_wl_groups' => 0,
						':last_checked' => null,
						':unavailable' => 0,
						':id' => $lowest_id
					));

					unset($stmt);
					break;
				case 9:
					$stmt = $this->db->prepare("UPDATE GiveawaysGeneral SET deleted=:deleted, blacklisted=:blacklisted, not_whitelisted=:not_whitelisted, not_region=:not_region, not_groups=:not_groups, not_wl_groups=:not_wl_groups, last_checked=:last_checked, unavailable=:unavailable WHERE id=:id");
					$stmt->execute(array(
						':deleted' => 0,
						':blacklisted' => 0,
						':not_whitelisted' => 1,
						':not_region' => 0,
						':not_groups' => 0,
						':not_wl_groups' => 0,
						':last_checked' => null,
						':unavailable' => 0,
						':id' => $lowest_id
					));

					unset($stmt);
					break;
				case 10:
					$stmt = $this->db->prepare("UPDATE GiveawaysGeneral SET deleted=:deleted, blacklisted=:blacklisted, not_whitelisted=:not_whitelisted, not_region=:not_region, not_groups=:not_groups, not_wl_groups=:not_wl_groups, last_checked=:last_checked, unavailable=:unavailable WHERE id=:id");
					$stmt->execute(array(
						':deleted' => 0,
						':blacklisted' => 1,
						':not_whitelisted' => 0,
						':not_region' => 0,
						':not_groups' => 1,
						':not_wl_groups' => 0,
						':last_checked' => null,
						':unavailable' => 0,
						':id' => $lowest_id
					));

					unset($stmt);
					break;
				case 11:
					$stmt = $this->db->prepare("UPDATE GiveawaysGeneral SET deleted=:deleted, blacklisted=:blacklisted, not_whitelisted=:not_whitelisted, not_region=:not_region, not_groups=:not_groups, not_wl_groups=:not_wl_groups, last_checked=:last_checked, unavailable=:unavailable WHERE id=:id");
					$stmt->execute(array(
						':deleted' => 0,
						':blacklisted' => 0,
						':not_whitelisted' => 0,
						':not_region' => 0,
						':not_groups' => 1,
						':not_wl_groups' => 0,
						':last_checked' => null,
						':unavailable' => 0,
						':id' => $lowest_id
					));

					unset($stmt);
					break;
			}
		}
	}

	public function db_is_error() {
		$giveaway_row = $this->giveaway_row;

		if ($giveaway_row['deleted'] === 1) {
			$stmt = $this->db->prepare("SELECT nickname FROM UsersGeneral WHERE id=:id");
			$stmt->execute(array(
				':id' => $giveaway_row['usersgeneral_id']
			));

			$usersgeneral_row = $stmt->fetch(PDO::FETCH_ASSOC);
			unset($stmt);

			throw new GeneralMsgException(array('errors' => array(
				'code' => 3,
				'description' => 'Giveaway deleted',
				'id' => $this->giv_id,
				'user' => $usersgeneral_row['nickname'],
				'reason' => $giveaway_row['deleted_reason'],
				'deleted_time' => $giveaway_row['deleted_time']
			)), 3);

		} elseif ($giveaway_row['not_region'] === 1 && $giveaway_row['blacklisted'] === 1) {
			throw new GeneralMsgException(array('errors' => array(
				'code' => 4,
				'description' => 'Blacklisted by the creator and not in the proper region',
				'id' => $this->giv_id,
				'region' => $giveaway_row['region']
			)), 4);

		} elseif ($giveaway_row['not_region'] === 1) {
			throw new GeneralMsgException(array('errors' => array(
				'code' => 5,
				'description' => 'Not in the proper region',
				'id' => $this->giv_id,
				'region' => $giveaway_row['region']
			)), 5);

		} elseif ($giveaway_row['not_wl_groups'] === 1 && $giveaway_row['blacklisted'] === 1) {
			throw new GeneralMsgException(array('errors' => array(
				'code' => 6,
				'description' => 'Blacklisted by the creator and not in the whitelist nor required groups',
				'id' => $this->giv_id
			)), 6);

		} elseif ($giveaway_row['not_wl_groups'] === 1) {
			throw new GeneralMsgException(array('errors' => array(
				'code' => 7,
				'description' => 'Not in the whitelist nor required groups',
				'id' => $this->giv_id
			)), 7);

		} elseif ($giveaway_row['not_whitelisted'] === 1 && $giveaway_row['blacklisted'] === 1) {
			throw new GeneralMsgException(array('errors' => array(
				'code' => 8,
				'description' => 'Blacklisted by the creator and not in the whitelist',
				'id' => $this->giv_id
			)), 8);

		} elseif ($giveaway_row['not_whitelisted'] === 1) {
			throw new GeneralMsgException(array('errors' => array(
				'code' => 9,
				'description' => 'Not in the whitelist',
				'id' => $giv_id
			)), 9);

		} elseif ($giveaway_row['not_groups'] === 1 && $giveaway_row['blacklisted'] === 1) {
			throw new GeneralMsgException(array('errors' => array(
				'code' => 10,
				'description' => 'Blacklisted by the creator and not in the required groups',
				'id' => $this->giv_id
			)), 10);

		} elseif ($giveaway_row['not_groups'] === 1) {
			throw new GeneralMsgException(array('errors' => array(
				'code' => 11,
				'description' => 'Not in the required groups',
				'id' => $this->giv_id
			)), 11);
		}
	}

	public function parse_giveaway($html) {
		$store_link = $html->find("a[href*='store.steampowered.com'], a[class*='global__image-outer-wrap--game-large']", 0);

		if (!is_null($store_link)) {
			$type_id_matches;
			preg_match("/http:\/\/store\.steampowered\.com\/(app|sub)\/([0-9]+)/", $store_link->href, $type_id_matches);

			if (!empty($type_id_matches)) {
				$this->data['game_type'] = $this->game_types_translation[$type_id_matches[1]];
				$this->data['game_id'] = intval($type_id_matches[2]);
			}
		}

		$game_title = $html->find("div[class*='featured__heading__medium']", 0)->innertext;
		$this->data['game_title'] = $game_title;

		$headings_small = $html->find('.featured__heading__small');
		if (!empty($headings_small) && count($headings_small) === 2) {
			$copies;
			preg_match("/(\d+)/", str_replace(",", "", $headings_small[0]->innertext), $copies);
			$this->data['copies'] = intval($copies[1]);

			$points;
			preg_match("/(\d+)/", $headings_small[1]->innertext, $points);
			$this->data['points'] = intval($points[1]);

			unset($copies);
			unset($points);

		} elseif (!empty($headings_small) && count($headings_small) === 1) {
			$this->data['copies'] = 1;

			$points;
			preg_match("/(\d+)/", $headings_small[0]->innertext, $points);
			$this->data['points'] = intval($points[1]);

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
					$this->data['starting_time'] = $start_end_time;
				} else {
					$this->data['ending_time'] = $start_end_time;
					if (time() >= $this->data['ending_time']) {
						$this->data['ended'] = true;
					}
				}

				unset($start_end_time);

			} elseif (strpos($column_class, 'featured__column--width-fill') !== false) {
				$created_time = $column->find('span', 0);
				$this->data['created_time'] = intval($created_time->getAttribute('data-timestamp'));

				$user = $column->find("a[href*='/user/']", 0);
				$this->data['user'] = $user->innertext;

				unset($created_time);
				unset($user);

			} elseif (strpos($column_class, 'featured__column--invite-only') !== false) {
				$bTypes['private'] = true;

			} elseif (strpos($column_class, 'featured__column--region-restricted') !== false) {
				$bTypes['region'] = true;

				if (array_key_exists(trim($column->plaintext), $regions_translation) !== false) {
					$this->data['region'] = 99;
				} else {
					$this->data['region'] = $regions_translation[trim($column->plaintext)];
				}

			} elseif (strpos($column_class, 'featured__column--whitelist') !== false) {
				$bTypes['whitelist'] = true;

			} elseif (strpos($column_class, 'featured__column--group') !== false) {
				$bTypes['group'] = true;

			} elseif (strpos($column_class, 'featured__column--contributor-level') !== false) {
				$level;
				preg_match("/(\d+)/", $column->innertext, $level);

				$this->data['level'] = intval($level[1]);

				unset($level);
			}
		}


		// Generate the giv_type int
		if ($bTypes['region'] && $bTypes['whitelist'] && $btypes['group']) {
			$this->data['type'] = 9;
		} elseif ($bTypes['region'] && $bTypes['whitelist']) {
			$this->data['type'] = 8;
		} elseif ($bTypes['region'] && $bTypes['group']) {
			$this->data['type'] = 7;
		} elseif ($bTypes['region'] && $bTypes['private']) {
			$this->data['type'] = 6;
		} elseif ($bTypes['whitelist'] && $bTypes['group']) {
			$this->data['type'] = 5;
		} elseif ($bTypes['group']) {
			$this->data['type'] = 4;
		} elseif ($bTypes['whitelist']) {
			$this->data['type'] = 3;
		} elseif ($bTypes['region']) {
			$this->data['type'] = 2;
		} elseif ($bTypes['private']) {
			$this->data['type'] = 1;
		} else {
			$this->data['type'] = 0;
		}

		$sidebar_numbers = $html->find('.sidebar__navigation__item');
		forEach($sidebar_numbers as $row) {
			switch ($row->find('.sidebar__navigation__item__name', 0)->innertext) {
				case 'Comments':
					$this->data['comments'] = intval(str_replace(",", "", $row->find('.sidebar__navigation__item__count', 0)->innertext));
					break;
				case 'Entries':
					$this->data['entries'] = intval(str_replace(",", "", $row->find('.sidebar__navigation__item__count', 0)->innertext));
					break;
				case 'Winners':
					$this->data['winners'] = intval(str_replace(",", "", $row->find('.sidebar__navigation__item__count', 0)->innertext));
					break;
			}
		}
		unset($sidebar_numbers);
	}

	public function store_giveaway_data() {
		if ($this->giveaway_row === null) {
			$stmt = $this->db->prepare("SELECT COUNT(*) AS count, id FROM GiveawaysGeneral WHERE giv_id=:giv_id");
			$stmt-execute(array(
				':giv_id' => $this->giv_id
			));

			$giveaway_row = $stmt->fetch(PDO::FETCH_ASSOC);
			unset($stmt);
		} else {
			$giveaway_row = $this->giveaway_row;
		}

		//Get user id if it exists
		$stmt = $this->db->prepare("SELECT COUNT(*) AS count, id FROM UsersGeneral WHERE nickname=:nickname");
		$stmt->execute(array(
			':nickname' => $this->data['user']
		));

		$usersgeneral_row = $stmt->fetch(PDO::FETCH_ASSOC);
		unset($stmt);

		if ($usersgeneral_row['count'] === 0 || $usersgeneral_row['count'] > 1) {
			$user_api_request = APIRequests::generic_get_request("http://api.sighery.com/SteamGifts/IUsers/GetUserInfo/?user=" . $this->data['user']);

			if ($user_api_request->status_code !== 200) {
				if ($usersgeneral_row['count'] === 0) {
					$stmt = $this->db->prepare("INSERT INTO UsersGeneral (nickname) VALUES (:nickname)");
					$stmt->execute(array(
						':nickname' => $this->data['user']
					));

					unset($stmt);

					$stmt = $this->db->query("SELECT LAST_INSERT_ID() AS id");
					$usersgeneral_row = $stmt->fetch(PDO::FETCH_ASSOC);

					unset($stmt);
				} elseif ($usersgeneral_row['count'] > 1) {
					$stmt = $this->db->prepare("SELECT id FROM UsersGeneral WHERE nickname=:nickname ORDER BY id");
					$stmt->execute(array(
						':nickname' => $this->data['user']
					));

					$count = 0;

					while($duplicate_row = $stmt->fetch(PDO::FETCH_ASSOC)) {
						if ($count !== 0) {
							$stmt2 = $this->db->prepare("DELETE FROM UsersGeneral WHERE id=:id");
							$stmt2->execute(array(
								':id' => $duplicate_row['id']
							));
							unset($stmt2);

							$count++;
						} else {
							$usersgeneral_row = $duplicate_row;
							$count++;
							continue;
						}
					}

					unset($count);
					unset($stmt);
				}
			} else {
				$stmt = $this->db->prepare("SELECT id FROM UsersGeneral WHERE nickname=:nickname ORDER BY id");
				$stmt->execute(array(
					':nickname' => $this->data['user']
				));

				$usersgeneral_row = $stmt->fetch(PDO::FETCH_ASSOC);
				unset($stmt);
			}
		}


		// Get gamesinfo id if it exists
		if ($this->data['game_id'] !== null && $this->data['game_type'] !== null) {
			$stmt = $this->db->prepare("SELECT COUNT(*) AS count, id, game_title FROM GamesInfo WHERE game_id=:game_id AND game_type=:game_type");
			$stmt->execute(array(
				':game_id' => $this->data['game_id'],
				':game_type' => $this->data['game_type']
			));

			$gamesinfo_row = $stmt->fetch(PDO::FETCH_ASSOC);
			unset($stmt);

			if ($gamesinfo_row['count'] === 0) {
				if (strlen($this->data['game_title']) > 40) {
					$gamesinfo_api_request = APIRequests::generic_get_request("http://api.sighery.com/SteamGifts/Interactions/GetGameTitle/?type=" . $this->data['game_type'] . "&id=" . $this->data['game_id']);

					if ($gamesinfo_api_request->status_code !== 200) {
						throw new GeneralMsgException(array('errors' => array(
							'code' => 1,
							'description' => 'The request to Steam was unsuccessful'
						)), 1);
					}
				} else {
					$stmt = $this->db->prepare("INSERT INTO GamesInfo (game_type, game_id, game_title) VALUES (:game_type, :game_id, :game_title)");
					$stmt->execute(array(
						':game_type' => $this->data['game_type'],
						':game_id' => $this->data['game_id'],
						':game_title' => $this->data['game_title']
					));

					unset($stmt);

					$stmt = $this->db->query("SELECT LAST_INSERT_ID() AS id");
					$gamesinfo_row = $stmt->fetch(PDO::FETCH_ASSOC);

					unset($stmt);
				}
			} elseif ($gamesinfo_row['count'] === 1) {
				if ($gamesinfo_row['game_title'] === null) {
					$gamesinfo_api_request = APIRequests::generic_get_request("http://api.sighery.com/SteamGifts/Interactions/GetGameTitle/?type=" . $this->data['game_type'] . "&id=" . $this->data['game_id']);

					if ($gamesinfo_api_request->status_code !== 200) {
						throw new GeneralMsgException(array('errors' => array(
							'code' => 1,
							'description' => 'The request to Steam was unsuccessful'
						)), 1);
					} elseif (strlen($this->data['game_title']) > 40) {
						$json_file = json_decode($gamesinfo_api_request, true);
						$this->data['game_title'] = $json_file['game_title'];
					}
				} elseif (strlen($this->data['game_title']) > 40) {
					$this->data['game_title'] = $gamesinfo_row['game_title'];
				}
			} elseif ($gamesinfo_row['count'] > 1) {
				$stmt = $this->db->prepare("SELECT id FROM GamesInfo WHERE game_id=:game_id AND game_type=:game_type ORDER BY id");
				$stmt->execute(array(
					':game_id' => $this->data['game_id'],
					':game_type' => $this->data['game_type']
				));

				$count = 0;

				while ($duplicate_row = $stmt->fetch(PDO::FETCH_ASSOC)) {
					if ($count !== 0) {
						$stmt2 = $this->db->prepare("DELETE FROM GamesInfo WHERE id=:id");
						$stmt2->execute(array(
							':id' => $duplicate_row['id']
						));
						unset($stmt2);

						$count++;
					} else {
						$gamesinfo_row = $duplicate_row;
						$count++;
						continue;
					}
				}

				unset($count);
				unset($stmt);

				if ($gamesinfo_row['game_title'] === null) {
					$gamesinfo_api_request = APIRequests::generic_get_request("http://api.sighery.com/SteamGifts/Interactions/GetGameTitle/?type=" . $this->data['game_type'] . "&id=" . $this->data['game_id']);

					if ($gamesinfo_api_request->status_code !== 200) {
						throw new GeneralMsgException(array('errors' => array(
							'code' => 1,
							'description' => 'The request to Steam was unsuccessful'
						)), 1);
					} elseif (strlen($this->data['game_title']) > 40) {
						$json_file = json_decode($gamesinfo_api_request, true);
						$this->data['game_title'] = $json_file['game_title'];
					}
				} elseif (strlen($this->data['game_title']) > 40) {
					$this->data['game_title'] = $gamesinfo_row['game_title'];
				}
			}
		}


		if ($giveaway_row['count'] === 0) {
			$stmt = $this->db->prepare("INSERT INTO GiveawaysGeneral (ended, region, giv_id, usersgeneral_id, giv_type, level, copies, points, gamesinfo_id, not_steam_game, created_time, starting_time, ending_time, comments, entries, winners) VALUES (:ended, :region, :giv_id, :usersgeneral_id, :giv_type, :level, :copies, :points, :gamesinfo_id, :not_steam_game, :created_time, :starting_time, :ending_time, :comments, :entries, :winners)");

			if ($this->data['game_id'] !== null && $this->data['game_type'] !== null) {
				$stmt->execute(array(
					':ended' => (int)$this->data['ended'],
					':region' => $this->data['region'],
					':giv_id' => $this->giv_id,
					':usersgeneral_id' => $usersgeneral_row['id'],
					':giv_type' => $this->data['type'],
					':level' => $this->data['level'],
					':copies' => $this->data['copies'],
					':points' => $this->data['points'],
					':gamesinfo_id' => $gamesinfo_row['id'],
					':not_steam_game' => null,
					':created_time' => $this->data['created_time'],
					':starting_time' => $this->data['starting_time'],
					':ending_time' => $this->data['ending_time'],
					':comments' => $this->data['comments'],
					':entries' => $this->data['entries'],
					':winners' => $this->data['winners']
				));
			} else {
				$stmt->execute(array(
					':ended' => (int)$this->data['ended'],
					':region' => $this->data['region'],
					':giv_id' => $this->giv_id,
					':usersgeneral_id' => $usersgeneral_row['id'],
					':giv_type' => $this->data['type'],
					':level' => $this->data['level'],
					':copies' => $this->data['copies'],
					':points' => $this->data['points'],
					':gamesinfo_id' => null,
					':not_steam_game' => $gamesinfo_row['game_title'],
					':created_time' => $this->data['created_time'],
					':starting_time' => $this->data['starting_time'],
					':ending_time' => $this->data['ending_time'],
					':comments' => $this->data['comments'],
					':entries' => $this->data['entries'],
					':winners' => $this->data['winners']
				));
			}

			unset($stmt);

			$stmt = $this->db->query("SELECT LAST_INSERT_ID() AS id");
			$stmt = $stmt->fetch(PDO::FETCH_ASSOC);

			$this->giveaway_inserted_id = $stmt['id'];
			unset($stmt);

			return $this->giveaway_inserted_id;

		} elseif ($giveaway_row['count'] === 1) {
			$stmt = $this->db->prepare("UPDATE GiveawaysGeneral SET ended=:ended, region=:region, giv_type=:giv_type, level=:level, copies=:copies, points=:points, gamesinfo_id=:gamesinfo_id, not_steam_game=:not_steam_game, created_time=:created_time, starting_time=:starting_time, ending_time=:ending_time, comments=:comments, entries=:entries, winners=:winners, unavailable=:unavailable, last_checked=:last_checked WHERE id=:id");

			if ($this->data['game_id'] !== null && $this->data['game_type'] !== null) {
				$stmt->execute(array(
					':ended' => (int)$this->data['ended'],
					':region' => $this->data['region'],
					':giv_type' => $this->data['type'],
					':level' => $this->data['level'],
					':copies' => $this->data['copies'],
					':points' => $this->data['points'],
					':gamesinfo_id' => $gamesinfo_row['id'],
					':not_steam_game' => null,
					':created_time' => $this->data['created_time'],
					':starting_time' => $this->data['starting_time'],
					':ending_time' => $this->data['ending_time'],
					':comments' => $this->data['comments'],
					':entries' => $this->data['entries'],
					':winners' => $this->data['winners'],
					':unavailable' => 0,
					':last_checked' => null,
					':id' => $giveaway_row['id']
				));
			} else {
				$stmt->execute(array(
					':ended' => (int)$this->data['ended'],
					':region' => $this->data['region'],
					':giv_type' => $this->data['type'],
					':level' => $this->data['level'],
					':copies' => $this->data['copies'],
					':points' => $this->data['points'],
					':gamesinfo_id' => null,
					':not_steam_game' => $this->data['game_title'],
					':created_time' => $this->data['created_time'],
					':starting_time' => $this->data['starting_time'],
					':ending_time' => $this->data['ending_time'],
					':comments' => $this->data['comments'],
					':entries' => $this->data['entries'],
					':winners' => $this->data['winners'],
					':unavailable' => 0,
					':last_checked' => null,
					':id' => $giveaway_row['id']
				));
			}

			unset($stmt);

			$this->giveaway_inserted_id = $giveaway_row['id'];
			return $this->giveaway_inserted_id;

		} elseif ($giveaway_row['count'] > 1) {
			// Purge the DB before updating
			$stmt = $this->db->prepare("SELECT id FROM GiveawaysGeneral WHERE giv_id=:giv_id ORDER BY id");
			$stmt->execute(array(
				':giv_id' => $this->giv_id
			));

			$count = 0;

			while ($duplicate_row = $stmt->fetch(PDO::FETCH_ASSOC)) {
				if ($count !== 0) {
					$stmt2 = $this->db->prepare("DELETE FROM GiveawaysGeneral WHERE id=:id");
					$stmt2->execute(array(
						':id' => $duplicate_row['id']
					));
					unset($stmt2);

					$count++;
				} else {
					$giveaway_row = $duplicate_row;
					$count++;
					continue;
				}
			}

			unset($stmt);
			unset($count);

			$this->giveaway_inserted_id = $giveaway_row['id'];
			return $this->giveaway_inserted_id;
		}
	}
}
?>
