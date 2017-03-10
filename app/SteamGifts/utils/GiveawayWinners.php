<?php
class GiveawayWinners {
	public static function parse_winners($html) {
		$data = array();

		$rows = $html->find('.table__row-inner-wrap');

		forEach($rows as $row) {
			$nickname = $row->children(1)->children(0)->first_child();

			if ($nickname === null) {
				// Is anonymous
				continue;
			} else {
				$nickname = $nickname->innertext;
			}

			$marked_status = $row->last_child()->plaintext;
			$marked_status = strtolower(trim($marked_status));

			if ($marked_status === "received") {
				$marked_status = 1;
			} else {
				$marked_status = 2;
			}

			$array = array(
				'nickname' => $nickname,
				'marked_status' => $marked_status
			);

			array_push($data, $array);
		}

		return $data;
	}

	public static function store_winner($winner_array, $db, $giv_row_id, $winner_row_id = null) {
		if ($winner_row_id === null) {
			$stmt = $db->prepare("SELECT COUNT(*) AS count, id FROM UsersGeneral WHERE nickname=:nickname");
			$stmt->execute(array(
				':nickname' => $winner_array['nickname']
			));

			$user_row = $stmt->fetch(PDO::FETCH_ASSOC);
			unset($stmt);


			if ($user_row['count'] > 1) {
				$stmt = $db->prepare("SELECT id FROM UsersGeneral WHERE nickname=:nickname ORDER BY id");
				$stmt->execute(array(
					':nickname' => $winner_array['nickname']
				));

				$count = 0;

				while($duplicate_row = $stmt->fetch(PDO::FETCH_ASSOC)) {
					if ($count !== 0) {
						$stmt2 = $db->prepare("DELETE FROM UsersGeneral WHERE id=:id");
						$stmt2->execute(array(
							':id' => $duplicate_row['id']
						));

						unset($stmt2);

						$count++;
					} else {
						if ($user_row['id'] !== $duplicate_row['id']) {
							$user_row['id'] = $duplicate_row['id'];
						}

						$user_row['count'] = 1;
						$count++;
					}
				}

				unset($stmt);
				unset($count);
			}

			if ($user_row['count'] === 0) {
				$user_api_request = APIRequests::generic_get_request("http://api.sighery.com/SteamGifts/IUsers/GetUserInfo/?user=" . $winner_array['nickname']);

				if ($user_api_request->status_code !== 200) {
					$stmt = $db->prepare("INSERT INTO UsersGeneral (nickname) VALUES (:nickname)");
					$stmt->execute(array(
						':nickname' => $winner_array['nickname']
					));

					unset($stmt);

					$stmt = $db->query("SELECT LAST_INSERT_ID() AS id");
					$usersgeneral_row = $stmt->fetch(PDO::FETCH_ASSOC);
					$user_row['id'] = $usersgeneral_row['id'];

					unset($stmt);
				} else {
					$stmt = $db->prepare("SELECT id FROM UsersGeneral WHERE nickname=:nickname");
					$stmt->execute(array(
						':nickname' => $winner_array['nickname']
					));

					$user_row = $stmt->fetch(PDO::FETCH_ASSOC);
					unset($stmt);
				}
			}


			$stmt = $db->prepare("INSERT INTO GiveawaysWinners (giveawaysgeneral_id, usersgeneral_id, marked_status) VALUES (:giveawaysgeneral_id, :usersgeneral_id, :marked_status)");
			$stmt->execute(array(
				':giveawaysgeneral_id' => $giv_row_id,
				':usersgeneral_id' => $user_row['id'],
				':marked_status' => $winner_array['marked_status']
			));

			unset($stmt);

		} else {
			$stmt = $db->prepare("UPDATE GiveawaysWinners SET marked_status=:marked_status, last_checked=:last_checked WHERE id=:id");
			$stmt->execute(array(
				':marked_status' => $winner_array['marked_status'],
				':last_checked' => null,
				':id' => $winner_row_id
			));

			unset($stmt);
		}
	}
}
?>
