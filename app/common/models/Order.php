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

    /**
     * 时间类型字段
     * @return array
     */
    public function getDates()
    {
        return ['create_time', 'refund_time', 'operate_time', 'send_time', 'return_time', 'end_time', 'pay_time', 'send_time', 'cancel_time', 'create_time', 'cancel_pay_time', 'cancel_send_time', 'finish_time'] + parent::getDates();
    }

    /**
     * 订单状态:待付款
     * @param $query
     * @return mixed
     */
    public function scopeWaitPay($query)
    {
        //AND o.status = 0 and o.paytype<>3
        return $query->where(['status' => self::WAIT_PAY]);
    }

    public function scopeNormal($query)
    {
        return $query->where('refund_id', '0');
    }

    /**
     * 订单状态:待发货
     * @param $query
     * @return mixed
     */
    public function scopeWaitSend($query)
    {
        //AND ( o.status = 1 or (o.status=0 and o.paytype=3) )
        return $query->where(['status' => self::WAIT_SEND]);
    }

    /**
     * 订单状态:待收货
     * @param $query
     * @return mixed
     */
    public function scopeWaitReceive($query)
    {
        return $query->where(['status' => self::WAIT_RECEIVE]);
    }

    /**
     * 订单状态:完成
     * @param $query
     * @return mixed
     */
    public function scopeCompleted($query)
    {
        return $query->where(['status' => self::COMPLETE]);
    }

    /**
     * 订单状态:退款中
     * @param $query
     * @return mixed
     */
    public function scopeRefund($query)
    {
        return $query->where('refund_id', '>', '0')->whereHas('hasOneRefundApply', function ($query) {
            return $query->refunding();
        });

    }

    /**
     * 订单状态:已退款
     * @param $query
     * @return mixed
     */
    public function scopeRefunded($query)
    {
        return $query->where('refund_id', '>', '0')->whereHas('hasOneRefundApply', function ($query) {
            return $query->refunded();
        });
    }

    /**
     * 订单状态:取消
     * @param $query
     * @return mixed
     */
    public function scopeCancelled($query)
    {
        return $query->where(['status' => self::CLOSE]);
    }

    /**
     * 关联模型 1对多:订单商品
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function hasManyOrderGoods()
    {
        return $this->hasMany(self::getNearestModel('OrderGoods'), 'order_id', 'id');
    }

    /**
     * 关联模型 1对多:改价记录
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function orderChangePriceLogs()
    {
        return $this->hasMany(OrderChangePriceLog::class, 'order_id', 'id');
    }

    /**
     * 关联模型 1对1:购买者
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function belongsToMember()
    {
        return $this->belongsTo(Member::class, 'uid', 'uid');
    }

    /**
     * 关联模型 1对1:退款列表
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function hasOneRefundApply()
    {
        return $this->hasOne(RefundApply::class, 'id', 'refund_id')->orderBy('created_at','desc');

    }

    /**
     * 关联模型 1对1:订单配送方式
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function hasOneDispatchType()
    {
        return $this->hasOne(DispatchType::class, 'id', 'dispatch_type_id');
    }

    /**
     * 关联模型 1对1:订单备注
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function hasOneOrderRemark()
    {
        return $this->hasOne(Remark::class, 'order_id', 'id');
    }

    /**
     * 关联模型 1对1:支付方式
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function hasOnePayType()
    {
        return $this->hasOne(PayType::class, 'id', 'pay_type_id');
    }

    /**
     * 关联模型 1对1:订单支付信息
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function hasOneOrderPay()
    {
        return $this->belongsTo(Pay::class, 'order_pay_id', 'id');
    }

    /**
     * 关联模型 1对1:订单快递
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function express()
    {
        return $this->hasOne(Express::class, 'order_id', 'id');
    }

    /**
     * 对应每个订单状态的状态类,过于啰嗦,考虑删除
     * @return \app\frontend\modules\order\services\status\Complete|\app\frontend\modules\order\services\status\WaitPay|\app\frontend\modules\order\services\status\WaitReceive|\app\frontend\modules\order\services\status\WaitSend
     */
    public function getStatusService()
    {
        if (!isset($this->StatusService)) {
            $this->StatusService = StatusServiceFactory::createStatusService($this);
        }
        return $this->StatusService;
    }

    /**
     * 关联模型 1对1:收货地址
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function address()
    {
        return $this->hasOne(Address::class, 'order_id', 'id');
    }

    /**
     * 关联模型 1对1:订单支付信息
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function hasOnePay()
    {
        return $this->hasOne(Pay::class, 'order_id', 'id');
    }

    /**
     * 订单状态汉字
     * @return string
     */
    public function getStatusNameAttribute()
    {
        return $this->getStatusService()->getStatusName();
    }

    /**
     * 支付类型汉字
     * @return string
     */
    public function getPayTypeNameAttribute()
    {
        if ($this->status == self::WAIT_PAY) {
            return PayType::defaultTypeName();
        }
        return $this->hasOnePayType->name;
    }

    /**
     * 订单可点的按钮
     * @return array
     */
    public function getButtonModelsAttribute()
    {
        $result = $this->getStatusService()->getButtonModels();

        return $result;
    }

    /**
     * 按状态分组获取订单数量
     * @param $query
     * @param array $status
     * @return array
     */
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

    /**
     * 区分订单属于插件或商城,考虑使用新添加的scopePluginId方法替代
     * @param $query
     * @return mixed
     */
    public function scopeIsPlugin($query)
    {
        return $query->where('is_plugin', 0);
    }

    /**
     * 用来区分订单属于哪个.当插件需要查询自己的订单时,复写此方法
     * @param $query
     * @param int $pluginId
     * @return mixed
     */
    public function scopePluginId($query, $pluginId = 0)
    {
        return $query->where('plugin_id', $pluginId);
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

    /**
     * 关系链 指定商品
     *
     * @param $uid
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public static function getOrderListByUid($uid)
    {
        return self::select(['*'])
            ->where('uid', $uid)
            ->where('status', '>=', 1)
            ->where('status', '<=', 3)
            ->with(['hasManyOrderGoods' => function ($query) {
                return $query->select(['*']);
            }])
            ->get();
    }

    public function isVirtual()
    {
        return $this->is_virtual == 1;
    }

    /**
     * 初始化方法
     */
    public static function boot()
    {
        parent::boot();
        static::observe(new OrderObserver());
        // 添加了公众号id的全局条件.
        static::addGlobalScope(function (Builder $builder) {
            $builder->uniacid();
        });
    }
}