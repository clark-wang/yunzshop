<?php
/**
 * Created by PhpStorm.
 * User: yanglei
 * Date: 2017/2/28
 * Time: 上午11:32
 */

namespace app\common\models;


use app\common\models\order\Pay;
use app\frontend\modules\order\services\behavior\OrderPay;
use app\frontend\modules\order\services\status\StatusServiceFactory;
use Illuminate\Support\Facades\DB;
use app\backend\modules\order\observers\OrderObserver;

class Order extends BaseModel
{
    public $table = 'yz_order';
    private $StatusService;
    protected $fillable = [];
    protected $guarded = ['id'];
    protected $appends = ['status_name','pay_type_name', 'button_models'];
    protected $search_fields = ['id', 'order_sn'];
    protected $attributes = ['discount_price'=>0];
    const CLOSE = -1;
    const WAIT_PAY = 0;
    const WAIT_SEND = 1;
    const WAIT_RECEIVE = 2;
    const COMPLETE = 3;

    public function scopeWaitPay($query)
    {
        //AND o.status = 0 and o.paytype<>3
        return $query->where(['status' => 0]);
    }

    public function scopeWaitSend($query)
    {
        //AND ( o.status = 1 or (o.status=0 and o.paytype=3) )
        return $query->where(['status' => 1]);
    }

    public function scopeWaitReceive($query)
    {
        return $query->where(['status' => 2]);
    }

    public function scopeCompleted($query)
    {
        return $query->where(['status' => 3]);
    }

    public function scopeCancelled($query)
    {
        return $query->where(['status' => -1]);
    }

    public function hasManyOrderGoods()
    {
        return $this->hasMany('\app\common\models\OrderGoods', 'order_id', 'id');
    }

    public function belongsToMember()
    {
        return $this->belongsTo('\app\common\models\Member', 'uid', 'uid');
    }

    //订单配送方式
    public function hasOneDispatchType()
    {
        return $this->hasOne('\app\common\models\DispatchType', 'id', 'dispatch_type_id');
    }

    //订单备注
    public function hasOneOrderRemark()
    {
        return $this->hasOne('\app\common\models\order\Remark', 'order_id', 'id');
    }

    public function hasOnePayType()
    {
        return $this->hasOne('\app\common\models\PayType', 'id', 'pay_type_id');
    }

    //订单支付信息
    public function hasOneOrderPay()
    {
        return $this->hasOne(Pay::class, 'order_id', 'id');
    }

    //订单快递
    public function hasOneOrderExpress()
    {
        return $this->hasOne('\app\common\models\order\Express', 'order_id', 'id');
    }

    public function scopeUn($query)
    {
        return $query->where(['uniacid' => 1]);
    }

    public function getStatusService()
    {
        if (!isset($this->StatusService)) {
            $this->StatusService = StatusServiceFactory::createStatusService($this);
        }
        return $this->StatusService;
    }

    //收货地址
    public function hasOneAddress()
    {
        return $this->hasOne('\app\common\models\order\Address', 'order_id', 'id');
    }

    //订单支付
    public function hasOnePay()
    {
        return $this->hasOne('\app\common\models\order\Pay', 'order_id', 'id');
    }

    public function getStatusNameAttribute()
    {
        return $this->getStatusService()->getStatusName();
    }

    public function getPayTypeNameAttribute()
    {
        return $this->hasOnePayType->name;
    }

    public function getButtonModelsAttribute()
    {
        return $this->getStatusService()->getButtonModels();
    }

    public function scopeGetOrderCountGroupByStatus($query, $status = [])
    {
        //$status = [Order::WAIT_PAY, Order::WAIT_SEND, Order::WAIT_RECEIVE, Order::COMPLETE];
        $status_counts = $query->select('status', DB::raw('count(*) as total'))
            ->whereIn('status', $status)->groupBy('status')->get()->makeHidden(['status_name', 'button_models'])->toArray();
        foreach ($status as $state) {
            if (!in_array($state, array_column($status_counts, 'status'))) {
                $status_counts[] = ['status' => $state, 'total' => 0];
            }
        }
        return $status_counts;
    }

    public static function boot()
    {
        parent::boot();
        static::observe(new OrderObserver());
    }

    /**
     * 通过会员ID获取订单信息
     *
     * @param $member_id
     */
    public static function getOrderInfoByMemberId($member_id)
    {
        return self::uniacid()
                   ->where('uid', $member_id);
    }
}