<?php
if (!defined('IN_IA')) {
    exit('Access Denied');
}
global $_W, $_GPC;
$openid = m('user')->getOpenid();
//Author:ym Date:2016-08-03 Content:重复调用了三次，代码需优化
$set = m('common')->getSysset(array('trade','shop'));
$shop_set = m('common')->getSysset(array('shop'));
$shopset   = m('common')->getSysset('shop');

$setdata = pdo_fetch("select * from " . tablename('sz_yi_sysset') . ' where uniacid=:uniacid limit 1', array(
	':uniacid' => $_W['uniacid']
));
$appset     = unserialize($setdata['sets']);
$app = $appset['app']['base'];

$member = m('member')->getMember($openid);
if (empty($member)) {
	header('Location: '.$this->createMobileUrl('member/login'));
}
$member['nickname'] = empty($member['nickname']) ? $member['mobile'] : $member['nickname'];

$uniacid = $_W['uniacid'];
$trade['withdraw'] = $set['trade']['withdraw'];
$trade['closerecharge'] = $set['trade']['closerecharge'];
$trade['transfer'] 		= $set['trade']['transfer'];
$hascom = false;
$supplier_switch = false;
$supplier_switch_centre = false;
if (p('merchant')) {
	if (!empty($member['id'])) {
		$ismerchant = pdo_fetchall("select * from " . tablename('sz_yi_merchants') . " where uniacid={$_W['uniacid']} and member_id={$member['id']}");
	}
}
if (p('supplier')) {
	$supplier_set = p('supplier')->getSet();
	$issupplier = p('supplier')->isSupplier($openid);
	$af_result = pdo_fetchcolumn("select status from " . tablename('sz_yi_af_supplier') . " where uniacid={$_W['uniacid']} and openid='{$openid}'");
	if ($af_result == 2) {
		$shopset['af_result'] = true;
	}
	$shopset['switch'] = $supplier_set['switch'];
	$shopset['switch_centre'] = $supplier_set['switch_centre'];
}
$ischannel = false;
if (p('channel')) {
	$result = m('member')->getInfo($openid);
	if (!empty($result['ischannel']) && !empty($result['channel_level'])) {
		$ischannel = true;
	}
	$channel_set = p('channel')->getSet();
}
$plugc = p('commission');
if ($plugc) {
	$pset = $plugc->getSet();
	if (!empty($pset['level'])) {
		if ($member['isagent'] == 1 && $member['status'] == 1) {
			$hascom = true;
		}
	}
}
$shopset['commission_text'] = $pset['texts']['center'];
$shopset['hascom'] = $hascom;
$hascoupon = false;
$hascouponcenter = false;
$plugin_coupon = p('coupon');
if ($plugin_coupon) {
	$pcset = $plugin_coupon->getSet();
	if (empty($pcset['closemember'])) {
		$hascoupon = true;
		$hascouponcenter = true;
	}
}
$shopset['hascoupon'] = $hascoupon;
$shopset['hascouponcenter'] = $hascouponcenter;
$pluginbonus = p("bonus");
$bonus_start = false;
$bonus_text = "";
if(!empty($pluginbonus)){
	$bonus_set = $pluginbonus->getSet();
	$islevel = $pluginbonus->isLevel($openid);
	if((!empty($bonus_set['start']) || !empty($bonus_set['area_start'])) && !empty($islevel)){
		$bonus_start = true;
		$bonus_text = $bonus_set['texts']['center'] ? $bonus_set['texts']['center'] : "分红明细";
	}

}
$shopset['bonus_start'] = $bonus_start;
$shopset['bonus_text'] = $bonus_text;
$shopset['is_weixin'] = is_weixin();

