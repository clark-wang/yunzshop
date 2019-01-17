<?php

namespace app\common\providers;



//use app\backend\modules\charts\listeners\Statistics;
use app\backend\modules\charts\modules\member\listeners\MemberLowerListener;
use app\backend\modules\charts\listeners\OrderStatistics;
use app\backend\modules\charts\modules\phone\listeners\PhoneAttribution;
use app\backend\modules\goods\listeners\LimitBuy;
use app\common\events\member\MemberCreateRelationEvent;
use app\common\events\message\SendMessageEvent;
use app\common\events\order\AfterOrderCreatedEvent;
use app\common\events\order\AfterOrderCreatedImmediatelyEvent;

use app\common\events\PayLog;
use app\common\events\WechatProcessor;
use app\common\listeners\charts\OrderBonusListeners;
use app\common\listeners\member\MemberCreateRelationEventListener;
use app\common\listeners\PayLogListener;
use app\common\listeners\point\PointListener;
use app\common\listeners\WechatProcessorListener;
use app\common\listeners\withdraw\WithdrawAuditListener;
use app\common\listeners\withdraw\WithdrawPayListener;
use app\common\listeners\withdraw\WithdrawSuccessListener;
use app\common\modules\coupon\events\AfterMemberReceivedCoupon;
use app\common\modules\coupon\listeners\AfterMemberReceivedCouponListener;
use app\common\modules\payType\events\AfterOrderPayTypeChangedEvent;
use app\common\modules\payType\remittance\listeners\AfterOrderPayTypeChangedListener;
use app\common\modules\process\events\AfterProcessStateChangedEvent;
use app\common\modules\process\events\AfterProcessStatusChangedEvent;
use app\common\modules\process\StateContainer;
use app\common\modules\status\StatusContainer;
use app\frontend\modules\coupon\listeners\CouponExpireNotice;
use app\frontend\modules\coupon\listeners\CouponSend;
use app\frontend\modules\finance\listeners\IncomeWithdraw;
use app\frontend\modules\goods\listeners\GoodsStock;
use app\frontend\modules\member\listeners\MemberLevelValidity;
use app\frontend\modules\order\listeners\orderListener;
use app\frontend\modules\withdraw\listeners\WithdrawApplyListener;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        \app\common\events\dispatch\OrderDispatchWasCalculated::class => [
            //订单邮费计算
            \app\frontend\modules\dispatch\listeners\prices\UnifyOrderDispatchPrice::class, //统一运费
            \app\frontend\modules\dispatch\listeners\prices\TemplateOrderDispatchPrice::class, //模板运费

        ],

        PayLog::class => [ //支付日志请求
            PayLogListener::class //保存支付参数
        ],
        \app\common\events\member\BecomeAgent::class => [ //会员成为下线
            \app\common\listeners\member\BecomeAgentListener::class
        ],
        AfterOrderCreatedEvent::class => [ //下单成功后调用会员成为下线事件
            \app\common\listeners\member\AfterOrderCreatedListener::class,
        ],

        AfterOrderCreatedImmediatelyEvent::class => [
            \app\frontend\modules\member\listeners\Order::class, //清空购物车

        ],
        /*AfterOrderReceivedEvent::class => [ //确认收货
            \app\common\listeners\member\AfterOrderReceivedListener::class
        ],*/
//        AfterOrderPaidEvent::class => [ //支付完成
//            \app\common\listeners\member\AfterOrderPaidListener::class,
//        ],
        //微信接口回调触发事件进程
        WechatProcessor::class => [
            WechatProcessorListener::class//示例监听类
        ],

        //发送消息触发事件
        SendMessageEvent::class => [

        ],
        AfterProcessStatusChangedEvent::class => [
            StatusContainer::class,
        ],
        AfterProcessStateChangedEvent::class => [
            StateContainer::class,
        ],
        AfterOrderPayTypeChangedEvent::class=>[
            AfterOrderPayTypeChangedListener::class
        ],
        MemberCreateRelationEvent::class=>[
            MemberCreateRelationEventListener::class
        ],
        AfterMemberReceivedCoupon::class=>[
            AfterMemberReceivedCouponListener::class
        ]
    ];
    /**
     * 注册监听者类
     * @var array
     */
    protected $subscribe = [

        /**
         * 收入提现监听者类
         */
        WithdrawApplyListener::class,
        WithdrawAuditListener::class,
        WithdrawPayListener::class,
        WithdrawSuccessListener::class,

        \app\common\listeners\MessageListener::class,

        //会员等级升级
        \app\common\listeners\member\level\LevelListener::class,
        \app\common\listeners\balance\BalanceListener::class,


        \app\frontend\modules\coupon\listeners\CouponDiscount::class,
        PointListener::class,
        GoodsStock::class,
        \app\frontend\modules\payment\listeners\Alipay::class,
        \app\frontend\modules\payment\listeners\Credit::class,
        \app\frontend\modules\payment\listeners\Wechat::class,
        \app\frontend\modules\payment\listeners\CloudPay::class,
        \app\frontend\modules\payment\listeners\Wechat_App::class,
        \app\frontend\modules\payment\listeners\Alipay_App::class,
        \app\frontend\modules\payment\listeners\YunPay::class,
        \app\frontend\modules\payment\listeners\Cloud_Alipay::class,
        \app\frontend\modules\payment\listeners\Yun_Alipay::class,
        \app\frontend\modules\payment\listeners\HuanxunPay::class,
        \app\frontend\modules\payment\listeners\EupPayListener::class,
        \app\frontend\modules\payment\listeners\PldPayListener::class,
        \app\frontend\modules\payment\listeners\WftPay::class,
        \app\frontend\modules\payment\listeners\WftAlipayListener::class,
        \app\frontend\modules\payment\listeners\HuanxunWxPay::class,
        \app\frontend\modules\payment\listeners\DianbangScan::class,

        orderListener::class,
        IncomeWithdraw::class,
        CouponExpireNotice::class,
        CouponSend::class,
        MemberLevelValidity::class,
        LimitBuy::class,
        OrderStatistics::class,
        PhoneAttribution::class,
        OrderBonusListeners::class,
        MemberLowerListener::class,
        //商品定时上下架
        \app\backend\modules\goods\listeners\GoodsServiceListener::class,
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();

        //
    }
}
