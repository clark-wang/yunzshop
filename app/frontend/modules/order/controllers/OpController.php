<?php
/**
 * Created by PhpStorm.
 * User: shenyang
 * Date: 2017/3/3
 * Time: 下午2:30
 */

namespace app\frontend\modules\order\controllers;

use app\common\models\Order;
use app\frontend\modules\order\services\behavior\OrderCancelPay;
use app\frontend\modules\order\services\behavior\OrderCancelSend;
use app\frontend\modules\order\services\behavior\OrderDelete;
use app\frontend\modules\order\services\behavior\OrderPay;
use app\frontend\modules\order\services\behavior\OrderSend;

class OpController
{
    public function pay(){
        $order = Order::first();
        $order_pay = new OrderPay($order);
        if (!$order_pay->payable()) {
            echo '状态不正确';exit;
        }
        $order_pay->pay();
    }
    public function cancelPay(){
        $order = Order::first();
        $cancel_pay = new OrderCancelPay($order);
        if (!$cancel_pay->cancelable()) {
            echo '状态不正确';exit;
        }
        $cancel_pay->cancelPay();
    }
    public function send(){
        $order = Order::first();
        $order_send = new OrderSend($order);
        if (!$order_send) {
            echo '状态不正确';exit;
        }
        $order_send->send();
    }
    public function cancelSend(){
        $order = Order::first();
        $cancel_send = new OrderCancelSend($order);
        if (!$cancel_send->sendable()) {
            echo '状态不正确';exit;
        }
        $cancel_send->cancelSend();
    }
    public function Receive(){
        $order = Order::first();
        $order_receive = new OrderReceive($order);
        if (!$order_receive->receiveable()) {
            echo '状态不正确';exit;
        }
        $order_receive->receive();
    }
    public function Delete()
    {
        $order = Order::first();
        $order_delete = new OrderDelete($order);
        if (!$order_delete->deleteable()) {
            echo '状态不正确';exit;
        }
        $order_delete->delete();
    }
}