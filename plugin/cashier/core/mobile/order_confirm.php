<?php
if (!defined('IN_IA')) {
    exit('Access Denied');
}
global $_W, $_GPC;
$operation = !empty($_GPC['op']) ? $_GPC['op'] : 'display';
$openid    = m('user')->getOpenid();
$hascouponplugin = false;
if (p('commission')) {
    $commission = p('commission')->getSet();
}
$plugc           = p("coupon");
$member = m('member')->getMember($openid);
if ($plugc) {
    $hascouponplugin = true;
}
if ($operation == 'display') {
    $shopset   = m('common')->getSysset('shop');
    $sid = $_GPC['sid'];
    if (!is_numeric($sid)) {
        redirect($this->createMobileUrl('member'));
    }
    $store = pdo_fetch('select * from ' . tablename('sz_yi_cashier_store') . ' where id=:id and uniacid=:uniacid limit 1', array(':id' => $sid, ':uniacid' => $_W['uniacid']));
    $store=set_medias($store,'thumb');

    if (!$store) {
        
        redirect($this->createMobileUrl('member'));
    }
    if($commission['become_child']==0){
        p('commission')->checkAgent();
    }
} else if ($operation == 'get-deduct') {
    $member = m('member')->getMember($openid);
    $sid = $_GPC['sid'];
    if (!is_numeric($sid)) {
        redirect($this->createMobileUrl('member'));
    }
    $store = pdo_fetch('select * from ' . tablename('sz_yi_cashier_store') . ' where id=:id and uniacid=:uniacid limit 1', array(':id' => $sid, ':uniacid' => $_W['uniacid']));
    if (!$store) {
        redirect($this->createMobileUrl('member'));
    }
    $orig_price = number_format($_GPC['orig_price'], 2);
    if (!is_numeric($orig_price) || $orig_price <= 0) {
        redirect($this->createMobileUrl('member'));
    }
    // 抵扣： 积分 余额
    $store_max_credit1 = $orig_price * $store['deduct_credit1'] / 100;  // 商家允许使用的最大积分百分比
    $store_max_credit2 = $orig_price * $store['deduct_credit2'] / 100;  // 商家允许使用的最大余额百分比

    $deductcredit  = 0;
    $deductmoney   = 0;
    $deductcredit2 = 0;
    $sale_plugin = p('sale');
    if ($sale_plugin) {
        $saleset = $sale_plugin->getSet();
        $credit = m('member')->getCredit($openid, 'credit1');
        if (!empty($saleset['creditdeduct'])) {
            $pcredit = intval($saleset['credit']);
            $pmoney  = round(floatval($saleset['money']), 2);
            if ($pcredit > 0 && $pmoney > 0) {
                if ($credit % $pcredit == 0) {
                    $deductmoney = round(intval($credit / $pcredit) * $pmoney, 2);
                } else {
                    $deductmoney = round((intval($credit / $pcredit) + 1) * $pmoney, 2);
                }
            }
            if ($deductmoney > $store_max_credit1) {
                $deductmoney = $store_max_credit1;
            }
            $deductcredit = $deductmoney / $pmoney * $pcredit;
        }
        if (!empty($saleset['moneydeduct'])) {
            $deductcredit2 = m('member')->getCredit($openid, 'credit2');
            if ($deductcredit2 > $store_max_credit2) {
                $deductcredit2 = $store_max_credit2;
            }
        }
    }
    // coupon
    $hascoupon = false;
    if ($hascouponplugin) {
        $couponcount = $plugc->consumeCouponCount($openid, $orig_price);
        $hascoupon   = $couponcount > 0;
    }
} else if ($operation == 'create-order') {
    $sid = $_GPC['sid'];
    
    if (!is_numeric($sid)) {
        redirect($this->createMobileUrl('member'));
    }
    $store = pdo_fetch('select * from ' . tablename('sz_yi_cashier_store') . ' where id=:id and uniacid=:uniacid limit 1', array(':id' => $sid, ':uniacid' => $_W['uniacid']));
    $store=set_medias($store,'thumb');
    if (!$store) {
        redirect($this->createMobileUrl('member'));
    }
    $orig_price = number_format($_GPC['orig_price'], 2);
    if (!is_numeric($orig_price) || $orig_price <= 0) {
        redirect($this->createMobileUrl('member'));
    }
    // 抵扣： 积分 余额
    $store_max_credit1 = $orig_price * $store['deduct_credit1'] / 100;  // 商家允许使用的最大积分百分比
    $store_max_credit2 = $orig_price * $store['deduct_credit2'] / 100;  // 商家允许使用的最大余额百分比

    $deductcredit  = 0;
    $deductmoney   = 0;
    $deductcredit2 = 0;
    $totalprice    = $orig_price;
    $sale_plugin = p('sale');
    if ($sale_plugin) {
        if (!empty($_GPC['deduct'])) {
            $saleset = $sale_plugin->getSet();
            $credit = m('member')->getCredit($openid, 'credit1');
            if (!empty($saleset['creditdeduct'])) {
                $pcredit = intval($saleset['credit']);
                $pmoney  = round(floatval($saleset['money']), 2);
                if ($pcredit > 0 && $pmoney > 0) {
                    if ($credit % $pcredit == 0) {
                        $deductmoney = round(intval($credit / $pcredit) * $pmoney, 2);
                    } else {
                        $deductmoney = round((intval($credit / $pcredit) + 1) * $pmoney, 2);
                    }
                }
                if ($deductmoney > $store_max_credit1) {
                    $deductmoney = $store_max_credit1;
                }
                $deductcredit = round($deductmoney / $pmoney * $pcredit, 2);
            }
        }
        $totalprice -= $deductmoney;
        if (!empty($_GPC['deduct2'])) {
            $deductcredit2 = m('member')->getCredit($openid, 'credit2');
            if ($deductcredit2 > $store_max_credit2) {
                $deductcredit2 = $store_max_credit2;
            }
        }
        $totalprice -= $deductcredit2;
    }
    //coupon
    $couponid    = intval($_GPC["couponid"]);
    if ($plugc) {
        $coupon = $plugc->getCouponByDataID($couponid);
        if (!empty($coupon)) {
            if ($totalprice >= $coupon["enough"] && empty($coupon["used"])) {
                if ($coupon["backtype"] == 0) {
                    if ($coupon["deduct"] > 0) {
                        $couponprice = $coupon["deduct"];
                    }
                } else if ($coupon["backtype"] == 1) {
                    if ($coupon["discount"] > 0) {
                        $couponprice = $totalprice * (1 - $coupon["discount"] / 10);
                    }
                }
                if ($couponprice > 0) {
                    $totalprice -= $couponprice;
                }
            }
        }
    }
    $carrier  = $_GPC['carrier'];
    $carriers = is_array($carrier) ? iserializer($carrier) : iserializer(array());
    // 生成订单
    $ordersn = m('common')->createNO('order', 'ordersn', 'SY');
    $user    = m('member')->getMember($openid);
    if (p('commission')) {
        $commission_set = p('commission')->getSet();
        if (empty($commission_set['selfbuy'])) {
            $agentid = $user['agentid'];
        } else {
            $agentid = $user['id'];
        }
    }
    $order   = array(
        'uniacid' => $_W['uniacid'],
        'openid' => $openid,
        'agentid' => $agentid,
        'ordersn' => $ordersn,
        'price' => $totalprice,
        'cash' => $cash,
        'discountprice' => 0.00,
        'deductprice' => $deductmoney,
        'deductcredit' => $deductcredit,
        'deductcredit2' => $deductcredit2,
        'deductenough' => 0.00,     // TODO:
        'status' => 0,
        'paytype' => 0,
        'transid' => '',
        'remark' => '',
        'addressid' => 0,
        'goodsprice' => $orig_price,
        'dispatchprice' => 0.00,
        'dispatchtype' => 0,
        'dispatchid' => 0,
        "storeid" => 0,
        'carrier' => $carriers,
        'createtime' => time(),
        'isverify' => 0,
        'verifycode' => '',
        'virtual' => 0,
        'isvirtual' => 0,
        'oldprice' => 0,
        'olddispatchprice' => 0,
        "couponid" => $couponid,
        "couponprice" => $couponprice,
        'cashier' => 1
    );
    pdo_insert('sz_yi_order', $order);
    $orderid = pdo_insertid();
    pdo_insert('sz_yi_cashier_order', array('order_id' => $orderid, 'uniacid' => $_W['uniacid'], 'cashier_store_id' => $sid));
    $order_goods = array(
        'uniacid' => $_W['uniacid'],
        'orderid' => $orderid,
        'goodsid' => 0,
        'price' => $orig_price,
        'total' => 1,
        'optionid' => 0,
        'createtime' => time(),
        'optionname' => '',
        'goodssn' => '',
        'productsn' => '',
        "realprice" => $orig_price,
        "oldprice" => $orig_price,
        "openid" => $openid
    );
    pdo_insert('sz_yi_order_goods', $order_goods);
    $this->model->calculateCommission($orderid);
    if (is_array($carrier)) {
        $up = array(
            'realname' => $carrier['carrier_realname'],
            'mobile' => $carrier['carrier_mobile']
        );
        pdo_update('sz_yi_member', $up, array(
            'id' => $member['id'],
            'uniacid' => $_W['uniacid']
        ));
        if (!empty($member['uid'])) {
            load()->model('mc');
            mc_update($member['uid'], $up);
        }
    }
    if ($deductcredit > 0) {
        $shop = m('common')->getSysset('shop');
        m('member')->setCredit($openid, 'credit1', -$deductcredit, array(
            '0', "收银台购物积分抵扣 消费积分: {$deductcredit} 抵扣金额: {$deductmoney} 订单号: {$ordersn}"
        ));
    }
    //$orderid
    if($commission['become_child']==1){
        p('commission')->checkOrderConfirm($orderid);
    }
    show_json(1, array('orderid' => $orderid));
}
include $this->template('cashier/order_confirm');
