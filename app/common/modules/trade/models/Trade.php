<?php
/**
 * Created by PhpStorm.
 * User: shenyang
 * Date: 2018/11/13
 * Time: 5:07 PM
 */

namespace app\common\modules\trade\models;

use app\common\models\BaseModel;
use app\common\modules\memberCart\MemberCartCollection;
use app\common\modules\order\OrderCollection;
use app\frontend\modules\order\models\PreOrder;
use Illuminate\Support\Facades\DB;

/**
 * Class Trade
 * @package app\common\modules\trade\models
 * @property OrderCollection orders
 * @property TradeDiscount discount
 * @property float total_deduction_price
 * @property float total_discount_price
 * @property float total_dispatch_price
 * @property float total_goods_price
 * @property float total_price
 */
class Trade extends BaseModel
{

    public function init(MemberCartCollection $memberCartCollection)
    {
        $this->setRelation('orders', $this->getOrderCollection($memberCartCollection));
        $this->setRelation('discount', $this->getDiscount());
        $this->setRelation('dispatch', $this->getDispatch());
        $this->initAttribute();

    }

    private function initAttribute()
    {
        $attributes = [
            'total_price' => $this->orders->sum('price'),
            'total_goods_price' => $this->orders->sum('order_goods_price'),
            'total_dispatch_price' => $this->orders->sum('dispatch_price'),
            'total_discount_price' => $this->orders->sum('discount_price'),
            'total_deduction_price' => $this->orders->sum('deduction_price'),
        ];

        $attributes = array_merge($this->getAttributes(), $attributes);
        $this->setRawAttributes($attributes);
        return $this;
    }

    /**
     * 显示订单数据
     * @return array
     */
    public function toArray()
    {
        $attributes = parent::toArray();
        $attributes = $this->formatAmountAttributes($attributes);
        return $attributes;
    }

    private function getOrderCollection(MemberCartCollection $memberCartCollection)
    {
        // 按插件分组
        $groups = $memberCartCollection->groupByGroupId()->values();
        // 分组下单
        $orderCollection = $groups->map(function (MemberCartCollection $memberCartCollection) {

            return $memberCartCollection->getOrder($memberCartCollection->getPlugin());
        });
        return new OrderCollection($orderCollection->all());
    }

    /**
     * @return TradeDiscount
     */
    private function getDiscount()
    {
        $tradeDiscount = new TradeDiscount();
        $tradeDiscount->init($this);
        return $tradeDiscount;
    }

    private function getDispatch()
    {
        $tradeDispatch = new TradeDispatch();
        $tradeDispatch->init($this);
        return $tradeDispatch;
    }

    public function generate()
    {
        DB::transaction(function () {
            return $this->orders->map(function (PreOrder $order) {
                /**
                 * @var $order
                 */
                $order->generate();
                $order->fireCreatedEvent();
            });
        });
    }
}