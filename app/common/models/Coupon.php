<?php

namespace app\common\models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class  Coupon extends BaseModel
{
    use SoftDeletes;
    protected $dates = ['deleted_at','time_start','time_end'];

    const COUPON_ALL_USE = 0; //适用范围 - 商城通用
    const COUPON_CATEGORY_USE = 1; //适用范围 - 指定分类
    const COUPON_GOODS_USE = 2; //适用范围 - 指定商品
    const COUPON_MONEY_OFF = 1; //优惠方式- 立减
    const COUPON_DISCOUNT = 2; //优惠方式- 折扣
    const COUPON_DATE_TIME_RANGE = 1;//有效期 - 时间范围
    const COUPON_SINCE_RECEIVE = 0;//有效期 - 领取后n天

    public $table = 'yz_coupon';

    protected $guarded = [];

    protected $casts = [
        'goods_ids' => 'json',
        'category_ids' => 'json',
        'goods_names' => 'json',
        'categorynames' => 'json',
    ];

    public static function getMemberCoupon($used = 0) { //todo 这张表没有used这个字段, 应该放在member_coupon表?
        return static::uniacid()->where('used', $used);
    }

    public function hasManyMemberCoupon()
    {
        return $this->hasMany('app\common\models\MemberCoupon');
    }

    public static function getValidCoupon($MemberModel)
    {
        return MemberCoupon::getMemberCoupon($MemberModel);
    }

    public static function getUsageCount($couponId)
    {
        return static::uniacid()
                    ->select(['id'])
                    ->where('id', '=', $couponId)
                    ->withCount(['hasManyMemberCoupon' => function($query){
                        return $query->where('used', '=', 0);
                    }]);
    }

    public static function getCouponById($couponId)
    {
        return static::uniacid()
                    ->where('id', '=', $couponId);
    }

    //getter
    public static function getter($couponId, $attribute)
    {
        return static::uniacid()
            ->where('id', '=', $couponId)
            ->value($attribute);
    }
}
