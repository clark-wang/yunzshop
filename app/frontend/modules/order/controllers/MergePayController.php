<?php
/**
 * Created by PhpStorm.
 * Author: 芸众商城 www.yunzshop.com
 * Date: 2017/4/25
 * Time: 上午11:00
 */

namespace app\frontend\modules\order\controllers;

use app\common\components\ApiController;
use app\common\events\payment\GetOrderPaymentTypeEvent;
use app\common\exceptions\AppException;
use app\common\models\Order;
use app\common\models\OrderPay;
use app\common\services\password\PasswordService;
use app\common\services\PayFactory;
use app\common\services\Session;
use app\frontend\modules\order\services\OrderService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MergePayController extends ApiController
{
    public $transactionActions = ['wechatPay', 'alipay'];
    /**
     * @var Collection
     */
    protected $orders;
    protected $orderPay;//todo 临时解决,后续需要重构
    protected $publicAction = ['alipay'];
    protected $ignoreAction = ['alipay'];

    /**
     * 支付的时候,生成支付记录的时候,通过订单ids获取订单集合
     * @param $orderIds
     * @return Collection
     * @throws AppException
     */
    protected function orders($orderIds)
    {
        if (!is_array($orderIds)) {
            $orderIds = explode(',', $orderIds);
        }
        array_walk($orderIds, function ($orderId) {
            if (!is_numeric($orderId)) {
                throw new AppException('(ID:' . $orderId . ')订单号id必须为数字');
            }
        });

        $this->orders = Order::select(['status', 'id', 'order_sn', 'price', 'uid'])->whereIn('id', $orderIds)->get();

        if ($this->orders->count() != count($orderIds)) {
            throw new AppException('(ID:' . implode(',', $orderIds) . ')未找到订单');
        }
        $this->orders->each(function ($order) {
            if ($order->status > Order::WAIT_PAY) {
                throw new AppException('(ID:' . $order->id . ')订单已付款,请勿重复付款');
            }
            if ($order->status == Order::CLOSE) {
                throw new AppException('(ID:' . $order->id . ')订单已关闭,无法付款');
            }
            if ($order->uid != \YunShop::app()->getMemberId()) {
                throw new AppException('(ID:' . $order->id . ')该订单属于其他用户');
            }
        });
        // 订单金额验证
        if ($this->orders->sum('price') < 0) {
            throw new AppException('(' . $this->orders->sum('price') . ')订单金额有误');
        }
        return $this->orders;
    }

    /**
     * 获取支付按钮列表接口
     * @param \Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws AppException
     */
    public function index(\Request $request)
    {
        // 验证
        $this->validate([
            'order_ids' => 'required|string'
        ]);
        // 订单集合
        $orders = $this->orders($request->input('order_ids'));
        // 用户余额
        $member = $orders->first()->belongsToMember()->select(['credit2'])->first()->toArray();
        // 验证支付密码
        $this->checkPassword($orders->first()->uid);
        // 支付类型
        $buttons = $this->getPayTypeButtons();
        // 生成支付记录 记录订单号,支付金额,用户,支付号
        $orderPay = new OrderPay();
        $orderPay->order_ids = explode(',', $request->input('order_ids'));
        $orderPay->amount = $orders->sum('price');
        $orderPay->uid = $orders->first()->uid;
        $orderPay->pay_sn = OrderService::createPaySN();
        $orderPayId = $orderPay->save();
        if (!$orderPayId) {
            throw new AppException('支付流水记录保存失败');
        }

        $data = ['order_pay' => $orderPay, 'member' => $member, 'buttons' => $buttons, 'typename' => '支付'];

        return $this->successJson('成功', $data);
    }

    /**
     * 校验支付密码
     * @param $uid
     * @return bool
     */
    private function checkPassword($uid){
        if(!\Setting::get('shop.pay.balance_pay_proving')){
            // 未开启
            return true;
        }
        $this->validate([
            'payment_password' => 'required|string'
        ]);
        return (new PasswordService())->checkMemberPassword($uid,request()->input('payment_password'));
    }

    /**
     * 通过事件获取支付按钮
     * @return array
     */
    private function getPayTypeButtons()
    {
        $event = new GetOrderPaymentTypeEvent($this->orders);
        event($event);
        $result = $event->getData();
        return $result ? $result : [];
    }

    /**
     * 支付
     * @param $payType
     * @return \app\common\services\strin5|array|bool|mixed|void
     * @throws AppException
     */
    protected function pay($payType)
    {
        $this->validate([
            'order_pay_id' => 'required|integer'
        ]);
        // 支付记录
        $this->orderPay = $orderPay = OrderPay::find(request()->input('order_pay_id'));
        if (!isset($orderPay)) {
            throw new AppException('(ID' . request()->input('order_pay_id') . ')支付流水记录不存在');
        }
        if ($orderPay->status > 0) {
            throw new AppException('(ID' . request()->input('order_pay_id') . '),此流水号已支付');
        }
        // 订单集合
        $orders = $this->orders($orderPay->order_ids);
        return $this->getPayResult($payType,$orderPay,$orders);
    }

    /**
     * 支付结果
     * @param $payType
     * @param $orderPay
     * @param $orders
     * @return \app\common\services\strin5|array|bool|mixed|void
     * @throws AppException
     */
    protected function getPayResult($payType,$orderPay,$orders){
        $query_str = $this->getPayParams($orderPay, $orders);
        $pay = PayFactory::create($payType);
        //如果支付模块常量改变 数据会受影响
        $result = $pay->doPay($query_str, $payType);
        if (!isset($result)) {
            throw new AppException('获取支付参数失败');
        }
        return $result;
    }

    /**
     * 拼写支付参数
     * @param $orderPay
     * @param Collection $orders
     * @return array
     */
    protected function getPayParams($orderPay, Collection $orders)
    {
        return [
            'order_no' => $orderPay->pay_sn,
            'amount' => $orderPay->amount,
            'subject' => $orders->first()->hasManyOrderGoods[0]->title ?: '芸众商品',
            'body' => ($orders->first()->hasManyOrderGoods[0]->title ?: '芸众商品') . ':' . \YunShop::app()->uniacid,
            'extra' => ['type' => 1]
        ];
    }

    /**
     * 微信支付
     * @param \Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws AppException
     */
    public function wechatPay(\Request $request)
    {
        if (\Setting::get('shop.pay.weixin') == false) {
            throw new AppException('商城未开启微信支付');
        }
        $data = $this->pay( PayFactory::PAY_WEACHAT);
        $data['js'] = json_decode($data['js'], 1);
        return $this->successJson('成功', $data);
    }

    /**
     * 支付宝支付
     * @param \Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws AppException
     */
    public function alipay(\Request $request)
    {
        if (\Setting::get('shop.pay.alipay') == false) {
            throw new AppException('商城未开启支付宝支付');
        }
        if ($request->has('uid')) {
            Session::set('member_id', $request->query('uid'));
        }
        $data = $this->pay( PayFactory::PAY_ALIPAY);
        return $this->successJson('成功', $data);
    }

    /**
     * 微信app支付
     * @param \Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws AppException
     */
    public function wechatAppPay(\Request $request)
    {
        if (\Setting::get('shop_app.pay.weixin') == false) {
            throw new AppException('商城未开启微信支付');
        }
        $data = $this->pay( PayFactory::PAY_APP_WEACHAT);
        return $this->successJson('成功', $data);
    }

    /**
     * 支付宝app支付
     * @param \Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws AppException
     */
    public function alipayAppRay(\Request $request)
    {
        if (\Setting::get('shop_app.pay.alipay') == false) {
            throw new AppException('商城未开启支付宝支付');
        }
        if ($request->has('uid')) {
            Session::set('member_id', $request->query('uid'));
        }
        $data = $this->pay( PayFactory::PAY_APP_ALIPAY);
        return $this->successJson('成功', $data);
    }

    /**
     * 微信云支付
     * @param \Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws AppException
     */
    public function cloudWechatPay(\Request $request)
    {
        if (\Setting::get('plugin.cloud_pay_set') == false) {
            throw new AppException('商城未开启支付宝支付');
        }

        $data = $this->pay( PayFactory::PAY_CLOUD_WEACHAT);
        return $this->successJson('成功', $data);
    }
}