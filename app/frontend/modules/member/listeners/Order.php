<?php
/**
 * Created by PhpStorm.
 * Author: 芸众商城 www.yunzshop.com
 * Date: 2017/4/1
 * Time: 下午4:49
 */

namespace app\frontend\modules\member\listeners;

use app\common\events\order\AfterOrderCreatedEvent;

class Order
{
    public function handle(AfterOrderCreatedEvent $event){

        $goods_ids = $event->getOrder()->first()->orderGoods->pluck('goods_id');
        $goods_option_ids = $event->getOrder()->first()->orderGoods->pluck('goods_option_id');

        app('OrderManager')->make('MemberCart')->uniacid()->whereIn('goods_id', $goods_ids)->delete();
        app('OrderManager')->make('MemberCart')->uniacid()->whereIn('option_id', $goods_option_ids)->delete();

    }
}