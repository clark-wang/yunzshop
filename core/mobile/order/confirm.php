<?php
if (!defined('IN_IA')) {
    exit('Access Denied');
}

global $_W, $_GPC;
$operation = !empty($_GPC['op']) ? $_GPC['op'] : 'display';
$openid    = m('user')->getOpenid();
$member    = m("member")->getMember($openid);
$shopset   = m('common')->getSysset('shop');
$uniacid   = $_W['uniacid'];
$fromcart  = 0;
$trade     = m('common')->getSysset('trade');
if (!empty($trade['shareaddress'])  && is_weixin()) {
    if (!$_W['isajax']) {
        $shareAddress = m('common')->shareAddress();
        if (empty($shareAddress)) {
            exit;
        }
    }
}
$pv = p('virtual');
$hascouponplugin = false;
$plugc           = p("coupon");
if ($plugc) {
    $hascouponplugin = true;
}
$goodid = $_GPC['id'] ? intval($_GPC['id']) : 0;
$cartid = $_GPC['cartids'] ? $_GPC['cartids'] : 0;
$diyform_plugin = p("diyform");
$order_formInfo = false;
if ($diyform_plugin) {
    $diyform_set = $diyform_plugin->getSet();
    if (!empty($diyform_set["order_diyform_open"])) {
        $orderdiyformid = intval($diyform_set["order_diyform"]);
        if (!empty($orderdiyformid)) {
            $order_formInfo = $diyform_plugin->getDiyformInfo($orderdiyformid);
            $fields         = $order_formInfo["fields"];
            $f_data         = $diyform_plugin->getLastOrderData($orderdiyformid, $member);
        }
    }
}

$carrier_list = pdo_fetchall("SELECT * FROM " . tablename("sz_yi_store") . " WHERE uniacid=:uniacid AND status=1", array(
            ":uniacid" => $_W["uniacid"]
        ));

if ($operation == "display" || $operation == "create") {
    $id   = intval($_GPC["id"]);
    $show = 1;
    if ($diyform_plugin) {
        if (!empty($id)) {
            $sql         = "SELECT id as goodsid,type,diyformtype,diyformid,diymode FROM " . tablename("sz_yi_goods") . " WHERE id=:id AND uniacid=:uniacid  limit 1";
            $goods_data  = pdo_fetch($sql, array(
                ":uniacid" => $uniacid,
                ":id" => $id
            ));
            $diyformtype = $goods_data["diyformtype"];
            $diyformid   = $goods_data["diyformid"];
            $diymode     = $goods_data["diymode"];
            if (!empty($diyformtype) && !empty($diyformid)) {
                $formInfo      = $diyform_plugin->getDiyformInfo($diyformid);
                $goods_data_id = intval($_GPC["gdid"]);
            }
        }
    }
}

$ischannelpick = $_GPC['ischannelpick'];

if ($operation == "date"){
        global $_GPC, $_W;
        $id = $_GPC['id'];
        if ($search_array && !empty($search_array['bdate']) && !empty($search_array['day'])) {
            $bdate = $search_array['bdate'];
            $day = $search_array['day'];
        } else {
            $bdate = date('Y-m-d');
            $day = 1;
        }
        load()->func('tpl');
        include $this->template('order/date');
        exit;
}else if ($operation == 'ajaxData') {
        global $_GPC, $_W;
        $id = $_GPC['id'];
        switch ($_GPC['ac'])
        {
            //选择日期
            case 'time':
                $bdate = $_GPC['bdate'];
                $day = $_GPC['day'];
                if (!empty($bdate) && !empty($day)) {
                    $btime = strtotime($bdate);
                    $etime = $btime + $day * 86400;
                    $weekarray = array("日", "一", "二", "三", "四", "五", "六");
                    $data['btime'] = $btime;
                    $data['etime'] = $etime;
                    $data['bdate'] = $bdate;
                    $data['edate'] = date('Y-m-d', $etime);
                    $data['bweek'] = '星期' . $weekarray[date("w", $btime)];
                    $data['eweek'] = '星期' . $weekarray[date("w", $etime)];
                    $data['day'] = $day;        
                    //setcookie('data',serialize($data),time()+2*7*24*3600);
                    $_SESSION['data']=$data;
                    $url = $this->createMobileUrl('order', array('p' =>'confirm','id'=> $id));
                    die(json_encode(array("result" => 1, "url" => $url)));
                }
                break;
        }
}


