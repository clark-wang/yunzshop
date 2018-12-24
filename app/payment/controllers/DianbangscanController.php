<?php

namespace app\payment\controllers;

use app\common\helpers\Url;
use app\common\models\AccountWechats;
use app\payment\PaymentController;
use app\frontend\modules\finance\models\BalanceRecharge;
use app\common\services\Pay;
use app\common\models\Order;
use app\common\models\OrderPay;

class DianbangscanController extends PaymentController
{

    //原始数据
    private $xml;

    private $key;

    private $parameters = [];

    public function __construct()
    {
        parent::__construct();

        if (empty(\YunShop::app()->uniacid)) {

            $this->parameters = $_POST;

            \Setting::$uniqueAccountId = \YunShop::app()->uniacid = $this->parameters['billDesc'];

            AccountWechats::setConfig(AccountWechats::getAccountByUniacid(\YunShop::app()->uniacid));
        }
    }

    //微信公众号支付通知
    public function notifyUrl()
    {
        \Log::debug('------------店帮微信异步通知---------------->');

        $this->log($this->parameters, '店帮微信');

        $set = \Setting::get('plugin.dian-bang-scan');
        $this->setKey($set['key']);

        $order_no = explode('-', $this->getParameter('billNo'));

        if($this->getSignResult()) {
            \Log::info('------店帮微信验证成功-----');
            if ($this->parameters['billPayment']['status'] == 'TRADE_SUCCESS') {
                \Log::info('-------店帮微信支付开始---------->');
                $data = [
                    'total_fee'    => floatval($this->getParameter('totalAmount')),
                    'out_trade_no' => $order_no[1],
                    'trade_no'     => $this->parameters['billPayment']['targetOrderId'],
                    'unit'         => 'fen',
                    'pay_type'     => '店帮微信支付',
                    'pay_type_id'  => 24,
                ];
                $this->payResutl($data);
                \Log::info('<---------店帮微信支付结束-------');
                echo 'SUCCESS';
                exit();
            } else {
                //支付失败
                echo 'FAILED';
                exit();
            }
        } else {
            //签名验证失败
            echo 'FAILED';
            exit();
        }
    }

    //支付宝支付通知
    public function alipayNotifyUrl()
    {
        \Log::debug('------------店帮支付宝异步通知---------------->');
        $this->log($this->parameters, '店帮支付宝');

        $set = \Setting::get('plugin.dian-bang-scan');
        $this->setKey($set['key']);

        if($this->getSignResult()) {
            \Log::info('------店帮支付宝验证成功-----');
            if ($this->getParameter('status') == 0 && $this->getParameter('result_code') == 0) {
                \Log::info('-------店帮支付宝支付开始---------->');
                $data = [
                    'total_fee'    => floatval($this->getParameter('total_fee')),
                    'out_trade_no' => $this->getParameter('out_trade_no'),
                    'trade_no'     => 'dian-bang-scan',
                    'unit'         => 'fen',
                    'pay_type'     => '店帮支付宝',
                    'pay_type_id'  => 24,
                ];
                $this->payResutl($data);
                \Log::info('<---------店帮支付宝支付结束-------');
                echo 'success';
                exit();
            } else {
                //支付失败
                echo 'failure';
                exit();
            }
        } else {
            //签名验证失败
            echo 'failure';
            exit();
        }
    }

    public function returnUrl()
    {

    }

    /**
     * 签名验证
     *
     * @return bool
     */
    public function getSignResult()
    {
        $swiftpassSign = strtolower($this->getParameter('sign'));
        $md5Sign = $this->getMD5Sign();

        return $swiftpassSign == $md5Sign;
    }

    //MD5签名
    public function getMD5Sign() {
        $signPars = "";
        ksort($this->parameters);
        foreach($this->parameters as $k => $v) {
            if("sign" != $k && "" != $v) {
                $signPars .= $k . "=" . $v . "&";
            }
        }
        $signPars .= "key=" . $this->getKey();

        return strtolower(md5($signPars));
    }

    /**
     *设置密钥
     */
    public function setKey($key) {
        $this->key = $key;
    }


    /**
     * @param 获取密钥
     * @return mixed
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     *获取参数值
     */
    public function getParameter($parameter) {
        return isset($this->parameters[$parameter])?$this->parameters[$parameter] : '';
    }

    /**
     * 支付日志
     *
     * @param $post
     */
    public function log($data, $msg = '店帮支付')
    {
        //访问记录
        Pay::payAccessLog();
        //保存响应数据
        Pay::payResponseDataLog($this->getParameter('out_trade_no'), $msg, json_encode($data));
    }
}