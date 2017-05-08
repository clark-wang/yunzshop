<?php
/**
 * Created by PhpStorm.
 * User: shenyang
 * Date: 2017/4/11
 * Time: 上午10:20
 */

namespace app\frontend\modules\order\controllers;

use app\frontend\modules\member\services\MemberCartService;
use Request;
use app\frontend\modules\order\services\OrderService;

class GoodsBuyController extends PreGeneratedController
{
    protected function getMemberCarts()
    {
        $goods_params = [
            'goods_id' => Request::query('goods_id'),
            'total' => Request::query('total'),
            'option_id' => Request::query('option_id'),
        ];
        $result = collect();
        $result->push(MemberCartService::newMemberCart($goods_params));
        return $result;
    }
}