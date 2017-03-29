<?php
namespace app\frontend\modules\order\services\models;

use app\common\events\order\AfterOrderCreatedEvent;
use app\common\models\Order;
use app\common\models\Member;

use app\frontend\modules\discount\services\DiscountService;
use app\frontend\modules\dispatch\services\DispatchService;
use app\frontend\modules\goods\services\models\PreGeneratedOrderGoodsModel;
use app\frontend\modules\order\services\OrderService;
use app\frontend\modules\shop\services\models\ShopModel;

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
    protected $id;
    /**
     * @var 商城model实例
     */
    protected $shop_model;
    /**
     * @var \app\frontend\models\Member
     */
    protected $member_model;

    /**
     * 记录添加的商品
     * PreGeneratedOrderModel constructor.
     * @param array|null $OrderGoodsModels
     */
    public function __construct(array $OrderGoodsModels = null)
    {
        if (!isset($OrderGoodsModels)) {
            echo '订单商品为空!';exit;
        }
        parent::__construct($OrderGoodsModels);
    }
    protected function setOrderGoodsModels(array $OrderGoodsModels)
    {
        $this->_OrderGoodsModels = $OrderGoodsModels;
    }

    protected function setDiscount()
    {
        $this->_OrderDiscount = DiscountService::getPreOrderDiscountModel($this);
    }
    protected function setDispatch()
    {
        $this->_OrderDispatch = DispatchService::getPreOrderDispatchModel($this);
    }

    /**
     * 对外提供的获取订单商品方法
     * @return array
     */
    public function getOrderGoodsModels()
    {
        return $this->_OrderGoodsModels;
    }

    /**
     * 添加订单商品
     * @param array $pre_order_goods_models
     */
    private function addPreGeneratedOrderGoods(array $pre_order_goods_models)
    {
        $this->_OrderGoodsModels = array_merge($this->_OrderGoodsModels, $pre_order_goods_models);
    }

    /**
     * 设置订单所属用户
     * @param Member $member_model
     */
    public function setMemberModel(Member $member_model)
    {
        $this->member_model = $member_model;
    }

    /**
     * 设置订单所属店铺
     * @param ShopModel $shop_model
     */

    public function setShopModel(ShopModel $shop_model)
    {
        $this->shop_model = $shop_model;
    }
    public function getShopModel(){
        return $this->shop_model;
    }
    public function getMemberModel(){
        return $this->member_model;
    }

    /**
     * 输出订单信息
     * @return array
     */
    public function toArray()
    {
        $data = array(
            'price' => $this->getPrice(),
            'goods_price' => $this->getVipPrice(),
            'dispatch_price' => $this->getDispatchPrice(),
            'discount_price' => $this->getDiscountPrice(),
            'deduction_price' => $this->getDeductionPrice(),

        );
        foreach ($this->_OrderGoodsModels as $order_goods_model) {
            $data['order_goods'][] = $order_goods_model->toArray();
        }
        return $data;
    }

    /**
     * @return bool 订单插入数据库,触发订单生成事件
     */
    public function generate()
    {
        $order_model = $this->createOrder();
        $this->id = $order_model->id;
        $this->createOrderGoods();
        event(new AfterOrderCreatedEvent($order_model));
        return true;
    }
    /**
     * 订单商品生成
     */
    private function createOrderGoods()
    {
        foreach ($this->_OrderGoodsModels as $preOrderGoodsModel) {
            /**
             * @var $preOrderGoodsModel PreGeneratedOrderGoodsModel
             */
            $preOrderGoodsModel->generate($this);
        }
    }
    protected function getDispatchPrice(){
        return $this->_OrderDispatch->getDispatchPrice();
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
            'goods_total'=> $this->getGoodsTotal(),//订单商品总数
            'order_sn' => OrderService::createOrderSN(),//订单编号
            'create_time' => time(),
            //配送类获取订单配送方式id
            'dispatch_type_id'=>$this->_OrderDispatch->getDispatchTypeId(),
            'uid' => $this->member_model->uid,
            'uniacid' => $this->shop_model->uniacid,
        );
        //todo 测试

        return Order::create($data);
    }

}