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
use app\frontend\models\order\PreOrderDiscount;
use app\frontend\modules\order\models\PreOrder;
use Illuminate\Support\Collection;
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
        $this->amount_items = $this->getAmountItems();
        $this->discount_amount_items = $this->getDiscountAmountItems();
        $this->total_price = $this->orders->sum('price');
    }

    private function getAmountItems()
    {
        $items = [
            [
                'code' => 'total_goods_price',
                'name' => '订单总金额',
                'amount' => $this->orders->sum('goods_price'),
            ], [
                'code' => 'total_dispatch_price',
                'name' => '总运费',
                'amount' => $this->orders->sum('dispatch_price'),
            ]
        ];
        if($this->orders->sum('deduction_price')){
            $items[] = [
                'code' => 'total_deduction_price',
                'name' => '总抵扣',
                'amount' => $this->orders->sum('deduction_price'),
            ];
        }

        return $items;
    }

    /**
     * @return mixed
     */
    private function getDiscountAmountItems()
    {

        $orderDiscountsItems = $this->orders->reduce(function (Collection $result, PreOrder $order) {
            foreach ($order->orderDiscounts as $orderDiscount) {
                /**
                 * @var PreOrderDiscount $orderDiscount
                 */
                $item = $result->where('code', $orderDiscount->discount_code)->first();
                if(!$orderDiscount->amount){
                    continue;
                }
                if (isset($item)) {

                    $item['amount'] += $orderDiscount->amount;
                } else {
                    $result[] = [
                        'code' => $orderDiscount->discount_code,
                        'name' => $orderDiscount->name,
                        'amount' => $orderDiscount->amount,
                    ];
                }
            }
            return $result;
        }, collect())->map(function ($item) {
            $item['amount'] = sprintf('%.2f', $item['amount']);
            return $item;
        })->toArray();
        return $orderDiscountsItems;
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