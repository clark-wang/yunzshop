<?php
if (!defined('IN_IA')) {
	exit('Access Denied');
}
define('IP','115.28.225.82');
define('PORT','80');
define('HOSTNAME','/FeieServer/');
//以下2项是平台相关的设置，您不需要更改
define('FEYIN_HOST','my.feyin.net');
define('FEYIN_PORT', 80);
include_once 'HttpClient.class.php';
if (!class_exists('YunprintModel')) {
	class YunprintModel extends PluginModel
	{
        public $client;

        function __construct () {
            $this->client = new HttpClient(IP, PORT);
        }

        function feiyin_print ($print_order,$member_code,$device_no,$key, $offers)
        {
            $orderinfo = "合计：                   {$print_order['goodsprice']}\n";
            $statement = "";
            if (!empty($offers)) {
                if ($offers['discountprice'] == 1) {
                    $orderinfo .= "会员折扣：               {$print_order['discountprice']}\n";
                }
                if ($offers['deductcredit2'] == 1) {
                    $orderinfo .= "余额抵扣：               {$print_order['deductcredit2']}\n";
                }
                if ($offers['deductenough'] == 1) {
                    $orderinfo .= "满额优惠：               {$print_order['deductenough']}\n";
                }
                if ($offers['deductprice'] == 1) {
                    $orderinfo .= "积分抵扣：               {$print_order['deductprice']}\n";
                }
                if ($offers['couponprice'] == 1) {
                    $orderinfo .= "优惠项目：               {$print_order['couponprice']}\n";
                }
                if ($offers['dispatchprice'] == 1) {
                    $orderinfo .= "运费：                   {$print_order['dispatchprice']}\n";
                }
                if (!empty($offers['statement'])) {
                    $statement = $offers['statement'];
                    $statement = str_replace('[换行]', "\n", $statement);
                }
            }
            $orderinfo .= "实际支付：               {$print_order['price']}\n";
            $goods = "";
            $num = 1;
            foreach ($print_order['goods'] as $value) {
                $goods .= "  ".$num."  ".$value['goodstitle']."\n             ".$value['marketprice']." ".$value['total']."   ".$value['price']."\n";
                $num++;
            }
            
            $msgNo = $print_order['ordersn'];
            $address = unserialize($print_order['address']);
            $time = date('Y-m-d H:i:s',$print_order['createtime']);
            $freeMessage = array(
                'memberCode'=>$member_code, 
                'msgDetail'=>
                "
    {$print_order['shopname']}
------------------------------
订单编号：{$msgNo}
订购时间：{$time}
客户姓名：{$address['realname']}
联系方式：{$address['mobile']}
配送地址：{$address['province']}{$address['city']}{$address['area']}{$address['address']}
订单备注：{$print_order['remark']}
------------------------------
序号 商品名称 单价 数量  金额
{$goods}
------------------------------
{$orderinfo}
------------------------------
{$statement}
客户签收：
            ",
                'deviceNo'=>$device_no, 
                'msgNo'=>$msgNo
            );

             $this->sendFreeMessage($freeMessage,$key);

            return $msgNo;
        }

        function sendFreeMessage ($msg,$key) 
        {
            $msg['reqTime'] = number_format(1000*time(), 0, '', '');
            $content = $msg['memberCode'].$msg['msgDetail'].$msg['deviceNo'].$msg['msgNo'].$msg['reqTime'].$key;
            $msg['securityCode'] = md5($content);
            $msg['mode']=2;
            return $this->sendMessage($msg);
        }

        function sendMessage ($msgInfo) 
        {
            $clientt = new HttpClient(FEYIN_HOST,FEYIN_PORT);
            if(!$clientt->post('/api/sendMsg',$msgInfo)){ //提交失败
                return 'faild';
            }else{
                echo "<pre>";print_r($clientt->getContent());exit;
                return $clientt->getContent();
            }
        }

        function feie_print ($order,$printer_sn,$key,$times,$url, $offers)
        {
            //标签说明："<BR>"为换行符,"<CB></CB>"为居中放大,"<B></B>"为放大,"<C></C>"为居中,"<L></L>"为字体变高
            //"<W></W>"为字体变宽,"<QR></QR>"为二维码,"<CODE>"为条形码,后面接12个数字
            $address = unserialize($order['address']);
            $goods = "";
            $num = 1;
            foreach ($order['goods'] as $value) {
                $goods .= " ".$num."    ".$value['goodstitle']."<BR>                         ".$value['marketprice']."      ".$value['total']."    ".$value['price']."<BR>";
                $num++;
            }
            $time = date('Y-m-d H:i:s',$order['createtime']);
            $orderInfo = "<CB><LOGO>{$order['shopname']}</CB><BR>";
            $orderInfo .= "订单编号：{$order['ordersn']}<BR>";
            $orderInfo .= "订购时间：{$time}<BR>";
            $orderInfo .= "客户姓名：{$address['realname']}<BR>";
            $orderInfo .= "联系方式：{$address['mobile']}<BR>";
            $orderInfo .= "配送地址：{$address['city']}{$address['area']}{$address['address']}<BR>";
            $orderInfo .= "订单备注：{$order['remark']}<BR>";
            $orderInfo .= "================================================<BR>";
            $orderInfo .= "序号  商品名称           单价   数量  金额<BR>";
            $orderInfo .= "{$goods}<BR>";
            $orderInfo .= "================================================<BR>";
            $orderInfo .= "合计：                          {$order['goodsprice']}<BR>";
            $statement = "";
            if (!empty($offers)) {
                if ($offers['discountprice'] == 1) {
                    $orderInfo .= "会员折扣：               {$order['discountprice']}<BR>";
                }
                if ($offers['deductcredit2'] == 1) {
                    $orderInfo .= "余额抵扣：               {$order['deductcredit2']}<BR>";
                }
                if ($offers['deductenough'] == 1) {
                    $orderInfo .= "满额优惠：               {$order['deductenough']}<BR>";
                }
                if ($offers['deductprice'] == 1) {
                    $orderInfo .= "积分抵扣：               {$order['deductprice']}<BR>";
                }
                if ($offers['couponprice'] == 1) {
                    $orderInfo .= "优惠项目：               {$order['couponprice']}<BR>";
                }
                if ($offers['dispatchprice'] == 1) {
                    $orderinfo .= "运费：                   {$print_order['dispatchprice']}<BR>";
                }
            }
            $orderInfo .= "实际支付：                    {$order['price']}<BR>";
            $orderInfo .= "================================================<BR>";
            $orderInfo .= "{$statement}";
            $orderInfo .= "客户签收：<BR>";
            $orderInfo .= "<QR>{$url}</QR>";//把二维码字符串用标签套上即可自动生成二维码
            $content = array(
                'sn'=>$printer_sn,  
                'printContent'=>$orderInfo,
                //'apitype'=>'php',//如果打印出来的订单中文乱码，请把注释打开
                'key'=>$key,
                'times'=>$times//打印次数
            );
            
            if(!$this->client->post(HOSTNAME.'/printOrderAction',$content)){
                echo 'error';
            }
            else{
                 $this->client->getContent();
            }
        }

        public function executePrint ($orderid) {
            global $_W;
            if (empty($orderid)) {
                return;
            }
            $set = $this->getSet();
            $offers = $set['offers'];
            $shopset = m('common')->getSysset('shop');
            $order = pdo_fetch("SELECT * FROM " . tablename('sz_yi_order') . " WHERE uniacid=:uniacid AND id=:id", array(
                    ':uniacid'  => $_W['uniacid'],
                    ':id'       => $orderid
                ));
            $order['shopname'] = $shopset['name'];
            $order['goods'] = pdo_fetchall("SELECT og.goodsid,og.price,og.total,g.title,g.marketprice FROM " . tablename('sz_yi_order_goods') . " og LEFT JOIN " . tablename('sz_yi_goods') . " g ON g.id=og.goodsid WHERE og.uniacid=:uniacid AND og.orderid=:orderid", array(
                    ':uniacid' => $_W['uniacid'],
                    ':orderid' => $orderid
                )); 
            foreach ($order['goods'] as &$value) {
                $value['totalmoney'] = number_format($value['price']*$value['total'],2);
            }
            unset($value);
            $openprint = pdo_fetch("SELECT * FROM " . tablename('sz_yi_yunprint_list') . " WHERE uniacid=:uniacid AND status=:status LIMIT 1 ", array(
                    ':uniacid'  => $_W['uniacid'],
                    ':status'   => 1
                ));
            // mode = 1 飞蛾   mode = 2 飞印
            if ($openprint['mode'] == 1) {
                $this->feie_print($order, $openprint['member_code'], $openprint['print_no'], $openprint['print_nums'], $offers);
            }
            if ($openprint['mode'] == 2) {
                $this->feiyin_print($order, $openprint['member_code'], $openprint['print_no'], $openprint['key'], $openprint['qrcode_link'], $offers);
            }
        }

        public function getSet()
        {
            $set = parent::getSet();
            return $set;
        }
	}
}