if ($_W['isajax']) {
    $ischannelpick = intval($_GPC['ischannelpick']);
    if ($operation == 'display') {
        $id       = intval($_GPC['id']);
        $optionid = intval($_GPC['optionid']);
        $total    = intval($_GPC['total']);
        $ischannelpay = intval($_GPC['ischannelpay']);
        $ids      = '';
        if ($total < 1) {
            $total = 1;
        }
        $buytotal  = $total;
        $isverify  = false;
        $isvirtual = false;
        $changenum = false;
        $goods     = array();

        if (empty($id)) {   //购物车,否则是直接购买的
            $condition = '';
            //todo, what? check var. cart store in db.
            $cartids   = $_GPC['cartids'];
            if (!empty($cartids)) {
                $condition = ' AND c.id in (' . $cartids . ')';
            }

           // $sql   = 'SELECT c.goodsid,c.total,g.maxbuy,g.type,g.issendfree,g.isnodiscount,g.weight,o.weight as optionweight,g.title,g.thumb,ifnull(o.marketprice, g.marketprice) as marketprice,o.title as optiontitle,c.optionid,g.storeids,g.isverify,g.isverifysend,g.deduct,g.deduct2,g.virtual,o.virtual as optionvirtual,discounts FROM ' . tablename('sz_yi_member_cart') . ' c ' . ' left join ' . tablename('sz_yi_goods') . ' g on c.goodsid = g.id ' . ' left join ' . tablename('sz_yi_goods_option') . ' o on c.optionid = o.id ' . " WHERE c.openid=:openid AND  c.deleted=0 AND c.uniacid=:uniacid {$condition} order by c.id desc";


            $suppliers = pdo_fetchall('SELECT distinct g.supplier_uid FROM ' . tablename('sz_yi_member_cart') . ' c ' . ' left join ' . tablename('sz_yi_goods') . ' g on c.goodsid = g.id ' . ' left join ' . tablename('sz_yi_goods_option') . ' o on c.optionid = o.id ' . " where c.openid=:openid and  c.deleted=0 and c.uniacid=:uniacid {$condition} order by g.supplier_uid asc", array(
                ':uniacid' => $uniacid,
                ':openid' => $openid
            ), 'supplier_uid');
            $sql   = 'SELECT c.goodsid,c.total,g.maxbuy,g.type,g.issendfree,g.isnodiscount,g.weight,o.weight as optionweight,g.title,g.thumb,ifnull(o.marketprice, g.marketprice) as marketprice,o.title as optiontitle,c.optionid,g.storeids,g.isverify,g.isverifysend,g.deduct,g.deduct2,g.virtual,o.virtual as optionvirtual,discounts,g.supplier_uid,g.dispatchprice,g.dispatchtype,g.dispatchid FROM ' . tablename('sz_yi_member_cart') . ' c ' . ' left join ' . tablename('sz_yi_goods') . ' g on c.goodsid = g.id ' . ' left join ' . tablename('sz_yi_goods_option') . ' o on c.optionid = o.id ' . " where c.openid=:openid and  c.deleted=0 and c.uniacid=:uniacid {$condition} order by g.supplier_uid asc";

            $goods = pdo_fetchall($sql, array(
                ':uniacid' => $uniacid,
                ':openid' => $openid
            ));
            if (empty($goods)) {
                show_json(-1, array(
                    'url' => $this->createMobileUrl('shop/cart')
                ));
            } else {
                foreach ($goods as $k => $v) {
                    if (!empty($v["optionvirtual"])) {
                        $goods[$k]["virtual"] = $v["optionvirtual"];
                    }
                    if (!empty($v["optionweight"])) {
                        $goods[$k]["weight"] = $v["optionweight"];
                    }
                }
            }
            $fromcart = 1;
        } else {

            //$sql              = "SELECT id as goodsid,type,title,weight,issendfree,isnodiscount, thumb,marketprice,storeids,isverify,isverifysend,deduct, manydeduct, virtual,maxbuy,usermaxbuy,discounts,total as stock, deduct2, ednum, edmoney, edareas, diyformtype, diyformid, diymode, dispatchtype, dispatchid, dispatchprice FROM " . tablename("sz_yi_goods") . " WHERE id=:id AND uniacid=:uniacid  limit 1";
            //$data             = pdo_fetch($sql, array(

            if(p('hotel')){
                $sql = "SELECT id as goodsid,type,title,weight,deposit,issendfree,isnodiscount, thumb,marketprice,storeids,isverify,isverifysend,deduct, manydeduct, virtual,maxbuy,usermaxbuy,discounts,total as stock, deduct2, ednum, edmoney, edareas, diyformtype, diyformid, diymode, dispatchtype, dispatchid, dispatchprice, supplier_uid FROM " . tablename("sz_yi_goods") . " where id=:id and uniacid=:uniacid  limit 1";
            }else{   
                $sql = "SELECT id as goodsid,type,title,weight,issendfree,isnodiscount, thumb,marketprice,storeids,isverify,isverifysend,deduct, manydeduct, virtual,maxbuy,usermaxbuy,discounts,total as stock, deduct2, ednum, edmoney, edareas, diyformtype, diyformid, diymode, dispatchtype, dispatchid, dispatchprice, supplier_uid FROM " . tablename("sz_yi_goods") . " where id=:id and uniacid=:uniacid  limit 1";
            }
            $data = pdo_fetch($sql, array(

                ':uniacid' => $uniacid,
                ':id' => $id
            ));
            $suppliers = array($data['supplier_uid'] => array("supplier_uid" => $data['supplier_uid']));
            $data['total']    = $total;
            $data['optionid'] = $optionid;
            if (!empty($optionid)) {
                $option = pdo_fetch('select id,title,marketprice,goodssn,productsn,virtual,stock,weight from ' . tablename('sz_yi_goods_option') . ' WHERE id=:id AND goodsid=:goodsid AND uniacid=:uniacid  limit 1', array(
                    ':uniacid' => $uniacid,
                    ':goodsid' => $id,
                    ':id' => $optionid
                ));
                if (!empty($option)) {
                    $data['optionid']    = $optionid;
                    $data['optiontitle'] = $option['title'];
                    $data['marketprice'] = $option['marketprice'];
                    $data['virtual']     = $option['virtual'];
                    $data['stock']       = $option['stock'];
                    if (!empty($option['weight'])) {
                        $data['weight'] = $option['weight'];
                    }
                }
            }
            $changenum   = true;
            $totalmaxbuy = $data['stock'];
            if ($data['maxbuy'] > 0) {
                if ($totalmaxbuy != -1) {
                    if ($totalmaxbuy > $data['maxbuy']) {
                        $totalmaxbuy = $data['maxbuy'];
                    }
                } else {
                    $totalmaxbuy = $data['maxbuy'];
                }
            }
            if ($data['usermaxbuy'] > 0) {
                $order_goodscount = pdo_fetchcolumn('select ifnull(sum(og.total),0)  from ' . tablename('sz_yi_order_goods') . ' og ' . ' left join ' . tablename('sz_yi_order') . ' o on og.orderid=o.id ' . ' WHERE og.goodsid=:goodsid AND  o.status>=1 AND o.openid=:openid  AND og.uniacid=:uniacid ', array(
                    ':goodsid' => $data['goodsid'],
                    ':uniacid' => $uniacid,
                    ':openid' => $openid
                ));
                $last = $data['usermaxbuy'] - $order_goodscount;
                if ($last <= 0) {
                    $last = 0;
                }
                if ($totalmaxbuy != -1) {
                    if ($totalmaxbuy > $last) {
                        $totalmaxbuy = $last;
                    }
                } else {
                    $totalmaxbuy = $last;
                }
            }
            $data['totalmaxbuy'] = $totalmaxbuy;
            if(p('hotel')){ 
                if($data['type']=='99'){              
                $btime =  $_SESSION['data']['btime'];
                $bdate =  $_SESSION['data']['bdate'];
                // 住几天
                $days =intval( $_SESSION['data']['day']);
                // 离店
                $etime =  $_SESSION['data']['etime'];
                $edate =  $_SESSION['data']['edate'] ;
                $date_array = array();
                $date_array[0]['date'] = $bdate;
                $date_array[0]['day'] = date('j', $btime);
                $date_array[0]['time'] = $btime;
                $date_array[0]['month'] = date('m',$btime);   

                if ($days > 1) {
                    for($i = 1; $i < $days; $i++) {
                        $date_array[$i]['time'] = $date_array[$i-1]['time'] + 86400;
                        $date_array[$i]['date'] = date('Y-m-d', $date_array[$i]['time']);
                        $date_array[$i]['day'] = date('j', $date_array[$i]['time']);
                        $date_array[$i]['month'] = date('m', $date_array[$i]['time']);
                    }
                }
                $sql2 = 'SELECT * FROM ' . tablename('sz_yi_hotel_room') . ' WHERE `goodsid` = :goodsid';
                $params2 = array(':goodsid' => $id);
                $room = pdo_fetch($sql2, $params2);
          
                $sql = 'SELECT `id`, `roomdate`, `num`, `status` FROM ' . tablename('sz_yi_hotel_room_price') . ' WHERE `roomid` = :roomid
                AND `roomdate` >= :btime AND `roomdate` < :etime AND `status` = :status';

                $params = array(':roomid' => $room['id'], ':btime' => $btime, ':etime' => $etime, ':status' => '1');
                $room_date_list = pdo_fetchall($sql, $params);
                $flag = intval($room_date_list);
                $list = array();
                $max_room = 5;//最大预约房间数
                $is_order = 1;
                if ($flag == 1) {
                    for($i = 0; $i < $days; $i++) {
                        $k = $date_array[$i]['time'];
                        foreach ($room_date_list as $p_key => $p_value) {
                            // 判断价格表中是否有当天的数据
                            if($p_value['roomdate'] == $k) {
                                $room_num = $p_value['num'];
                                if (empty($room_num)) {
                                    $is_order = 0;
                                    $max_room = 0;
                                    $list['num'] = 0;
                                    $list['date'] =  $date_array[$i]['date'];
                                } else if ($room_num > 0 && $room_num < $max_room) {
                                    $max_room = $room_num;
                                    $list['num'] =  $room_num;
                                    $list['date'] =  $date_array[$i]['date'];
                                }else {
                                    $list['num'] =  $max_room;
                                    $list['date'] =  $date_array[$i]['date'];
                                }
                                break;
                            }
                        }
                    }
               }   
               $data['totalmaxbuy']= $list['num'];   
            }
        }
             $goods[] = $data;
        }

       
        $goods = set_medias($goods, 'thumb');
        foreach ($goods as $g) {
            if ($g['isverify'] == 2) {
                $isverify = true;
            }
            if ($g['isverifysend'] == 1) {
                $isverifysend = true;
            }
            if (!empty($g['virtual']) || $g['type'] == 2) {
                $isvirtual = true;
            }
            if (p('channel')) {
                if ($ischannelpay == 1 && empty($ischannelpick)) {
                    $isvirtual = true;
                }
            }
        }
        //多店值分开初始化
        foreach ($suppliers as $key => $val) {
            $order_all[$val['supplier_uid']]['weight']         = 0;
            $order_all[$val['supplier_uid']]['total']          = 0;
            $order_all[$val['supplier_uid']]['goodsprice']     = 0;
            $order_all[$val['supplier_uid']]['realprice']      = 0;
            $order_all[$val['supplier_uid']]['deductprice']    = 0;
            $order_all[$val['supplier_uid']]['discountprice']  = 0;
            $order_all[$val['supplier_uid']]['deductprice2']   = 0;
            $order_all[$val['supplier_uid']]['dispatch_price'] = 0;
            $order_all[$val['supplier_uid']]['storeids']       = array();
            $order_all[$val['supplier_uid']]['dispatch_array'] = array();
            $order_all[$val['supplier_uid']]['supplier_uid'] = $val['supplier_uid'];
            if ($val['supplier_uid']==0) {
                $order_all[$val['supplier_uid']]['supplier_name'] = $shopset['name'];
            } else {
                $supplier_names = pdo_fetch('select username, brandname from ' . tablename('sz_yi_perm_user') . ' where uid='. $val['supplier_uid'] . " and uniacid=" . $_W['uniacid']);
                if (!empty($supplier_names)) {
                    $order_all[$val['supplier_uid']]['supplier_name'] = $supplier_names['brandname'] ? $supplier_names['brandname'] : "";
                } else {
                    $order_all[$val['supplier_uid']]['supplier_name'] = '';
                }
            }
        }
        $member        = m('member')->getMember($openid);
        $level          = m("member")->getLevel($openid);
        //$weight         = 0;
        //$total          = 0;
        //$goodsprice     = 0;
        //$realprice      = 0;
        //$deductprice    = 0;
        //$discountprice  = 0;
        //$deductprice2   = 0;
        $stores        = array();
        $address       = false;
        $carrier       = false;
        $carrier_list  = array();
        $dispatch_list = false;

        //$dispatch_price = 0;
        //$dispatch_array = array();
        
        //$carrier_list = pdo_fetchall("select * from " . tablename("sz_yi_store") . " where  uniacid=:uniacid and status=1 and type in(1,3)", array(
        $carrier_list = pdo_fetchall("select * from " . tablename("sz_yi_store") . " where  uniacid=:uniacid and status=1 and myself_support=1 ", array(

            ":uniacid" => $_W["uniacid"]
        ));
        if (!empty($carrier_list)) {
            $carrier = $carrier_list[0];
        }
        if (p('channel')) {
            $my_info = p('channel')->getInfo($openid);
        }
        foreach ($goods as &$g) {
            if (empty($g["total"]) || intval($g["total"]) == "-1") {
                $g["total"] = 1;
            }
            if (p('channel')) {
                if ($ischannelpay == 1) {
                    $g['marketprice'] = $g['marketprice'] * $my_info['my_level']['purchase_discount']/100;
                }
            }
            $gprice    = $g["marketprice"] * $g["total"];
            $discounts = json_decode($g["discounts"], true);
            if (is_array($discounts)) {
                if (!empty($level["id"])) {
                    if (floatval($discounts["level" . $level["id"]]) > 0 && floatval($discounts["level" . $level["id"]]) < 10) {
                        $level["discount"] = floatval($discounts["level" . $level["id"]]);
                    } else if (floatval($level["discount"]) > 0 && floatval($level["discount"]) < 10) {
                        $level["discount"] = floatval($level["discount"]);
                    } else {
                        $level["discount"] = 0;
                    }
                } else {
                    if (floatval($discounts["default"]) > 0 && floatval($discounts["default"]) < 10) {
                        $level["discount"] = floatval($discounts["default"]);
                    } else if (floatval($level["discount"]) > 0 && floatval($level["discount"]) < 10) {
                        $level["discount"] = floatval($level["discount"]);
                    } else {
                        $level["discount"] = 0;
                    }
                }
            }
            if (empty($g["isnodiscount"]) && floatval($level["discount"]) > 0 && floatval($level["discount"]) < 10) {
                $price = round(floatval($level["discount"]) / 10 * $gprice, 2);
                $order_all[$g['supplier_uid']]['discountprice'] += $gprice - $price;
            } else {
                $price = $gprice;
            }
            $g["ggprice"] = $price;
            $order_all[$g['supplier_uid']]['realprice'] += $price;
            $order_all[$g['supplier_uid']]['goodsprice'] += $gprice;
            //商品为酒店时候的价格
            if(p('hotel') && $data['type']=='99'){
            $sql2 = 'SELECT * FROM ' . tablename('sz_yi_hotel_room') . ' WHERE `goodsid` = :goodsid';
            $params2 = array(':goodsid' => $id);
            $room = pdo_fetch($sql2, $params2);
            $pricefield ='oprice';
            $r_sql = 'SELECT `roomdate`, `num`, `oprice`, `status`, ' . $pricefield . ' AS `m_price` FROM ' . tablename('sz_yi_hotel_room_price') .
            ' WHERE `roomid` = :roomid AND `roomdate` >= :btime AND ' .
            ' `roomdate` < :etime';
            $params = array(':roomid' => $room['id'],':btime' => $btime, ':etime' => $etime);
            $price_list = pdo_fetchall($r_sql, $params);  
            $this_price = $old_price =  $pricefield == 'cprice' ?  $room['oprice']*$member_p[$_W['member']['groupid']] : $room['roomprice'];
            if ($this_price == 0) {
                $this_price = $old_price = $room['oprice'] ;
            } 
            $totalprice =  $old_price * $days;
            if ($price_list) {//价格表中存在   
                $check_date = array();
                foreach($price_list as $k => $v) {
                    $price_list[$k]['time']=date('Y-m-d',$v['roomdate']);
                    $new_price = $pricefield == 'mprice' ? $this_price : $v['m_price'];
                    $roomdate = $v['roomdate'];
                    if ($v['status'] == 0 || $v['num'] == 0 ) {
                        $has = 0;                   
                    } else {
                        if ($new_price && $roomdate) {
                            if (!in_array($roomdate, $check_date)) {
                                $check_date[] = $roomdate;
                                if ($old_price != $new_price) {
                                    $totalprice = $totalprice - $old_price + $new_price;
                                }
                            }
                        }
                    }
                } 
                $goodsprice = round($totalprice);
            }else{ 
                $goodsprice = round($goods[0]['marketprice']) * $days;
            }          
            $order_all[$g['supplier_uid']]['realprice'] = $goodsprice;
            $order_all[$g['supplier_uid']]['goodsprice'] = $goodsprice;
            $price = $goodsprice;
          
        }
            $order_all[$g['supplier_uid']]['total'] += $g["total"];
            if ($g["manydeduct"]) {
                $order_all[$g['supplier_uid']]['deductprice'] += $g["deduct"] * $g["total"];
            } else {
                $order_all[$g['supplier_uid']]['deductprice'] += $g["deduct"];
            }
            if ($g["deduct2"] == 0) {
                $order_all[$g['supplier_uid']]['deductprice2'] += $price;
            } else if ($g["deduct2"] > 0) {
                if ($g["deduct2"] > $price) {
                    $order_all[$g['supplier_uid']]['deductprice2'] += $price;
                } else {
                    $order_all[$g['supplier_uid']]['deductprice2'] += $g["deduct2"];
                }
            }
            $order_all[$g['supplier_uid']]['goods'][] = $g;
        }

        unset($g);
        //核销
        if ($isverify) {
            $storeids = array();
            foreach ($goods as $g) {
                if (!empty($g['storeids'])) {
                    $order_all[$g['supplier_uid']]['storeids'] = array_merge(explode(',', $g['storeids']), $order_all[$g['supplier_uid']]['storeids']);
                }
            }

            foreach ($suppliers as $key => $val) {
                if (empty($order_all[$val['supplier_uid']]['storeids'])) {
                    $order_all[$val['supplier_uid']]['stores'] = pdo_fetchall('select * from ' . tablename('sz_yi_store') . ' where  uniacid=:uniacid and status=1 and myself_support=1', array(
                        ':uniacid' => $_W['uniacid']
                    ));
                } else {
                    $order_all[$val['supplier_uid']]['stores'] = pdo_fetchall('select * from ' . tablename('sz_yi_store') . ' where id in (' . implode(',', $order_all[$val['supplier_uid']]['storeids']) . ') and uniacid=:uniacid and status=1 and myself_support=1', array(
                        ':uniacid' => $_W['uniacid']
                    ));
                }
                $stores = $order_all[$val['supplier_uid']]['stores'];
            }
            
            $address      = pdo_fetch('select id,realname,mobile,address,province,city,area from ' . tablename('sz_yi_member_address') . ' where openid=:openid and deleted=0 and isdefault=1  and uniacid=:uniacid limit 1', array(

                ':uniacid' => $uniacid,
                ':openid' => $openid
            ));
        } else {
            $address      = pdo_fetch('select id,realname,mobile,address,province,city,area from ' . tablename('sz_yi_member_address') . ' WHERE openid=:openid AND deleted=0 AND isdefault=1  AND uniacid=:uniacid limit 1', array(
                ':uniacid' => $uniacid,
                ':openid' => $openid
            ));
        }

        //如果开启核销并且不支持配送，则没有运费
        $isDispath = true;
        if ($isverify && !$isverifysend) {
            $isDispath = false;
        }

        if (!$isvirtual && $isDispath) {
            foreach ($goods as $g) {
                $sendfree = false;
                if (!empty($g["issendfree"])) { //包邮
                    $sendfree = true;
                } else {
                    if ($g["total"] >= $g["ednum"] && $g["ednum"] > 0) {    //单品满xx件包邮
                        $gareas = explode(";", $g["edareas"]);  //不参加包邮地区
                        if (empty($gareas)) {
                            $sendfree = true;
                        } else {
                            if (!empty($address)) {
                                if (!in_array($address["city"], $gareas)) {
                                    $sendfree = true;
                                }
                            } else if (!empty($member["city"])) {
                                if (!in_array($member["city"], $gareas)) {
                                    $sendfree = true;
                                }
                            } else {
                                $sendfree = true;
                            }
                        }
                    }
                    if ($g["ggprice"] >= floatval($g["edmoney"]) && floatval($g["edmoney"]) > 0) {  //满额包邮
                        $gareas = unserialize($g["edareas"]);
                        if (empty($gareas)) {
                            $sendfree = true;
                        } else {
                            if (!empty($address)) {
                                if (!in_array($address["city"], $gareas)) {
                                    $sendfree = true;
                                }
                            } else if (!empty($member["city"])) {
                                if (!in_array($member["city"], $gareas)) {
                                    $sendfree = true;
                                }
                            } else {
                                $sendfree = true;
                            }
                        }
                    }
                }

                if (!$sendfree) {   //计算运费
                    if ($g["dispatchtype"] == 1) {  //统一邮费
                        if ($g["dispatchprice"] > 0) {
                            $order_all[$g['supplier_uid']]['dispatch_price'] += $g["dispatchprice"] * $g["total"];
                        }
                    } else if ($g["dispatchtype"] == 0) {   //运费模板
                        if (empty($g["dispatchid"])) {
                            $order_all[$g['supplier_uid']]['dispatch_data'] = m("order")->getDefaultDispatch($g['supplier_uid']);
                        } else {
                            $order_all[$g['supplier_uid']]['dispatch_data'] = m("order")->getOneDispatch($g["dispatchid"], $g['supplier_uid']);
                        }
                        if (empty($order_all[$g['supplier_uid']]['dispatch_data'])) {
                            $order_all[$g['supplier_uid']]['dispatch_data'] = m("order")->getNewDispatch($g['supplier_uid']);
                        }
                        if (!empty($order_all[$g['supplier_uid']]['dispatch_data'])) {
                            if ($order_all[$g['supplier_uid']]['dispatch_data']["calculatetype"] == 1) {
                                $order_all[$g['supplier_uid']]['param'] = $g["total"];
                            } else {
                                $order_all[$g['supplier_uid']]['param'] = $g["weight"] * $g["total"];
                            }
                            $dkey = $order_all[$g['supplier_uid']]['dispatch_data']["id"];
                            if (array_key_exists($dkey, $order_all[$g['supplier_uid']]['dispatch_array'])) {
                                $order_all[$g['supplier_uid']]['dispatch_array'][$dkey]["param"] += $order_all[$g['supplier_uid']]['param'];
                            } else {
                                $order_all[$g['supplier_uid']]['dispatch_array'][$dkey]["data"]  = $order_all[$g['supplier_uid']]['dispatch_data'];
                                $order_all[$g['supplier_uid']]['dispatch_array'][$dkey]["param"] = $order_all[$g['supplier_uid']]['param'];
                            }
                        }
                    }
                }
                foreach ($suppliers as $key => $val) {
                    if (!empty($order_all[$val['supplier_uid']]['dispatch_array'])) {
                        foreach ($order_all[$val['supplier_uid']]['dispatch_array'] as $k => $v) {
                            $order_all[$val['supplier_uid']]['dispatch_data'] = $order_all[$val['supplier_uid']]['dispatch_array'][$k]["data"];
                            $param         = $order_all[$val['supplier_uid']]['dispatch_array'][$k]["param"];
                            $areas         = unserialize($order_all[$val['supplier_uid']]['dispatch_data']["areas"]);
                            if (!empty($address)) {
                                $order_all[$val['supplier_uid']]['dispatch_price'] += m("order")->getCityDispatchPrice($areas, $address["city"], $param, $order_all[$val['supplier_uid']]['dispatch_data'], $val['supplier_uid']);
                            } else if (!empty($member["city"])) {
                                $order_all[$val['supplier_uid']]['dispatch_price'] += m("order")->getCityDispatchPrice($areas, $member["city"], $param, $order_all[$val['supplier_uid']]['dispatch_data'], $val['supplier_uid']);
                            } else {
                                $order_all[$val['supplier_uid']]['dispatch_price'] += m("order")->getDispatchPrice($param, $order_all[$val['supplier_uid']]['dispatch_data'], -1, $val['supplier_uid']);
                            }
                        }
                    }
                }
            }
        }

        $sale_plugin   = p('sale');
        $saleset       = false;
        if ($sale_plugin) {
            $saleset = $sale_plugin->getSet();
            $saleset["enoughs"] = $sale_plugin->getEnoughs();
        }
        //订单总价
        $realprice_total = 0;
        foreach ($suppliers as $key => $val) {
            if ($saleset) {
                //满额包邮
                if (!empty($saleset["enoughfree"])) {
                    if (floatval($saleset["enoughorder"]) <= 0) {
                        $order_all[$val['supplier_uid']]['dispatch_price'] = 0;
                    } else {
                        if ($order_all[$val['supplier_uid']]['realprice'] >= floatval($saleset["enoughorder"])) {
                            if (empty($saleset["enoughareas"])) {
                                $order_all[$val['supplier_uid']]['dispatch_price'] = 0;
                            } else {
                                $areas = explode(",", $saleset["enoughareas"]);
                                if (!empty($address)) {
                                    if (!in_array($address["city"], $areas)) {
                                        $order_all[$val['supplier_uid']]['dispatch_price'] = 0;
                                    }
                                } else if (!empty($member["city"])) {
                                    if (!in_array($member["city"], $areas)) {
                                        $order_all[$val['supplier_uid']]['dispatch_price'] = 0;
                                    }
                                } else if (empty($member["city"])) {
                                    $order_all[$val['supplier_uid']]['dispatch_price'] = 0;
                                }
                            }
                        }
                    }
                }
                if(p('hotel') &&  $data['type']=='99'){
                    $order_all[$val['supplier_uid']]['dispatch_price']  = 0;
                }
                $order_all[$val['supplier_uid']]['saleset'] = $saleset;
                
                if (!empty($saleset["enoughs"])) {
                    //取满额条件值最大的1个条件
                    $tmp_money = 0;

                    foreach ($saleset["enoughs"] as $e) {
                        if ($order_all[$val['supplier_uid']]['realprice'] >= floatval($e["enough"]) && floatval($e["money"]) > 0) {
                            if ($e["enough"] > $tmp_money) {
                                $tmp_money = $e["enough"];

                                $order_all[$val['supplier_uid']]['saleset']["showenough"]   = true;
                                $order_all[$val['supplier_uid']]['saleset']["enoughmoney"]  = $e["enough"];
                                $order_all[$val['supplier_uid']]['saleset']["enoughdeduct"] = number_format($e["money"], 2);
                                $final_money = $e["money"];

                                //确定匹配的满额条件,页面显示
                                $saleset['enoughmoney'] = $e["enough"];
                                $saleset['enoughdeduct'] = number_format($e["money"], 2);
                            }
                        }
                    }

                    $order_all[$val['supplier_uid']]['realprice'] -= floatval($final_money);
                }

                if (empty($saleset["dispatchnodeduct"])) {
                    $order_all[$val['supplier_uid']]['deductprice2'] += $order_all[$val['supplier_uid']]['dispatch_price'];
                }
            }
            $order_all[$val['supplier_uid']]['hascoupon'] = false;
            if ($hascouponplugin) {
                $order_all[$val['supplier_uid']]['couponcount'] = $plugc->consumeCouponCount($openid, $order_all[$val['supplier_uid']]['realprice'], $val['supplier_uid'], 0, 0, $goodid, $cartid);
                $order_all[$val['supplier_uid']]['hascoupon']   = $order_all[$val['supplier_uid']]['couponcount'] > 0;
            }
            $order_all[$val['supplier_uid']]['realprice'] += $order_all[$val['supplier_uid']]['dispatch_price'];

            $realprice_total += $order_all[$val['supplier_uid']]['realprice'];
            $order_all[$val['supplier_uid']]['deductcredit']  = 0;
            $order_all[$val['supplier_uid']]['deductmoney']   = 0;
            $order_all[$val['supplier_uid']]['deductcredit2'] = 0;
            if ($sale_plugin) {
                $credit = m('member')->getCredit($openid, 'credit1');
                if (!empty($saleset['creditdeduct'])) {
                    $pcredit = intval($saleset['credit']);
                    $pmoney  = round(floatval($saleset['money']), 2);
                    if ($pcredit > 0 && $pmoney > 0) {
                        if ($credit % $pcredit == 0) {
                            $order_all[$val['supplier_uid']]['deductmoney'] = round(intval($credit / $pcredit) * $pmoney, 2);
                        } else {
                            $order_all[$val['supplier_uid']]['deductmoney'] = round((intval($credit / $pcredit) + 1) * $pmoney, 2);
                        }
                    }
                    if ($order_all[$val['supplier_uid']]['deductmoney'] >$order_all[$g['supplier_uid']]['deductprice']) {
                        $order_all[$val['supplier_uid']]['deductmoney'] = $order_all[$g['supplier_uid']]['deductprice'];
                    }
                    if ($order_all[$val['supplier_uid']]['deductmoney'] > $order_all[$val['supplier_uid']]['realprice']) {
                        $order_all[$val['supplier_uid']]['deductmoney'] = $order_all[$val['supplier_uid']]['realprice'];
                    }
                    $order_all[$val['supplier_uid']]['deductcredit'] = $order_all[$val['supplier_uid']]['deductmoney'] / $pmoney * $pcredit;
                }
                if (!empty($saleset['moneydeduct'])) {
                    $order_all[$val['supplier_uid']]['deductcredit2'] = m('member')->getCredit($openid, 'credit2');
                    if ($order_all[$val['supplier_uid']]['deductcredit2'] > $order_all[$val['supplier_uid']]['realprice']) {
                        $order_all[$val['supplier_uid']]['deductcredit2'] = $order_all[$val['supplier_uid']]['realprice'];
                    }
                    if ($order_all[$val['supplier_uid']]['deductcredit2'] > $order_all[$val['supplier_uid']]['deductprice2']) {
                            $order_all[$val['supplier_uid']]['deductcredit2'] = $order_all[$val['supplier_uid']]['deductprice2'];
                    }
                }
            }
            $order_all[$val['supplier_uid']]['goodsprice'] = number_format($order_all[$val['supplier_uid']]['goodsprice'], 2);
            $order_all[$val['supplier_uid']]['totalprice'] = number_format($order_all[$val['supplier_uid']]['totalprice'], 2);
            $order_all[$val['supplier_uid']]['discountprice'] = number_format($order_all[$val['supplier_uid']]['discountprice'], 2);
            $order_all[$val['supplier_uid']]['realprice'] = number_format($order_all[$val['supplier_uid']]['realprice'], 2);
            $order_all[$val['supplier_uid']]['dispatch_price'] = number_format($order_all[$val['supplier_uid']]['dispatch_price'], 2);

        }
        $supplierids = implode(',', array_keys($suppliers));
        if(p('hotel')){
            if($data['type']=='99'){
            $sql2 = 'SELECT * FROM ' . tablename('sz_yi_hotel_room') . ' WHERE `goodsid` = :goodsid';
            $params2 = array(':goodsid' => $id);
            $room = pdo_fetch($sql2, $params2);
            $pricefield ='oprice';
            $r_sql = 'SELECT `roomdate`, `num`, `oprice`, `status`, ' . $pricefield . ' AS `m_price` FROM ' . tablename('sz_yi_hotel_room_price') .
            ' WHERE `roomid` = :roomid AND `roomdate` >= :btime AND ' .
            ' `roomdate` < :etime';
            $btime =  $_SESSION['data']['btime'];           
            $etime =  $_SESSION['data']['etime'];
            $params = array(':roomid' => $room['id'],':btime' => $btime, ':etime' => $etime);
            $price_list = pdo_fetchall($r_sql, $params);  
            $this_price = $old_price =  $pricefield == 'cprice' ?  $room['oprice']*$member_p[$_W['member']['groupid']] : $room['roomprice'];
            if ($this_price == 0) {
                $this_price = $old_price = $room['oprice'] ;
            } 
            $totalprice =  $old_price * $days;
            if ($price_list) {//价格表中存在   
                $check_date = array();
                foreach($price_list as $k => $v) {
                    $price_list[$k]['time']=date('Y-m-d',$v['roomdate']);
                    $new_price = $pricefield == 'mprice' ? $this_price : $v['m_price'];
                    $roomdate = $v['roomdate'];
                    if ($v['status'] == 0 || $v['num'] == 0 ) {
                        $has = 0;                   
                    } else {
                        if ($new_price && $roomdate) {
                            if (!in_array($roomdate, $check_date)) {
                                $check_date[] = $roomdate;
                                if ($old_price != $new_price) {
                                    $totalprice = $totalprice - $old_price + $new_price;
                                }
                            }
                        }
                    }
                } 
                $goodsprice = round($totalprice);
            }else{ 
                $goodsprice = round($goods[0]['marketprice']) * $days;
            }
            $realprice  = $goodsprice+$goods[0]['deposit'];
            $deposit = $goods[0]['deposit'];
            $order_all[$g['supplier_uid']]['realprice'] = $goodsprice;
            $order_all[$g['supplier_uid']]['goodsprice'] = $goodsprice;
          
        }}
        show_json(1, array(
            'member' => $member,
            //'deductcredit' => $deductcredit,
            'deductmoney' => $deductmoney,
            'deductcredit2' => $deductcredit2,
            'saleset' => $saleset,
            'goods' => $goods,
            'has'=>$has,
            'weight' => $weight / $buytotal,
            'set' => $shopset,
            'fromcart' => $fromcart,
            'haslevel' => !empty($level['id']) && $level['discount'] > 0 && $level['discount'] < 10,
            'total' => $total,
            //"dispatchprice" => number_format($dispatch_price, 2),
            'totalprice' => number_format($totalprice, 2),
            'goodsprice' => number_format($goodsprice, 2),
            'discountprice' => number_format($discountprice, 2),
            'discount' => $level['discount'],
            'realprice_total' => number_format($realprice_total, 2),
            'address' => $address,
            //'carrier' => $carrier,
            //'carrier_list' => $carrier_list,
            'carrier' => $stores[0],
            'carrier_list' => $stores,
            'dispatch_list' => $dispatch_list,
            'isverify' => $isverify,
            'isverifysend' => $isverifysend,
            'stores' => $stores,
            'isvirtual' => $isvirtual,
            'changenum' => $changenum,
            //'hascoupon' => $hascoupon,
            //'couponcount' => $couponcount,
            'order_all' => $order_all,
            'supplierids' => $supplierids,
            "deposit" => number_format($deposit, 2),
            'price_list' => $price_list,
            'realprice' => number_format($realprice, 2),
            'type'=>$goods[0]['type'],
        ));
    } elseif ($operation == 'getdispatchprice') {
        $isverify       = false;
        $isvirtual      = false;
        $isverifysend   = false;
        $deductprice    = 0;
        $deductprice2   = 0;
        $deductcredit2  = 0;
        $dispatch_array = array();
        $totalprice = floatval($_GPC['totalprice']);
        $dflag          = $_GPC["dflag"];
        $hascoupon      = false;
        $couponcount    = 0;
        $pc             = p("coupon");
        $supplier_uid   = $_GPC["supplier_uid"];
        $coupon_carrierid = intval($_GPC['carrierid']);
        $goodsid = $_GPC['id'] ? intval($_GPC['id']) : 0;
        $cartids = $_GPC['cartids'] ? $_GPC['cartids'] : 0;
        if ($pc) {
            $pset = $pc->getSet();
            if (empty($pset["closemember"])) {
                $couponcount = $pc->consumeCouponCount($openid, $totalprice, $supplier_uid, 0, 0, $goodsid, $cartids,$coupon_carrierid);
                $hascoupon   = $couponcount > 0;
            }
        }
        $addressid           = intval($_GPC["addressid"]);
        $address     = pdo_fetch('select id,realname,mobile,address,province,city,area from ' . tablename('sz_yi_member_address') . ' WHERE  id=:id AND openid=:openid AND uniacid=:uniacid limit 1', array(
            ':uniacid' => $uniacid,
            ':openid' => $openid,
            ':id' => $addressid
        ));
        if (!empty($coupon_carrierid)) {
            show_json(1,array(
                             "hascoupon" => $hascoupon,
                            "couponcount" => $couponcount,
                            )
            );
        }
        $member              = m("member")->getMember($openid);
        $level               = m("member")->getLevel($openid);
        $weight              = $_GPC["weight"];
        $dispatch_price      = 0;
        $deductenough_money  = 0;
        $deductenough_enough = 0;
        $sale_plugin = p('sale');
        $saleset     = false;
        if ($sale_plugin && $supplier_uid==0) {
            $saleset = $sale_plugin->getSet();
            $saleset["enoughs"] = $sale_plugin->getEnoughs();
        }
        if ($sale_plugin && $supplier_uid==0) {
            if ($saleset) {
                foreach ($saleset["enoughs"] as $e) {
                    if ($totalprice >= floatval($e["enough"]) && floatval($e["money"]) > 0) {
                        $deductenough_money  = floatval($e["money"]);
                        $deductenough_enough = floatval($e["enough"]);
                        break;
                    }
                }
                if (!empty($saleset['enoughfree'])) {
                    if (floatval($saleset['enoughorder']) <= 0) {
                        show_json(1, array(
                            'price' => 0,
                            "hascoupon" => $hascoupon,
                            "couponcount" => $couponcount,
                            "deductenough_money" => $deductenough_money,
                            "deductenough_enough" => $deductenough_enough
                        ));
                    }
                }
                if (!empty($saleset['enoughfree']) && $totalprice >= floatval($saleset['enoughorder'])) {
                    if (!empty($saleset['enoughareas'])) {
                        $areas = explode(";", $saleset['enoughareas']);
                        if (!in_array($address['city'], $areas)) {
                            show_json(1, array(
                                "price" => 0,
                                "hascoupon" => $hascoupon,
                                "couponcount" => $couponcount,
                                "deductenough_money" => $deductenough_money,
                                "deductenough_enough" => $deductenough_enough
                            ));
                        }
                    } else {
                        show_json(1, array(
                            "price" => 0,
                            "hascoupon" => $hascoupon,
                            "couponcount" => $couponcoun,
                            "deductenough_money" => $deductenough_money,
                            "deductenough_enough" => $deductenough_enough
                        ));
                    }
                }
            }
        }
        $goods = trim($_GPC["goods"]);
        if (!empty($goods)) {
            $weight   = 0;
            $allgoods = array();
            $goodsarr = explode("|", $goods);
            foreach ($goodsarr as &$g) {
                if (empty($g)) {
                    continue;
                }
                $goodsinfo  = explode(",", $g);
                $goodsid    = !empty($goodsinfo[0]) ? intval($goodsinfo[0]) : '';
                $optionid   = !empty($goodsinfo[1]) ? intval($goodsinfo[1]) : 0;
                $goodstotal = !empty($goodsinfo[2]) ? intval($goodsinfo[2]) : "1";
                if ($goodstotal < 1) {
                    $goodstotal = 1;
                }
                if (empty($goodsid)) {
                    show_json(1, array(
                        "price" => 0
                    ));
                }
                $sql  = "SELECT id as goodsid,title,type, weight,total,issendfree,isnodiscount, thumb,marketprice,cash,isverify,goodssn,productsn,sales,istime,timestart,timeend,usermaxbuy,maxbuy,unit,buylevels,buygroups,deleted,status,deduct,manydeduct,virtual,discounts,deduct2,ednum,edmoney,edareas,diyformid,diyformtype,diymode,dispatchtype,dispatchid,dispatchprice FROM " . tablename("sz_yi_goods") . " WHERE id=:id AND uniacid=:uniacid  limit 1";
                $data = pdo_fetch($sql, array(
                    ":uniacid" => $uniacid,
                    ":id" => $goodsid
                ));
                if (empty($data)) {
                    show_json(1, array(
                        "price" => 0
                    ));
                }
                $data["stock"] = $data["total"];
                $data["total"] = $goodstotal;
                if (!empty($optionid)) {
                    $option = pdo_fetch("select id,title,marketprice,goodssn,productsn,stock,virtual,weight from " . tablename("sz_yi_goods_option") . " WHERE id=:id AND goodsid=:goodsid AND uniacid=:uniacid  limit 1", array(
                        ":uniacid" => $uniacid,
                        ":goodsid" => $goodsid,
                        ":id" => $optionid
                    ));
                    if (!empty($option)) {
                        $data["optionid"]    = $optionid;
                        $data["optiontitle"] = $option["title"];
                        $data["marketprice"] = $option["marketprice"];
                        if (!empty($option["weight"])) {
                            $data["weight"] = $option["weight"];
                        }
                    }
                }
                $discounts = json_decode($data["discounts"], true);
                if (is_array($discounts)) {
                    if (!empty($level["id"])) {
                        if ($discounts["level" . $level["id"]] > 0 && $discounts["level" . $level["id"]] < 10) {
                            $level["discount"] = $discounts["level" . $level["id"]];
                        } else if (floatval($level["discount"]) > 0 && floatval($level["discount"]) < 10) {
                            $level["discount"] = floatval($level["discount"]);
                        } else {
                            $level["discount"] = 0;
                        }
                    } else {
                        if ($discounts["default"] > 0 && $discounts["default"] < 10) {
                            $level["discount"] = $discounts["default"];
                        } else if (floatval($level["discount"]) > 0 && floatval($level["discount"]) < 10) {
                            $level["discount"] = floatval($level["discount"]);
                        } else {
                            $level["discount"] = 0;
                        }
                    }
                }
                $gprice  = $data["marketprice"] * $goodstotal;
                $ggprice = 0;
                if (empty($data["isnodiscount"]) && $level["discount"] > 0 && $level["discount"] < 10) {
                    $dprice = round($gprice * $level["discount"] / 10, 2);
                    $discountprice += $gprice - $dprice;
                    $ggprice = $dprice;
                } else {
                    $ggprice = $gprice;
                }
                $data["ggprice"] = $ggprice;
                $allgoods[]      = $data;
            }
            unset($g);
            foreach ($allgoods as $g) {
                if ($g["isverify"] == 2) {
                    $isverify = true;
                }
                if (!empty($g["virtual"]) || $g["type"] == 2) {
                    $isvirtual = true;
                }
                if ($g['isverifysend'] == 1) {
                    $isverifysend = true;
                }
                if ($g["manydeduct"]) {
                    $deductprice += $g["deduct"] * $g["total"];
                } else {
                    $deductprice += $g["deduct"];
                }
                if ($g["deduct2"] == 0) {
                    $deductprice2 += $g["ggprice"];
                } else if ($g["deduct2"] > 0) {
                    if ($g["deduct2"] > $g["ggprice"]) {
                        $deductprice2 += $g["ggprice"];
                    } else {
                        $deductprice2 += $g["deduct2"];
                    }
                }
                if (p('channel')) {
                    if ($ischannelpay == 1 && empty($ischannelpick)) {
                        $isvirtual = true;
                    }
                }
            }
            //仅判断核销了，还需要判断支持配送
            //如果开启核销并且不支持配送，则没有运费
            $isDispath = true;
            if ($isverify && !$isverifysend) {
                $isDispath = false;
            }

            if ($isverify && $isDispath) {
                show_json(1, array(
                    "price" => 0,
                    "hascoupon" => $hascoupon,
                    "couponcount" => $couponcount
                ));
            }
            if (!empty($allgoods)) {
                foreach ($allgoods as $g) {
                    $sendfree = false;
                    if (!empty($g["issendfree"])) {
                        $sendfree = true;
                    }
                    if ($g["type"] == 2 || $g["type"] == 3) {
                        $sendfree = true;
                    } else {
                        if ($g["total"] >= $g["ednum"] && $g["ednum"] > 0) {
                            $gareas = explode(";", $g["edareas"]);
                            if (empty($gareas)) {
                                $sendfree = true;
                            } else {
                                if (!empty($address)) {
                                    if (!in_array($address["city"], $gareas)) {
                                        $sendfree = true;
                                    }
                                } else if (!empty($member["city"])) {
                                    if (!in_array($member["city"], $gareas)) {
                                        $sendfree = true;
                                    }
                                } else {
                                    $sendfree = true;
                                }
                            }
                        }
                        if ($g["ggprice"] >= floatval($g["edmoney"]) && floatval($g["edmoney"]) > 0) {
                            $gareas = unserialize($g["edareas"]);
                            if (empty($gareas)) {
                                $sendfree = true;
                            } else {
                                if (!empty($address)) {
                                    if (!in_array($address["city"], $gareas)) {
                                        $sendfree = true;
                                    }
                                } else if (!empty($member["city"])) {
                                    if (!in_array($member["city"], $gareas)) {
                                        $sendfree = true;
                                    }
                                } else {
                                    $sendfree = true;
                                }
                            }
                        }
                    }
                    if (!$sendfree) {
                        if ($g["dispatchtype"] == 1) {
                            if ($g["dispatchprice"] > 0) {
                                $dispatch_price += $g["dispatchprice"] * $g["total"];
                            }
                        } else if ($g["dispatchtype"] == 0) {
                            if (empty($g["dispatchid"])) {
                                $dispatch_data = m("order")->getDefaultDispatch($supplier_uid);
                            } else {
                                $dispatch_data = m("order")->getOneDispatch($g["dispatchid"], $supplier_uid);
                            }
                            if (empty($dispatch_data)) {
                                $dispatch_data = m("order")->getNewDispatch($supplier_uid);
                            }
                            if (!empty($dispatch_data)) {
                                $areas = unserialize($dispatch_data["areas"]);
                                if ($dispatch_data["calculatetype"] == 1) {
                                    $param = $g["total"];
                                } else {
                                    $param = $g["weight"] * $g["total"];
                                }
                                $dkey = $dispatch_data["id"];
                                if (array_key_exists($dkey, $dispatch_array)) {
                                    $dispatch_array[$dkey]["param"] += $param;
                                } else {
                                    $dispatch_array[$dkey]["data"]  = $dispatch_data;
                                    $dispatch_array[$dkey]["param"] = $param;
                                }
                            }
                        }
                    }
                }
                if (!empty($dispatch_array)) {
                    foreach ($dispatch_array as $k => $v) {
                        $dispatch_data = $dispatch_array[$k]["data"];
                        $param         = $dispatch_array[$k]["param"];
                        $areas         = unserialize($dispatch_data["areas"]);
                        if (!empty($address)) {
                            $dispatch_price += m("order")->getCityDispatchPrice($areas, $address["city"], $param, $dispatch_data, $supplier_uid);
                        } else if (!empty($member["city"])) {
                            $dispatch_price += m("order")->getCityDispatchPrice($areas, $member["city"], $param, $dispatch_data, $supplier_uid);
                        } else {
                            $dispatch_price += m("order")->getDispatchPrice($param, $dispatch_data, -1, $supplier_uid);
                        }
                    }
                }
            }
            if ($dflag != "true") {
                if (empty($saleset["dispatchnodeduct"])) {
                    $deductprice2 += $dispatch_price;
                }
            }
            $deductcredit = 0;
            $deductmoney  = 0;
            if ($sale_plugin) {
                $credit = m("member")->getCredit($openid, "credit1");
                if (!empty($saleset["creditdeduct"])) {
                    $pcredit = intval($saleset["credit"]);
                    $pmoney  = round(floatval($saleset["money"]), 2);
                    if ($pcredit > 0 && $pmoney > 0) {
                        if ($credit % $pcredit == 0) {
                            $deductmoney = round(intval($credit / $pcredit) * $pmoney, 2);
                        } else {
                            $deductmoney = round((intval($credit / $pcredit) + 1) * $pmoney, 2);
                        }
                    }
                    if ($deductmoney > $deductprice) {
                        $deductmoney = $deductprice;
                    }
                    if ($deductmoney > $totalprice) {
                        $deductmoney = $totalprice;
                    }
                    $deductcredit = $deductmoney / $pmoney * $pcredit;
                }
                if (!empty($saleset["moneydeduct"])) {
                    $deductcredit2 = m("member")->getCredit($openid, "credit2");
                    if ($deductcredit2 > $totalprice) {
                        $deductcredit2 = $totalprice;
                    }
                    if ($deductcredit2 > $deductprice2) {
                        $deductcredit2 = $deductprice2;
                    }
                }
            }
        }
        show_json(1, array(
            "price" => $dispatch_price,
            "hascoupon" => $hascoupon,
            "couponcount" => $couponcount,
            "deductenough_money" => $deductenough_money,
            "deductenough_enough" => $deductenough_enough,
            "deductcredit2" => $deductcredit2,
            "deductcredit" => $deductcredit,
            "deductmoney" => $deductmoney
        ));

    } elseif ($operation == 'create' && $_W['ispost']) {
        $ischannelpay = intval($_GPC['ischannelpay']);
        $ischannelpick = intval($_GPC['ischannelpick']);
        $order_data = $_GPC['order'];
        if(p('hotel')){ 
            if($_GPC['type']=='99'){
                $order_data[] = $_GPC; 

            }
        }  

        //通用订单号，支付用
        $ordersn_general    = m('common')->createNO('order', 'ordersn', 'SH');
        $member       = m('member')->getMember($openid);
        $level         = m('member')->getLevel($openid);
        foreach ($order_data as $key => $order_row) {
            $dispatchtype = intval($order_row['dispatchtype']);
            $addressid    = intval($order_row['addressid']);
            $address      = false;
            if (!empty($addressid) && $dispatchtype == 0) {
                $address = pdo_fetch('select id,realname,mobile,address,province,city,area from ' . tablename('sz_yi_member_address') . ' where id=:id and openid=:openid and uniacid=:uniacid   limit 1', array(

                    ':uniacid' => $uniacid,
                    ':openid' => $openid,
                    ':id' => $addressid
                ));
                if (empty($address)) {
                    show_json(0, '未找到地址');
                }
            }
            $carrierid = intval($order_row["carrierid"]);
            $goods = $order_row['goods'];
            if (empty($goods)) {
                show_json(0, '未找到任何商品');
            }
            $allgoods      = array();
            $totalprice    = 0;
            $goodsprice    = 0;
            $redpriceall   = 0;
            $weight        = 0;
            $discountprice = 0;
            $goodsarr      = explode('|', $goods);
            $cash          = 1;
            
            $deductprice   = 0;
            $deductprice2   = 0;
            $virtualsales  = 0;
            $dispatch_price = 0;
            $dispatch_array = array();
            $sale_plugin   = p('sale');
            $saleset       = false;
            if ($sale_plugin) {
                $saleset = $sale_plugin->getSet();
                $saleset["enoughs"] = $sale_plugin->getEnoughs();
            }
            $isvirtual = false;
            $isverify  = false;
            $isverifysend  = false;
            foreach ($goodsarr as $g) {
                if (empty($g)) {
                    continue;
                }
                $goodsinfo  = explode(',', $g);
                $goodsid    = !empty($goodsinfo[0]) ? intval($goodsinfo[0]) : '';
                $optionid   = !empty($goodsinfo[1]) ? intval($goodsinfo[1]) : 0;
                $goodstotal = !empty($goodsinfo[2]) ? intval($goodsinfo[2]) : '1';
                if ($goodstotal < 1) {
                    $goodstotal = 1;
                }

                if (empty($goodsid)) {
                    show_json(0, '参数错误，请刷新重试');
                }
                $sql  = 'SELECT id as goodsid,costprice,supplier_uid,title,type, weight,total,issendfree,isnodiscount, thumb,marketprice,cash,isverify,goodssn,productsn,sales,istime,timestart,timeend,usermaxbuy,maxbuy,unit,buylevels,buygroups,deleted,status,deduct,manydeduct,virtual,discounts,deduct2,ednum,edmoney,edareas,diyformtype,diyformid,diymode,dispatchtype,dispatchid,dispatchprice,redprice FROM ' . tablename('sz_yi_goods') . ' where id=:id and uniacid=:uniacid  limit 1';
                $data = pdo_fetch($sql, array(

                    ':uniacid' => $uniacid,
                    ':id' => $goodsid
                ));

                if (empty($data['status']) || !empty($data['deleted'])) {
                    show_json(-1, $data['title'] . '<br/> 已下架!');
                }
                $virtualid     = $data['virtual'];
                $data['stock'] = $data['total'];
                $data['total'] = $goodstotal;
                if ($data['cash'] != 2) {
                    $cash = 0;
                }
                $unit = empty($data['unit']) ? '件' : $data['unit'];
                if ($data['maxbuy'] > 0) {
                    if ($goodstotal > $data['maxbuy']) {
                        show_json(-1, $data['title'] . '<br/> 一次限购 ' . $data['maxbuy'] . $unit . "!");

                    }
                }
                if ($data['usermaxbuy'] > 0) {
                    $order_goodscount = pdo_fetchcolumn('select ifnull(sum(og.total),0)  from ' . tablename('sz_yi_order_goods') . ' og ' . ' left join ' . tablename('sz_yi_order') . ' o on og.orderid=o.id ' . ' where og.goodsid=:goodsid and  o.status>=1 and o.openid=:openid  and og.uniacid=:uniacid ', array(
                        ':goodsid' => $data['goodsid'],
                        ':uniacid' => $uniacid,
                        ':openid' => $openid
                    ));
                    if ($order_goodscount >= $data['usermaxbuy']) {
                        show_json(-1, $data['title'] . '<br/> 最多限购 ' . $data['usermaxbuy'] . $unit . "!");
                    }
                }
                if ($data['istime'] == 1) {
                    if (time() < $data['timestart']) {
                        show_json(-1, $data['title'] . '<br/> 限购时间未到!');
                    }
                    if (time() > $data['timeend']) {
                        show_json(-1, $data['title'] . '<br/> 限购时间已过!');
                    }
                }
                $levelid = intval($member['level']);
                $groupid = intval($member['groupid']);
                if ($data['buylevels'] != '') {
                    $buylevels = explode(',', $data['buylevels']);
                    if (!in_array($levelid, $buylevels)) {
                        show_json(-1, '您的会员等级无法购买<br/>' . $data['title'] . '!');
                    }
                }
                if ($data['buygroups'] != '') {
                    $buygroups = explode(',', $data['buygroups']);
                    if (!in_array($groupid, $buygroups)) {
                        show_json(-1, '您所在会员组无法购买<br/>' . $data['title'] . '!');
                    }
                }

                if (!empty($optionid)) {
                    $option = pdo_fetch('select * from ' . tablename('sz_yi_goods_option') . ' where id=:id and goodsid=:goodsid and uniacid=:uniacid  limit 1', array(
                        ':uniacid' => $uniacid,
                        ':goodsid' => $goodsid,
                        ':id' => $optionid

                    ));
                    if (p('channel') && !empty($ischannelpick)) {
                        $my_option_stock = p('channel')->getMyOptionStock($openid,$goodsid,$optionid);
                        $option['stock'] = $my_option_stock;
                    }
                    if (!empty($option)) {                                             
                        if ($option['stock'] != -1) {
                            if (empty($option['stock'])) {
                                show_json(-1, $data['title'] . "<br/>" . $option['title'] . " 库存不足!");
                            }
                        }
                        $data['optionid']    = $optionid;
                        $data['optiontitle'] = $option['title'];
                        $data['marketprice'] = $option['marketprice'];
                        if (!empty($option['costprice'])) {
                            $data['costprice']   = $option['costprice'];
                        }
                        $virtualid           = $option['virtual'];
                        if (!empty($option['goodssn'])) {
                            $data['goodssn'] = $option['goodssn'];
                        }
                        if (!empty($option['productsn'])) {
                            $data['productsn'] = $option['productsn'];
                        }
                        if (!empty($option['weight'])) {
                            $data['weight'] = $option['weight'];
                        }
                        if (!empty($option['redprice'])) {
                            $data['redprice'] = $option['redprice'];
                        }
                    }
                } else {
                    if ($data['stock'] != -1) {
                        if (empty($data['stock'])) {
                            show_json(-1, $data['title'] . "<br/>库存不足!");
                        }
                    }
                }
                $data["diyformdataid"] = 0;
                $data["diyformdata"]   = iserializer(array());
                $data["diyformfields"] = iserializer(array());
                if ($_GPC["fromcart"] == 1) {
                    if ($diyform_plugin) {
                        $cartdata = pdo_fetch("select id,diyformdataid,diyformfields,diyformdata from " . tablename("sz_yi_member_cart") . " " . " where goodsid=:goodsid and optionid=:optionid and openid=:openid and deleted=0 order by id desc limit 1", array(
                            ":goodsid" => $data["goodsid"],
                            ":optionid" => $data["optionid"],
                            ":openid" => $openid
                        ));
                        if (!empty($cartdata)) {
                            $data["diyformdataid"] = $cartdata["diyformdataid"];
                            $data["diyformdata"]   = $cartdata["diyformdata"];
                            $data["diyformfields"] = $cartdata["diyformfields"];
                        }
                    }
                } else {
                    if (!empty($diyformtype) && !empty($data["diyformid"])) {
                        $temp_data             = $diyform_plugin->getOneDiyformTemp($goods_data_id, 0);
                        $data["diyformfields"] = $temp_data["diyformfields"];
                        $data["diyformdata"]   = $temp_data["diyformdata"];
                        $data["diyformid"]     = $formInfo["id"];
                    }
                }

                /**
                 *  红包价格计算
                 */
                if (strpos($data['redprice'], "%") === false) {
                    if (strpos($data['redprice'], "-") === false) {
                        $redprice = $data['redprice'];

                    } else {
                        $rprice = explode("-", $data['redprice']);
                        if ($rprice[1]>200) {
                            $redprice = rand($rprice[0]*100, 200*100)/100;
                        } else if ($rprice[0]<0) {
                            $redprice = rand(0, $rprice[1]*100)/100;
                        } else {
                            $redprice = rand($rprice[0]*100, $rprice[1]*100)/100;
                        }
                    }
                } else {
                    $rprice = explode("%", $data['redprice']);
                    $redprice = ($rprice[0] * $data['marketprice']) / 100;
                }
                $redprice = $redprice * $goodstotal;
                $redpriceall += $redprice;
                if (p('channel')) {
                    $my_info = p('channel')->getInfo($openid);
                    if ($ischannelpay == 1) {
                        $data['marketprice'] = $data['marketprice'] * $my_info['my_level']['purchase_discount']/100;
                    }
                }
                $gprice = $data['marketprice'] * $goodstotal;
                $goodsprice += $gprice;
                $discounts = json_decode($data['discounts'], true);
                if (is_array($discounts)) {
                    if (!empty($level["id"])) {
                        if (floatval($discounts["level" . $level["id"]]) > 0 && floatval($discounts["level" . $level["id"]]) < 10) {
                            $level["discount"] = floatval($discounts["level" . $level["id"]]);
                        } else if (floatval($level["discount"]) > 0 && floatval($level["discount"]) < 10) {
                            $level["discount"] = floatval($level["discount"]);
                        } else {
                            $level["discount"] = 0;
                        }
                    } else {
                        if (floatval($discounts["default"]) > 0 && floatval($discounts["default"]) < 10) {
                            $level["discount"] = floatval($discounts["default"]);
                        } else if (floatval($level["discount"]) > 0 && floatval($level["discount"]) < 10) {
                            $level["discount"] = floatval($level["discount"]);
                        } else {
                            $level["discount"] = 0;
                        }
                    }
                }

                $ggprice = 0;
                if(p('hotel') && $_GPC['type']=='99'){
                     $gprice =$_GPC['goodsprice'];
                     $ggpric = $_GPC['goodsprice'];
                 }
                if (empty($data['isnodiscount']) && $level['discount'] > 0 && $level['discount'] < 10) {
                    $dprice = round($gprice * $level['discount'] / 10, 2);
                    $discountprice += $gprice - $dprice;
                    $ggprice = $dprice;

                } else {
                    $ggprice = $gprice;
                }
                $data["realprice"] = $ggprice;
                $totalprice += $ggprice;
                if ($data['isverify'] == 2) {
                    $isverify = true;
                }
                if ($data['isverifysend'] == 1) {
                    $isverifysend = true;
                }
                if (!empty($data["virtual"]) || $data["type"] == 2) {
                    $isvirtual = true;
                }
                if (p('channel')) {
                    if ($ischannelpay == 1 && empty($ischannelpick)) {
                        $isvirtual = true;
                    }
                }
                if ($data["manydeduct"]) {
                    $deductprice += $data["deduct"] * $data["total"];
                } else {
                    $deductprice += $data["deduct"];
                }
                $virtualsales += $data["sales"];
                if ($data["deduct2"] == 0.00) {
                    $deductprice2 += $ggprice;
                } else if ($data["deduct2"] > 0) {
                    if ($data["deduct2"] > $ggprice) {
                        $deductprice2 += $ggprice;
                    } else {
                        $deductprice2 += $data["deduct2"];
                    }
                }
                $allgoods[] = $data;
            }
            if (empty($allgoods)) {
                show_json(0, '未找到任何商品');
            }
            $deductenough = 0;
            if ($saleset) {
                foreach ($saleset["enoughs"] as $e) {
                    if ($totalprice >= floatval($e["enough"]) && floatval($e["money"]) > 0) {
                        $deductenough = floatval($e["money"]);
                        if ($deductenough > $totalprice) {
                            $deductenough = $totalprice;
                        }
                        break;
                    }
                }
            }

            //如果开启核销并且不支持配送，则没有运费
            $isDispath = true;
            if ($isverify && !$isverifysend) {
                $isDispath = false;
            }

            if (!$isvirtual && $isDispath && $dispatchtype == 0) {
                foreach ($allgoods as $g) {
                    $g["ggprice"] = $ggprice;
                    $sendfree = false;
                    if (!empty($g["issendfree"])) {
                        $sendfree = true;
                    } else {
                        if ($g["total"] >= $g["ednum"] && $g["ednum"] > 0) {
                            $gareas = explode(";", $g["edareas"]);
                            if (empty($gareas)) {
                                $sendfree = true;
                            } else {
                                if (!empty($address)) {
                                    if (!in_array($address["city"], $gareas)) {
                                        $sendfree = true;
                                    }
                                } else if (!empty($member["city"])) {
                                    if (!in_array($member["city"], $gareas)) {
                                        $sendfree = true;
                                    }
                                } else {
                                    $sendfree = true;
                                }
                            }
                        }
                        if ($g["ggprice"] >= floatval($g["edmoney"]) && floatval($g["edmoney"]) > 0) {
                            $gareas = unserialize($g["edareas"]);
                            if (empty($gareas)) {
                                $sendfree = true;
                            } else {
                                if (!empty($address)) {
                                    if (!in_array($address["city"], $gareas)) {
                                        $sendfree = true;
                                    }
                                } else if (!empty($member["city"])) {
                                    if (!in_array($member["city"], $gareas)) {
                                        $sendfree = true;
                                    }
                                } else {
                                    $sendfree = true;
                                }
                            }
                        }
                    }
                    if (!$sendfree) {
                        if ($g["dispatchtype"] == 1) {
                            if ($g["dispatchprice"] > 0) {
                                $dispatch_price += $g["dispatchprice"] * $g["total"];
                            }
                        } else if ($g["dispatchtype"] == 0) {
                            if (empty($g["dispatchid"])) {
                                $dispatch_data = m("order")->getDefaultDispatch($g['supplier_uid']);
                            } else {
                                $dispatch_data = m("order")->getOneDispatch($g["dispatchid"], $g['supplier_uid']);
                            }
                            if (empty($dispatch_data)) {
                                $dispatch_data = m("order")->getNewDispatch($g['supplier_uid']);
                            }
                            if (!empty($dispatch_data)) {
                                $areas = unserialize($dispatch_data["areas"]);
                                if ($dispatch_data["calculatetype"] == 1) {
                                    $param = $g["total"];
                                } else {
                                    $param = $g["weight"] * $g["total"];
                                }
                                $dkey = $dispatch_data["id"];
                                if (array_key_exists($dkey, $dispatch_array)) {
                                    $dispatch_array[$dkey]["param"] += $param;
                                } else {
                                    $dispatch_array[$dkey]["data"]  = $dispatch_data;
                                    $dispatch_array[$dkey]["param"] = $param;
                                }
                            }
                        }
                    }
                }
                if (!empty($dispatch_array)) {
                    foreach ($dispatch_array as $k => $v) {
                        $dispatch_data = $dispatch_array[$k]["data"];
                        $param         = $dispatch_array[$k]["param"];
                        $areas         = unserialize($dispatch_data["areas"]);
                        if (!empty($address)) {
                            $dispatch_price += m("order")->getCityDispatchPrice($areas, $address["city"], $param, $dispatch_data, $order_row['supplier_uid']);
                        } else if (!empty($member["city"])) {
                            $dispatch_price += m("order")->getCityDispatchPrice($areas, $member["city"], $param, $dispatch_data, $order_row['supplier_uid']);
                        } else {
                            $dispatch_price += m("order")->getDispatchPrice($param, $dispatch_data, -1, $order_row['supplier_uid']);
                        }
                    }
                }
            }
            if ($saleset) {
                if (!empty($saleset["enoughfree"])) {
                    if (floatval($saleset["enoughorder"]) <= 0) {
                        $dispatch_price = 0;
                    } else {
                        if ($totalprice >= floatval($saleset["enoughorder"])) {
                            if (empty($saleset["enoughareas"])) {
                                $dispatch_price = 0;
                            } else {
                                $areas = explode(",", $saleset["enoughareas"]);
                                if (!empty($address)) {
                                    if (!in_array($address["city"], $areas)) {
                                        $dispatch_price = 0;
                                    }
                                } else if (!empty($member["city"])) {
                                    if (!in_array($member["city"], $areas)) {
                                        $dispatch_price = 0;
                                    }
                                } else if (empty($member["city"])) {
                                    $dispatch_price = 0;
                                }
                            }
                        }
                    }
                }
            }
            $couponprice = 0;
            $couponid    = intval($order_row["couponid"]);
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
            $totalprice -= $deductenough;
            $totalprice += $dispatch_price;
            if ($saleset && empty($saleset["dispatchnodeduct"])) {
                $deductprice2 += $dispatch_price;
            }
            $deductcredit  = 0;
            $deductmoney   = 0;
            $deductcredit2 = 0;
            if ($sale_plugin) {
                if (isset($_GPC['order']) && !empty($_GPC['order'][0]['deduct'])) {
                    $credit  = m('member')->getCredit($openid, 'credit1');
                    $saleset = $sale_plugin->getSet();
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
                        if ($deductmoney > $deductprice) {
                            $deductmoney = $deductprice;
                        }
                        if ($deductmoney > $totalprice) {
                            $deductmoney = $totalprice;
                        }
                        $deductcredit = round($deductmoney / $pmoney * $pcredit, 2);
                    }
                }
                $totalprice -= $deductmoney;
                if (!empty($order_row['deduct2'])) {
                    $deductcredit2 = m('member')->getCredit($openid, 'credit2');
                    if ($deductcredit2 > $totalprice) {
                        $deductcredit2 = $totalprice;
                    }
                    if ($deductcredit2 > $deductprice2) {
                        $deductcredit2 = $deductprice2;
                    }
                }
                $totalprice -= $deductcredit2;
            }
            $ordersn    = m('common')->createNO('order', 'ordersn', 'SH');
            $verifycode = "";
            if ($isverify) {
                $verifycode = random(8, true);
                while (1) {
                    $count = pdo_fetchcolumn('select count(*) from ' . tablename('sz_yi_order') . ' where verifycode=:verifycode and uniacid=:uniacid limit 1', array(
                        ':verifycode' => $verifycode,
                        ':uniacid' => $_W['uniacid']
                    ));
                    if ($count <= 0) {
                        break;
                    }
                    $verifycode = random(8, true);
                }
            }
            
            $carrier  = $_GPC['order'][0]['carrier'];
            $carriers = is_array($carrier) ? iserializer($carrier) : iserializer(array());
            if ($totalprice <= 0) {
                $totalprice = 0;
            }
            if ($redpriceall > 200) {
                $redpriceall = 200;
            }
            if(p('hotel')){//判断如果安装酒店插件订单金额计算
               if($_GPC['type']=='99'){   
                    $btime =  $_SESSION['data']['btime'];
                    // 住几天
                    $days =intval( $_SESSION['data']['day']);
                    // 离店
                    $etime =  $_SESSION['data']['etime'];          
                    $sql2 = 'SELECT * FROM ' . tablename('sz_yi_hotel_room') . ' WHERE `goodsid` = :goodsid';
                    $params2 = array(':goodsid' =>$_GPC['id']);
                    $room = pdo_fetch($sql2, $params2);
                    if( $discountprice!='0'){
                        $totalprice =$_GPC['totalprice'] -$discountprice;
                    }else{
                        $totalprice =$_GPC['totalprice'];
                    }
                    $goodsprice =$_GPC['goodsprice'];
                }
            }

            $order   = array(
                'supplier_uid' => $order_row['supplier_uid'],
                'uniacid' => $uniacid,
                'openid' => $openid,
                'ordersn' => $ordersn,
                'ordersn_general' => $ordersn_general,
                'price' => $totalprice,
                'cash' => $cash,
                'discountprice' => $discountprice,
                'deductprice' => $deductmoney,
                'deductcredit' => $deductcredit,
                'deductcredit2' => $deductcredit2,
                'deductenough' => $deductenough,
                'status' => 0,
                'paytype' => 0,
                'transid' => '',
                'remark' => $order_row['remark'],
                'addressid' => empty($dispatchtype) ? $addressid : 0,
                'goodsprice' => $goodsprice,
                'dispatchprice' => $dispatch_price,
                'dispatchtype' => $dispatchtype,
                'dispatchid' => $dispatchid,
                "storeid" => $carrierid,
                'carrier' => $carriers,
                'createtime' => time(),
                'isverify' => $isverify ? 1 : 0,
                'verifycode' => $verifycode,
                'virtual' => $virtualid,
                'isvirtual' => $isvirtual ? 1 : 0,
                'oldprice' => $totalprice,
                'olddispatchprice' => $dispatch_price,
                "couponid" => $couponid,
                "couponprice" => $couponprice,
                'redprice' => $redpriceall,
     
            );
            if (p('channel')) {
                if (!empty($ischannelpick)) {
                    $order['ischannelself'] = 1;
                    $order['status']        = 1;
                }
            }
            if(p('hotel')){
                if($_GPC['type']=='99'){ 
                     $order['order_type']='3';
                     $order['addressid']='9999999';
                     $order['checkname']=$_GPC['realname'];//以下为酒店订单
                     $order['realmobile']=$_GPC['realmobile'];
                     $order['realsex']=$_GPC['realsex'];
                     $order['invoice']=$_GPC['invoice'];
                     $order['invoiceval']=$_GPC['invoiceval'];
                     $order['invoicetext']=$_GPC['invoicetext'];
                     $order['num']=$_GPC['goodscount'];
                     $order['btime']=$btime;
                     $order['etime']=$etime;
                     $order['depositprice']=$_GPC['depositprice'];
                     $order['depositpricetype']=$_GPC['depositpricetype'];
                     $order['roomid']=$room['id'];
                     $order['days']=$days;        
                     $order['dispatchprice']=0;              
                     $order['olddispatchprice']=0;    
                     $order['deductcredit2']=$_GPC['deductcredit2']; 
                     $order['deductcredit']=$_GPC['deductcredit'];
                     $order['deductprice']=$_GPC['deductcredit'];  
                     
                }
            }
            if ($diyform_plugin) {
                if (is_array($order_row["diydata"]) && !empty($order_formInfo)) {
                    $diyform_data           = $diyform_plugin->getInsertData($fields, $order_row["diydata"]);
                    $idata                  = $diyform_data["data"];
                    $order["diyformfields"] = iserializer($fields);
                    $order["diyformdata"]   = $idata;
                    $order["diyformid"]     = $order_formInfo["id"];
                }

            }
            if (!empty($address)) {
                $order['address'] = iserializer($address);
            }
            pdo_insert('sz_yi_order',$order);
            $orderid = pdo_insertid();
            if(p('hotel')){
                 if($_GPC['type']=='99'){  
                //像订单管理房间信息表插入数据
                $r_sql = 'SELECT * FROM ' . tablename('sz_yi_hotel_room_price') .
                ' WHERE `roomid` = :roomid AND `roomdate` >= :btime AND ' .
                ' `roomdate` < :etime';
                $params = array(':roomid' => $room['id'],':btime' => $btime, ':etime' => $etime);
                $price_list = pdo_fetchall($r_sql, $params);  
                if($price_list!=''){
                    foreach ($price_list as $key => $value) {
                        $order_room = array(
                            'orderid'=>$orderid ,
                            'roomid'=>$room['id'],
                            'roomdate'=>$value['roomdate'],
                            'thisdate'=>$value['thisdate'],
                            'oprice'=>$value['oprice'],
                            'cprice'=>$value['cprice'],
                            'mprice'=>$value['mprice'],
                        );
                      pdo_insert('sz_yi_order_room', $order_room);
                    }
                }
                //减去房量
                $sql2 = 'SELECT * FROM ' . tablename('sz_yi_hotel_room') . ' WHERE `goodsid` = :goodsid';
                $params2 = array(':goodsid' =>  $allgoods[0]['goodsid']);
                $room = pdo_fetch($sql2, $params2);         
                $starttime = $btime;
                for ($i = 0; $i <  $days; $i++) {
                    $sql = 'SELECT * FROM '. tablename('sz_yi_hotel_room_price'). ' WHERE  roomid = :roomid AND roomdate = :roomdate';
                    $day = pdo_fetch($sql, array(':roomid' => $room['id'], ':roomdate' => $btime));
                    pdo_update('sz_yi_hotel_room_price', array('num' => $day['num'] - $_GPC['goodscount']), array('id' => $day['id']));
                    $btime += 86400;
                } 
   
            }
        }

       
            if (is_array($carrier)) {
                //todo, carrier_realname和carrier_mobile字段表里有么?
                $up = array(
                    'realname' => $carrier['carrier_realname'],
                    'mobile' => $carrier['carrier_mobile']
                );
                pdo_update('sz_yi_member', $up, array(
                    'id' => $member['id'],
                    'uniacid' => $_W['uniacid']
                ));
                if (!empty($member['uid'])) {
                    pdo_update('mc_members', $up, array(
                        'uid' => $member['uid'],
                        'uniacid' => $_W['uniacid']
                    ));
                }
            }
            if ($order_row['fromcart'] == 1) {
                $cartids = $order_row['cartids'];
                if (!empty($cartids)) {
                    pdo_query('update ' . tablename('sz_yi_member_cart') . ' set deleted=1 where id in (' . $cartids . ') and openid=:openid and uniacid=:uniacid ', array(
                        ':uniacid' => $uniacid,
                        ':openid' => $openid
                    ));
                } else {
                    pdo_query('update ' . tablename('sz_yi_member_cart') . ' set deleted=1 where openid=:openid and uniacid=:uniacid ', array(
                        ':uniacid' => $uniacid,
                        ':openid' => $openid
                    ));
                }
            }
            foreach ($allgoods as $goods) {
                $order_goods = array(
                    'uniacid' => $uniacid,
                    'orderid' => $orderid,
                    'goodsid' => $goods['goodsid'],
                    'price' => $goods['marketprice'] * $goods['total'],
                    'total' => $goods['total'],
                    'optionid' => $goods['optionid'],
                    'createtime' => time(),
                    'optionname' => $goods['optiontitle'],
                    'goodssn' => $goods['goodssn'],
                    'productsn' => $goods['productsn'],
                    "realprice" => $goods["realprice"],
                    "oldprice" => $goods["realprice"],
                    "openid" => $openid,
                    'goods_op_cost_price' => $goods['costprice']
                );
                //修改全返插件中房价
                if(p('hotel') && $_GPC['type']=='99'){
                     $order_goods['price'] = $goodsprice ;
                     $order_goods['realprice'] = $goodsprice-$discountprice;
                     $order_goods['oldprice'] = $goodsprice-$discountprice;
                }
                if ($diyform_plugin) {
                    $order_goods["diyformid"]     = $goods["diyformid"];
                    $order_goods["diyformdata"]   = $goods["diyformdata"];
                    $order_goods["diyformfields"] = $goods["diyformfields"];
                }
                if (p('supplier')) {
                    $order_goods['supplier_uid'] = $goods['supplier_uid'];
                }
                if (p('channel')) {
                    $my_info = p('channel')->getInfo($openid,$goods['goodsid'],$goods['optionid'],$goods['total']);
                    /*if (!empty($ischannelpick)) {
                        $my_option_stock = p('channel')->getMyOptionStock($openid, $goods['goodsid'], $goods['optionid']);
                        $stock = $my_option_stock - $goods['total'];
                        pdo_update('sz_yi_channel_stock', 
                            array(
                                'stock_total' => $stock
                            ), 
                            array(
                                'uniacid'   => $_W['uniacid'],
                                'openid'    => $openid,
                                'goodsid'   => $goods['goodsid'],
                                'optionid'  => $goods['optionid']
                            ));
                    }*/
                    if ($ischannelpay == 1 && empty($ischannelpick)) {
                        $every_turn_price           = $goods['marketprice']/($my_info['my_level']['purchase_discount']/100);
                        $channel_cond = '';
                        if (!empty($goods['optionid'])) {
                            $channel_cond = " AND optionid={$goods['optionid']}";
                        }
                        $ischannelstock             = pdo_fetch("SELECT * FROM " . tablename('sz_yi_channel_stock') . " WHERE uniacid={$_W['uniacid']} AND openid='{$openid}' AND goodsid={$goods['goodsid']} {$channel_cond}");
                        if (empty($ischannelstock)) {
                            pdo_insert('sz_yi_channel_stock', array(
                                'uniacid'       => $_W['uniacid'],
                                'openid'        => $openid,
                                'goodsid'       => $goods['goodsid'],
                                'optionid'      => $goods['optionid'],
                                'stock_total'   => $goods['total']
                                ));
                        } else {
                            $stock_total = $ischannelstock['stock_total'] + $goods['total'];
                            pdo_update('sz_yi_channel_stock', array(
                                'stock_total'   => $stock_total
                                ), array(
                                'uniacid'       => $_W['uniacid'],
                                'openid'        => $openid,
                                'optionid'      => $goods['optionid'],
                                'goodsid'       => $goods['goodsid']
                                ));
                        }
                        $op_where = '';
                        if (!empty($goods['optionid'])) {
                            $op_where = " AND optionid={$goods['optionid']}";
                        }
                        $surplus_stock = pdo_fetchcolumn("SELECT stock_total FROM " . tablename('sz_yi_channel_stock') . " WHERE uniacid={$_W['uniacid']} AND openid='{$openid}' AND goodsid={$goods['goodsid']} {$op_where}");
                        $up_mem = m('member')->getInfo($my_info['up_channel']['openid']);
                        $stock_log = array(
                              'uniacid'             => $_W['uniacid'],
                              'openid'              => $openid,
                              'goodsid'             => $goods['goodsid'],
                              'optionid'            => $goods['optionid'],
                              'every_turn'          => $goods['total'],
                              'every_turn_price'    => $goods['marketprice'],
                              'every_turn_discount' => $my_info['my_level']['purchase_discount'],
                              'goods_price'         => $every_turn_price,
                              'paytime'             => time(),
                              'type'                => 1,
                              'surplus_stock'       => $surplus_stock,
                              'mid'                 => $up_mem['id']
                            );
                        // type==1  进货
                        pdo_insert('sz_yi_channel_stock_log', $stock_log);
                        $order_goods['ischannelpay']  = $ischannelpay;
                    }
                    $order_goods['channel_id'] = 0;
                    //if (!empty($ischannelpay)) {
                        if (!empty($my_info['up_level'])) {
                            $up_member = m('member')->getInfo($my_info['up_level']['openid']);
                            $order_goods['channel_id'] = $up_member['id'];
                        }
                    //}
                }
                pdo_insert('sz_yi_order_goods', $order_goods);
            }
            if(p('hotel')){
                //打印订单      
                $set = set_medias(m('common')->getSysset('shop'), array('logo', 'img'));
                //订单信息
                $print_order = $order;
                //商品信息
                $ordergoods = pdo_fetchall("select * from " . tablename('sz_yi_order_goods') . " where uniacid=".$_W['uniacid']." and orderid=".$orderid);
                    foreach ($ordergoods as $key =>$value) {
                        $ordergoods[$key]['price'] = pdo_fetchcolumn("select marketprice from " . tablename('sz_yi_goods') . " where uniacid={$_W['uniacid']} and id={$value['goodsid']}");
                        $ordergoods[$key]['goodstitle'] = pdo_fetchcolumn("select title from " . tablename('sz_yi_goods') . " where uniacid={$_W['uniacid']} and id={$value['goodsid']}");
                        $ordergoods[$key]['totalmoney'] = number_format($ordergoods[$key]['price']*$value['total'],2);
                        $ordergoods[$key]['print_id'] = pdo_fetchcolumn("select print_id from " . tablename('sz_yi_goods') . " where uniacid={$_W['uniacid']} and id={$value['goodsid']}");
                        $ordergoods[$key]['type'] = pdo_fetchcolumn("select type from " . tablename('sz_yi_goods') . " where uniacid={$_W['uniacid']} and id={$value['goodsid']}");

                    }
                    $print_order['goods']=$ordergoods;
                    $print_id = $print_order['goods'][0]['print_id'];
                    $goodtype = $print_order['goods'][0]['type'];
                    if($print_id!=''){
                        $print_detail = pdo_fetch("select * from " . tablename('sz_yi_print_list') . " where uniacid={$_W['uniacid']} and id={$print_id}");
                        if(!empty($print_detail)){
                                $member_code = $print_detail['member_code'];
                                $device_no = $print_detail['print_no'];
                                $key = $print_detail['key'];
                                include IA_ROOT.'/addons/sz_yi/core/model/print.php';
                                if($goodtype=='99'){//类型为房间
                                    //房间金额信息
                                    $sql2 = 'SELECT * FROM ' . tablename('sz_yi_order_room') . ' WHERE `orderid` = :orderid';
                                    $params2 = array(':orderid' => $orderid);
                                    $price_list = pdo_fetchall($sql2, $params2);
                                    $msgNo = testSendFreeMessage($print_order, $member_code, $device_no, $key,$set,$price_list);
                                }else if($goodtype=='1'){
                                     $msgNo = testSendFreeMessageshop($print_order, $member_code, $device_no, $key,$set);
                                }
                        }
                    }
            }
            if ($deductcredit > 0) {
                $shop = m('common')->getSysset('shop');
                m('member')->setCredit($openid, 'credit1', -$deductcredit, array(
                    '0',
                    $shop['name'] . "购物积分抵扣 消费积分: {$deductcredit} 抵扣金额: {$deductmoney} 订单号: {$ordersn}"
                ));
            }
            if (p('channel') && !empty($ischannelpick)) {
                p('channel')->deductChannelStock($orderid);
            } else {
                if (empty($virtualid)) {
                    m('order')->setStocksANDCredits($orderid, 0);
                } else {
                    if (isset($allgoods[0])) {
                        $vgoods = $allgoods[0];
                        pdo_update('sz_yi_goods', array(
                            'sales' => $vgoods['sales'] + $vgoods['total']
                        ), array(
                            'id' => $vgoods['goodsid']
                        ));
                    }
                }
            }
            /*if (empty($virtualid)) {
                m('order')->setStocksAndCredits($orderid, 0);
            } else {
                if (isset($allgoods[0])) {
                    $vgoods = $allgoods[0];
                    pdo_update('sz_yi_goods', array(
                        'sales' => $vgoods['sales'] + $vgoods['total']
                    ), array(
                        'id' => $vgoods['goodsid']
                    ));
                }
            }*/
            $plugincoupon = p("coupon");
            if ($plugincoupon) {
                $plugincoupon->useConsumeCoupon($orderid);
            }
            m('notice')->sendOrderMessage($orderid);
            if (p('channel')) {
                if (empty($ischannelpick)) {
                    $pluginc = p('commission');
                    if ($pluginc) {
                        $pluginc->checkOrderConfirm($orderid);
                    }
                }
            } else {
                $pluginc = p('commission');
                if ($pluginc) {
                    $pluginc->checkOrderConfirm($orderid);
                }
            }
            /*$pluginc = p('commission');
            if ($pluginc) {
                $pluginc->checkOrderConfirm($orderid);
            }*/

        }
        /*if ($pluginc) {
            $pluginc->checkOrderConfirm($orderid);
        }*/
        show_json(1, array(
            'orderid' => $orderid,
            'ischannelpay' => $ischannelpay,
            'ischannelpick' => $ischannelpick
        ));
    }else if ($operation == 'date') {
        global $_GPC, $_W;
        $id = $_GPC['id'];
        if ($search_array && !empty($search_array['bdate']) && !empty($search_array['day'])) {
            $bdate = $search_array['bdate'];
            $day = $search_array['day'];
        } else {
            $bdate = date('Y-m-d');
            $day = 1;
        }
        load()->func('tpl');
    include $this->template('order/date');
}
}
if(p('hotel') && $goods_data['type']=='99'){ //判断是否开启酒店插件
      if(empty($_SESSION['data'])){
            $btime = strtotime(date('Y-m-d'));
            $day=1;
            $etime = $btime + $day * 86400;
            $weekarray = array("日", "一", "二", "三", "四", "五", "六");
            $arr['btime'] = $btime;
            $arr['etime'] = $etime;
            $arr['bdate'] = date('Y-m-d');
            $arr['edate'] = date('Y-m-d', $etime);
            $arr['bweek'] = '星期' . $weekarray[date("w", $btime)];
            $arr['eweek'] = '星期' . $weekarray[date("w", $etime)];
            $arr['day'] = $day; 
            $_SESSION['data']=$arr;                           
         }
        include $this->template('order/confirm_hotel');
}else{
        include $this->template('order/confirm');
}

