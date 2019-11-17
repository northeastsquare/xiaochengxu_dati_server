<?php
//泉州大白网络科技
defined("IN_IA") or exit("Access Denied");
require_once IA_ROOT . "/addons/hc_answer/functions.php";
require_once "wxBizDataCrypt.php";
class Hc_answerModuleWxapp extends WeModuleWxapp
{
	public function doPageGetopenid()
	{
		global $_GPC, $_W;
		$code = $_GPC["code"];
		$account = pdo_get("account_wxapp", array("uniacid" => $_W["uniacid"]));
		$url = "https://api.weixin.qq.com/sns/jscode2session?appid=" . $account["key"] . "&secret=" . $account["secret"] . "&js_code=" . $code . "&grant_type=authorization_code";
		$result = ihttp_post($url);
		$result = json_decode($result["content"], true);
		return $this->result(0, "获取成功", $result);
	}
	public function doPageGetuserinfo()
	{
		global $_GPC, $_W;
		$sessionKey = $_GPC["session_key"];
		$encryptedData = $_GPC["encryptedData"];
		$iv = $_GPC["iv"];
		$appid = pdo_getcolumn("account_wxapp", array("uniacid" => $_W["uniacid"]), array("key"));
		$pc = new WXBizDataCrypt($appid, $sessionKey);
		$errCode = $pc->decryptData($encryptedData, $iv, $data);
		$return = json_decode($data, true);
		$openid = $return["openId"];
		if (empty($openid) || $openid == "undefined") {
			return $this->result(1, "参数错误，缺少OPENID");
		}
		$ishave = pdo_get("hc_user", array("openid" => $openid, "weid" => 1000));
		if (empty($ishave)) {
			$arr["createtime"] = time();
			$arr["weid"] = 1000;
			$arr["openid"] = $openid;
			$arr["nickname"] = $return["nickName"];
			$arr["gender"] = $return["gender"];
			$arr["city"] = $return["city"];
			$arr["province"] = $return["province"];
			$arr["country"] = $return["country"];
			$arr["avatar"] = $return["avatarUrl"];
			$arr["unionid"] = $return["unionId"];
			$arr["sessionkey"] = $sessionKey;
			$result = pdo_insert("hc_user", $arr);
			if (!empty($result)) {
				$uid = pdo_insertid();
				$season = $this->currseason();
				$max_expe = $this->grade(1);
				$basic = $this->basic();
				pdo_insert("hc_user_info", array("uid" => $uid, "sid" => $season, "weid" => 1000, "gold" => $basic["firstgift"], "maxexpe" => $max_expe));
			}
		} else {
			pdo_update("hc_user", array("sessionkey" => $_GPC["session_key"]), array("uid" => $ishave["uid"], "weid" => 1000));
			$uid = $ishave["uid"];
		}
		return $this->result(0, "操作成功", $uid);
	}
	public function doPageIndexmodule()
	{
		global $_GPC, $_W;
		$weid = 1000;
		$index = json_decode(pdo_getcolumn("hc_setting", array("weid" => $weid), array("index")), true);
		foreach ($index as $key => $val) {
			if ($key > 2 && $key < 6) {
				$list["right"][$key] = $val;
				if (!empty($val["img"])) {
					$list["right"][$key]["img"] = $_W["attachurl"] . $val["img"];
				} else {
					unset($list["right"][$key]);
				}
			} else {
				if ($key < 3) {
					$list["left"][$key] = $val;
					if (!empty($val["img"])) {
						$list["left"][$key]["img"] = $_W["attachurl"] . $val["img"];
					} else {
						unset($list["left"][$key]);
					}
				} else {
					if ($key == 6 || $key == 7 || $key == 8) {
						$list["left"][$key] = $val;
						if (!empty($val["img"])) {
							$list["left"][$key]["img"] = $_W["attachurl"] . $val["img"];
						} else {
							unset($list["left"][$key]);
						}
					} else {
						if ($key == 9 || $key == 10 || $key == 11) {
							$list["right"][$key] = $val;
							if (!empty($val["img"])) {
								$list["right"][$key]["img"] = $_W["attachurl"] . $val["img"];
							} else {
								unset($list["right"][$key]);
							}
						}
					}
				}
			}
		}
		foreach ($list["left"] as $key => $val) {
			if (is_numeric($val["link"])) {
				$ad = pdo_get("hc_ad", array("weid" => 1000, "id" => $val["link"]));
				$list["left"][$key]["appid"] = $ad["appid"];
				$list["left"][$key]["path"] = $ad["path"];
			}
		}
		foreach ($list["right"] as $key => $val) {
			if (is_numeric($val["link"])) {
				$ad = pdo_get("hc_ad", array("weid" => 1000, "id" => $val["link"]));
				$list["right"][$key]["appid"] = $ad["appid"];
				$list["right"][$key]["path"] = $ad["path"];
			}
		}
		return $this->result(0, "操作成功", $list);
	}
	public function doPageGetlevel()
	{
		global $_GPC, $_W;
		$sid = $this->currseason();
		$uid = $_GPC["user_id"];
		$weid = 1000;
		$info1 = pdo_get("hc_user", array("uid" => $uid, "weid" => $weid));
		$info2 = pdo_get("hc_user_info", array("uid" => $uid, "weid" => $weid));
		$info = array_merge($info1, $info2);
		$sign = pdo_fetch("SELECT `date` FROM " . tablename("hc_user_sign") . " WHERE weid=:weid AND uid=:uid AND sid=:sid ORDER BY signtime desc LIMIT 1", array(":weid" => $weid, ":uid" => $uid, ":sid" => $sid));
		if ($sign["date"] == strtotime(date("Ymd"))) {
			$info["sign"] = 1;
		} else {
			$info["sign"] = 0;
		}
		$last = $this->lastseason();
		$sover = pdo_getcolumn("hc_user_history", array("sid" => $last, "weid" => $weid, "uid" => $uid), array("status"));
		if (!empty($sover) && $sover == 1) {
			$info["sover"] = 0;
		} else {
			$info["sover"] = 1;
		}
		return $this->result(0, "操作成功", $info);
	}
	public function doPageCurrentsj()
	{
		global $_GPC, $_W;
		$info = pdo_get("hc_season", array("status" => 1, "weid" => 1000));
		$info["starttime"] = date("Y年m月d日", $info["starttime"]);
		$info["endtime"] = date("Y年m月d日", $info["endtime"]);
		return $this->result(0, "操作成功", $info);
	}
	function currseason()
	{
		global $_W;
		return pdo_getcolumn("hc_season", array("status" => 1, "weid" => 1000), array("id"));
	}
	function maxdan()
	{
		global $_W;
		return pdo_getcolumn("hc_dan", array("weid" => 1000), array("max(dan_id)"));
	}
	function lastseason()
	{
		global $_W;
		$no = pdo_getcolumn("hc_season", array("status" => 1, "weid" => 1000), array("no"));
		return pdo_fetchcolumn("SELECT id FROM " . tablename("hc_season") . " WHERE no<" . $no . " AND weid=1000 ORDER BY no DESC");
	}
	function grade($no)
	{
		global $_W;
		return pdo_getcolumn("hc_grade", array("weid" => 1000, "levelno" => $no), array("levelexp"));
	}
	function userinfo($uid)
	{
		return pdo_get("hc_user", array("uid" => $uid), array("uid", "nickname", "avatar", "border", "city"));
	}
	function basic()
	{
		global $_W;
		return json_decode(pdo_getcolumn("hc_setting", array("weid" => 1000), array("basic")), true);
	}
	function matchtime()
	{
		global $_W;
		return json_decode(pdo_getcolumn("hc_setting", array("weid" => 1000), array("ques")), true);
	}
	function active()
	{
		global $_W;
		return json_decode(pdo_getcolumn("hc_setting", array("weid" => 1000), array("active")), true);
	}
	public function doPageMatching()
	{
		global $_GPC, $_W;
		$user_id = $_GPC["user_id"];
		$dan = $_GPC["dan"];
		$weid = 1000;
		$matchtime = $this->matchtime();
		$expire = time() + $matchtime["match"];
		$needgold = pdo_getcolumn("hc_dan", array("weid" => $weid, "dan_id" => $dan), array("use_gold"));
		$usergold = pdo_getcolumn("hc_user_info", array("weid" => $weid, "uid" => $user_id), array("gold"));
		if ($needgold > $usergold) {
			return $this->result(1, "金币不足");
		}
		$key = "user_1000_" . $dan;
		$match = "match_1000_" . $dan;
		$redis = new Redis();
		$redis->connect("127.0.0.1", 6379);
		$quene = $redis->zRevRange($match, 0, -1, true);
		foreach ($quene as $key => $val) {
			$kv = explode(":", $key);
			if (($kv[0] == $user_id || $kv[1] == $user_id) && $val > time()) {
				$res = pdo_fetch("SELECT id FROM " . tablename("hc_pk_record") . " WHERE userid_one=:uid or userid_two=:uid AND weid=:weid ORDER BY createtime desc", array(":uid" => $user_id, ":weid" => $weid));
				pdo_update("hc_user_info", array("gold" => $usergold - $needgold), array("weid" => $weid, "uid" => $user_id));
				pdo_insert("hc_user_gold", array("weid" => $weid, "uid" => $user_id, "type" => 1, "gold" => $needgold, "addtime" => time()));
				$data = array("user" => array($this->userinfo($kv[0]), $this->userinfo($kv[1])), "robot" => $kv[2], "rid" => $res["id"]);
				return $this->result(0, "操作成功", $data);
			}
		}
		$all = $redis->zRevRange($key, 0, -1, true);
		if (!empty($all[$user_id]) && $all[$user_id] < time()) {
			$redis->zDelete($key, $user_id);
			$isrobot = pdo_getcolumn("hc_dan", array("weid" => $weid, "dan_id" => $dan), array("robot"));
			if ($isrobot == 1) {
				$user2 = pdo_fetch("SELECT uid FROM " . tablename("hc_user") . " WHERE robot=1 AND inans=0 AND weid=:weid ORDER BY rand()", array(":weid" => 1000));
				$quesids = pdo_getcolumn("hc_dan", array("weid" => $weid, "dan_id" => $dan), array("quesids"));
				$qids = explode(",", $quesids);
				$ques = array_rand($qids, min(count($qids), $matchtime["quesnum"]));
				foreach ($ques as $key => $val) {
					$newq[$key] = $qids[$val];
				}
				$record = implode(",", $newq);
			} else {
				$user2 = pdo_fetch("SELECT q.uid,q.rid,r.questions FROM " . tablename("hc_pk_question") . " as q LEFT JOIN " . tablename("hc_pk_record") . " as r on r.id=q.rid LEFT JOIN " . tablename("hc_user") . " as u on q.uid=u.uid WHERE r.dan = :dan AND r.weid = :weid AND r.userid_one!=:uid AND r.userid_two !=:uid AND u.status=1 AND robot !=1 GROUP BY r.id HAVING COUNT(1)=:quesnum ORDER BY r.id DESC limit 1", array(":dan" => $dan, ":weid" => $weid, ":uid" => $user_id, ":quesnum" => $matchtime["quesnum"]));
				if (!empty($user2) && $redis->get("isrobot_" . $dan) == 1) {
					$record = $user2["questions"];
					$redis->set("isrobot_" . $dan, 2);
				} else {
					$user2 = pdo_fetch("SELECT uid FROM " . tablename("hc_user") . " WHERE robot=1 AND inans=0 AND weid=:weid ORDER BY rand()", array(":weid" => 1000));
					$quesids = pdo_getcolumn("hc_dan", array("weid" => $weid, "dan_id" => $dan), array("quesids"));
					$qids = explode(",", $quesids);
					$ques = array_rand($qids, min(count($qids), $matchtime["quesnum"]));
					foreach ($ques as $key => $val) {
						$newq[$key] = $qids[$val];
					}
					$record = implode(",", $newq);
					$isrobot = 1;
					$redis->set("isrobot_" . $dan, 1);
				}
			}
			$redis->zAdd($match, $expire, $user_id . ":" . $user2["uid"] . ":1");
			$pkres = pdo_insert("hc_pk_record", array("weid" => $weid, "dan" => $dan, "userid_one" => $user_id, "userid_two" => $user2["uid"], "questions" => $record, "createtime" => time()));
			if (!empty($pkres)) {
				$pkid = pdo_insertid();
			}
			if ($isrobot == 2) {
				$ansyc = pdo_getall("hc_pk_question", array("weid" => $weid, "rid" => $user2["rid"], "uid" => $user2["uid"]));
				foreach ($ansyc as $key => $val) {
					unset($val["id"]);
					$val["rid"] = $pkid;
					pdo_insert("hc_pk_question", $val);
				}
			}
			pdo_update("hc_user_info", array("gold" => $usergold - $needgold), array("weid" => $weid, "uid" => $user_id));
			pdo_insert("hc_user_gold", array("weid" => $weid, "uid" => $user_id, "type" => 1, "gold" => $needgold, "addtime" => time()));
			$data = array("user" => array($this->userinfo($user_id), $this->userinfo($user2["uid"])), "robot" => 1, "rid" => $pkid);
			return $this->result(0, "操作成功", $data);
		} else {
			if ($redis->zSize($key) >= 1) {
				$match_user_id = $redis->zRevRange($key, -1, -1);
				if ($match_user_id[0] != $user_id) {
					$redis->zAdd($match, $expire, $user_id . ":" . $match_user_id[0] . ":0");
					$redis->zDelete($key, $match_user_id[0]);
					$quesids = pdo_getcolumn("hc_dan", array("weid" => $weid, "dan_id" => $dan), array("quesids"));
					$qids = explode(",", $quesids);
					$ques = array_rand($qids, min(count($qids), $matchtime["quesnum"]));
					foreach ($ques as $key => $val) {
						$newq[$key] = $qids[$val];
					}
					$record = implode(",", $newq);
					$pkres = pdo_insert("hc_pk_record", array("weid" => $weid, "dan" => $dan, "userid_one" => $user_id, "userid_two" => $match_user_id[0], "questions" => $record, "createtime" => time()));
					if (!empty($pkres)) {
						$pkid = pdo_insertid();
					}
					pdo_update("hc_user_info", array("gold" => $usergold - $needgold), array("weid" => $weid, "uid" => $user_id));
					pdo_insert("hc_user_gold", array("weid" => $weid, "uid" => $user_id, "type" => 1, "gold" => $needgold, "addtime" => time()));
					$data = array("user" => array($this->userinfo($user_id), $this->userinfo($match_user_id[0])), "robot" => 0, "rid" => $pkid);
					return $this->result(0, "操作成功", $data);
				}
				return $this->result(1, "继续等待1");
			} else {
				$redis->zAdd($key, $expire, $user_id);
				return $this->result(1, "继续等待2");
			}
		}
	}
	public function doPageGetques()
	{
		global $_GPC, $_W;
		$rid = $_GPC["rid"];
		$uid = $_GPC["user_id"];
		$num = $_GPC["ansnumber"];
		$weid = 1000;
		$set = $this->matchtime();
		$sid = $this->currseason();
		$record = pdo_get("hc_pk_record", array("id" => $rid, "weid" => $weid));
		$ques = explode(",", $record["questions"]);
		if (count($ques) < $num + 1) {
			$user = pdo_get("hc_user_info", array("uid" => $uid, "weid" => $weid));
			$dan = pdo_get("hc_dan", array("dan_id" => $record["dan"], "weid" => $weid));
			$count = pdo_get("hc_pk_question", array("weid" => $weid, "uid" => $uid, "rid" => $rid), array("sum(score) as score", "sum(plus) as plus"));
			if ($uid == $record["userid_one"]) {
				$ds = pdo_get("hc_pk_question", array("weid" => $weid, "uid" => $record["userid_two"], "rid" => $rid), array("sum(score) as score", "sum(plus) as plus"));
				$me = $count["score"] + $count["plus"];
				$he = $ds["score"] + $ds["plus"];
				if ($me > $he) {
					$pk_record["status1"] = 1;
				} else {
					if ($me == $he) {
						$pk_record["status1"] = 1;
						$pk_record["status2"] = 1;
					} else {
						$pk_record["status2"] = 1;
					}
				}
				$pk_record["userone_score"] = $me;
				$pk_record["usertwo_score"] = $he;
				$uid2 = $record["userid_two"];
			}
			if ($uid == $record["userid_two"]) {
				$ds = pdo_get("hc_pk_question", array("weid" => $weid, "uid" => $record["userid_one"], "rid" => $rid), array("sum(score) as score", "sum(plus) as plus"));
				$me = $count["score"] + $count["plus"];
				$he = $ds["score"] + $ds["plus"];
				if ($me > $he) {
					$pk_record["status1"] = 1;
				} else {
					if ($me == $he) {
						$pk_record["status1"] = 1;
						$pk_record["status2"] = 1;
					} else {
						$pk_record["status2"] = 0;
					}
				}
				$pk_record["userone_score"] = $he;
				$pk_record["usertwo_score"] = $me;
				$uid2 = $record["userid_one"];
			}
			pdo_update("hc_pk_record", $pk_record, array("id" => $rid, "weid" => $weid));
			$users["weid"] = $weid;
			$users["rid"] = $rid;
			$users["uid"] = $uid;
			$users["usegold"] = $dan["use_gold"];
			$users["score"] = $me;
			if ($me > $he) {
				$users["gold"] = $dan["win_gold"];
				$users["expe"] = $dan["winexp"];
				if ($user["dan"] <= $record["dan"]) {
					if ($dan["win_star"] == $user["star"] + 1) {
						$inf["star"] = 0;
						if ($user["dan"] == $this->maxdan()) {
							$inf["star"] = $user["star"] + 1;
						} else {
							$inf["dan"] = $user["dan"] + 1;
						}
					} else {
						if ($dan["win_star"] > $user["star"] + 1) {
							$inf["star"] = $user["star"] + 1;
						}
					}
				}
			} else {
				if ($me == $he) {
					$users["gold"] = $dan["use_gold"];
					$users["expe"] = $dan["winexp"];
				} else {
					$users["gold"] = 0;
					$users["expe"] = $dan["failexp"];
					if ($user["dan"] <= $record["dan"]) {
						if ($user["star"] == 0) {
							$inf["star"] = 0;
						} else {
							$inf["star"] = $user["star"] - 1;
						}
					}
					$this->senderrornotice($rid, $uid);
					$this->sendsuccessnotice($rid, $uid2);
				}
			}
			if ($record["type"] == 1) {
				$users["usegold"] = 0;
				$users["gold"] = 0;
				$users["expe"] = 0;
			}
			pdo_insert("hc_pk_log", $users);
			if ($record["type"] != 1) {
				$inf["gold"] = $user["gold"] + $users["gold"];
				if ($user["maxexpe"] <= $user["expe"] + $users["expe"]) {
					$inf["expe"] = $user["expe"] + $users["expe"] - $user["maxexpe"];
					$inf["level"] = $user["level"] + 1;
					$inf["maxexpe"] = pdo_getcolumn("hc_grade", array("levelno" => $user["level"] + 1, "weid" => $weid), array("levelexp"));
				} else {
					$inf["expe"] = $user["expe"] + $users["expe"];
				}
				pdo_update("hc_user_info", $inf, array("uid" => $uid, "weid" => $weid));
				pdo_insert("hc_user_gold", array("weid" => $weid, "uid" => $uid, "type" => 0, "gold" => $users["gold"], "addtime" => time()));
			}
			$record = pdo_get("hc_pk_record", array("id" => $rid, "weid" => $weid));
			$log = pdo_get("hc_pk_log", array("rid" => $rid, "weid" => $weid, "uid" => $uid), array("gold", "expe"));
			$record["gold"] = $log["gold"];
			$record["expe"] = $log["expe"];
			$record["status"] = 1;
			return $this->result(0, "刷新成功", $record);
		} else {
			$qid = $ques[$num];
			$info = pdo_get("hc_question", array("weid" => $weid, "id" => $qid));
			$list["qid"] = $info["id"];
			$list["djt"] = $num + 1;
			$list["cate"] = pdo_getcolumn("hc_question_type", array("id" => $info["type_id"], "weid" => $weid), array("name"));
			$list["title"] = $info["question"];
			$select = array("A" => 0, "B" => 1, "C" => 2, "D" => 3);
			$list["right"] = $select[$info["answer"]];
			$i = 0;
			while ($i < 4) {
				if ($i == 0) {
					$list["ans"][$i]["ans"] = $info["answer_a"];
				} else {
					if ($i == 1) {
						$list["ans"][$i]["ans"] = $info["answer_b"];
					} else {
						if ($i == 2) {
							$list["ans"][$i]["ans"] = $info["answer_c"];
						} else {
							if ($i == 3) {
								$list["ans"][$i]["ans"] = $info["answer_d"];
							}
						}
					}
				}
				$list["ans"][$i]["key"] = $i;
				$list["ans"][$i]["status"] = $select[$info["answer"]] == $i ? 1 : 0;
				$i++;
			}
			$record = pdo_get("hc_pk_record", array("id" => $rid, "weid" => $weid));
			if ($uid == $record["userid_one"]) {
				$uid2 = $record["userid_two"];
			} else {
				if ($uid == $record["userid_two"]) {
					$uid2 = $record["userid_one"];
				}
			}
			$robot = pdo_getcolumn("hc_user", array("uid" => $uid2, "weid" => $weid), array("robot"));
			if ($robot == 1) {
				$select = array("A", "B", "C", "D");
				$quesnum = count($ques);
				$quesrate = empty($set["rebotrate"]) ? 80 : $set["rebotrate"];
				$rightnum = ceil($quesnum * $quesrate / 100);
				if ($num < $rightnum) {
					$choose = array("A" => 0, "B" => 1, "C" => 2, "D" => 3);
					$ans = $choose[$info["answer"]];
				} else {
					$ans = rand(0, 3);
				}
				$min = rand(1, $set["times"] - 4);
				if ($info["answer"] == $select[$ans]) {
					$right = 1;
					$score = $set["maxscore"] - $set["secscore"] * $min;
					if ($num + 1 == $quesnum) {
						$score = $set["lastscore"] - $set["secscore"] * $min;
					}
					if ($score < 0) {
						$score = 0;
					}
					$plus = 0;
				} else {
					$right = 0;
					$plus = 0;
					$score = 0;
				}
				$data = array("weid" => $weid, "rid" => $rid, "uid" => $uid2, "qid" => $qid, "answer" => $select[$ans], "plus" => $plus, "score" => $score, "min" => $min, "right" => $right, "addtime" => time());
				$res = pdo_insert("hc_pk_question", $data);
			}
			return $this->result(0, "获取成功", $list);
		}
	}
	public function doPageGetresult()
	{
		global $_GPC, $_W;
		$rid = $_GPC["rid"];
		$uid = $_GPC["user_id"];
		$weid = 1000;
		$record = pdo_get("hc_pk_record", array("id" => $rid, "weid" => $weid));
		if ($record["status1"] == 0 && $record["status2"] == 0) {
			if ($uid == $record["userid_one"]) {
				$me = $record["userone_score"];
				$he = $record["usertwo_score"];
				if ($me > $he) {
					$pk_record["status1"] = 1;
				} else {
					if ($me == $he) {
						$pk_record["status1"] = 1;
						$pk_record["status2"] = 1;
					} else {
						$pk_record["status2"] = 1;
					}
				}
				$uid2 = $record["userid_two"];
			} else {
				if ($uid == $record["userid_two"]) {
					$me = $record["usertwo_score"];
					$he = $record["userone_score"];
					if ($me > $he) {
						$pk_record["status2"] = 1;
					} else {
						if ($me == $he) {
							$pk_record["status1"] = 1;
							$pk_record["status2"] = 1;
						} else {
							$pk_record["status2"] = 0;
						}
					}
					$uid2 = $record["userid_one"];
				}
			}
			pdo_update("hc_pk_record", $pk_record, array("id" => $rid, "weid" => $weid));
		} else {
			if ($uid == $record["userid_one"]) {
				$me = $record["userone_score"];
				$he = $record["usertwo_score"];
			} else {
				if ($uid == $record["userid_two"]) {
					$me = $record["usertwo_score"];
					$he = $record["userone_score"];
				}
			}
		}
		$user = pdo_get("hc_user_info", array("uid" => $uid, "weid" => $weid));
		$users["weid"] = $weid;
		$users["rid"] = $rid;
		$users["uid"] = $uid;
		$users["score"] = $me;
		if ($record["type"] == 1) {
			$users["usegold"] = 0;
			$users["gold"] = 0;
			$users["expe"] = 0;
		} else {
			$maxdan = pdo_getcolumn("hc_dan", array("weid" => $weid, "id" => $activ["condition"]), array("dan_id"));
			$dan = pdo_get("hc_dan", array("dan_id" => $record["dan"], "weid" => $weid));
			$users["usegold"] = $dan["use_gold"];
			if ($me > $he) {
				$users["gold"] = $dan["win_gold"];
				$users["expe"] = $dan["winexp"];
				if ($user["dan"] <= $record["dan"]) {
					if ($dan["win_star"] == $user["star"] + 1) {
						$inf["star"] = 0;
						if ($user["dan"] == $maxdan) {
							$inf["star"] = $user["star"] + 1;
						} else {
							$inf["dan"] = $user["dan"] + 1;
						}
					} else {
						if ($dan["win_star"] > $user["star"] + 1) {
							$inf["star"] = $user["star"] + 1;
						}
					}
				}
			} else {
				if ($me == $he) {
					$users["gold"] = $dan["use_gold"];
					$users["expe"] = $dan["winexp"];
				} else {
					$users["gold"] = 0;
					$users["expe"] = $dan["failexp"];
					if ($user["dan"] <= $record["dan"]) {
						if ($user["star"] == 0) {
							$inf["star"] = 0;
						} else {
							$inf["star"] = $user["star"] - 1;
						}
					}
				}
			}
		}
		pdo_insert("hc_pk_log", $users);
		if ($record["type"] != 1) {
			$inf["gold"] = ceil($user["gold"] + $users["gold"]);
			if ($user["maxexpe"] <= $user["expe"] + $users["expe"]) {
				$inf["expe"] = ceil($user["expe"] + $users["expe"] - $user["maxexpe"]);
				$inf["level"] = ceil($user["level"] + 1);
				$inf["maxexpe"] = pdo_getcolumn("hc_grade", array("levelno" => $user["level"] + 1, "weid" => $weid), array("levelexp"));
			} else {
				$inf["expe"] = ceil($user["expe"] + $users["expe"]);
			}
			pdo_update("hc_user_info", $inf, array("uid" => $uid, "weid" => $weid));
			pdo_insert("hc_user_gold", array("weid" => $weid, "uid" => $uid, "type" => 0, "gold" => $users["gold"], "addtime" => time()));
		}
		$record = pdo_get("hc_pk_record", array("id" => $rid, "weid" => $weid));
		$log = pdo_get("hc_pk_log", array("rid" => $rid, "weid" => $weid, "uid" => $uid), array("gold", "expe"));
		$record["gold"] = empty($log["gold"]) ? 0 : $log["gold"];
		$record["expe"] = empty($log["expe"]) ? 0 : $log["expe"];
		$record["status"] = 1;
		return $this->result(0, "刷新成功", $record);
	}
	public function sendsuccessnotice($rid, $uid)
	{
		global $_GPC, $_W;
		$weid = 1000;
		$account_api = WeAccount::create();
		$token = $account_api->getAccessToken();
		$url = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=" . $token;
		$tpl = pdo_getcolumn("hc_setting", array("weid" => $weid), array("tpl"));
		$tpl = json_decode($tpl, true);
		$users = pdo_fetch("SELECT a.openid,b.formid FROM " . tablename("hc_user") . " AS a LEFT JOIN " . tablename("hc_formid") . " AS b ON a.uid=b.uid WHERE a.weid=:weid AND a.uid=:uid AND b.formid !='' AND b.status=0", array(":weid" => $weid, ":uid" => $uid));
		$data["touser"] = $users["openid"];
		$data["template_id"] = $tpl["pk"];
		$data["form_id"] = $users["formid"];
		$uids = pdo_get("hc_pk_record", array("id" => $rid, "weid" => $weid), array("userid_one", "userid_two"));
		$nick1 = pdo_getcolumn("hc_user", array("uid" => $uid, "weid" => $weid), array("nickname"));
		if ($uid == $uids["userid_one"]) {
			$nick2 = pdo_getcolumn("hc_user", array("uid" => $uids["userid_two"], "weid" => $weid), array("nickname"));
		} else {
			if ($uid == $uids["userid_two"]) {
				$nick2 = pdo_getcolumn("hc_user", array("uid" => $uids["userid_one"], "weid" => $weid), array("nickname"));
			}
		}
		$keyword1 = $nick1 . " VS " . $nick2;
		$data["data"]["keyword1"]["value"] = date("Y-m-d H:i:s");
		$data["data"]["keyword1"]["color"] = "#173177";
		$data["data"]["keyword2"]["value"] = $keyword1;
		$data["data"]["keyword2"]["color"] = "#173177";
		$data["data"]["keyword3"]["value"] = "成功";
		$data["data"]["keyword3"]["color"] = "#173177";
		$data["data"]["keyword4"]["value"] = "哎哟，不错哦";
		$data["data"]["keyword4"]["color"] = "#173177";
		$data["emphasis_keyword"] = "keyword3.DATA";
		$data["page"] = "hc_answer/pages/index/index";
		$json = json_encode($data);
		$res = ihttp_post($url, $json);
		if ($res["status"] == "OK") {
			pdo_update("hc_formid", array("status" => 1), array("formid" => $users["formid"]));
		}
	}
	public function senderrornotice($rid, $uid)
	{
		global $_GPC, $_W;
		$weid = 1000;
		$account_api = WeAccount::create();
		$token = $account_api->getAccessToken();
		$url = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=" . $token;
		$tpl = pdo_getcolumn("hc_setting", array("weid" => $weid), array("tpl"));
		$tpl = json_decode($tpl, true);
		$users = pdo_fetch("SELECT a.openid,b.formid FROM " . tablename("hc_user") . " AS a LEFT JOIN " . tablename("hc_formid") . " AS b ON a.uid=b.uid WHERE a.weid=:weid AND a.uid=:uid AND b.formid !='' AND b.status=0", array(":weid" => $weid, ":uid" => $uid));
		$data["touser"] = $users["openid"];
		$data["template_id"] = $tpl["pk"];
		$data["form_id"] = $users["formid"];
		$uids = pdo_get("hc_pk_record", array("id" => $rid, "weid" => $weid), array("userid_one", "userid_two"));
		$nick1 = pdo_getcolumn("hc_user", array("uid" => $uid, "weid" => $weid), array("nickname"));
		if ($uid == $uids["userid_one"]) {
			$nick2 = pdo_getcolumn("hc_user", array("uid" => $uids["userid_two"], "weid" => $weid), array("nickname"));
		} else {
			if ($uid == $uids["userid_two"]) {
				$nick2 = pdo_getcolumn("hc_user", array("uid" => $uids["userid_one"], "weid" => $weid), array("nickname"));
			}
		}
		$keyword1 = $nick1 . " VS " . $nick2;
		$data["data"]["keyword1"]["value"] = date("Y-m-d H:i:s");
		$data["data"]["keyword1"]["color"] = "#173177";
		$data["data"]["keyword2"]["value"] = $keyword1;
		$data["data"]["keyword2"]["color"] = "#173177";
		$data["data"]["keyword3"]["value"] = "失败";
		$data["data"]["keyword3"]["color"] = "#173177";
		$data["data"]["keyword4"]["value"] = "请再接再厉哦";
		$data["data"]["keyword4"]["color"] = "#173177";
		$data["emphasis_keyword"] = "keyword3.DATA";
		$data["page"] = "hc_answer/pages/index/index";
		$json = json_encode($data);
		$res = ihttp_post($url, $json);
		if ($res["status"] == "OK") {
			pdo_update("hc_formid", array("status" => 1), array("formid" => $users["formid"]));
		}
	}
	public function doPagePutanswer()
	{
		global $_GPC, $_W;
		$rid = $_GPC["rid"];
		$uid = $_GPC["user_id"];
		$qid = $_GPC["qid"];
		$ans = $_GPC["ans"];
		$min = $_GPC["min"];
		$weid = 1000;
		$set = $this->matchtime();
		$select = array("A", "B", "C", "D");
		$answer = pdo_getcolumn("hc_question", array("weid" => $weid, "id" => $qid), array("answer"));
		if ($answer == $select[$ans]) {
			$right = 1;
			$alrques = pdo_getcolumn("hc_pk_question", array("weid" => $weid, "rid" => $rid, "uid" => $uid), array("count(*)"));
			if ($alrques + 1 == $set["quesnum"]) {
				$score = $set["lastscore"] - $set["secscore"] * $min;
			} else {
				$score = $set["maxscore"] - $set["secscore"] * $min;
			}
			if ($score < 0) {
				$score = 0;
			}
			$tid = pdo_get("hc_question", array("weid" => $weid, "id" => $qid), array("type_id"));
			$plus = pdo_getcolumn("hc_user_cate", array("weid" => $weid, "uid" => $uid, "tid" => $tid), array("plus"));
		} else {
			$right = 0;
			$plus = 0;
			$score = 0;
		}
		$data = array("weid" => $weid, "rid" => $rid, "uid" => $uid, "qid" => $qid, "answer" => $select[$ans], "plus" => $plus, "score" => $score, "min" => $min, "right" => $right, "addtime" => time());
		$res = pdo_insert("hc_pk_question", $data);
		if (!empty($res)) {
			$score = pdo_getcolumn("hc_pk_question", array("weid" => $weid, "rid" => $rid, "uid" => $uid), array("sum(score)"));
			$score1 = pdo_getcolumn("hc_pk_question", array("weid" => $weid, "rid" => $rid, "uid" => $uid), array("sum(plus)"));
			$score += $score1;
			return $this->result(0, "答题成功", array("min" => $min, "score" => $score, "plus" => $plus, "answer" => $ans));
		} else {
			return $this->result(0, "答题失败");
		}
	}
	public function doPageReloadans()
	{
		global $_GPC, $_W;
		$rid = $_GPC["rid"];
		$weid = 1000;
		$qid = $_GPC["qid"];
		$uid = $_GPC["user_id"];
		$min = $_GPC["min"];
		$record = pdo_get("hc_pk_record", array("id" => $rid, "weid" => $weid));
		if ($uid == $record["userid_one"]) {
			$uid2 = $record["userid_two"];
		} else {
			if ($uid == $record["userid_two"]) {
				$uid2 = $record["userid_one"];
			}
		}
		$user = pdo_get("hc_pk_question", array("weid" => $weid, "rid" => $rid, "uid" => $uid2, "qid" => $qid), array("id", "min", "score", "plus", "answer"));
		$select = array("A" => 0, "B" => 1, "C" => 2, "D" => 3);
		if (!empty($user["answer"])) {
			$user["answer"] = $select[$user["answer"]];
		}
		if (empty($user)) {
			$user["status"] = 0;
		} else {
			$robot = pdo_getcolumn("hc_user", array("uid" => $uid2, "weid" => $weid), array("robot"));
			if ($robot == 1) {
				if ($user["min"] <= $min) {
					$user["status"] = 1;
				} else {
					unset($user);
					$user["status"] = 0;
				}
			} else {
				$user["status"] = 1;
			}
		}
		$score = pdo_getcolumn("hc_pk_question", array("weid" => $weid, "rid" => $rid, "uid" => $uid2, "id <=" => $user["id"]), array("sum(score)"));
		$score1 = pdo_getcolumn("hc_pk_question", array("weid" => $weid, "rid" => $rid, "uid" => $uid2, "id <=" => $user["id"]), array("sum(plus)"));
		$user["score"] = $score + $score1;
		return $this->result(0, "刷新成功", $user);
	}
	public function doPageOftenuse()
	{
		global $_GPC, $_W;
		$uid = $_GPC["user_id"];
		$weid = 1000;
		$list = pdo_getall("hc_prop", array("weid" => $weid, "dan" => 1), array(), '', "id asc", array(1, 5));
		foreach ($list as $key => $val) {
			$list[$key]["thumb"] = $_W["attachurl"] . $val["thumb"];
			$list[$key]["usethumb"] = $_W["attachurl"] . $val["usethumb"];
			if (!empty($val["cc"]) && !empty($val["jb"])) {
				$jbnum = pdo_getcolumn("hc_user_info", array("weid" => $weid, "uid" => $uid), array("jbnum"));
				if ($jbnum > 0) {
					$list[$key]["open"] = 1;
					$list[$key]["ccnum"] = $jbnum;
				}
			} else {
				if (!empty($val["cc"]) && !empty($val["jy"])) {
					$jynum = pdo_getcolumn("hc_user_info", array("weid" => $weid, "uid" => $uid), array("jynum"));
					if ($jynum > 0) {
						$list[$key]["open"] = 1;
						$list[$key]["ccnum"] = $jynum;
					}
				} else {
					if (!empty($val["sj"]) && !empty($val["jb"])) {
						$jbtime = pdo_getcolumn("hc_user_info", array("weid" => $weid, "uid" => $uid), array("jbtime"));
						if ($jbtime > time()) {
							$list[$key]["open"] = 1;
							$list[$key]["synum"] = $jbtime;
						}
					} else {
						if (!empty($val["sj"]) && !empty($val["jy"])) {
							$jytime = pdo_getcolumn("hc_user_info", array("weid" => $weid, "uid" => $uid), array("jytime"));
							if ($jytime > time()) {
								$list[$key]["open"] = 1;
								$list[$key]["synum"] = $jytime;
							}
						} else {
							if (!empty($val["cc"]) && !empty($val["jf"])) {
								$jfnum = pdo_getcolumn("hc_user_info", array("weid" => $weid, "uid" => $uid), array("jfnum"));
								if ($jfnum > 0) {
									$list[$key]["open"] = 1;
									$list[$key]["ccnum"] = $jfnum;
								}
							}
						}
					}
				}
			}
			$num = pdo_getcolumn("hc_user_prop", array("uid" => $uid, "pid" => $val["id"], "weid" => $weid), array("num"));
			$list[$key]["num"] = empty($num) ? "0" : $num;
		}
		return $this->result(0, "获取成功", $list);
	}
	public function doPageSelfdan()
	{
		global $_GPC, $_W;
		$uid = $_GPC["user_id"];
		$season = $this->currseason();
		$user = pdo_get("hc_user_info", array("uid" => $uid, "sid" => $season, "weid" => 1000));
		$user["dan"] = $user["dan"] == 0 ? 1 : $user["dan"];
		$last = $user["dan"] + 1;
		$list = pdo_getall("hc_dan", array("weid" => 1000), array(), '', "dan_id asc", array(1, $last));
		foreach ($list as $key => $val) {
			$result[$key]["dan_id"] = $val["dan_id"];
			$result[$key]["title"] = $val["name"];
			$result[$key]["thumb"] = $_W["attachurl"] . $val["thumb"];
			$result[$key]["needcoin"] = $val["use_gold"];
			$result[$key]["reword"] = $val["win_gold"];
			if ($user["dan"] > $val["dan_id"]) {
				$result[$key]["status"] = 1;
				$i = 1;
				while ($i <= $val["win_star"]) {
					$pass[]["status"] = 1;
					$i++;
				}
			} else {
				if ($val["dan_id"] == $user["dan"]) {
					if ($this->maxdan() == $user["dan"] && $val["win_star"] == $user["star"]) {
						$result[$key]["status"] = 1;
					} else {
						$result[$key]["status"] = 0;
					}
					$i = 1;
					while ($i <= $val["win_star"]) {
						if ($user["star"] >= $i) {
							$pass[]["status"] = 1;
						} else {
							$pass[]["status"] = 0;
						}
						$i++;
					}
				} else {
					$result[$key]["status"] = 0;
					$i = 1;
					while ($i <= $val["win_star"]) {
						$pass[]["status"] = 0;
						$i++;
					}
				}
			}
			$result[$key]["pass"] = array_reverse($pass);
			unset($pass);
		}
		return $this->result(0, "获取成功", $result);
	}
	public function doPageSignprop()
	{
		global $_GPC, $_W;
		$uid = $_GPC["user_id"];
		$sid = $this->currseason();
		$sign = json_decode(pdo_getcolumn("hc_setting", array("weid" => 1000), array("sign"), true));
		foreach ($sign as $key => $val) {
			$pan = explode(":", $val);
			$list[$key] = pdo_get("hc_prop", array("id" => $pan[0], "weid" => 1000));
			$list[$key]["thumb"] = $_W["attachurl"] . $list[$key]["thumb"];
			$list[$key]["num"] = $pan[1];
		}
		$countsign = pdo_getcolumn("hc_user_sign", array("weid" => 1000, "uid" => $uid, "sid" => $sid), array("count(*)"));
		$current = $countsign % 7;
		foreach ($list as $key => $val) {
			$day = $key + 1;
			if ($key < $current) {
				$list[$key]["status"] = 1;
				$list[$key]["show"] = "已领";
			} else {
				if ($key == $current) {
					$list[$key]["status"] = 0;
					$list[$key]["show"] = "今天";
				} else {
					if ($key == $current + 1) {
						$list[$key]["status"] = 0;
						$list[$key]["show"] = "明天";
					} else {
						if ($key == $current + 2) {
							$list[$key]["status"] = 0;
							$list[$key]["show"] = "后天";
						} else {
							$list[$key]["status"] = 2;
							$list[$key]["show"] = "第" . $day . "天";
						}
					}
				}
			}
		}
		return $this->result(0, "获取成功", $list);
	}
	public function doPageGetprop()
	{
		global $_GPC, $_W;
		$uid = $_GPC["user_id"];
		$day = $_GPC["day"];
		$formid = $_GPC["formid"];
		pdo_insert("hc_formid", array("weid" => 1000, "uid" => $uid, "formid" => $formid));
		$sid = $this->currseason();
		$sign = json_decode(pdo_getcolumn("hc_setting", array("weid" => 1000), array("sign"), true));
		foreach ($sign as $key => $val) {
			if ($day == $key + 1) {
				$pan = explode(":", $val);
			}
		}
		$signdata = array("weid" => 1000, "uid" => $uid, "sid" => $sid, "date" => strtotime(date("Y-m-d")), "signtime" => time(), "pid" => $pan[0]);
		$signres = pdo_insert("hc_user_sign", $signdata);
		$sfcz = pdo_get("hc_user_prop", array("pid" => $val, "uid" => $uid, "weid" => $weid));
		if (empty($sfcz)) {
			$prop = array("weid" => 1000, "uid" => $uid, "pid" => $pan[0], "num" => $pan[1]);
			$propres = pdo_insert("hc_user_prop", $prop);
		} else {
			$userprop = array("num" => $sfcz["num"] + $pan[1], "status" => 0);
			pdo_update("hc_user_prop", $prop, array("weid" => 1000, "uid" => $uid, "pid" => $pan[0]));
		}
		$data = array("weid" => 1000, "uid" => $uid, "pid" => $pan[0], "type" => 1, "num" => $pan[1], "createtime" => time());
		$logres = pdo_insert("hc_user_proplog", $data);
		if (!empty($logres)) {
			return $this->result(0, "领取成功");
		} else {
			return $this->result(1, "领取失败");
		}
	}
	public function doPageShop()
	{
		global $_GPC, $_W;
		$uid = $_GPC["user_id"];
		$newid = pdo_getcolumn("hc_prop", array("shop" => 1, "type" => 2, "weid" => 1000), array("id"));
		$isnew = pdo_get("hc_user_prop", array("pid" => $newid, "uid" => $uid, "weid" => 1000));
		if (empty($isnew)) {
			$list["new"] = pdo_get("hc_prop", array("shop" => 1, "type" => 2, "weid" => 1000));
			$list["new"]["thumb"] = $_W["attachurl"] . $list["new"]["thumb"];
			$list["new"]["newthumb"] = $_W["attachurl"] . $list["new"]["newthumb"];
		}
		$list["goods"] = pdo_getall("hc_prop", array("shop" => 1, "type !=" => 2, "weid" => 1000), array(), '', "sort ASC", array(1, 100));
		foreach ($list["goods"] as $key => $val) {
			$list["goods"][$key]["thumb"] = $_W["attachurl"] . $val["thumb"];
		}
		$userinfo = pdo_get("hc_user_info", array("uid" => $uid, "weid" => 1000), array("dan", "star"));
		if ($userinfo["star"] == 0) {
			$dan = max(1, $userinfo["dan"] - 1);
		} else {
			$dan = $userinfo["dan"];
		}
		$gold = pdo_getcolumn("hc_dan", array("dan_id" => $dan, "weid" => 1000), array("use_gold"));
		$list["bottom"] = $gold;
		return $this->result(0, "获取成功", $list);
	}
	public function doPagePay()
	{
		global $_GPC, $_W;
		$uid = $_GPC["user_id"];
		$pid = $_GPC["pid"];
		$weid = 1000;
		$price = pdo_getcolumn("hc_prop", array("id" => $pid, "weid" => $weid), array("price"));
		$data = array("weid" => $weid, "uid" => $uid, "pid" => $pid, "fee" => $price, "ordersn" => date("YmdHis") . time() . rand(100000, 999999), "status" => 1, "createtime" => time());
		$res = pdo_insert("hc_order", $data);
		if (!empty($res)) {
			$oid = pdo_insertid();
			$pay_params = $this->payment($oid);
			if (is_error($pay_params)) {
				return $this->result(1, "支付失败，请重试");
			}
			return $this->result(0, '', $pay_params);
		} else {
			return $this->result(1, "操作失败");
		}
	}
	public function payment($order_id)
	{
		global $_GPC, $_W;
		$weid = 1000;
		load()->model("payment");
		load()->model("account");
		$setting = uni_setting($_W["uniacid"], array("payment"));
		$wechat_payment = array("appid" => $_W["account"]["key"], "signkey" => $setting["payment"]["wechat"]["signkey"], "mchid" => $setting["payment"]["wechat"]["mchid"]);
		$order = pdo_get("hc_order", array("id" => $order_id, "weid" => $weid));
		$openid = pdo_getcolumn("hc_user", array("uid" => $order["uid"]), array("openid"));
		$notify_url = $_W["siteroot"] . "addons/hc_answer/wxpay.php";
		$res = $this->getPrePayOrder($wechat_payment, $notify_url, $order, $openid);
		if ($res["return_code"] == "FAIL") {
			return $this->result(1, "操作失败", $res["return_msg"]);
		}
		if ($res["result_code"] == "FAIL") {
			return $this->result(1, "操作失败", $res["err_code"] . $res["err_code_des"]);
		}
		if ($res["return_code"] == "SUCCESS") {
			$wxdata = $this->getOrder($res["prepay_id"], $wechat_payment);
			pdo_update("hc_order", array("package" => $res["prepay_id"]), array("id" => $order_id));
			return $this->result(0, "操作成功", $wxdata);
		} else {
			return $this->result(1, "操作失败");
		}
	}
	public function getPrePayOrder($wechat_payment, $notify_url, $order, $openid)
	{
		$model = new HcfkModel();
		$url = "https://api.mch.weixin.qq.com/pay/unifiedorder";
		$data["appid"] = $wechat_payment["appid"];
		$data["body"] = $order["ordersn"];
		$data["mch_id"] = $wechat_payment["mchid"];
		$data["nonce_str"] = $model->getRandChar(32);
		$data["notify_url"] = $notify_url;
		$data["out_trade_no"] = $order["ordersn"];
		$data["spbill_create_ip"] = $model->get_client_ip();
		$data["total_fee"] = $order["fee"] * 100;
		$data["trade_type"] = "JSAPI";
		$data["openid"] = $openid;
		$data["sign"] = $model->getSign($data, $wechat_payment["signkey"]);
		$xml = $model->arrayToXml($data);
		$response = $model->postXmlCurl($xml, $url);
		return $model->xmlstr_to_array($response);
	}
	public function getOrder($prepayId, $wechat_payment)
	{
		$model = new HcfkModel();
		$data["appId"] = $wechat_payment["appid"];
		$data["nonceStr"] = $model->getRandChar(32);
		$data["package"] = "prepay_id=" . $prepayId;
		$data["signType"] = "MD5";
		$data["timeStamp"] = time();
		$data["sign"] = $model->getSign1($data, $wechat_payment["signkey"]);
		return $data;
	}
	public function doPageSelfprop()
	{
		global $_GPC, $_W;
		$weid = 1000;
		$uid = $_GPC["user_id"];
		$list = pdo_fetchall("SELECT b.*,num FROM " . tablename("hc_user_prop") . " AS a LEFT JOIN " . tablename("hc_prop") . " AS b ON a.pid=b.id WHERE a.weid=:weid AND a.uid=:uid AND a.status=0 AND b.type!=4 GROUP BY pid", array(":weid" => $weid, "uid" => $uid));
		foreach ($list as $key => $val) {
			$list[$key]["thumb"] = $_W["attachurl"] . $val["thumb"];
		}
		return $this->result(0, "获取成功", $list);
	}
	public function doPageUseprop()
	{
		global $_GPC, $_W;
		$weid = 1000;
		$uid = $_GPC["user_id"];
		$pid = $_GPC["pid"];
		$info = pdo_get("hc_prop", array("id" => $pid, "weid" => $weid));
		$self = pdo_get("hc_user_prop", array("pid" => $pid, "uid" => $uid, "weid" => $weid));
		if ($self["num"] <= 0 || $self["status"] == 1) {
			return $this->result(1, "商品已使用完！");
		}
		$num = $self["num"] - 1;
		$usenum = $self["usenum"] + 1;
		if ($num == 0) {
			$status = 1;
		}
		$userprop = array("num" => $num, "usenum" => $usenum, "status" => $status);
		$res = pdo_update("hc_user_prop", $userprop, array("weid" => $weid, "uid" => $uid, "pid" => $pid));
		if (!empty($res)) {
			$proplog = array("weid" => $weid, "uid" => $uid, "pid" => $pid, "type" => 4, "num" => 1, "createtime" => time());
			$result = pdo_insert("hc_user_proplog", $proplog);
		}
		$selfinfo = pdo_get("hc_user_info", array("weid" => $weid, "uid" => $uid));
		if (!empty($info["cc"]) && !empty($info["jb"]) && $info["type"] == 5) {
			$jbnum = $selfinfo["jbnum"] + $info["cc"];
			pdo_update("hc_user_info", array("jbnum" => $jbnum), array("weid" => $weid, "uid" => $uid));
		} else {
			if (!empty($info["cc"]) && !empty($info["jy"]) && $info["type"] == 5) {
				$jynum = $selfinfo["jynum"] + $info["cc"];
				pdo_update("hc_user_info", array("jynum" => $jynum), array("weid" => $weid, "uid" => $uid));
			} else {
				if (!empty($info["sj"]) && !empty($info["jb"]) && $info["type"] == 6) {
					if ($selfinfo["jbtime"] > time()) {
						$jbtime = $selfinfo["jbtime"] + $info["sj"] * 3600;
					} else {
						$jbtime = time() + $info["sj"] * 3600;
					}
					pdo_update("hc_user_info", array("jbtime" => $jbtime), array("weid" => $weid, "uid" => $uid));
				} else {
					if (!empty($info["sj"]) && !empty($info["jy"]) && $info["type"] == 6) {
						if ($selfinfo["jytime"] > time()) {
							$jytime = $selfinfo["jytime"] + $info["sj"] * 3600;
						} else {
							$jytime = time() + $info["sj"] * 3600;
						}
						pdo_update("hc_user_info", array("jytime" => $jytime), array("weid" => $weid, "uid" => $uid));
					} else {
						if (!empty($info["cc"]) && !empty($info["jf"]) && $info["type"] == 7) {
							$jfnum = $selfinfo["jfnum"] + $info["cc"];
							pdo_update("hc_user_info", array("jfnum" => $jfnum), array("weid" => $weid, "uid" => $uid));
						}
					}
				}
			}
		}
		if ($info["type"] == 3) {
			$gold = pdo_getcolumn("hc_user_info", array("uid" => $uid, "weid" => $weid), array("gold"));
			$nowgold = $gold + $info["jb"];
			pdo_update("hc_user_info", array("gold" => $nowgold), array("weid" => $weid, "uid" => $uid));
			pdo_insert("hc_user_gold", array("weid" => $weid, "uid" => $uid, "gold" => $gold, "addtime" => time()));
		} else {
			if (!empty($info["remark"])) {
				$proparr = explode(",", $info["remark"]);
				$randnum = rand(2, $info["randnum"]);
				$propnum = array_rand($proparr, min($randnum, count($proparr)));
				$maxnum = ceil($info["randnum"] / count($propnum));
				foreach ($propnum as $key => $val) {
					$sendprop[] = $proparr[$val];
				}
				$i = 0;
				while ($i < count($sendprop)) {
					if ($i + 1 == count($sendprop)) {
						if ($aa < $info["randnum"]) {
							$nums[$i] = $info["randnum"] - $aa;
						} else {
							unset($nums[$i]);
						}
					} else {
						$nums[$i] = rand(1, $maxnum);
						$aa += $nums[$i];
					}
					$i++;
				}
				foreach ($sendprop as $key => $val) {
					if (!empty($nums[$key])) {
						$sfcz = pdo_get("hc_user_prop", array("pid" => $val, "uid" => $uid, "weid" => $weid));
						if (empty($sfcz)) {
							$userprop = array("weid" => $weid, "uid" => $uid, "pid" => $val, "num" => $nums[$key]);
							pdo_insert("hc_user_prop", $userprop);
						} else {
							$userprop = array("num" => $sfcz["num"] + $nums[$key], "status" => 0);
							pdo_update("hc_user_prop", $userprop, array("weid" => $weid, "uid" => $uid, "pid" => $val));
						}
						$proplog = array("weid" => $weid, "uid" => $uid, "pid" => $val, "type" => 5, "top" => $self["id"], "num" => $nums[$key], "createtime" => time());
						pdo_insert("hc_user_proplog", $proplog);
					}
				}
				foreach ($sendprop as $key => $val) {
					if (!empty($nums[$key])) {
						$resarr[$key] = pdo_get("hc_prop", array("id" => $val, "weid" => $weid));
						$resarr[$key]["num"] = $nums[$key];
						$resarr[$key]["thumb"] = $_W["attachurl"] . $resarr[$key]["thumb"];
					}
				}
			}
		}
		return $this->result(0, "使用成功", $resarr);
	}
	public function doPageKnowledge()
	{
		global $_GPC, $_W;
		$weid = 1000;
		$uid = $_GPC["user_id"];
		$parent = pdo_getall("hc_question_type", array("pid" => 0, "weid" => $weid), array("id", "thumbs", "name"), '', "sort asc");
		foreach ($parent as $key => $val) {
			$child = pdo_getall("hc_question_type", array("pid" => $val["id"], "weid" => $weid), array(), '', "sort asc");
			foreach ($child as $k => $v) {
				$arr[$k] = pdo_get("hc_user_cate", array("weid" => $weid, "uid" => $uid, "tid" => $v["id"]));
				if (!empty($arr[$k])) {
					$child[$k]["level"] = $arr[$k]["level"];
					$child[$k]["plus"] = $arr[$k]["plus"];
				} else {
					$child[$k]["level"] = 0;
					$child[$k]["plus"] = 0;
				}
				$upgrade = json_decode($v["upgrade"], true);
				$child[$k]["next"] = $upgrade[$child[$k]["level"]];
				$userprop[$k] = pdo_get("hc_user_prop", array("weid" => $weid, "uid" => $uid, "pid" => $upgrade[0]["book"]), array("num", "usenum"));
				$child[$k]["maxlevel"] = count($upgrade);
				$child[$k]["have"] = $userprop[$k]["num"];
				$thumb[$k] = pdo_getcolumn("hc_prop", array("weid" => $weid, "id" => $child[$k]["next"]["book"]), array("thumb"));
				$child[$k]["next"]["thumb"] = $_W["attachurl"] . $thumb[$k];
			}
			$parent[$key]["child"] = $child;
			unset($arr);
			unset($child);
			unset($userprop);
			unset($upgrade);
		}
		return $this->result(0, "获取成功", $parent);
	}
	public function doPageUpgrade()
	{
		global $_GPC, $_W;
		$weid = 1000;
		$uid = $_GPC["user_id"];
		$tid = $_GPC["tid"];
		$upgrade = json_decode(pdo_getcolumn("hc_question_type", array("weid" => $weid, "id" => $tid), array("upgrade")), true);
		$level = pdo_getcolumn("hc_user_cate", array("weid" => $weid, "uid" => $uid, "tid" => $tid), array("level"));
		if (!empty($level)) {
			$need = $upgrade[$level];
		} else {
			$need = $upgrade[0];
		}
		if (empty($need)) {
			return $this->result(1, "已升到最高级");
		}
		$gold = pdo_getcolumn("hc_user_info", array("uid" => $uid, "weid" => $weid), array("gold"));
		if ($need["gold"] > $gold) {
			return $this->result(1, "金币不足");
		}
		$booknum = pdo_getcolumn("hc_user_prop", array("weid" => $weid, "uid" => $uid, "pid" => $need["book"]), array("num"));
		if ($need["num"] > $booknum) {
			return $this->result(1, "知识书不足");
		}
		$usercate = pdo_get("hc_user_cate", array("weid" => $weid, "uid" => $uid, "tid" => $tid));
		if (!empty($usercate)) {
			$cate = array("level" => $usercate["level"] + 1, "plus" => $need["score"]);
			pdo_update("hc_user_cate", $cate, array("weid" => $weid, "uid" => $uid, "tid" => $tid));
		} else {
			$cate = array("weid" => $weid, "uid" => $uid, "tid" => $tid, "level" => 1, "plus" => $need["score"]);
			pdo_insert("hc_user_cate", $cate);
		}
		$catelog = array("weid" => $weid, "uid" => $uid, "tid" => $tid, "before" => $usercate["level"], "now" => $usercate["level"] + 1, "book" => $need["book"], "num" => $need["num"], "gold" => $need["gold"], "createtime" => time());
		pdo_insert("hc_user_catelog", $catelog);
		pdo_update("hc_user_info", array("gold" => $gold - $need["gold"]), array("weid" => $weid, "uid" => $uid));
		pdo_insert("hc_user_gold", array("weid" => $weid, "uid" => $uid, "gold" => $need["gold"], "type" => 1, "addtime" => time()));
		$sfcz = pdo_get("hc_user_prop", array("pid" => $need["book"], "uid" => $uid, "weid" => $weid));
		if ($sfcz["num"] > $need["num"]) {
			$userprop["num"] = $sfcz["num"] - $need["num"];
			$userprop["usenum"] = $sfcz["usenum"] + $need["num"];
		} else {
			if ($sfcz["num"] == $need["num"]) {
				$userprop["num"] = $sfcz["num"] - $need["num"];
				$userprop["usenum"] = $sfcz["usenum"] + $need["num"];
				$userprop["status"] = 1;
			}
		}
		pdo_update("hc_user_prop", $userprop, array("weid" => $weid, "uid" => $uid, "pid" => $need["book"]));
		$proplog = array("weid" => $weid, "uid" => $uid, "pid" => $need["book"], "type" => 4, "num" => $need["num"], "createtime" => time());
		pdo_insert("hc_user_proplog", $proplog);
		return $this->result(0, "升级成功");
	}
	public function doPageShare()
	{
		global $_GPC, $_W;
		$weid = 1000;
		$uid = $_GPC["user_id"];
		$cate = pdo_getall("hc_question_type", array("pid" => 0, "weid" => $weid), array("id", "name", "desc1", "desc2"), '', "id ASC", array(1, 6));
		foreach ($cate as $key => $val) {
			$cid = pdo_getall("hc_question_type", array("pid" => $val["id"], "weid" => $weid), array("id"));
			foreach ($cid as $k => $v) {
				$tid[] = "'" . $v["id"] . "'";
			}
			$gid = implode(",", $tid);
			$all = pdo_fetch("SELECT count(*) as num FROM " . tablename("hc_pk_question") . " AS a LEFT JOIN " . tablename("hc_question") . " AS b ON a.qid=b.id WHERE a.uid = :uid AND a.weid=:weid AND type_id in(" . $gid . ")", array(":uid" => $uid, ":weid" => $weid));
			$right = pdo_fetch("SELECT count(*) as num FROM " . tablename("hc_pk_question") . " AS a LEFT JOIN " . tablename("hc_question") . " AS b ON a.qid=b.id WHERE a.uid = :uid AND a.weid=:weid AND type_id in(" . $gid . ") AND a.right=1", array(":uid" => $uid, ":weid" => $weid));
			$cator[] = ceil(270 * ($right["num"] / $all["num"]));
			$indicator[$key]["name"] = $val["name"];
			$indicator[$key]["max"] = 270;
			unset($cid);
			unset($tid);
			unset($gid);
		}
		foreach ($cator as &$key) {
			if ($key == 0) {
				$key = 130;
			}
		}
		return $this->result(0, "获取成功", array("indicator" => $indicator, "cator" => $cator, "cate" => $cate));
	}
	public function doPageLookshare()
	{
		global $_GPC, $_W;
		$weid = 1000;
		$uid = $_GPC["user_id"];
		$userinfo = pdo_get("hc_user", array("weid" => $weid, "uid" => $uid), array("uid", "nickname", "avatar"));
		$userinfo1 = pdo_get("hc_user_info", array("weid" => $weid, "uid" => $uid), array("level", "expe", "maxexpe", "dan"));
		$userinfo1["name"] = pdo_getcolumn("hc_dan", array("weid" => $weid, "dan_id" => $userinfo1["dan"]), array("name"));
		$user = array_merge($userinfo, $userinfo1);
		$cate = pdo_getall("hc_question_type", array("pid" => 0, "weid" => $weid), array("id", "name", "desc1", "desc2"), '', "id ASC", array(1, 6));
		foreach ($cate as $key => $val) {
			$cid = pdo_getall("hc_question_type", array("pid" => $val["id"], "weid" => $weid), array("id"));
			foreach ($cid as $k => $v) {
				$tid[] = "'" . $v["id"] . "'";
			}
			$gid = implode(",", $tid);
			$all = pdo_fetch("SELECT count(*) as num FROM " . tablename("hc_pk_question") . " AS a LEFT JOIN " . tablename("hc_question") . " AS b ON a.qid=b.id WHERE a.uid = :uid AND a.weid=:weid AND type_id in(" . $gid . ")", array(":uid" => $uid, ":weid" => $weid));
			$right = pdo_fetch("SELECT count(*) as num FROM " . tablename("hc_pk_question") . " AS a LEFT JOIN " . tablename("hc_question") . " AS b ON a.qid=b.id WHERE a.uid = :uid AND a.weid=:weid AND type_id in(" . $gid . ") AND a.right=1 ", array(":uid" => $uid, ":weid" => $weid));
			$cator[] = ceil(270 * ($right["num"] / $all["num"]));
			$indicator[$key]["name"] = $val["name"];
			$indicator[$key]["max"] = 270;
			unset($cid);
			unset($tid);
			unset($gid);
		}
		$zcc = pdo_fetchcolumn("SELECT count(*) as num FROM " . tablename("hc_pk_record") . " WHERE (userid_one = :uid OR userid_two=:uid) AND weid=:weid ", array(":uid" => $uid, ":weid" => $weid));
		$win = pdo_fetchcolumn("SELECT count(*) as num FROM " . tablename("hc_pk_record") . " WHERE (userid_one = :uid AND status1=1) OR (userid_two=:uid AND status2=1) AND weid=:weid ", array(":uid" => $uid, ":weid" => $weid));
		$winrate = ceil($win / $zcc * 100) . "%";
		foreach ($cator as &$key) {
			if ($key == 0) {
				$key = 130;
			}
		}
		return $this->result(0, "获取成功", array("indicator" => $indicator, "cator" => $cator, "user" => $user, "bottom" => array("zcc" => $zcc, "win" => $win, "winrate" => $winrate)));
	}
	public function doPageShareimg()
	{
		global $_GPC, $_W;
		$weid = 1000;
		$uid = $_GPC["user_id"];
		$model = new HcfkModel();
		$userinfo = pdo_get("hc_user", array("weid" => $weid, "uid" => $uid));
		$filename = "qrcode_" . $uid . ".jpg";
		$basic = json_decode(pdo_getcolumn("hc_setting", array("weid" => $weid), array("basic")), true);
		$bg = empty($basic["shareimg"]) ? IA_ROOT . "/addons/hc_answer/public/sharebg.jpg" : $_W["attachurl"] . $basic["shareimg"];
		$avatar_url = $userinfo["avatar"];
		$wxapp_url = $model->wxappqrcode($uid);
		$cate = pdo_getall("hc_question_type", array("pid" => 0, "weid" => $weid), array("id", "name", "desc1", "desc2"), '', "id ASC", array(1, 6));
		foreach ($cate as $key => $val) {
			$cid = pdo_getall("hc_question_type", array("pid" => $val["id"], "weid" => $weid), array("id"));
			foreach ($cid as $k => $v) {
				$tid[] = "'" . $v["id"] . "'";
			}
			$gid = implode(",", $tid);
			$all = pdo_fetch("SELECT count(*) as num FROM " . tablename("hc_pk_question") . " AS a LEFT JOIN " . tablename("hc_question") . " AS b ON a.qid=b.id WHERE a.uid = :uid AND a.weid=:weid AND type_id in(" . $gid . ")", array(":uid" => $uid, ":weid" => $weid));
			$right = pdo_fetch("SELECT count(*) as num FROM " . tablename("hc_pk_question") . " AS a LEFT JOIN " . tablename("hc_question") . " AS b ON a.qid=b.id WHERE a.uid = :uid AND a.weid=:weid AND type_id in(" . $gid . ") AND a.right=1", array(":uid" => $uid, ":weid" => $weid));
			$cator[] = ceil(270 * ($right["num"] / $all["num"]));
			$name[] = $val["name"];
			unset($cid);
			unset($tid);
			unset($gid);
		}
		$min = $cator[0];
		$minkey = 0;
		$i = 1;
		while ($i < count($cator)) {
			if ($cator[$i] < $min) {
				$min = $cator[$i];
				$minkey = $i;
			}
			$i++;
		}
		foreach ($cator as &$key) {
			if ($key == 0) {
				$key = 130;
			}
		}
		$power["desc"] = array($cate[$minkey]["desc1"], $cate[$minkey]["desc2"]);
		$power["cate"] = $name;
		$power["sc"] = $cator;
		$qrcode = $model->qrcode($bg, $avatar_url, $wxapp_url, $power, $filename);
		$result = $_W["siteroot"] . $qrcode;
		return $this->result(0, "获取成功", $result);
	}
	public function doPageForward()
	{
		global $_GPC, $_W;
		$weid = 1000;
		$uid = $_GPC["user_id"];
		$encryptedData = $_GPC["encryptedData"];
		$iv = $_GPC["iv"];
		$appid = pdo_getcolumn("account_wxapp", array("uniacid" => $_W["uniacid"]), array("key"));
		$sessionKey = pdo_getcolumn("hc_user", array("uid" => $uid, "weid" => $weid), array("sessionkey"));
		$pc = new WXBizDataCrypt($appid, $sessionKey);
		$errCode = $pc->decryptData($encryptedData, $iv, $data);
		$return = json_decode($data, true);
		$openGId = $return["openGId"];
		$tfnum = pdo_getcolumn("hc_user_forward", array("weid" => $weid, "uid" => $uid, "openGId" => $openGId, "createtime >" => strtotime(date("Y-m-d"))), array("count(*)"));
		if ($tfnum >= 1) {
			return $this->result(1, "分享奖励", "今天已经分享过了哦，换其它群试试吧");
		}
		if ($errCode == 0) {
			$userinfo = pdo_get("hc_user_info", array("uid" => $uid, "weid" => $weid), array("dan", "star"));
			if ($userinfo["star"] == 0) {
				$dan = max(1, $userinfo["dan"] - 1);
			} else {
				$dan = $userinfo["dan"];
			}
			$gold = pdo_getcolumn("hc_dan", array("dan_id" => $dan, "weid" => $weid), array("use_gold"));
			pdo_insert("hc_user_forward", array("weid" => $weid, "uid" => $uid, "openGId" => $openGId, "createtime" => time()));
			pdo_insert("hc_user_gold", array("weid" => $weid, "uid" => $uid, "gold" => $gold, "type" => 1, "source" => 1, "addtime" => time()));
			$usergold = pdo_getcolumn("hc_user_info", array("weid" => $weid, "uid" => $uid), array("gold"));
			pdo_update("hc_user_info", array("gold" => $usergold + $gold), array("weid" => $weid, "uid" => $uid));
			return $this->result(0, "分享奖励", $gold);
		} else {
			return $this->result(0, $errCode);
		}
	}
	public function doPageFriendspk()
	{
		global $_GPC, $_W;
		$weid = 1000;
		$uid = $_GPC["user_id"];
		$ques = $this->matchtime();
		$question = pdo_fetchall("SELECT id FROM " . tablename("hc_question") . " WHERE weid=:weid ORDER BY rand() LIMIT 1," . $ques["quesnum"], array(":weid" => $weid));
		foreach ($question as $key => $val) {
			if ($key + 1 < count($question)) {
				$qid .= $val["id"] . ",";
			} else {
				$qid .= $val["id"];
			}
		}
		$data = array("weid" => $weid, "userid_one" => $uid, "questions" => $qid, "createtime" => time(), "type" => 1);
		$res = pdo_insert("hc_pk_record", $data);
		if (!empty($res)) {
			$rid = pdo_insertid();
			return $this->result(0, "邀请好友去吧", $rid);
		} else {
			return $this->result(1, "操作失败");
		}
	}
	public function doPageFriendsjoin()
	{
		global $_GPC, $_W;
		$weid = 1000;
		$uid = $_GPC["user_id"];
		$rid = $_GPC["rid"];
		$data = array("userid_two" => $uid, "leave" => 1);
		$res = pdo_update("hc_pk_record", $data, array("weid" => $weid, "id" => $rid));
		$uid1 = pdo_getcolumn("hc_pk_record", array("weid" => $weid, "id" => $rid), array("userid_one"));
		if (!empty($res)) {
			$ihave = pdo_get("hc_user_friends", array("uid" => $uid1, "weid" => $weid, "fid" => $uid));
			if (empty($ihave)) {
				pdo_insert("hc_user_friends", array("uid" => $uid1, "weid" => $weid, "fid" => $uid));
			}
			$user = pdo_get("hc_user", array("uid" => $uid1, "weid" => $weid), array("uid", "avatar", "nickname"));
			return $this->result(0, "去对战吧", $user);
		} else {
			return $this->result(1, "操作失败");
		}
	}
	public function doPageCheckjoin()
	{
		global $_GPC, $_W;
		$weid = 1000;
		$uid = $_GPC["user_id"];
		$rid = $_GPC["rid"];
		$user = pdo_get("hc_pk_record", array("weid" => $weid, "id" => $rid, "type" => 1, "leave" => 1), array("userid_one", "userid_two"));
		if (!empty($user)) {
			$user1 = pdo_get("hc_user", array("uid" => $user["userid_one"], "weid" => $weid), array("uid", "avatar", "nickname"));
			if (!empty($user1)) {
				$user1["fq"] = 1;
			}
			$user2 = pdo_get("hc_user", array("uid" => $user["userid_two"], "weid" => $weid), array("uid", "avatar", "nickname"));
			if (!empty($user2)) {
				$user2["fq"] = 0;
			}
			return $this->result(0, "去对战吧", array($user1, $user2));
		} else {
			return $this->result(0, "继续等待");
		}
	}
	public function doPageStartstatus()
	{
		global $_GPC, $_W;
		$weid = 1000;
		$rid = $_GPC["rid"];
		$leave = pdo_getcolumn("hc_pk_record", array("weid" => $weid, "id" => $rid), array("leave"));
		if ($leave == 2) {
			return $this->result(0, "开始答题", $leave);
		} else {
			if ($leave == 3) {
				return $this->result(0, "对方已退出", $leave);
			} else {
				return $this->result(0, "继续等待");
			}
		}
	}
	public function doPageStartans()
	{
		global $_GPC, $_W;
		$weid = 1000;
		$rid = $_GPC["rid"];
		$res = pdo_update("hc_pk_record", array("leave" => 2), array("weid" => $weid, "id" => $rid));
		return $this->result(0, "开始答题");
	}
	public function doPageFriendsquit()
	{
		global $_GPC, $_W;
		$weid = 1000;
		$uid = $_GPC["user_id"];
		$rid = $_GPC["rid"];
		$res = pdo_update("hc_pk_record", array("leave" => 3), array("weid" => $weid, "id" => $rid));
		return $this->result(0, "退出成功");
	}
	public function doPageRanklist()
	{
		global $_GPC, $_W;
		$weid = 1000;
		$uid = $_GPC["user_id"];
		$type = $_GPC["type"];
		$sid = $this->currseason();
		if ($type == "frends") {
			$friends = pdo_getall("hc_user_friends", array("uid" => $uid, "weid" => $weid), array("fid"));
			if (!empty($friends)) {
				$gid[$uid] = $uid;
				foreach ($friends as $key => $val) {
					$gid[$val["fid"]] = $val["fid"];
				}
				$gid = implode(",", $gid);
				$list = pdo_fetchall("SELECT level,dan,star,uid FROM " . tablename("hc_user_info") . " WHERE weid=:weid AND uid in(" . $gid . ") AND sid=" . $sid . " order by dan desc,star desc,level desc", array(":weid" => $weid));
				foreach ($list as $key => $val) {
					$list[$key]["sort"] = $key + 1;
					$list[$key]["dan"] = pdo_getcolumn("hc_dan", array("dan_id" => $val["dan"], "weid" => $weid), array("name"));
					$user = pdo_get("hc_user", array("uid" => $val["uid"]), array("avatar", "nickname", "city"));
					$list[$key]["avatar"] = $user["avatar"];
					$list[$key]["nickname"] = $user["nickname"];
					$list[$key]["city"] = $user["city"];
					unset($user);
				}
			}
		} else {
			if ($type == "last") {
				if ($sid == 1) {
					return $this->result(1, "没有上赛季数据");
				}
				$last = $this->lastseason();
				$list = pdo_getall("hc_user_history", array("weid" => $weid, "sid" => $last), array("level", "dan", "star", "uid"), '', "dan desc,star desc,level desc", array(1, 100));
				foreach ($list as $key => $val) {
					$list[$key]["dan"] = pdo_getcolumn("hc_dan", array("dan_id" => $val["dan"], "weid" => $weid), array("name"));
					$user = pdo_get("hc_user", array("uid" => $val["uid"]), array("avatar", "nickname", "city"));
					$list[$key]["avatar"] = $user["avatar"];
					$list[$key]["nickname"] = $user["nickname"];
					$list[$key]["city"] = $user["city"];
					unset($user);
				}
			} else {
				$list = pdo_getall("hc_user_info", array("weid" => $weid, "sid" => $sid), array("level", "dan", "star", "uid"), '', "dan desc,star desc,level desc", array(1, 100));
				foreach ($list as $key => $val) {
					$list[$key]["sort"] = $key + 1;
					$list[$key]["dan"] = pdo_getcolumn("hc_dan", array("dan_id" => $val["dan"], "weid" => $weid), array("name"));
					$user = pdo_get("hc_user", array("uid" => $val["uid"]), array("avatar", "nickname", "city"));
					$list[$key]["avatar"] = $user["avatar"];
					$list[$key]["nickname"] = $user["nickname"];
					$list[$key]["city"] = $user["city"];
					unset($user);
				}
			}
		}
		return $this->result(0, "获取成功", $list);
	}
	public function doPageDanlist()
	{
		global $_GPC, $_W;
		$weid = 1000;
		$list = pdo_getall("hc_dan", array("weid" => $weid), array(), '', "dan_id DESC");
		foreach ($list as $key => $val) {
			if (!empty($val["border"])) {
				$list[$key]["border"] = $_W["attachurl"] . $val["border"];
			}
			$reward = pdo_get("hc_prop", array("weid" => $weid, "id" => $val["reward"]), array("name", "thumb"));
			$list[$key]["rewardname"] = $reward["name"];
			if (!empty($reward["thumb"])) {
				$list[$key]["rewardimg"] = $_W["attachurl"] . $reward["thumb"];
			}
		}
		return $this->result(0, "获取成功", $list);
	}
	public function doPageGetformid()
	{
		global $_GPC, $_W;
		$uid = $_GPC["user_id"];
		$formid = $_GPC["formid"];
		if ($formid != "undefined") {
			pdo_insert("hc_formid", array("weid" => 1000, "uid" => $uid, "formid" => $formid));
		}
		return $this->result(0, "OK");
	}
	public function doPageSeason()
	{
		global $_GPC, $_W;
		$uid = $_GPC["user_id"];
		$type = $_GPC["type"];
		$weid = 1000;
		$sid = $this->lastseason();
		$activ = $this->active();
		switch ($type) {
			case "rank":
				$list = pdo_getall("hc_user_history", array("sid" => $sid, "weid" => $weid), array("level", "dan", "star", "uid", "sid"), '', "dan desc,star desc,level desc", array(1, 3));
				$list["3"] = pdo_get("hc_user_history", array("sid" => $sid, "weid" => $weid, "uid" => $uid), array("level", "dan", "star", "uid", "sid"));
				foreach ($list as $key => $val) {
					$list[$key]["sort"] = $key + 1;
					$list[$key]["dan"] = pdo_getcolumn("hc_dan", array("dan_id" => $val["dan"], "weid" => $weid), array("name"));
					$user = pdo_get("hc_user", array("uid" => $val["uid"]), array("avatar", "nickname", "city"));
					$list[$key]["avatar"] = $user["avatar"];
					$list[$key]["nickname"] = $user["nickname"];
					$list[$key]["city"] = $user["city"];
					unset($user);
					if ($list[3]["uid"] == $val["uid"] && $key < 3) {
						$isunset = 1;
					}
				}
				if ($isunset == 1) {
					unset($list[3]);
				}
				$name = pdo_getcolumn("hc_season", array("weid" => $weid, "id" => $sid), array("name"));
				return $this->result(0, "获取成功", array("list" => $list, "name" => $name));
				break;
			case "border":
				$dan = pdo_getcolumn("hc_user_history", array("sid" => $sid, "weid" => $weid, "uid" => $uid), array("dan"));
				$res = pdo_get("hc_dan", array("dan_id" => $dan, "weid" => $weid), array("name", "border"));
				$res["border"] = $_W["attachurl"] . $res["border"];
				return $this->result(0, "获取成功", $res);
				break;
			case "prop":
				$dan = pdo_getcolumn("hc_user_history", array("sid" => $sid, "weid" => $weid, "uid" => $uid), array("dan"));
				$res = pdo_get("hc_dan", array("dan_id" => $dan, "weid" => $weid), array("reward", "rewardnum"));
				$res["thumb"] = $_W["attachurl"] . pdo_getcolumn("hc_prop", array("id" => $res["reward"], "weid" => $weid), array("thumb"));
				return $this->result(0, "获取成功", $res);
				break;
			case "money":
				$dan = pdo_getcolumn("hc_user_history", array("sid" => $sid, "weid" => $weid, "uid" => $uid), array("dan"));
				$maxdan = pdo_getcolumn("hc_dan", array("weid" => $weid, "id" => $activ["condition"]), array("dan_id"));
				if ($dan >= $maxdan) {
					$res["status"] = 1;
					$res["img"] = $_W["attachurl"] . $activ["reachimg"];
					$res["text"] = $activ["reachtext"];
				} else {
					$res["status"] = 0;
					$res["img"] = $_W["attachurl"] . $activ["unreachimg"];
					$res["text"] = $activ["unreachtext"];
				}
				return $this->result(0, "获取成功", $res);
				break;
			default:
				$curr = $this->currseason();
				$season = pdo_get("hc_season", array("id" => $curr, "weid" => $weid));
				$res["name"] = $season["name"];
				$res["starttime"] = date("Y年m月d日", $season["starttime"]);
				$res["endtime"] = date("Y年m月d日", $season["endtime"]);
				return $this->result(0, "获取成功", $res);
		}
	}
	public function doPageGetreward()
	{
		global $_GPC, $_W;
		$uid = $_GPC["user_id"];
		$type = $_GPC["type"];
		$weid = 1000;
		$sid = $this->lastseason();
		$activ = $this->active();
		switch ($type) {
			case "border":
				$dan = pdo_getcolumn("hc_user_history", array("sid" => $sid, "weid" => $weid, "uid" => $uid), array("dan"));
				$res = pdo_get("hc_dan", array("dan_id" => $dan, "weid" => $weid), array("name", "border"));
				if (!empty($res["border"])) {
					$border = $_W["attachurl"] . $res["border"];
					pdo_update("hc_user", array("border" => $border), array("uid" => $uid, "weid" => $weid));
				}
				pdo_update("hc_user_history", array("status" => 2), array("sid" => $sid, "weid" => $weid, "uid" => $uid));
				return $this->result(0, "领取成功");
				break;
			case "prop":
				$dan = pdo_getcolumn("hc_user_history", array("sid" => $sid, "weid" => $weid, "uid" => $uid), array("dan"));
				$res = pdo_get("hc_dan", array("dan_id" => $dan, "weid" => $weid), array("reward", "rewardnum"));
				$sfcz = pdo_get("hc_user_prop", array("pid" => $res["reward"], "uid" => $uid, "weid" => $weid));
				if (empty($sfcz)) {
					$prop = array("weid" => $weid, "uid" => $uid, "pid" => $res["reward"], "num" => $res["rewardnum"]);
					pdo_insert("hc_user_prop", $prop);
				} else {
					$userprop = array("num" => $sfcz["num"] + $res["rewardnum"], "status" => 0);
					pdo_update("hc_user_prop", $prop, array("weid" => $weid, "uid" => $uid, "pid" => $res["reward"]));
				}
				$data = array("weid" => $weid, "uid" => $uid, "pid" => $res["rewardnum"], "type" => 3, "num" => $res["reward"], "createtime" => time());
				pdo_insert("hc_user_proplog", $data);
				return $this->result(0, "获取成功", $res);
				break;
			case "money":
				$dan = pdo_getcolumn("hc_user_history", array("sid" => $sid, "weid" => $weid, "uid" => $uid), array("dan"));
				$active = json_decode(pdo_getcolumn("hc_setting", array("weid" => $weid), array("active")), true);
				$maxdan = pdo_getcolumn("hc_dan", array("weid" => $weid, "id" => $active["condition"]), array("dan_id"));
				if ($dan >= $maxdan) {
					$people = pdo_getcolumn("hc_user_history", array("sid" => $sid, "weid" => $weid, "dan" => $maxdan), array("count(*)"));
					pdo_insert("hc_bonus", array("weid" => $weid, "sid" => $sid, "uid" => $uid, "money" => $active["money"], "addtime" => time()));
					$account_api = WeAccount::create();
					$token = $account_api->getAccessToken();
					$url = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=" . $token;
					$tpl = pdo_getcolumn("hc_setting", array("weid" => $weid), array("tpl"));
					$tpl = json_decode($tpl, true);
					$users = pdo_fetch("SELECT a.openid,b.formid FROM " . tablename("hc_user") . " AS a LEFT JOIN " . tablename("hc_formid") . " AS b ON a.uid=b.uid WHERE a.weid=:weid AND a.uid=:uid AND b.formid !='' AND b.status=0", array(":weid" => $weid, ":uid" => $uid));
					$data["touser"] = $users["openid"];
					$data["template_id"] = $tpl["red"];
					$data["form_id"] = $users["formid"];
					$data["data"]["keyword1"]["value"] = $active["activename"];
					$data["data"]["keyword1"]["color"] = "#173177";
					$data["data"]["keyword2"]["value"] = $active["money"];
					$data["data"]["keyword2"]["color"] = "#173177";
					$data["data"]["keyword3"]["value"] = $active["moneytime"];
					$data["data"]["keyword3"]["color"] = "#173177";
					$data["emphasis_keyword"] = "keyword2.DATA";
					$data["page"] = "hc_answer/pages/index/index";
					$json = json_encode($data);
					$res = ihttp_post($url, $json);
					if ($res["status"] == "OK") {
						pdo_update("hc_formid", array("status" => 1), array("formid" => $users["formid"]));
					}
					return $this->result(0, "获取成功");
				} else {
					return $this->result(0, "不符合领取条件");
				}
				break;
			default:
		}
	}
	public function doPageActivetx()
	{
		global $_GPC, $_W;
		$uid = $_GPC["user_id"];
		$weid = 1000;
		$account_api = WeAccount::create();
		$token = $account_api->getAccessToken();
		$url = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=" . $token;
		$tpl = pdo_getcolumn("hc_setting", array("weid" => $weid), array("tpl"));
		$tpl = json_decode($tpl, true);
		$season = pdo_get("hc_season", array("status" => 1, "weid" => 1000), array("starttime", "endtime"));
		$active = json_decode(pdo_getcolumn("hc_setting", array("weid" => $weid), array("active")), true);
		$users = pdo_fetch("SELECT a.openid,b.formid FROM " . tablename("hc_user") . " AS a LEFT JOIN " . tablename("hc_formid") . " AS b ON a.uid=b.uid WHERE a.weid=:weid AND a.uid=:uid AND b.formid !='' AND b.status=0", array(":weid" => $weid, ":uid" => $uid));
		$data["touser"] = $users["openid"];
		$data["template_id"] = $tpl["act"];
		$data["form_id"] = $users["formid"];
		$data["data"]["keyword1"]["value"] = $active["activename"];
		$data["data"]["keyword1"]["color"] = "#173177";
		$data["data"]["keyword2"]["value"] = date("Y-m-d H:i", $season["endtime"]) . "截止";
		$data["data"]["keyword2"]["color"] = "#173177";
		$data["data"]["keyword3"]["value"] = date("Y-m-d H:i");
		$data["data"]["keyword3"]["color"] = "#173177";
		$data["data"]["keyword4"]["value"] = "恭喜您参与本次活动";
		$data["data"]["keyword4"]["color"] = "#173177";
		$data["emphasis_keyword"] = "keyword1.DATA";
		$data["page"] = "hc_answer/pages/index/index";
		$json = json_encode($data);
		$res = ihttp_post($url, $json);
		if ($res["status"] == "OK") {
			pdo_update("hc_formid", array("status" => 1), array("formid" => $users["formid"]));
		}
		return $this->result(0, "操作成功");
	}
	public function doPageRichactive()
	{
		global $_GPC, $_W;
		$uid = $_GPC["user_id"];
		$weid = 1000;
		$sid = $this->currseason();
		$dan = pdo_getcolumn("hc_user_info", array("sid" => $sid, "weid" => $weid, "uid" => $uid), array("dan"));
		$active = json_decode(pdo_getcolumn("hc_setting", array("weid" => $weid), array("active")), true);
		$maxdan = pdo_getcolumn("hc_dan", array("weid" => $weid, "id" => $active["condition"]), array("dan_id"));
		if ($dan >= $maxdan) {
			$res["status"] = 1;
			$res["reachimg"] = $_W["attachurl"] . $active["reachimg"];
			$res["moneytime"] = $active["moneytime"];
			return $this->result(0, "获取成功", $res);
		} else {
			return $this->result(0, "不符合领取条件");
		}
	}
	public function doPageUploadmoneycode()
	{
		global $_GPC, $_W;
		$uid = $_GPC["user_id"];
		$moneycode = $_GPC["moneycode"];
		$weid = 1000;
		pdo_update("hc_user", array("moneycode" => $moneycode), array("weid" => $weid, "uid" => $uid));
		return $this->result(0, "提交成功");
	}
	public function doPageSys()
	{
		global $_GPC, $_W;
		$conf = pdo_get("hc_setting", array("weid" => 1000));
		$answer = array("success" => $_W["siteroot"] . "addons/hc_answer/public/success.png", "draw" => $_W["siteroot"] . "addons/hc_answer/public/draw.png", "share2" => $_W["siteroot"] . "addons/hc_answer/public/share2.png");
		$conf["answer"] = json_encode($answer);
		$basic = json_decode($conf["basic"], true);
		$basic["loginimg"] = !empty($basic["loginimg"]) ? $_W["attachurl"] . $basic["loginimg"] : '';
		$basic["answerimg"] = !empty($basic["answerimg"]) ? $_W["attachurl"] . $basic["answerimg"] : '';
		$basic["indextopimg"] = !empty($basic["indextopimg"]) ? $_W["attachurl"] . $basic["indextopimg"] : '';
		$basic["dantopimg"] = !empty($basic["dantopimg"]) ? $_W["attachurl"] . $basic["dantopimg"] : '';
		$basic["knowledgetopimg"] = !empty($basic["knowledgetopimg"]) ? $_W["attachurl"] . $basic["knowledgetopimg"] : '';
		$basic["matchingimg"] = !empty($basic["matchingimg"]) ? $_W["attachurl"] . $basic["matchingimg"] : '';
		$basic["matchedimg"] = !empty($basic["matchedimg"]) ? $_W["attachurl"] . $basic["matchedimg"] : '';
		$basic["kfinto"] = !empty($basic["kfinto"]) ? $_W["attachurl"] . $basic["kfinto"] : '';
		$basic["indexbgm"] = !empty($basic["indexbgm"]) ? $_W["attachurl"] . $basic["indexbgm"] : '';
		$basic["rankbgm"] = !empty($basic["rankbgm"]) ? $_W["attachurl"] . $basic["rankbgm"] : '';
		$basic["wxgzhimg"] = !empty($basic["wxgzhimg"]) ? $_W["attachurl"] . $basic["wxgzhimg"] : '';
		$basic["ownpowerimg"] = !empty($basic["ownpowerimg"]) ? $_W["attachurl"] . $basic["ownpowerimg"] : '';
		$basic["ownmoneyimg"] = !empty($basic["ownmoneyimg"]) ? $_W["attachurl"] . $basic["ownmoneyimg"] : '';
		$basic["owninfoimg"] = !empty($basic["owninfoimg"]) ? $_W["attachurl"] . $basic["owninfoimg"] : '';
		$basic["danintoimg"] = !empty($basic["danintoimg"]) ? $_W["attachurl"] . $basic["danintoimg"] : '';
		$basic["inviteimg"] = !empty($basic["inviteimg"]) ? $_W["attachurl"] . $basic["inviteimg"] : '';
		$basic["giveupimg"] = !empty($basic["giveupimg"]) ? $_W["attachurl"] . $basic["giveupimg"] : '';
		$basic["gdbarimg"] = !empty($basic["gdbarimg"]) ? $_W["attachurl"] . $basic["gdbarimg"] : '';
		$basic["starlevelimg"] = !empty($basic["starlevelimg"]) ? $_W["attachurl"] . $basic["starlevelimg"] : '';
		$basic["avatarleftimg"] = !empty($basic["avatarleftimg"]) ? $_W["attachurl"] . $basic["avatarleftimg"] : '';
		$basic["avatarrightimg"] = !empty($basic["avatarrightimg"]) ? $_W["attachurl"] . $basic["avatarrightimg"] : '';
		$basic["questypeimg"] = !empty($basic["questypeimg"]) ? $_W["attachurl"] . $basic["questypeimg"] : '';
		$basic["ranknumimg"] = !empty($basic["ranknumimg"]) ? $_W["attachurl"] . $basic["ranknumimg"] : '';
		$basic["ownpowershareimg"] = !empty($basic["ownpowershareimg"]) ? $_W["attachurl"] . $basic["ownpowershareimg"] : '';
		$basic["expeimg"] = !empty($basic["expeimg"]) ? $_W["attachurl"] . $basic["expeimg"] : '';
		$basic["goldimg"] = !empty($basic["goldimg"]) ? $_W["attachurl"] . $basic["goldimg"] : '';
		$basic["pplineimg"] = !empty($basic["pplineimg"]) ? $_W["attachurl"] . $basic["pplineimg"] : '';
		$basic["doublescoreimg"] = !empty($basic["doublescoreimg"]) ? $_W["attachurl"] . $basic["doublescoreimg"] : '';
		$basic["seasonbgimg"] = !empty($basic["seasonbgimg"]) ? $_W["attachurl"] . $basic["seasonbgimg"] : '';
		$basic["anssuccessimg"] = !empty($basic["anssuccessimg"]) ? $_W["attachurl"] . $basic["anssuccessimg"] : '';
		$basic["ansdrawimg"] = !empty($basic["ansdrawimg"]) ? $_W["attachurl"] . $basic["ansdrawimg"] : '';
		$conf["basic"] = json_encode($basic);
		$forward = json_decode($conf["forward"], true);
		$forward["img"] = !empty($forward["img"]) ? $_W["attachurl"] . $forward["img"] : '';
		$conf["forward"] = json_encode($forward);
		$ques = json_decode($conf["ques"], true);
		$ques["countscore"] = $ques["maxscore"] * ($ques["quesnum"] - 1) + $ques["lastscore"] + 100;
		$ques["rightmp3"] = !empty($ques["rightmp3"]) ? $_W["attachurl"] . $ques["rightmp3"] : '';
		$ques["errormp3"] = !empty($ques["errormp3"]) ? $_W["attachurl"] . $ques["errormp3"] : '';
		$ques["descbgm"] = !empty($ques["descbgm"]) ? $_W["attachurl"] . $ques["descbgm"] : '';
		$conf["ques"] = json_encode($ques);
		$active = json_decode($conf["active"], true);
		$active["entry"] = !empty($active["entry"]) ? $_W["attachurl"] . $active["entry"] : '';
		$active["click"] = !empty($active["click"]) ? $_W["attachurl"] . $active["click"] : '';
		$active["reachimg"] = !empty($active["reachimg"]) ? $_W["attachurl"] . $active["reachimg"] : '';
		$active["unreachimg"] = !empty($active["unreachimg"]) ? $_W["attachurl"] . $active["unreachimg"] : '';
		$active["dan_id"] = pdo_getcolumn("hc_dan", array("id" => $active["condition"]), array("dan_id"));
		$active["condition"] = pdo_getcolumn("hc_dan", array("id" => $active["condition"]), array("name"));
		$conf["active"] = json_encode($active);
		return $this->result(0, "获取成功", $conf);
	}
	public function doPageUploadimg()
	{
		global $_GPC, $_W;
		if (empty($_FILES["image"])) {
			return $this->result(1, "请上传图片！");
		}
		$type = $_FILES["image"]["type"];
		$type = explode("/", $type);
		$newfilename = "wxapp" . date("YmdHis") . rand(1000, 9999);
		$dir = IA_ROOT . "/addons/hc_answer/upload/";
		if (!file_exists($dir)) {
			mkdir($dir);
			chmod($dir, 0777);
		}
		if (move_uploaded_file($_FILES["image"]["tmp_name"], "../addons/hc_answer/upload/" . $newfilename . "." . $type[1])) {
			$thumb = $_W["siteroot"] . "addons/hc_answer/upload/" . $newfilename . "." . $type[1];
			return $this->result(0, "上传成功", $thumb);
		} else {
			return $this->result(1, "上传失败");
		}
	}
	public function doPageHome()
	{
		global $_GPC, $_W;
		$set = json_decode(pdo_getcolumn("hc_setting", array("weid" => 1000), array("basic")), true);
		if ($set["version"] == $_GPC["v"]) {
			$setup["stake"] = 1;
		} else {
			$setup["stake"] = 0;
		}
		$shenhe = pdo_getall("hc_shenhe", array("stact" => 1, "weid" => 1000), array(), '', "sort DESC", array(1, 6));
		foreach ($shenhe as $key => $val) {
			$val["img"] = $_W["attachurl"] . $val["img"];
			if ($key > 2) {
				$list["right"][$key] = $val;
			} else {
				$list["left"][$key] = $val;
			}
		}
		$setup["shenhe"] = $list;
		$this->result(0, "获取成功", $setup);
	}
	public function doPageWenzhang()
	{
		global $_GPC, $_W;
		$shenhe = pdo_get("hc_shenhe", array("id" => $_GPC["id"]));
		$this->result(0, "获取成功", $shenhe);
	}
	public function doPageAddpeople()
	{
		global $_GPC, $_W;
		$active = json_decode(pdo_getcolumn("hc_setting", array("weid" => 1000), array("active")), true);
		$season = pdo_get("hc_season", array("status" => 1, "weid" => 1000));
		$maxnum = round($active["truemoney"] / $active["money"]) - $active["truepeople"];
		$maxdan = pdo_get("hc_dan", array("weid" => 1000, "id" => $active["condition"]), array("dan_id", "win_star"));
		$currtime = ceil((time() - $season["starttime"]) / 86400);
		if ($currtime >= $active["xnnumstart"]) {
			$days = round(($season["endtime"] - time()) / 86400) - $active["xnnumstart"] + 1;
		} else {
			$days = round(($season["endtime"] - $season["starttime"]) / 86400) - $active["xnnumstart"] + 1;
		}
		$everyday = round($maxnum / $days);
		$everyhour = round($everyday / 24);
		$everymins = round($everyhour / 60);
		if ($everyday > 1) {
			if ($everyhour > 1) {
				if ($everymins > 1) {
					$plan = "1分钟执行一次" . $everymins;
				} else {
					$xnnum = 1;
					$plan = ceil(60 / $everyhour) . "分钟执行一次";
				}
			} else {
				$xnnum = 1;
				$plan = ceil(24 / $everyday) . "小时执行一次";
			}
		}
		$num = pdo_fetchcolumn("SELECT count(*) FROM " . tablename("hc_user_info") . " WHERE sid= :sid AND weid=:weid AND (dan > :dan OR (dan=:dan AND star=:star)) ", array(":sid" => $season["id"], ":weid" => 1000, ":dan" => $maxdan["dan_id"], ":star" => $maxdan["win_star"]));
		$start = $active["xnnumstart"] * 86400;
		if ($season["starttime"] + $start < time()) {
			$active["maxxnnum"] = $maxnum;
			$active["people"] = $active["people"] + $xnnum;
			$active["truepeople"] = $num;
		}
		pdo_update("hc_setting", array("active" => json_encode($active)), array("weid" => 1000));
	}
}