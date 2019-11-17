<?php
//泉州大白网络科技
define('IN_MOBILE', true);
require '../../framework/bootstrap.inc.php';
$postStr = file_get_contents("php://input"); // 这里拿到微信返回的数据结果
libxml_disable_entity_loader(true);
$xmlstring = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
$payreturn = json_decode(json_encode($xmlstring),true);
if ($payreturn['result_code'] == 'SUCCESS' && $payreturn['result_code'] == 'SUCCESS') {

    $ordersn = trim($payreturn['out_trade_no']);
    $order = pdo_get('hc_order',array('ordersn'=>$ordersn));
    if ($order){

        //添加物品
        $prop = pdo_get('hc_user_prop',array('weid'=>$order['weid'],'uid'=>$order['uid'],'pid'=>$order['pid']));
        if(!empty($prop)){
            pdo_update('hc_user_prop',array('num'=>$prop['num']+1,'status'=>0),array('weid'=>$order['weid'],'uid'=>$order['uid'],'pid'=>$order['pid']));
        }else{
            pdo_insert('hc_user_prop',array('weid'=>$order['weid'],'uid'=>$order['uid'],'pid'=>$order['pid'],'num'=>1));
        }
        //添加记录
        pdo_insert('hc_user_proplog',array('weid'=>$order['weid'],'uid'=>$order['uid'],'pid'=>$order['pid'],'type'=>2,'num'=>1,'createtime'=>time()));
        
        //更新订单状态
        $order_data = array(
            'paystatus'=>1,
            'paytime'=>time(),
            'transid'=>$payreturn['transaction_id']
        );
    	pdo_update('hc_order', $order_data, array('ordersn'=>$ordersn));

        $prop = array(
            'weid' => $order['weid'],
            'uid'  => $order['uid'],
            'pid'  => $order['pid'],
            'num'  => 1,
            'addtime'=> time(),
        );
        $propres = pdo_insert('hc_user_prop',$prop);
        $lastid = pdo_insertid();

        $data = array(
            'uid'        => $order['uid'],
            'type'       => 2,
            'remark'     => $lastid.':'.$order['weid'].':'.$order['uid'].':'.$order['pid'],
            'createtime' => time()
        );
        $logres = pdo_insert('hc_user_proplog',$data);
    }
    echo 'success';
    return ;
}