$plugin_article = p('article');
if ($plugin_article) {
	$article_set = $plugin_article->getSys();
	$shopset['article_text'] = $article_set['article_text'] ? $article_set['article_text'] : '文章管理';

	$shopset['isarticle'] = $article_set['isarticle'];
}
//这两段代码 用哪个会好一些 实现的功能都一样
// <!---------------------
// $plugin_return = p('return');
// if($plugin_return){
// 	$returnset = $plugin_return->getSet();
// 	$shopset['isreturn'] = false;
// 	if($reurnset['isqueue'] == 1 || $reurnset['isreturn']== 1 ){
// 		$shopset['isreturn'] = true;
// 	}
// }
// ==========================================
// $reurnset = m('plugin')->getpluginSet('return');
// $shopset['isreturn'] = false;
// if($reurnset['isqueue'] == 1 || $reurnset['isreturn']== 1 ){
// 	$shopset['isreturn'] = true;
// }
// --------------------->
if (p('return')) {
	$reurnset = m('plugin')->getpluginSet('return');
	$shopset['isreturn'] = false;
	if($reurnset['isqueue'] == 1 || $reurnset['isreturn']== 1 || $reurnset['islevelreturn']== 1 ){
		$shopset['isreturn'] = true;
	}
}
if (p('yunbi')) {
	$yunbiset = m('plugin')->getpluginSet('yunbi');
	$shopset['isyunbi'] = false;
	if($yunbiset['isyunbi'] == 1 ){
		$shopset['isyunbi'] = true;
	}
	$yunbi_title = $yunbiset['yunbi_title']?$yunbiset['yunbi_title']:'云币';
	$shopset['yunbi_title'] = $yunbi_title;
}
if (p('ranking')) {
	$ranking_set = p('ranking')->getSet();
	if ($ranking_set['ranking']['isranking'] && ($ranking_set['ranking']['isintegral'] || $ranking_set['ranking']['isexpense'] || $ranking_set['ranking']['iscommission'])) {
		$shopset['isranking'] = true;
		$shopset['rankingname'] = $ranking_set['ranking']['rankingname']?$ranking_set['ranking']['rankingname']:"排行榜";
		//$shopset['isranking'] = $ranking_set['ranking']['isranking'];		
	}
}

$open_creditshop = false;
$creditshop = p('creditshop');
if ($creditshop) {
	$creditshop_set = $creditshop->getSet();
	if (!empty($creditshop_set['centeropen'])) {
		$open_creditshop = true;
	}
}

