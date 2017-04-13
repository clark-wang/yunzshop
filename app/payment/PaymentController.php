<?php

namespace  app\payment;

use app\common\components\BaseController;
use app\frontend\modules\order\services\OrderService;

/**
 * Created by PhpStorm.
 * User: jan
 * Date: 24/03/2017
 * Time: 09:06
 */
class PaymentController extends BaseController
{
    public function __construct()
    {
        parent::__construct();

        $script_info = pathinfo($_SERVER['SCRIPT_NAME']);

        if (!empty($script_info)) {
            switch ($script_info['filename']) {
                case 'notifyUrl':
                    \YunShop::app()->uniacid = $this->getUniacid();
                    break;
                case 'refundNotifyUrl':
                case 'withdrawNotifyUrl':
                    $batch_no = !empty($_REQUEST['batch_no']) ? $_REQUEST['batch_no'] : '';

                    \YunShop::app()->uniacid = substr($batch_no, 17, 5);
                    break;
                default:
                    \YunShop::app()->uniacid = $this->getUniacid();
                    break;
            }
        }

       \Setting::$uniqueAccountId = \YunShop::app()->uniacid;
    }

    private function getUniacid()
    {
        $body = !empty($_REQUEST['body']) ? $_REQUEST['body'] : '';
        $splits = explode(':', $body);

        if (!empty($splits[1])) {
            return intval($splits[1]);
        } else {
            return 0;
        }
    }

    public function payResutl($data)
    {
        $type = $this->getPayType($data['out_trade_no']);

        $pay_order_model = PayOrder::uniacid()->where('order_sn', $data['out_trade_no']);
        $pay_order_model->status = 2;
        $pay_order_model->save();

        switch ($type) {
            case "charge.succeeded":
                $order_info = Order::uniacid()->where('order_sn', $data['out_trade_no']);

                if (bccomp($order_info->price, $data['total_fee'], 2) == 0) {
                    OrderService::orderPay(['order_id' => $data['out_trade_no']]);
                }
                break;
            case "recharge.succeeded":
                break;
        }
    }
}