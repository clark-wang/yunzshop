<?php
/**
 * Created by PhpStorm.
 * Author: 芸众商城 www.yunzshop.com
 * Date: 2017/3/2
 * Time: 下午4:52
 */

namespace app\frontend\modules\order\services\status;


class StatusServiceFactory
{
    public static function createStatusService($order){
        switch ($order->status){
            case -1:
                return new Close($order);
                break;
            case 0:
                return new WaitPay($order);
                break;
            case 1:
                return new WaitSend($order);
                break;
            case 2:
                return new WaitReceive($order);
                break;
            case 3:
                return new Complete($order);
                break;

        }
    }
}