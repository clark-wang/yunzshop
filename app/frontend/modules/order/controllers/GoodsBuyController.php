<?php
/**
 * Created by PhpStorm.
 * Author: 芸众商城 www.yunzshop.com
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
            'goods_id' => request()->input('goods_id'),
            'total' => request()->input('total'),
            'option_id' => request()->input('option_id'),
        ];

        dd(request()->input());
        exit;

        $result = collect();
        $result->push(MemberCartService::newMemberCart($goods_params));
        return $result;
    }
}