if ($_W['isajax']) {
	$level = array('levelname' => empty($this->yzShopSet['levelname']) ? '普通会员' : $this->yzShopSet['levelname']);
	if (!empty($member['level'])) {
		$level = m('member')->getLevel($openid);
	}
	
	$orderparams = array(':uniacid' => $_W['uniacid'], ':openid' => $openid);
	$order = array('status0' => pdo_fetchcolumn('select count(distinct ordersn_general) from ' . tablename('sz_yi_order') . ' where openid=:openid and status=0  and uniacid=:uniacid limit 1', $orderparams), 'status1' => pdo_fetchcolumn('select count(distinct ordersn_general) from ' . tablename('sz_yi_order') . ' where openid=:openid and status=1 and refundid=0 and uniacid=:uniacid limit 1', $orderparams), 'status2' => pdo_fetchcolumn('select count(distinct ordersn_general) from ' . tablename('sz_yi_order') . ' where openid=:openid and status=2 and refundid=0 and uniacid=:uniacid limit 1', $orderparams), 'status4' => pdo_fetchcolumn('select count(distinct ordersn_general) from ' . tablename('sz_yi_order') . ' where openid=:openid and refundstate>0 and uniacid=:uniacid limit 1', $orderparams),);
	if(p('hotel')){
		$order = array('status0' => pdo_fetchcolumn('select count(distinct ordersn_general) from ' . tablename('sz_yi_order') . ' where openid=:openid and status=0 and order_type<>3  and uniacid=:uniacid limit 1', $orderparams), 'status1' => pdo_fetchcolumn('select count(distinct ordersn_general) from ' . tablename('sz_yi_order') . ' where openid=:openid and status=1 and order_type<>3 and refundid=0 and uniacid=:uniacid limit 1', $orderparams), 'status2' => pdo_fetchcolumn('select count(distinct ordersn_general) from ' . tablename('sz_yi_order') . ' where openid=:openid and status=2 and order_type<>3 and refundid=0 and uniacid=:uniacid limit 1', $orderparams), 'status4' => pdo_fetchcolumn('select count(distinct ordersn_general) from ' . tablename('sz_yi_order') . ' where openid=:openid and order_type<>3 and refundstate>0 and uniacid=:uniacid limit 1', $orderparams),);
	    $orderhotel = array('status0' => pdo_fetchcolumn('select count(distinct ordersn_general) from ' . tablename('sz_yi_order') . ' where openid=:openid and status=0 and order_type=3  and uniacid=:uniacid limit 1', $orderparams), 'status1' => pdo_fetchcolumn('select count(distinct ordersn_general) from ' . tablename('sz_yi_order') . ' where openid=:openid and status=1 and order_type=3 and refundid=0 and uniacid=:uniacid limit 1', $orderparams), 'status6' => pdo_fetchcolumn('select count(distinct ordersn_general) from ' . tablename('sz_yi_order') . ' where openid=:openid and status=6 and order_type=3 and refundid=0 and uniacid=:uniacid limit 1', $orderparams), 'status4' => pdo_fetchcolumn('select count(distinct ordersn_general) from ' . tablename('sz_yi_order') . ' where openid=:openid and order_type=3 and refundstate>0 and uniacid=:uniacid limit 1', $orderparams),);
		$hotel = p('hotel');
	    $memberhotel = $hotel->check_plugin('hotel');
	}
	if (mb_strlen($member['nickname'], 'utf-8') > 6) {
		$member['nickname'] = mb_substr($member['nickname'], 0, 6, 'utf-8');
	}

	$referrer = array();
	if($shop_set['shop']['isreferrer'] ){
		if($member['agentid']>0){
			$referrer = pdo_fetch("select * from " . tablename("sz_yi_member") . " where uniacid=".$_W['uniacid']." and id = '".$member['agentid']."' ");
			$nickname = $referrer['nickname'] ? $referrer['nickname'] :  $referrer['realname'];
			$nickname = $nickname ? $nickname :  $referrer['mobile'];
			$referrer['realname'] = mb_substr($nickname, 0, 6, 'utf-8');
		}else
		{
			$referrer['realname'] = "总店";
		}
	}


	$counts = array('cartcount' => pdo_fetchcolumn('select ifnull(sum(total),0) from ' . tablename('sz_yi_member_cart') . ' where uniacid=:uniacid and openid=:openid and deleted=0 ', array(':uniacid' => $uniacid, ':openid' => $openid)), 'favcount' => pdo_fetchcolumn('select count(*) from ' . tablename('sz_yi_member_favorite') . ' where uniacid=:uniacid and openid=:openid and deleted=0 ', array(':uniacid' => $uniacid, ':openid' => $openid)));
	if ($plugin_coupon) {
		$time = time();
		$sql = 'select count(*) from ' . tablename('sz_yi_coupon_data') . ' d';
		$sql .= ' left join ' . tablename('sz_yi_coupon') . ' c on d.couponid = c.id';
		$sql .= ' where d.openid=:openid and d.uniacid=:uniacid and  d.used=0 ';
		$sql .= " and (   (c.timelimit = 0 and ( c.timedays=0 or c.timedays*86400 + d.gettime >=unix_timestamp() ) )  or  (c.timelimit =1 and c.timestart<={$time} && c.timeend>={$time})) order by d.gettime desc";
		$counts['couponcount'] = pdo_fetchcolumn($sql, array(':openid' => $openid, ':uniacid' => $_W['uniacid']));
	}
<<<<<<< HEAD
    $variable = array(
        'yunbiset'=> $yunbiset
    );
	return show_json(1, array('member' => $member,'referrer'=>$referrer,'shop_set'=>$shop_set, 'order' => $order,'orderhotel' => $orderhotel,'memberhotel'=>$memberhotel,'level' => $level, 'open_creditshop' => $open_creditshop, 'counts' => $counts, 'shopset' => $shopset, 'trade' => $trade, 'app'=>$app, 'set'=> $set),$variable);
=======
return show_json(1, array('member' => $member,'referrer'=>$referrer,'shop_set'=>$shop_set, 'order' => $order,'orderhotel' => $orderhotel,'memberhotel'=>$memberhotel,'level' => $level, 'open_creditshop' => $open_creditshop, 'counts' => $counts, 'shopset' => $shopset, 'trade' => $trade, 'app'=>$app));
>>>>>>> 3c75c44a5bf009686088ff7895808079a6f87418
}
$pcashier = p('cashier');
$has_cashier = false;
if ($pcashier) {
    $store = pdo_fetch('select * from ' . tablename('sz_yi_cashier_store') . ' where uniacid=:uniacid and member_id=:member_id limit 1', array(
        ':uniacid' => $_W['uniacid'], ':member_id' => $member['id']
    ));
    $store_waiter = pdo_fetch('select * from ' . tablename('sz_yi_cashier_store_waiter') . ' where uniacid=:uniacid and member_id=:member_id limit 1', array(
        ':uniacid' => $_W['uniacid'], ':member_id' => $member['id']
    ));
    if ($store || $store_waiter) {
        $has_cashier = true;
    }
}
include $this->template('member/center');
