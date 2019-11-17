<?php
defined('IN_IA') or exit('Access Denied');
require_once IA_ROOT . '/addons/hc_answer/functions.php';
class Hc_answerModuleSite extends WeModuleSite
{
	public function doWebMain()
	{
		global $_GPC, $_W;
		include $this->template('main');
	}
	public function doWebBasic()
	{
		global $_GPC, $_W;
		$res = pdo_get('hc_setting', array("weid" => 1000));
		$info['index'] = json_decode($res['index'], true);
		$info['basic'] = json_decode($res['basic'], true);
		$info['ques'] = json_decode($res['ques'], true);
		$info['follow'] = json_decode($res['follow'], true);
		$info['forward'] = json_decode($res['forward'], true);
		$info['notice'] = json_decode($res['notice'], true);
		$info['sign'] = json_decode($res['sign'], true);
		$info['tpl'] = json_decode($res['tpl'], true);
		$info['active'] = json_decode($res['active'], true);
		$sid = pdo_getcolumn('hc_season', array("status" => 1, "weid" => 1000), array("id"));
		$dan = pdo_getall('hc_dan', array("weid" => 1000), array(), '', 'dan_id desc');
		$ad = pdo_getall('hc_ad', array("weid" => 1000), array(), '', 'id asc');
		$active = json_decode(pdo_getcolumn('hc_setting', array("weid" => 1000), array("active")), true);
		$season = pdo_get('hc_season', array("status" => 1, "weid" => 1000));
		$currtime = ceil((time() - $season['starttime']) / 86400);
		if ($currtime >= $active['xnnumstart']) {
			$days = round(($season['endtime'] - time()) / 86400) - $active['xnnumstart'] + 1;
		} else {
			$days = round(($season['endtime'] - $season['starttime']) / 86400) - $active['xnnumstart'] + 1;
		}
		$maxnum = round($active['truemoney'] / $active['money']) - $active['truepeople'];
		$everyday = round($maxnum / $days);
		$everyhour = round($everyday / 24);
		$everymins = round($everyhour / 60);
		if ($everyday > 1) {
			if ($everyhour > 1) {
				if ($everymins > 1) {
					$plan = '1分钟执行一次';
				} else {
					$plan = ceil(60 / $everyhour) . '分钟执行一次';
				}
			} else {
				$plan = ceil(24 / $everyday) . '小时执行一次';
			}
		}
		include $this->template('setting/basic');
	}
	public function doWebBasicdo()
	{
		global $_GPC, $_W;
		$data = array("weid" => 1000, "index" => json_encode($_POST['index']), "basic" => json_encode($_POST['basic']), "ques" => json_encode($_POST['ques']), "follow" => json_encode($_POST['follow']), "forward" => json_encode($_POST['forward']), "notice" => json_encode($_POST['notice']), "sign" => json_encode($_POST['sign']), "tpl" => json_encode($_POST['tpl']), "active" => json_encode($_POST['active']));
		$res = pdo_get('hc_setting', array("weid" => 1000));
		if (empty($res)) {
			$result = pdo_insert('hc_setting', $data);
		} else {
			$result = pdo_update('hc_setting', $data, array("weid" => 1000));
		}
		if ($result) {
			exit(json_encode(array("code" => 1, "msg" => "编辑成功")));
		}
		exit(json_encode(array("code" => 0, "msg" => "编辑失败")));
	}
	public function doWebSeason()
	{
		global $_GPC, $_W;
		$sid = $_GPC['season'];
		$where['weid'] = 1000;
		$pagesize = 15;
		$count = pdo_getcolumn('hc_season', $where, array("count(*)"));
		$nums = ceil($count / $pagesize);
		include $this->template('setting/season');
	}
	public function doWebSeasonajax()
	{
		global $_GPC, $_W;
		$pageindex = max(1, intval($_POST['page']));
		$pagesize = 15;
		$where['weid'] = 1000;
		$currno = pdo_getcolumn('hc_season', array("status" => 1, "weid" => 1000), array("no"));
		$list = pdo_getslice('hc_season', $where, array($pageindex, $pagesize), $total, array(), '', 'no asc');
		foreach ($list as $key => $val) {
			if ($val['no'] < $currno) {
				$list[$key]['status'] = 2;
			}
			$list[$key]['thumb'] = $_W['attachurl'] . $val['thumb'];
			$list[$key]['starttime'] = date('Y-m-d H:i', $val['starttime']);
			$list[$key]['endtime'] = date('Y-m-d H:i', $val['endtime']);
		}
		exit(json_encode(array("code" => 1, "msg" => "获取成功", "data" => $list)));
	}
	public function doWebSeasonadd()
	{
		global $_GPC, $_W;
		$id = $_GPC['id'];
		if (!empty($id)) {
			$info = pdo_get('hc_season', array("weid" => 1000, "id" => $id));
			$info['starttime'] = date('Y-m-d H:i', $info['starttime']);
			$info['endtime'] = date('Y-m-d H:i', $info['endtime']);
		}
		include $this->template('setting/seasonadd');
	}
	public function doWebSeasondo()
	{
		global $_GPC, $_W;
		$id = $_GPC['id'];
		if ($_GPC['act'] == 'del') {
			$res = pdo_delete('hc_season', array("id" => $id));
			if ($res) {
				exit(json_encode(array("code" => 1, "msg" => "删除成功")));
			}
			exit(json_encode(array("code" => 0, "msg" => "删除失败")));
		}
		if ($_GPC['act'] == 'start') {
			$res = pdo_update('hc_season', array("status" => 1), array("weid" => 1000, "id" => $id));
			pdo_update('hc_season', array("status" => 0), array("weid" => 1000, "id !=" => $id));
			$lastseason = pdo_getall('hc_user_info', array("weid" => 1000));
			foreach ($lastseason as $key => $val) {
				$data = array("uid" => $val['uid'], "weid" => $val['weid'], "sid" => $val['sid'], "level" => $val['level'], "expe" => $val['expe'], "dan" => $val['dan'], "star" => $val['star'], "winrate" => $val['winrate'], "totalnum" => $val['totalnum'], "winnum" => $val['winnum']);
				pdo_insert('hc_user_history', $data);
				$udate = array("sid" => $id, "dan" => 1, "star" => 0, "winrate" => 0, "totalnum" => 0, "winnum" => 0);
				pdo_update('hc_user_info', $udate, array("uid" => $val['uid'], "weid" => $val['weid'], "sid" => $val['sid']));
			}
			if ($res) {
				exit(json_encode(array("code" => 1, "msg" => "开启成功")));
			}
			exit(json_encode(array("code" => 0, "msg" => "开启失败")));
		} else {
			$data = array("no" => $_GPC['no'], "name" => $_GPC['name'], "starttime" => strtotime($_GPC['starttime']), "endtime" => strtotime($_GPC['endtime']), "weid" => 1000, "createtime" => time());
			if (!empty($id)) {
				$res = pdo_update('hc_season', $data, array("id" => $id));
			} else {
				$res = pdo_insert('hc_season', $data);
			}
			if (!empty($res)) {
				exit(json_encode(array("code" => 1, "msg" => "操作成功")));
			}
			exit(json_encode(array("code" => 0, "msg" => "操作失败")));
		}
	}
	public function doWebGrade()
	{
		global $_GPC, $_W;
		$list = pdo_getall('hc_grade', array("weid" => 1000), array(), '', 'levelno asc');
		include $this->template('setting/grade');
	}
	public function doWebGradedo()
	{
		global $_GPC, $_W;
		$id = $_POST['id'];
		if ($_POST['act'] == 'del') {
			$res = pdo_delete('hc_grade', array("id" => $id));
			if ($res) {
				exit(json_encode(array("code" => 1, "msg" => "删除成功")));
			}
			exit(json_encode(array("code" => 0, "msg" => "删除失败")));
		} else {
			$data = $_POST['level'];
			$arr = json_decode($data, true);
			foreach ($arr as $key => $val) {
				$data = array("weid" => 1000, "levelno" => $val['no'], "levelname" => $val['name'], "levelexp" => $val['exp']);
				$res = pdo_getcolumn('hc_grade', array("weid" => 1000, "levelno" => $val['no']), array("id"));
				if (empty($res)) {
					$result = pdo_insert('hc_grade', $data);
				} else {
					$result = pdo_update('hc_grade', $data, array("weid" => 1000, "levelno" => $val['no']));
				}
			}
			if ($result) {
				exit(json_encode(array("code" => 1, "msg" => "保存成功")));
			} else {
				exit(json_encode(array("code" => 0, "msg" => "保存失败")));
			}
		}
	}
	public function doWebRank()
	{
		global $_GPC, $_W;
		$where['weid'] = 1000;
		$pagesize = 15;
		$count = pdo_getcolumn('hc_dan', $where, array("count(*)"));
		$nums = ceil($count / $pagesize);
		include $this->template('setting/rank');
	}
	public function doWebRankajax()
	{
		global $_GPC, $_W;
		$pageindex = max(1, intval($_POST['page']));
		$pagesize = 15;
		$where['weid'] = 1000;
		$list = pdo_getslice('hc_dan', $where, array($pageindex, $pagesize), $total, array(), '', 'dan_id asc');
		foreach ($list as $key => $val) {
			$quesids = explode(',', $val['quesids']);
			$list[$key]['thumb'] = $_W['attachurl'] . $val['thumb'];
			$list[$key]['season'] = pdo_getcolumn('hc_season', array("status" => 1, "weid" => 1000), array("name"));
			$list[$key]['quesnum'] = empty($val['quesids']) ? 0 : count($quesids);
			unset($quesids);
		}
		exit(json_encode(array("code" => 1, "msg" => "获取成功", "data" => $list)));
	}
	public function doWebRankadd()
	{
		global $_GPC, $_W;
		$id = $_GPC['id'];
		if (!empty($id)) {
			$info = pdo_get('hc_dan', array("weid" => 1000, "id" => $id));
			$prop = pdo_get('hc_prop', array("id" => $info['reward'], "weid" => 1000));
		}
		$season = pdo_getall('hc_season', array("weid" => 1000), array(), '', 'no desc');
		include $this->template('setting/rankadd');
	}
	public function doWebRanklook()
	{
		global $_GPC, $_W;
		$id = $_GPC['id'];
		if (!empty($id)) {
			$quess = pdo_getall('hc_pk_record', array("weid" => 1000, "dan" => $id), array("questions"));
			if (!empty($quess)) {
				foreach ($quess as $key => $val) {
					$ques[$key] = $val['questions'];
				}
				$quesstr = implode(',', $ques);
				$quesarr = explode(',', $quesstr);
				$countval = array_count_values($quesarr);
				$quesall = array_unique($quesarr);
				$alrcount = count($quesall);
			} else {
				$alrcount = 0;
			}
			$info = pdo_get('hc_dan', array("weid" => 1000, "id" => $id));
			$quesdan = explode(',', $info['quesids']);
			$quescount = count($quesdan);
			$info['res'] = pdo_fetchall('SELECT * FROM ' . tablename('hc_question') . ' WHERE id in(' . $info['quesids'] . ') AND weid=:weid', array(":weid" => 1000));
			foreach ($info['res'] as $key => $val) {
				$info['res'][$key]['count'] = $countval[$val['id']];
				$info['res'][$key]['type_id'] = pdo_getcolumn('hc_question_type', array("weid" => 1000, "id" => $val['type_id']), array("name"));
			}
		}
		include $this->template('setting/ranklook');
	}
	public function doWebRankdo()
	{
		global $_GPC, $_W;
		$id = $_GPC['id'];
		if ($_GPC['act'] == 'del') {
			$res = pdo_delete('hc_dan', array("id" => $id));
			if ($res) {
				exit(json_encode(array("code" => 1, "msg" => "删除成功")));
			}
			exit(json_encode(array("code" => 0, "msg" => "删除失败")));
		} else {
			if ($_GPC['act'] == 'ques') {
				$quesids = explode(',', $_GPC['remark']);
				$quesids = array_unique($quesids);
				$quesids = implode(',', $quesids);
				$res = pdo_update('hc_dan', array("quesids" => $quesids), array("id" => $id));
				exit(json_encode(array("code" => 1, "msg" => "操作成功")));
			} else {
				if ($_GPC['act'] == 'update') {
					$question = pdo_getall('hc_question', array("weid" => 1000), array("id"), '', 'createtime desc');
					foreach ($question as $key => $val) {
						if ($key == 0) {
							$ques = $val['id'];
						} else {
							$ques .= ',' . $val['id'];
						}
					}
					$tk = explode(',', $ques);
					$dan = pdo_getall('hc_dan', array("weid" => 1000), array("id", "quesids"), '', 'createtime desc');
					foreach ($dan as $key => $val) {
						$arr = explode(',', $val['quesids']);
						$res = array_intersect($tk, $arr);
						$quesids = implode(',', $res);
						pdo_update('hc_dan', array("quesids" => $quesids), array("weid" => 1000, "id" => $val['id']));
						unset($arr);
						unset($res);
						unset($quesids);
					}
					exit(json_encode(array("code" => 1, "msg" => "更新成功")));
				} else {
					$quesids = explode(',', $_GPC['remark']);
					$quesids = array_unique($quesids);
					$quesids = implode(',', $quesids);
					$data = array("dan_id" => $_GPC['dan_id'], "season" => $_GPC['season'], "name" => $_GPC['name'], "thumb" => $_GPC['thumbs'], "win_star" => $_GPC['win_star'], "use_gold" => $_GPC['use_gold'], "win_gold" => $_GPC['win_gold'], "quesids" => $quesids, "reward" => $_GPC['reward'], "winexp" => $_GPC['winexp'], "failexp" => $_GPC['failexp'], "rewardnum" => $_GPC['rewardnum'], "border" => $_GPC['border'], "robot" => $_GPC['robot'], "weid" => 1000, "createtime" => time());
					if (!empty($id)) {
						$res = pdo_update('hc_dan', $data, array("id" => $id));
					} else {
						$res = pdo_insert('hc_dan', $data);
					}
					if (!empty($res)) {
						exit(json_encode(array("code" => 1, "msg" => "操作成功")));
					}
					exit(json_encode(array("code" => 0, "msg" => "操作失败")));
				}
			}
		}
	}
	public function doWebSelectlib()
	{
		global $_GPC, $_W;
		$id = $_GPC['id'];
		$where = ' weid=1000';
		$cate_id = $_GPC['cate_id'];
		if (!empty($cate_id)) {
			$where .= ' AND type_id=' . $cate_id;
		}
		$question = $_GPC['question'];
		if (!empty($question)) {
			$where .= "{ AND question like=%}{$question}{%}";
		}
		$easy = $_GPC['easy'];
		if (!empty($easy)) {
			$where .= ' AND easy=' . $easy;
		}
		$type = $_GPC['type'];
		if ($type == 1) {
			$questions = pdo_getcolumn('hc_dan', array("id" => $id, "weid" => 1000), array("quesids"));
			$where .= ' AND id in(' . $questions . ')';
		}
		if ($type == 2) {
			$questions = pdo_getcolumn('hc_dan', array("id" => $id, "weid" => 1000), array("quesids"));
			$where .= ' AND id not in(' . $questions . ')';
		}
		$pagesize = max(100, intval($_GPC['pagenum']));
		$count = pdo_fetchcolumn('SELECT count(*) FROM ' . tablename('hc_question') . ' WHERE ' . $where . '');
		$nums = ceil($count / $pagesize);
		$cate = pdo_getall('hc_question_type', array("weid" => 1000, "pid" => 0));
		foreach ($cate as $key => $val) {
			$cate[$key]['children'] = pdo_getall('hc_question_type', array("pid" => $val['id']));
		}
		$questions = pdo_getcolumn('hc_dan', array("id" => $id, "weid" => 1000), array("quesids"));
		include $this->template('setting/selectlib');
	}
	public function doWebSelectlibajax()
	{
		global $_GPC, $_W;
		$id = $_GPC['id'];
		$where = ' weid=1000';
		$cate_id = $_GPC['cate_id'];
		if (!empty($cate_id)) {
			$where .= ' AND type_id=' . $cate_id;
		}
		$question = $_GPC['question'];
		if (!empty($question)) {
			$where .= "{ AND question like=%}{$question}{%}";
		}
		$easy = $_GPC['easy'];
		if (!empty($easy)) {
			$where .= ' AND easy=' . $easy;
		}
		$yxques = '';
		if (!empty($id)) {
			$dataarr = array("id !=" => $id, "weid" => 1000);
		} else {
			$dataarr = array("weid" => 1000);
		}
		$yxquestions = pdo_getall('hc_dan', $dataarr, array("quesids"));
		if (!empty($yxquestions)) {
			foreach ($yxquestions as $key => $val) {
				if (!empty($val['quesids'])) {
					$yxarr[$key] = $val['quesids'];
				}
			}
			$yxques = implode(',', $yxarr);
		}
		$type = $_GPC['type'];
		if ($type == 1) {
			$questions = pdo_getcolumn('hc_dan', array("id" => $id, "weid" => 1000), array("quesids"));
			$where .= ' AND id in(' . $questions . ')';
		} else {
			if ($type == 2) {
				$questions = pdo_getcolumn('hc_dan', array("id" => $id, "weid" => 1000), array("quesids"));
				if (!empty($questions)) {
					if (!empty($yxques)) {
						$questions = $questions . ',' . $yxques;
					}
				} else {
					$questions = $yxques;
				}
				if (!empty($questions)) {
					$where .= ' AND id not in(' . $questions . ')';
				}
			} else {
				if (!empty($yxques)) {
					$where .= ' AND id not in(' . $yxques . ')';
				}
			}
		}
		$pageindex = max(1, intval($_GPC['page']));
		$pagesize = max(100, intval($_GPC['pagenum']));
		$total = pdo_fetchcolumn('SELECT count(*) FROM ' . tablename('hc_question') . ' WHERE ' . $where . '');
		$p = ($pageindex - 1) * $pagesize;
		$list = pdo_fetchall('SELECT * FROM ' . tablename('hc_question') . ' WHERE ' . $where . ' ORDER BY id ASC LIMIT ' . $p . ',' . $pagesize);
		$page = pagination($total, $pageindex, $pagesize);
		$questions = pdo_getcolumn('hc_dan', array("id" => $id, "weid" => 1000), array("quesids"));
		$ques = explode(',', $questions);
		foreach ($list as $key => $val) {
			$list[$key]['type_id'] = pdo_getcolumn('hc_question_type', array("id" => $val['type_id']), array("name"));
			if ($val['easy'] == 1) {
				$list[$key]['easy'] = '简单';
			} else {
				if ($val['easy'] == 2) {
					$list[$key]['easy'] = '一般';
				} else {
					if ($val['easy'] == 3) {
						$list[$key]['easy'] = '复杂';
					}
				}
			}
			foreach ($ques as $k => $v) {
				if ($val['id'] == $v) {
					$list[$key]['status'] = 1;
				}
			}
		}
		exit(json_encode(array("code" => 1, "msg" => "获取成功", "data" => $list, "id" => $id)));
	}
	public function doWebLibrary()
	{
		global $_GPC, $_W;
		$where['weid'] = 1000;
		$cate_id = $_GPC['cate_id'];
		if (!empty($cate_id)) {
			$where['type_id'] = $cate_id;
		}
		$question = $_GPC['question'];
		if (!empty($question)) {
			$where['question like'] = '%' . $question . '%';
		}
		$easy = $_GPC['easy'];
		if (!empty($easy)) {
			$where['easy'] = $easy;
		}
		$pagesize = 15;
		$count = pdo_getcolumn('hc_question', $where, array("count(*)"));
		$nums = ceil($count / $pagesize);
		$cate = pdo_getall('hc_question_type', array("weid" => 1000, "pid" => 0));
		foreach ($cate as $key => $val) {
			$cate[$key]['children'] = pdo_getall('hc_question_type', array("pid" => $val['id']));
		}
		include $this->template('setting/library');
	}
	public function doWebLibraryajax()
	{
		global $_GPC, $_W;
		$pageindex = max(1, intval($_POST['page']));
		$cate_id = $_POST['cate_id'];
		if (!empty($cate_id)) {
			$where['type_id'] = $cate_id;
		}
		$question = $_POST['question'];
		if (!empty($question)) {
			$where['question like'] = '%' . $question . '%';
		}
		$easy = $_POST['easy'];
		if (!empty($easy)) {
			$where['easy'] = $easy;
		}
		$pagesize = 15;
		$where['weid'] = 1000;
		$list = pdo_getslice('hc_question', $where, array($pageindex, $pagesize), $total, array(), '', 'id desc');
		foreach ($list as $key => $val) {
			$list[$key]['type_id'] = pdo_getcolumn('hc_question_type', array("id" => $val['type_id']), array("name"));
			if ($val['easy'] == 1) {
				$list[$key]['easy'] = '简单';
			} else {
				if ($val['easy'] == 2) {
					$list[$key]['easy'] = '一般';
				} else {
					if ($val['easy'] == 3) {
						$list[$key]['easy'] = '复杂';
					}
				}
			}
		}
		exit(json_encode(array("code" => 1, "msg" => "获取成功", "data" => $list)));
	}
	public function doWebLibraryadd()
	{
		global $_GPC, $_W;
		$id = $_GPC['id'];
		if (!empty($id)) {
			$info = pdo_get('hc_question', array("weid" => 1000, "id" => $id));
		}
		$cate = pdo_getall('hc_question_type', array("weid" => 1000, "pid" => 0));
		foreach ($cate as $key => $val) {
			$cate[$key]['children'] = pdo_getall('hc_question_type', array("pid" => $val['id']));
		}
		include $this->template('setting/libraryadd');
	}
	public function doWebLibraryimport()
	{
		global $_GPC, $_W;
		$cate = pdo_getall('hc_question_type', array("weid" => 1000, "pid" => 0));
		foreach ($cate as $key => $val) {
			$cate[$key]['children'] = pdo_getall('hc_question_type', array("pid" => $val['id']));
		}
		include $this->template('setting/libraryimport');
	}
	public function doWebLibrarynotice()
	{
		global $_GPC, $_W;
		include $this->template('setting/librarynotice');
	}
	public function doWebLibrarydo()
	{
		global $_GPC, $_W;
		$id = $_GPC['id'];
		if ($_GPC['act'] == 'del') {
			$res = pdo_delete('hc_question', array("id" => $id));
			if ($res) {
				exit(json_encode(array("code" => 1, "msg" => "删除成功")));
			}
			exit(json_encode(array("code" => 0, "msg" => "删除失败")));
		} else {
			if ($_GPC['act'] == 'alldel') {
				$ids = $_GPC['ids'];
				$res = pdo_query('DELETE FROM ' . tablename('hc_question') . ' WHERE id in(' . $ids . ')');
				if ($res) {
					exit(json_encode(array("code" => 1, "msg" => "删除成功")));
				} else {
					exit(json_encode(array("code" => 0, "msg" => "删除失败")));
				}
			} else {
				if ($_GPC['act'] == 'import') {
					if (empty($_GPC['type_id'])) {
						exit(json_encode(array("code" => 0, "msg" => "请选择题目分类")));
					}
					if (empty($_GPC['easy'])) {
						exit(json_encode(array("code" => 0, "msg" => "请选择难易度")));
					}
					load()->library('phpexcel/PHPExcel');
					$path = IA_ROOT . $_GPC['excels'];
					$objReader = \PHPExcel_IOFactory::createReader('Excel5');
					$objPHPExcel = $objReader->load($path, $encode = 'utf-8');
					$sheet = $objPHPExcel->getSheet(0);
					$highestRow = $sheet->getHighestRow();
					$i = 2;
					JQ9GP:
					if ($i <= $highestRow) {
						$data['type_id'] = $_GPC['type_id'];
						$data['easy'] = $_GPC['easy'];
						$data['weid'] = 1000;
						$data['question'] = $objPHPExcel->getActiveSheet()->getCell('A' . $i)->getValue();
						$data['answer_a'] = $objPHPExcel->getActiveSheet()->getCell('B' . $i)->getValue();
						$data['answer_b'] = $objPHPExcel->getActiveSheet()->getCell('C' . $i)->getValue();
						$data['answer_c'] = $objPHPExcel->getActiveSheet()->getCell('D' . $i)->getValue();
						$data['answer_d'] = $objPHPExcel->getActiveSheet()->getCell('E' . $i)->getValue();
						$data['answer'] = $objPHPExcel->getActiveSheet()->getCell('F' . $i)->getValue();
						$data['createtime'] = time();
						if (!empty($data['question'])) {
							$res = pdo_insert('hc_question', $data);
							$i++;
							goto JQ9GP;
						}
					}
					if ($res) {
						exit(json_encode(array("code" => 1, "msg" => "导入成功")));
					} else {
						exit(json_encode(array("code" => 0, "msg" => "导入失败")));
					}
				} else {
					$data = array("question" => $_GPC['question'], "type_id" => $_GPC['type_id'], "answer_a" => $_GPC['answer_a'], "answer_b" => $_GPC['answer_b'], "answer_c" => $_GPC['answer_c'], "answer_d" => $_GPC['answer_d'], "answer" => $_GPC['answer'], "easy" => $_GPC['easy'], "weid" => 1000, "createtime" => time());
					if (!empty($id)) {
						$res = pdo_update('hc_question', $data, array("id" => $id));
					} else {
						$res = pdo_insert('hc_question', $data);
					}
					if (!empty($res)) {
						exit(json_encode(array("code" => 1, "msg" => "操作成功")));
					}
					exit(json_encode(array("code" => 0, "msg" => "操作失败")));
				}
			}
		}
	}
	public function doWebCategory()
	{
		global $_GPC, $_W;
		$where['weid'] = 1000;
		$pagesize = 10;
		$count = pdo_getcolumn('hc_question_type', $where, array("count(*)"));
		$nums = ceil($count / $pagesize);
		include $this->template('setting/category');
	}
	public function doWebCategoryajax()
	{
		global $_GPC, $_W;
		$pageindex = max(1, intval($_POST['page']));
		$pagesize = 10;
		$where['weid'] = 1000;
		$list = pdo_getslice('hc_question_type', $where, array($pageindex, $pagesize), $total, array(), '', 'sort asc');
		foreach ($list as $key => $val) {
			$list[$key]['createtime'] = date('Y-m-d H:i', $val['createtime']);
			if ($val['pid'] == '0') {
				$list[$key]['pid'] = '顶级分类';
			} else {
				$list[$key]['pid'] = pdo_getcolumn('hc_question_type', array("id" => $val['pid']), array("name"));
			}
		}
		exit(json_encode(array("code" => 1, "msg" => "获取成功", "data" => $list)));
	}
	public function doWebCategoryadd()
	{
		global $_GPC, $_W;
		$id = $_GPC['id'];
		if (!empty($id)) {
			$info = pdo_get('hc_question_type', array("weid" => 1000, "id" => $id));
		}
		$list = pdo_getall('hc_question_type', array("weid" => 1000, "pid" => 0));
		include $this->template('setting/categoryadd');
	}
	public function doWebCategoryup()
	{
		global $_GPC, $_W;
		$id = $_GPC['id'];
		if (!empty($id)) {
			$info = pdo_getcolumn('hc_question_type', array("weid" => 1000, "id" => $id), array("upgrade"));
			$list = json_decode($info, true);
		}
		include $this->template('setting/categoryup');
	}
	public function doWebCategorydo()
	{
		global $_GPC, $_W;
		$id = $_GPC['id'];
		if ($_GPC['act'] == 'del') {
			$res = pdo_delete('hc_question_type', array("id" => $id, "weid" => 1000));
			pdo_delete('hc_question', array("type_id" => $id, "weid" => 1000));
			if ($res) {
				exit(json_encode(array("code" => 1, "msg" => "删除成功")));
			}
			exit(json_encode(array("code" => 0, "msg" => "删除失败")));
		} else {
			if ($_GPC['act'] == 'upgrade') {
				$upgrade = $_GPC['upgrade'];
				$upgrade = htmlspecialchars_decode($upgrade, ENT_QUOTES);
				$res = pdo_update('hc_question_type', array("upgrade" => $upgrade), array("id" => $id));
				if ($res) {
					exit(json_encode(array("code" => 1, "msg" => "操作成功")));
				}
				exit(json_encode(array("code" => 0, "msg" => "操作失败")));
			} else {
				$data = array("name" => $_POST['name'], "thumbs" => $_POST['thumbs'], "pid" => $_POST['pid'], "desc1" => $_POST['desc1'], "desc2" => $_POST['desc2'], "sort" => $_POST['sort'], "weid" => 1000, "createtime" => time());
				if (!empty($id)) {
					$res = pdo_update('hc_question_type', $data, array("id" => $id));
				} else {
					$res = pdo_insert('hc_question_type', $data);
				}
				if (!empty($res)) {
					exit(json_encode(array("code" => 1, "msg" => "操作成功")));
				}
				exit(json_encode(array("code" => 0, "msg" => "操作失败")));
			}
		}
	}
	public function doWebProp()
	{
		global $_GPC, $_W;
		$type = $_GPC['type'];
		if (!empty($type) && $type > 0) {
			$where['type'] = $type;
		} else {
			if ($type == 0) {
				$where['type'] = 0;
			}
		}
		$where['weid'] = 1000;
		$pagesize = 15;
		$count = pdo_getcolumn('hc_prop', $where, array("count(*)"));
		$nums = ceil($count / $pagesize);
		include $this->template('prop/prop');
	}
	public function doWebPropajax()
	{
		global $_GPC, $_W;
		$type = $_POST['type'];
		if (!empty($type) && $type > 0) {
			$where['type'] = $type;
		} else {
			if ($type == 0) {
				$where['type'] = 0;
			}
		}
		$pageindex = max(1, intval($_POST['page']));
		$pagesize = 15;
		$where['weid'] = 1000;
		$list = pdo_getslice('hc_prop', $where, array($pageindex, $pagesize), $total, array(), '', 'id desc');
		foreach ($list as $key => $val) {
			$list[$key]['thumb'] = $_W['attachurl'] . $val['thumb'];
			$list[$key]['shop'] = $val['shop'] == 1 ? '是' : '否';
			$list[$key]['give'] = $val['give'] == 1 ? '是' : '否';
		}
		exit(json_encode(array("code" => 1, "msg" => "获取成功", "data" => $list)));
	}
	public function doWebPropadd()
	{
		global $_GPC, $_W;
		$id = $_GPC['id'];
		if (!empty($id)) {
			$info = pdo_get('hc_prop', array("weid" => 1000, "id" => $id));
			$info['res'] = pdo_fetchall('SELECT * FROM ' . tablename('hc_prop') . ' WHERE id in(' . $info['remark'] . ') AND weid=:weid', array(":weid" => 1000));
			foreach ($info['res'] as $key => $val) {
				$info['res'][$key]['thumb'] = $_W['attachurl'] . $val['thumb'];
			}
		}
		include $this->template('prop/propadd');
	}
	public function doWebPropdo()
	{
		global $_GPC, $_W;
		$id = $_GPC['id'];
		if ($_GPC['act'] == 'del') {
			$res = pdo_delete('hc_prop', array("id" => $id));
			if ($res) {
				exit(json_encode(array("code" => 1, "msg" => "删除成功")));
			}
			exit(json_encode(array("code" => 0, "msg" => "删除失败")));
		} else {
			if ($_GPC['act'] == 'ajaxlook') {
				$ids = $_GPC['ids'];
				$res = pdo_fetchall('SELECT * FROM ' . tablename('hc_prop') . ' WHERE id in(' . $ids . ') AND weid=:weid', array(":weid" => 1000));
				foreach ($res as $key => $val) {
					$res[$key]['thumb'] = $_W['attachurl'] . $val['thumb'];
				}
				if (!empty($res)) {
					exit(json_encode(array("code" => 1, "msg" => "操作成功", "data" => $res)));
				}
				exit(json_encode(array("code" => 0, "msg" => "操作失败")));
			} else {
				if ($_GPC['type'] == 5) {
					if (!empty($_GPC['jb']) && !empty($_GPC['cc'])) {
						$res = pdo_get('hc_prop', array("weid" => 1000, "type" => 5, "cc >" => 0, "jb >" => 0));
						if (!empty($res) && $res['id'] != $id) {
							exit(json_encode(array("code" => 0, "msg" => "限次金币卡已存在")));
						}
					}
					if (!empty($_GPC['jy']) && !empty($_GPC['cc'])) {
						$res = pdo_get('hc_prop', array("weid" => 1000, "type" => 5, "cc >" => 0, "jy >" => 0));
						if (!empty($res) && $res['id'] != $id) {
							exit(json_encode(array("code" => 0, "msg" => "限次经验卡已存在")));
						}
					}
				}
				if ($_GPC['type'] == 6) {
					if (!empty($_GPC['jb']) && !empty($_GPC['sj'])) {
						$res = pdo_get('hc_prop', array("weid" => 1000, "type" => 6, "sj >" => 0, "jb >" => 0));
						if (!empty($res) && $res['id'] != $id) {
							exit(json_encode(array("code" => 0, "msg" => "限时金币卡已存在")));
						}
					}
					if (!empty($_GPC['jy']) && !empty($_GPC['sj'])) {
						$res = pdo_get('hc_prop', array("weid" => 1000, "type" => 6, "sj >" => 0, "jy >" => 0));
						if (!empty($res) && $res['id'] != $id) {
							exit(json_encode(array("code" => 0, "msg" => "限时经验卡已存在")));
						}
					}
				}
				if ($_GPC['type'] == 7) {
					if (!empty($_GPC['jf']) && !empty($_GPC['cc'])) {
						$res = pdo_get('hc_prop', array("weid" => 1000, "type" => 7, "cc >" => 0, "jf >" => 0));
						if (!empty($res) && $res['id'] != $id) {
							exit(json_encode(array("code" => 0, "msg" => "加分卡已存在")));
						}
					}
				}
				if ($_GPC['type'] == 3) {
					$jb = $_GPC['jbs'];
				} else {
					$jb = $_GPC['jb'];
				}
				$data = array("name" => $_GPC['name'], "thumb" => $_GPC['thumbs'], "cc" => $_GPC['cc'], "sj" => $_GPC['sj'], "jb" => $jb, "jy" => $_GPC['jy'], "jf" => $_GPC['jf'], "give" => $_GPC['give'], "price" => $_GPC['price'], "remark" => implode(',', array_unique(explode(',', $_GPC['remark']))), "type" => $_GPC['type'], "shop" => $_GPC['shop'], "dan" => $_GPC['dan'], "randnum" => $_GPC['randnum'], "desc" => $_GPC['desc'], "sort" => $_GPC['sort'], "newthumb" => $_GPC['newthumbs'], "usethumb" => $_GPC['usethumb'], "weid" => 1000);
				if (!empty($id)) {
					$res = pdo_update('hc_prop', $data, array("id" => $id));
				} else {
					$res = pdo_insert('hc_prop', $data);
				}
				if (!empty($res)) {
					exit(json_encode(array("code" => 1, "msg" => "操作成功")));
				}
				exit(json_encode(array("code" => 0, "msg" => "操作失败")));
			}
		}
	}
	public function doWebSelectprop()
	{
		global $_GPC, $_W;
		$id = $_GPC['id'];
		$types = $_GPC['types'];
		if (!empty($types)) {
			$where['type'] = $types;
		}
		$where['weid'] = 1000;
		$pagesize = 15;
		$count = pdo_getcolumn('hc_prop', $where, array("count(*)"));
		$nums = ceil($count / $pagesize);
		include $this->template('prop/selectprop');
	}
	public function doWebSelectpropajax()
	{
		global $_GPC, $_W;
		$pageindex = max(1, intval($_POST['page']));
		$id = $_POST['id'];
		$types = $_POST['types'];
		if (!empty($types)) {
			$where['type'] = $types;
		}
		$pagesize = 15;
		$where['weid'] = 1000;
		$list = pdo_getslice('hc_prop', $where, array($pageindex, $pagesize), $total, array(), '', 'id desc');
		foreach ($list as $key => $val) {
			$list[$key]['thumb'] = $_W['attachurl'] . $val['thumb'];
		}
		exit(json_encode(array("code" => 1, "msg" => "获取成功", "data" => $list, "id" => $id)));
	}
	public function doWebUser()
	{
		global $_GPC, $_W;
		$where['weid'] = 1000;
		$robot = $_GPC['robot'];
		if (!empty($robot)) {
			$where['robot'] = $robot;
		}
		$nickname = $_GPC['nickname'];
		if (!empty($nickname)) {
			$where['nickname like'] = '%' . $nickname . '%';
		}
		$pagesize = 15;
		$count = pdo_getcolumn('hc_user', $where, array("count(*)"));
		$nums = ceil($count / $pagesize);
		include $this->template('user/list');
	}
	public function doWebUserajax()
	{
		global $_GPC, $_W;
		$pageindex = max(1, intval($_GPC['page']));
		$robot = $_GPC['robot'];
		if (!empty($robot)) {
			$where['robot'] = $robot - 1;
		}
		$nickname = $_GPC['nickname'];
		if (!empty($nickname)) {
			$where['nickname like'] = '%' . $nickname . '%';
		}
		$pagesize = 15;
		$where['weid'] = 1000;
		$season = pdo_getcolumn('hc_season', array("status" => 1, "weid" => 1000), array("id"));
		$list = pdo_getslice('hc_user', $where, array($pageindex, $pagesize), $total, array(), '', 'uid desc');
		foreach ($list as $key => $val) {
			$list[$key]['gender'] = $val['gender'] == 1 ? '男' : '女';
			$list[$key]['robot'] = $val['robot'] == 1 ? '是' : '不是';
			$list[$key]['province'] = empty($val['province']) ? '未知' : $val['province'];
			$list[$key]['city'] = empty($val['city']) ? '未知' : $val['city'];
			$list[$key]['level'] = pdo_getcolumn('hc_user_info', array("uid" => $val['uid'], "sid" => $season, "weid" => 1000), array("level"));
			$list[$key]['dan'] = pdo_getcolumn('hc_user_info', array("uid" => $val['uid'], "sid" => $season, "weid" => 1000), array("dan"));
		}
		exit(json_encode(array("code" => 1, "msg" => "获取成功", "data" => $list)));
	}
	public function doWebUseradd()
	{
		global $_GPC, $_W;
		$id = $_GPC['id'];
		if (!empty($id)) {
			$info = pdo_get('hc_user', array("weid" => 1000, "uid" => $id));
			$sid = pdo_getcolumn('hc_season', array("status" => 1, "weid" => 1000), array("id"));
			$info['else'] = pdo_get('hc_user_info', array("weid" => 1000, "uid" => $id, "sid" => $sid));
		}
		include $this->template('user/useradd');
	}
	public function doWebUserdo()
	{
		global $_GPC, $_W;
		$id = $_GPC['id'];
		if ($_GPC['act'] == 'del') {
			pdo_delete('hc_user', array("uid" => $id, "weid" => 1000));
			pdo_delete('hc_user_info', array("uid" => $id, "weid" => 1000));
			exit(json_encode(array("code" => 1, "msg" => "删除成功")));
		} else {
			if ($_GPC['act'] == 'black') {
				pdo_update('hc_user', array("status" => 2), array("uid" => $id, "weid" => 1000));
				exit(json_encode(array("code" => 1, "msg" => "拉黑成功")));
			} else {
				if ($_GPC['act'] == 'unblack') {
					pdo_update('hc_user', array("status" => 1), array("uid" => $id, "weid" => 1000));
					exit(json_encode(array("code" => 1, "msg" => "取消成功")));
				} else {
					if ($_GPC['robot'] == 1) {
						$avatar = $_W['attachurl'] . $_GPC['avatar'];
					} else {
						$avatar = $_GPC['avatar'];
					}
					$data['avatar'] = $avatar;
					$data['nickname'] = $_GPC['nickname'];
					$data['gender'] = $_GPC['gender'];
					$data['province'] = $_GPC['province'];
					$data['city'] = $_GPC['city'];
					$data['country'] = $_GPC['country'];
					$data['robot'] = $_GPC['robot'];
					$data['weid'] = 1000;
					$data['createtime'] = time();
					if (!empty($id)) {
						$res = pdo_update('hc_user', $data, array("uid" => $id));
					} else {
						$res = pdo_insert('hc_user', $data);
						$id = pdo_insertid();
					}
					if (!empty($res)) {
						$info = pdo_get('hc_user_info', array("weid" => 1000, "uid" => $id));
						$sid = pdo_getcolumn('hc_season', array("status" => 1, "weid" => 1000), array("id"));
						$udata = array("uid" => $id, "sid" => $sid, "dan" => $_GPC['dan'], "star" => $_GPC['star'], "level" => $_GPC['level'], "expe" => $_GPC['expe'], "gold" => $_GPC['gold'], "weid" => 1000);
						if (empty($info)) {
							pdo_insert('hc_user_info', $udata);
						} else {
							pdo_update('hc_user_info', $udata, array("uid" => $id));
						}
						exit(json_encode(array("code" => 1, "msg" => "操作成功")));
					}
					exit(json_encode(array("code" => 0, "msg" => "操作失败")));
				}
			}
		}
	}
	public function doWebOrder()
	{
		global $_GPC, $_W;
		$where['weid'] = 1000;
		$ordersn = $_GPC['ordersn'];
		if (!empty($ordersn)) {
			$where['ordersn'] = $ordersn;
		}
		$pagesize = 15;
		$count = pdo_getcolumn('hc_order', $where, array("count(*)"));
		$nums = ceil($count / $pagesize);
		include $this->template('order/list');
	}
	public function doWebOrderajax()
	{
		global $_GPC, $_W;
		$pageindex = max(1, intval($_GPC['page']));
		$ordersn = $_GPC['ordersn'];
		if (!empty($ordersn)) {
			$where['ordersn'] = $ordersn;
		}
		$pagesize = 15;
		$where['weid'] = 1000;
		$list = pdo_getslice('hc_order', $where, array($pageindex, $pagesize), $total, array(), '', 'uid desc');
		foreach ($list as $key => $val) {
			$user = pdo_get('hc_user', array("weid" => 1000, "uid" => $val['uid']), array("nickname", "avatar"));
			$list[$key]['nickname'] = $user['nickname'];
			$list[$key]['avatar'] = $user['avatar'];
			$list[$key]['paystatus'] = $val['paystatus'] == 1 ? '已支付' : '未支付';
		}
		exit(json_encode(array("code" => 1, "msg" => "获取成功", "data" => $list)));
	}
	public function doWebNews()
	{
		global $_GPC, $_W;
		$where['weid'] = 1000;
		$pagesize = 15;
		$count = pdo_getcolumn('hc_shenhe', $where, array("count(*)"));
		$nums = ceil($count / $pagesize);
		include $this->template('news/news');
	}
	public function doWebNewsajax()
	{
		global $_GPC, $_W;
		$pageindex = max(1, intval($_GPC['page']));
		$pagesize = 15;
		$where['weid'] = 1000;
		$list = pdo_getslice('hc_shenhe', $where, array($pageindex, $pagesize), $total, array(), '', 'id desc');
		foreach ($list as $key => $val) {
			$list[$key]['img'] = $_W['attachurl'] . $val['img'];
		}
		exit(json_encode(array("code" => 1, "msg" => "获取成功", "data" => $list)));
	}
	public function doWebNewsadd()
	{
		global $_GPC, $_W;
		$id = $_GPC['id'];
		if (!empty($id)) {
			$info = pdo_get('hc_shenhe', array("weid" => 1000, "id" => $id));
			$info['res'] = pdo_fetchall('SELECT * FROM ' . tablename('hc_shenhe') . ' WHERE id in(' . $info['remark'] . ') AND weid=:weid', array(":weid" => 1000));
		}
		include $this->template('news/newsadd');
	}
	public function doWebNewsdo()
	{
		global $_GPC, $_W;
		$id = $_GPC['id'];
		if ($_GPC['act'] == 'del') {
			$res = pdo_delete('hc_shenhe', array("id" => $id));
			if ($res) {
				exit(json_encode(array("code" => 1, "msg" => "删除成功")));
			}
			exit(json_encode(array("code" => 0, "msg" => "删除失败")));
		} else {
			$data = array("weid" => 1000, "stact" => 1, "name" => $_GPC['name'], "img" => $_GPC['img'], "time" => date('Y-m-d H:i:s'), "sort" => 1, "content" => $_GPC['content']);
			if (!empty($id)) {
				$res = pdo_update('hc_shenhe', $data, array("id" => $id));
			} else {
				$res = pdo_insert('hc_shenhe', $data);
			}
			if (!empty($res)) {
				exit(json_encode(array("code" => 1, "msg" => "操作成功")));
			}
			exit(json_encode(array("code" => 0, "msg" => "操作失败")));
		}
	}
	public function doWebAdv()
	{
		global $_GPC, $_W;
		$where['weid'] = 1000;
		$pagesize = 15;
		$count = pdo_getcolumn('hc_ad', $where, array("count(*)"));
		$nums = ceil($count / $pagesize);
		include $this->template('adv/adv');
	}
	public function doWebAdvajax()
	{
		global $_GPC, $_W;
		$pageindex = max(1, intval($_GPC['page']));
		$pagesize = 15;
		$where['weid'] = 1000;
		$list = pdo_getslice('hc_ad', $where, array($pageindex, $pagesize), $total, array(), '', 'id desc');
		exit(json_encode(array("code" => 1, "msg" => "获取成功", "data" => $list)));
	}
	public function doWebAdvadd()
	{
		global $_GPC, $_W;
		$id = $_GPC['id'];
		if (!empty($id)) {
			$info = pdo_get('hc_ad', array("weid" => 1000, "id" => $id));
		}
		include $this->template('adv/advadd');
	}
	public function doWebAdvdo()
	{
		global $_GPC, $_W;
		$id = $_GPC['id'];
		if ($_GPC['act'] == 'del') {
			$res = pdo_delete('hc_ad', array("id" => $id));
			if ($res) {
				exit(json_encode(array("code" => 1, "msg" => "删除成功")));
			}
			exit(json_encode(array("code" => 0, "msg" => "删除失败")));
		} else {
			$data = array("weid" => 1000, "name" => $_GPC['name'], "appid" => $_GPC['appid'], "path" => $_GPC['path'], "desc" => $_GPC['desc']);
			if (!empty($id)) {
				$res = pdo_update('hc_ad', $data, array("id" => $id));
			} else {
				$res = pdo_insert('hc_ad', $data);
			}
			if (!empty($res)) {
				exit(json_encode(array("code" => 1, "msg" => "操作成功")));
			}
			exit(json_encode(array("code" => 0, "msg" => "操作失败")));
		}
	}
	public function doWebActive()
	{
		global $_GPC, $_W;
		$where['weid'] = 1000;
		$pagesize = 15;
		$count = pdo_getcolumn('hc_bonus', $where, array("count(*)"));
		$nums = ceil($count / $pagesize);
		include $this->template('active/active');
	}
	public function doWebActiveajax()
	{
		global $_GPC, $_W;
		$pageindex = max(1, intval($_GPC['page']));
		$pagesize = 15;
		$where['weid'] = 1000;
		$list = pdo_getslice('hc_bonus', $where, array($pageindex, $pagesize), $total, array(), '', 'addtime desc');
		foreach ($list as $key => $val) {
			$user = pdo_get('hc_user', array("weid" => $val['weid'], "uid" => $val['uid']), array("nickname", "avatar", "moneycode"));
			$list[$key]['nickname'] = $user['nickname'];
			$list[$key]['avatar'] = $user['avatar'];
			$list[$key]['moneycode'] = $user['moneycode'];
			$list[$key]['addtime'] = date('Y-m-d H:i', $val['addtime']);
		}
		exit(json_encode(array("code" => 1, "msg" => "获取成功", "data" => $list)));
	}
	public function doWebActivedo()
	{
		global $_GPC, $_W;
		$id = $_GPC['id'];
		$res = pdo_update('hc_bonus', array("status" => 1, "dealtime" => time()), array("id" => $id));
		if (!empty($res)) {
			exit(json_encode(array("code" => 1, "msg" => "操作成功")));
		}
		exit(json_encode(array("code" => 0, "msg" => "操作失败")));
	}
	public function doWebUploadimg()
	{
		global $_GPC, $_W;
		if (empty($_FILES['thumb'])) {
			exit(json_encode(array("code" => 0, "msg" => "请上传图片")));
		}
		$type = $_FILES['thumb']['type'];
		$type = explode('/', $type);
		$newfilename = date('YmdHis') . rand(1000, 9999);
		$dir = IA_ROOT . '/addons/hc_answer/upload';
		if (!file_exists($dir)) {
			mkdir($dir);
			chmod($dir, 0777);
		}
		if (move_uploaded_file($_FILES['thumb']['tmp_name'], '../addons/hc_answer/upload/' . $newfilename . '.' . $type[1])) {
			$thumb = $_W['siteroot'] . 'addons/hc_answer/upload/' . $newfilename . '.' . $type[1];
			exit(json_encode(array("code" => 1, "msg" => "上传成功", "path" => $thumb)));
		} else {
			exit(json_encode(array("code" => 0, "msg" => "上传失败")));
		}
	}
	public function doWebUploadexcel()
	{
		global $_GPC, $_W;
		if (empty($_FILES['excel'])) {
			exit(json_encode(array("code" => 0, "msg" => "请上传图片")));
		}
		$newfilename = $_FILES['excel']['name'];
		$dir = IA_ROOT . '/addons/hc_answer/excel';
		if (!file_exists($dir)) {
			mkdir($dir);
			chmod($dir, 0777);
		}
		if (move_uploaded_file($_FILES['excel']['tmp_name'], '../addons/hc_answer/excel/' . $newfilename)) {
			$excel = '/addons/hc_answer/excel/' . $newfilename;
			exit(json_encode(array("code" => 1, "msg" => "上传成功", "path" => $excel)));
		} else {
			exit(json_encode(array("code" => 0, "msg" => "上传失败")));
		}
	}
	public function doMobileApi()
	{
		global $_W, $_GPC;
		$weid = 1000;
		$input = $_GPC['__input'];
		if ($input) {
			$openid = $input['FromUserName'];
			$account_api = WeAccount::create();
			$token = $account_api->getAccessToken();
			$model = new HcfkModel();
			$settings = pdo_getcolumn('hc_setting', array("weid" => $weid), array("follow"));
			$follow = json_decode($settings, true);
			$post_url = 'https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token=' . $token;
			load()->func('communication');
			$post_data = $model->json_encode2(array("touser" => $openid, "msgtype" => "link", "link" => array("title" => $follow['title'], "description" => $follow['desc'], "url" => $follow['url'], "thumb_url" => toimage($follow['img']))));
			ihttp_post($post_url, $post_data);
			echo success;
		} else {
			return $_GPC['echostr'];
		}
	}
	public function doWebSendlibtpl()
	{
		global $_W, $_GPC;
		$weid = 1000;
		$keyword1 = $_GPC['keyword1'];
		$keyword2 = $_GPC['keyword2'];
		$account_api = WeAccount::create();
		$token = $account_api->getAccessToken();
		$url = 'https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=' . $token;
		$tpl = pdo_getcolumn('hc_setting', array("weid" => $weid), array("tpl"));
		$tpl = json_decode($tpl, true);
		$users = pdo_fetchall('SELECT a.openid,b.formid FROM ' . tablename('hc_user') . ' AS a LEFT JOIN ' . tablename('hc_formid') . ' AS b ON a.uid=b.uid WHERE a.weid=:weid AND b.formid !=\'\' AND b.status=0 group by a.uid', array(":weid" => $weid));
		foreach ($users as $key => $val) {
			$data['touser'] = $val['openid'];
			$data['template_id'] = $tpl['tk'];
			$data['form_id'] = $val['formid'];
			$data['data']['keyword1']['value'] = $keyword1;
			$data['data']['keyword1']['color'] = '#173177';
			$data['data']['keyword2']['value'] = $keyword2;
			$data['data']['keyword2']['color'] = '#173177';
			$data['emphasis_keyword'] = 'keyword1.DATA';
			$data['page'] = 'hc_answer/pages/index/index';
			$json = json_encode($data);
			$res = ihttp_post($url, $json);
			if ($res['status'] == 'OK') {
				pdo_update('hc_formid', array("status" => 1), array("formid" => $val['formid']));
			}
		}
		exit(json_encode(array("code" => 1, "msg" => "操作成功")));
	}
}