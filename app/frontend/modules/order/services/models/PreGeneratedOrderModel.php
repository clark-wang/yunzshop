<?php

namespace app\frontend\modules\order\services\models;

use app\common\models\Order;

use app\frontend\modules\discount\services\DiscountService;
use app\frontend\modules\discount\services\models\OrderDiscount;
use app\frontend\modules\dispatch\services\DispatchService;
use app\frontend\modules\goods\services\models\PreGeneratedOrderGoodsModel;
use app\frontend\modules\order\services\OrderService;
use Illuminate\Support\Facades\DB;

/**
 * 订单生成类
 * 输入
 *  用户model
 *  店铺model
 *  未生成的订单商品(实例)app\frontend\modules\goods\services\models\PreGeneratedOrderGoodsModel
 * 输出
 *  预下单信息
 *  订单表插入结果
 * 执行
 *  订单表操作
 * 事件通知
 *  终止订单生成
 *  订单生成后
 * Class PreGeneratedOrderModel
 * @package app\frontend\modules\order\services\models
 */
class PreGeneratedOrderModel extends OrderModel
{
    /**
     * @var ShopModel 商城model实例
     */
    /**
     * @var \app\frontend\models\Member
     */
    /**
     * 记录添加的商品
     * PreGeneratedOrderModel constructor.
     * @param array|null $orderGoodsModels
     */
    /**
     * @var \app\frontend\modules\dispatch\services\models\OrderDispatch 运费类实例
     */
    protected $orderDispatch;
    /**
     * @var OrderDiscount 优惠类实例
     */
    protected $orderDiscount;
    public function setOrderGoodsModels(array $orderGoodsModels)
    {
        $this->orderGoodsModels = $orderGoodsModels;
        $this->setDispatch();
        $this->setDiscount();
    }

    protected function setDiscount()
    {
        $this->orderDiscount = DiscountService::getPreOrderDiscountModel($this);
    }

    protected function setDispatch()
    {
        $this->orderDispatch = DispatchService::getPreOrderDispatchModel($this);
    }

    /**
     * 对外提供的获取订单商品方法
     * @return array
     */
    public function getOrderGoodsModels()
    {
        return $this->orderGoodsModels;
    }

    public function getOrder()
    {
        return $this;
    }

    public function getMember()
    {
        return $this->belongsToMember;
    }
    /**
     * 计算订单优惠金额
     * @return number
     */
    protected function getDiscountPrice()
    {
        return $this->orderDiscount->getDiscountPrice();
    }

    /**
     * 获取订单抵扣金额
     * @return number
     */
    protected function getDeductionPrice()
    {
        return $this->orderDiscount->getDeductionPrice();
    }

    /**
     * 计算订单运费
     * @return int|number
     */
    protected function getDispatchPrice()
    {
        return $this->orderDispatch->getDispatchPrice();
    }
    /**
     * 输出订单信息
     * @return array
     */
    public function toArray()
    {
        $data = array(
            'price' => number_format($this->getPrice(),2),
            'goods_price' => number_format($this->getVipPrice(),2),
            'dispatch_price' => number_format($this->getDispatchPrice(),2),
            'discount_price' => number_format($this->getDiscountPrice(),2),
            'deduction_price' => number_format($this->getDeductionPrice(),2),

        );
        foreach ($this->orderGoodsModels as $orderGoodsModel) {
            $data['order_goods'][] = $orderGoodsModel->toArray();
        }
        return $data;
    }

    /**
     * @return bool 订单插入数据库,触发订单生成事件
     */
    public function generate()
    {
        $orderModel = $this->createOrder();
        $orderGoodsModels = $this->createOrderGoods();
        $order = Order::create($orderModel);
        foreach ($orderGoodsModels as $orderGoodsModel) {
            $orderGoodsModel->order_id = $order->id;
            $orderGoodsModel->save();
        }

        $this->id = $order->id;

        return $order->id;
    }

    /**
     * 订单商品生成
     */
    private function createOrderGoods()
    {
        $result = [];
        foreach ($this->orderGoodsModels as $preOrderGoodsModel) {
            /**
             * @var $preOrderGoodsModel PreGeneratedOrderGoodsModel
             */
            $result[] = $preOrderGoodsModel->generate($this);
        }
        return $result;
    }


    /**
     * 订单插入数据库
     * @return static 新生成的order model
     */
    private function createOrder()
    {
        $data = array(
            'price' => $this->getPrice(),//订单最终支付价格
            'order_goods_price' => $this->getOrderGoodsPrice(),//订单商品商城价
            'goods_price' => $this->getVipPrice(),//订单会员价

            'discount_price' => $this->getDiscountPrice(),//订单优惠金额
            'deduction_price' => $this->getDeductionPrice(),//订单抵扣金额
            'dispatch_price' => $this->getDispatchPrice(),//订单运费
            'goods_total' => $this->getGoodsTotal(),//订单商品总数
            'order_sn' => OrderService::createOrderSN(),//订单编号
            'create_time' => time(),
            //配送类获取订单配送方式id
            'dispatch_type_id' => 0,
            'uid' => $this->uid,
            'uniacid' => $this->uniacid,
        );
        //todo 测试

        return $data;
    }

}