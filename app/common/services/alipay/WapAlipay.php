<?php
/**
 * Created by PhpStorm.
 * Author: 芸众商城 www.yunzshop.com
 * Date: 2017/3/23
 * Time: 上午11:03
 */

/**
 * 手机WAP端支付宝支付功能
 */
namespace app\common\services\alipay;

use app\common\services\AliPay;

class WapAlipay extends AliPay
{
    public function __construct()
    {
        //todo
    }

    public function doPay($data = [])
    {
        $type = \YunShop::request()->type;

        if ($type == 7) {
            $isnewalipay = \Setting::get('shop_app.pay.newalipay');
            if ($isnewalipay == 1) {
                \Log::info('-------test-------', print_r($data,true));

                $content = [
                    'body' => $data['order_no'],

                    'subject' => $data['subject'],
                    'out_trade_no' => $data['order_no'],
                    //'timeout_express' => '',
                    'total_amount' => $data['amount'],
                    'product_code' => 'QUICK_WAP_WAY',
                ];

                // 跳转到支付页面。
                $result = app('alipay.wap2')->pageExecute(json_encode($content));

                \Log::info('-------test2-------', print_r($result,true));

                //dd(123);
                return $result;
            }
        }

        // 创建支付单。
        $alipay = app('alipay.wap');

        $alipay->setOutTradeNo($data['order_no']);
        $alipay->setTotalFee($data['amount']);
        $alipay->setSubject($data['subject']);
        $alipay->setBody($data['body']);

        // 跳转到支付页面。
        return $alipay->getPayLink();
    }
}