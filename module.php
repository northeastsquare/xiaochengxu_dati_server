<?php
/**
 * 知乎答题王I头脑王者模块定义
 *
 * @author 泉州大白网络科技
 * @url http://bbs.we7.cc/
 */
defined('IN_IA') or exit('Access Denied');

class Hc_answerModule extends WeModule {


	public function welcomeDisplay($menus = array()) {
		global $_W;$_GPC;
		include $this->template('index');
	}
}