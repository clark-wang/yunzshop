<?php
/**
 * Created by PhpStorm.
 * Author: 芸众商城 www.yunzshop.com
 * Date: 2017/2/28
 * Time: 上午11:32
 */

namespace app\common\models;


use app\common\models\order\Address;
use app\common\models\order\Express;
use app\common\models\order\OrderChangePriceLog;
use app\common\models\order\Pay;
use app\common\models\order\Remark;
use app\common\models\refund\RefundApply;
use app\frontend\modules\order\services\status\StatusServiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use app\backend\modules\order\observers\OrderObserver;

class Order extends BaseModel
{
    public $table = 'yz_order';
    private $StatusService;
    protected $fillable = [];
    protected $guarded = ['id'];
    protected $appends = ['status_name', 'pay_type_name'];
    protected $search_fields = ['id', 'order_sn'];
    //protected $attributes = ['discount_price'=>0];
    const CLOSE = -1;
    const WAIT_PAY = 0;
    const WAIT_SEND = 1;
    const WAIT_RECEIVE = 2;
    const COMPLETE = 3;
    const REFUND = 11;

    public function getDates()
    {
        return ['create_time', 'refund_time', 'operate_time', 'send_time', 'return_time', 'end_time', 'pay_time', 'send_time', 'cancel_time', 'create_time', 'cancel_pay_time', 'cancel_send_time', 'finish_time'] + parent::getDates();
    }

    public function scopeWaitPay($query)
    {
        //AND o.status = 0 and o.paytype<>3
        return $query->where(['status' => self::WAIT_PAY]);
    }

    public function scopeWaitSend($query)
    {
        //AND ( o.status = 1 or (o.status=0 and o.paytype=3) )
        return $query->where(['status' => self::WAIT_SEND]);
    }

    public function scopeWaitReceive($query)
    {
        return $query->where(['status' => self::WAIT_RECEIVE]);
    }

    public function scopeCompleted($query)
    {
        return $query->where(['status' => self::COMPLETE]);
    }

    public function scopeRefund($query)
    {
        return $query->where('refund_id', '>', '0')->whereHas('hasOneRefundApply', function ($query) {
            return $query->refunding();
        });

    }

    public function scopeRefunded($query)
    {
        return $query->where('refund_id', '>', '0')->whereHas('hasOneRefundApply', function ($query) {
            return $query->refunded();
        });
    }

    public function scopeCancelled($query)
    {
        return $query->where(['status' => self::CLOSE]);
    }

    public function hasManyOrderGoods()
    {
        return $this->hasMany(self::getNearestModel('OrderGoods'), 'order_id', 'id');
    }

    public function orderChangePriceLogs()
    {
        return $this->hasMany(OrderChangePriceLog::class, 'order_id', 'id');
    }

    public function belongsToMember()
    {
        return $this->belongsTo(Member::class, 'uid', 'uid');
    }

    //退款列表
    public function hasOneRefundApply()
    {
        return $this->hasOne(RefundApply::class, 'id', 'refund_id');

    }

    //订单配送方式
    public function hasOneDispatchType()
    {
        return $this->hasOne(DispatchType::class, 'id', 'dispatch_type_id');
    }

    //订单备注
    public function hasOneOrderRemark()
    {
        return $this->hasOne(Remark::class, 'order_id', 'id');
    }

    //支付方式
    public function hasOnePayType()
    {
        return $this->hasOne(PayType::class, 'id', 'pay_type_id');
    }

    //订单支付信息
    public function hasOneOrderPay()
    {
        return $this->belongsTo(Pay::class, 'order_pay_id', 'id');
    }

    //订单快递
    public function express()
    {
        return $this->hasOne(Express::class, 'order_id', 'id');
    }

    public function getStatusService()
    {
        if (!isset($this->StatusService)) {
            $this->StatusService = StatusServiceFactory::createStatusService($this);
        }
        return $this->StatusService;
    }

    //收货地址
    public function address()
    {
        return $this->hasOne(Address::class, 'order_id', 'id');
    }

    //订单支付
    public function hasOnePay()
    {
        return $this->hasOne(Pay::class, 'order_id', 'id');
    }

    public function getStatusNameAttribute()
    {
        return $this->getStatusService()->getStatusName();
    }

    public function getPayTypeNameAttribute()
    {
        if ($this->status == self::WAIT_PAY) {
            return PayType::defaultTypeName();
        }
        return $this->hasOnePayType->name;
    }

    public function getButtonModelsAttribute()
    {
        $result = $this->getStatusService()->getButtonModels();

        return $result;
    }

    public function scopeGetOrderCountGroupByStatus($query, $status = [])
    {
        //$status = [Order::WAIT_PAY, Order::WAIT_SEND, Order::WAIT_RECEIVE, Order::COMPLETE, Order::REFUND];
        $status_counts = $query->select('status', DB::raw('count(*) as total'))
            ->whereIn('status', $status)->groupBy('status')->get()->makeHidden(['status_name', 'pay_type_name', 'has_one_pay_type', 'button_models'])->toArray();
        if (in_array(Order::REFUND, $status)) {
            $refund_count = $query->refund()->count();
            $status_counts[] = ['status' => Order::REFUND, 'total' => $refund_count];
        }
        foreach ($status as $state) {
            if (!in_array($state, array_column($status_counts, 'status'))) {
                $status_counts[] = ['status' => $state, 'total' => 0];
            }
        }
        return $status_counts;
    }

    public function scopeIsPlugin($query)
    {
        return $query->where('is_plugin', 0);
    }

    /**
     * 通过会员ID获取订单信息
     *
     * @param $member_id
     */
    public static function getOrderInfoByMemberId($member_id, $status)
    {
        return self::where('uid', $member_id)->isComment($status);
    }

    public static function boot()
    {
        parent::boot();
        static::observe(new OrderObserver());

        static::addGlobalScope(function (Builder $builder) {
            $builder->uniacid();
        });
    }
}