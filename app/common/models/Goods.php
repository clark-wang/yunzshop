<?php
/**
 * Created by PhpStorm.
 * Author: 芸众商城 www.yunzshop.com
 * Date: 2017/2/22
 * Time: 19:35
 */

namespace app\common\models;

use app\common\exceptions\AppException;
use app\common\models\goods\Discount;
use app\common\models\goods\GoodsDispatch;
use app\common\models\goods\Privilege;
use app\framework\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * Class Goods
 * @package app\common\models
 * @property string status
 * @property string status_name
 * @property string title
 * @property int id
 * @property int stock
 * @property float max_price
 * @property float min_price
 * @property string thumb
 * @property string thumb_url
 * @property int buyNum
 * @property int has_option
 * @property int virtual_sales
 * @property int plugin_id
 * @property int comment_num
 * @property int is_comment
 * @property int is_recommand
 * @property int is_discount
 * @property int is_hot
 * @property int is_new
 * @property int weight
 * @property int real_sales
 * @property int show_sales
 * @property int reduce_stock_method
 * @property int cost_price
 * @property int price
 * @property int market_price
 * @property int product_sn
 * @property string goods_sn
 * @property string content
 * @property string description
 * @property string sku
 * @property int type
 * @property int brand_id
 * @property int goods_video
 * @property int display_order
 * @property Collection hasManySpecs
 * @property Collection hasManyOptions
 * @property GoodsDiscount hasManyGoodsDiscount
 * @property GoodsDispatch hasOneGoodsDispatch
 * @property Privilege hasOnePrivilege
 */
class Goods extends BaseModel
{

    use SoftDeletes;

    public $table = 'yz_goods';
    public $attributes = ['display_order' => 0];
    protected $mediaFields = ['thumb', 'thumb_url'];
    protected $dates = ['deleted_at'];
    protected $appends = ['status_name'];

    public $fillable = [];

    protected $guarded = ['widgets'];

    public $widgets = [];

    protected $search_fields = ['title'];

    static protected $needLog = true;

    /**
     * 实物
     */
    const REAL_GOODS = 1;
    /**
     * 虚拟物品
     */
    const VIRTUAL_GOODS = 2;

    /**
     * 定义字段名
     *
     * @return array
     */
    public function atributeNames()
    {
        return [
            'title' => '商品名称',
            'price' => '价格',
            'cost_price' => '成本价',
            'sku' => '商品单位',
            'thumb' => '图片',
            'weight' => '重量',
            'stock' => '库存',
        ];
    }

    /**
     * 字段规则
     *
     * @return array
     */
    public function rules()
    {
        return [
            'title' => 'required',
            'price' => 'required|numeric|min:0',
            'cost_price' => 'required|numeric|min:0',
            'sku' => 'required',
            'thumb' => 'required',
            'weight' => 'required',
            'stock' => 'required|numeric|min:0',
        ];
    }


    public static function getList()
    {
        return static::uniacid();
    }

    public static function getGoodsById($id)
    {
        return static::find($id);
    }

    public function hasManyParams()
    {
        return $this->hasMany('app\common\models\GoodsParam');
    }

    public function belongsToCategorys()
    {
        return $this->hasMany('app\common\models\GoodsCategory', 'goods_id', 'id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function hasManyGoodsDiscount()
    {
        return $this->hasMany('app\common\models\GoodsDiscount');
    }

    public function hasManyOptions()
    {
        return $this->hasMany('app\common\models\GoodsOption');
    }

    public function hasOneBrand()
    {
        return $this->hasOne('app\common\models\Brand', 'id', 'brand_id');
    }

    public function hasOneShare()
    {
        return $this->hasOne('app\common\models\goods\Share');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     * @throws \app\common\exceptions\ShopException
     */
    public function hasOnePrivilege()
    {
        return $this->hasOne($this->getNearestModel('goods\Privilege'));
    }

    public function hasOneGoodsDispatch()
    {
        return $this->hasOne('app\common\models\goods\GoodsDispatch');
    }

    //该条关联可能出错了不是一对一关系 是一对多
    public function hasOneDiscount()
    {
        return $this->hasOne('app\common\models\goods\Discount');
    }

    public function hasManyDiscount()
    {
        return $this->hasMany(Discount::class, 'goods_id', 'id');
    }

    public function hasManyGoodsCategory()
    {
        return $this->hasMany('app\common\models\GoodsCategory', 'goods_id', 'id');
    }

    public function hasManySpecs()
    {
        return $this->hasMany('app\common\models\GoodsSpec');
    }

    public function hasOneSale()
    {
        return $this->hasOne(app('GoodsManager')->make('GoodsSale'), 'goods_id', 'id');
    }

    public function hasOneGoodsCoupon()
    {
        return $this->hasOne('app\common\models\goods\GoodsCoupon', 'goods_id', 'id');
    }

    public function hasOneGoodsLimitBuy()
    {
        return $this->hasOne('app\common\models\goods\GoodsLimitBuy', 'goods_id', 'id');
    }

    public function hasOneGoodsService()
    {
        return $this->hasOne('app\common\models\goods\GoodsService', 'goods_id', 'id');
    }


    public function hasOneGoodsVideo()
    {
        return $this->hasOne('app\common\models\goods\GoodsVideo', 'goods_id', 'id');
    }

    public function scopeIsPlugin($query)
    {
        return $query->where('is_plugin', 0);
    }

    public function scopeSearch(BaseModel $query, $filters)
    {
        $query->uniacid();

        if (!$filters) {
            return;
        }

        foreach ($filters as $key => $value) {
            switch ($key) {
                /*case 'category':
                    $category[] = ['id' => $value * 1];
                    $query->with("")->where('category_id', $category);
                    break;*/
                //上架商品库存筛选
                case 'sell_stock':
                    if ($value) {
                        $query->where('status', 1)->where('stock', '>', 0);
                    } else {
                        $query->where('status', 1)->where('stock', '=', 0);
                    }
                    break;
                //新加过滤搜索
                case 'filtering':
                    $scope = explode(',', rtrim($value, ','));
                    $query->join('yz_goods_filtering', function ($join) use ($scope) {
                        $join->on('yz_goods_filtering.goods_id', '=', 'yz_goods.id')
                            ->whereIn('yz_goods_filtering.filtering_id', $scope);
                    });
                    break;
                case 'keyword':
                    $query->where('title', 'LIKE', "%{$value}%");
                    break;
                case 'brand_id':
                    $query->where('brand_id', $value);
                    break;
                case 'product_attr':
                    //前端传参是 string 类型，后端传参是 array 类型
                    if (!is_array($value)) {
                        $value = explode(',', rtrim($value, ','));
                    }
                    //$value = explode(',', rtrim($value, ','));
                    foreach ($value as $attr) {
                        if ($attr == 'limit_buy') {
                            $query->whereHas('hasOneGoodsLimitBuy', function (BaseModel $q) {
                                $q->where('status', 1);
                            });
                        } else {
                            $query->where($attr, 1);
                        }
                    }
                    break;
                case 'status':
                    $query->where('status', $value);
                    break;
                case 'min_price':
                    $query->where('price', '>', $value);
                    break;
                case 'max_price':
                    $query->where('price', '<', $value);
                    break;
                case 'category':
                    if (array_key_exists('parentid', $value) || array_key_exists('childid', $value) || array_key_exists('thirdid', $value)) {
                        $id = $value['parentid'][0] ? $value['parentid'][0] : '';
                        $id = $value['childid'][0] ? $value['childid'][0] : $id;
                        $id = $value['thirdid'][0] ? $value['thirdid'][0] : $id;
                        $query->select([
                            'yz_goods.*',
                            'yz_goods_category.id as goods_category_id',
                            'yz_goods_category.goods_id as goods_id',
                            'yz_goods_category.category_id as category_id',
                            'yz_goods_category.category_ids as category_ids'
                        ])->join('yz_goods_category', 'yz_goods_category.goods_id', '=', 'yz_goods.id')->whereRaw('FIND_IN_SET(?,category_ids)', [$id]);
                    } elseif (strpos($value, ',')) {
                        $scope = explode(',', $value);
                        $query->select([
                            'yz_goods.*',
                            'yz_goods_category.id as goods_category_id',
                            'yz_goods_category.goods_id as goods_id',
                            'yz_goods_category.category_id as category_id',
                            'yz_goods_category.category_ids as category_ids'
                        ])->join('yz_goods_category', function ($join) use ($scope) {
                            $join->on('yz_goods_category.goods_id', '=', 'yz_goods.id')
                                ->whereIn('yz_goods_category.category_id', $scope);
                        });
                    } else {
                        $query->select([
                            'yz_goods.*',
                            'yz_goods_category.id as goods_category_id',
                            'yz_goods_category.goods_id as goods_id',
                            'yz_goods_category.category_id as category_id',
                            'yz_goods_category.category_ids as category_ids'
                        ])->join('yz_goods_category', function ($join) use ($value) {
                            $join->on('yz_goods_category.goods_id', '=', 'yz_goods.id')
                                ->whereRaw('FIND_IN_SET(?,category_ids)', [$value]);
//                                ->where('yz_goods_category.category_id', $value);
                        });
                    }
                    break;
                case 'couponid': //搜索指定优惠券适用的商品
                    $res = Coupon::getApplicableScope($value);
                    switch ($res['type']) {
                        case Coupon::COUPON_GOODS_USE: //优惠券适用于指定商品
                            if (is_array($res['scope'])) {
                                $query->whereIn('id', $res['scope']);
                            } else {
                                $query->where('id', $res['scope']);
                            }
                            break;
                        case Coupon::COUPON_CATEGORY_USE: //优惠券适用于指定商品分类
                            if (is_array($res['scope'])) {
                                $query->join('yz_goods_category', function ($join) use ($res) {
                                    $join->on('yz_goods_category.goods_id', '=', 'yz_goods.id')
                                        ->whereIn('yz_goods_category.category_id', $res['scope']);
                                });
                            } else {
                                $query->join('yz_goods_category', function ($join) use ($res) {
                                    $join->on('yz_goods_category.goods_id', '=', 'yz_goods.id')
                                        ->where('yz_goods_category.category_id', $res['scope']);
                                });
                            }
                            break;
                        default: //优惠券适用于整个商城
                            break;
                    }
                    break;
                default:
                    break;
            }
        }
    }

    /**
     * @param $keyword
     * @return mixed
     */
    public static function getGoodsByName($keyword)
    {
        return static::uniacid()->select('id', 'title', 'thumb', 'market_price', 'price', 'real_sales', 'sku','plugin_id','stock')
            ->where('title', 'like', '%' . $keyword . '%')
            ->where('status', 1)
            //->where('is_plugin', 0)
            ->whereNotIn('plugin_id', [20, 31, 60])//屏蔽门店、码上点餐、第三方插件接口的虚拟商品
            ->get();
    }

    /**
     * @param $keyword
     * @return mixed
     */
    public static function getGoodsByNameForLimitBuy($keyword)
    {

        return static::uniacid()->select('id', 'title', 'thumb', 'market_price', 'price', 'real_sales', 'sku','plugin_id','stock')
            ->where('title', 'like', '%' . $keyword . '%')
            ->where('status', 1)
            ->with(['hasOneGoodsLimitBuy' => function (BaseModel $query) {
                 return $query->where('status',1)->select('goods_id', 'start_time', 'end_time');
            }])
            ->whereHas('hasOneGoodsLimitBuy', function (BaseModel $query) {
                return $query->where('status',1);
            })
            ->whereNotIn('plugin_id', [20,31,60])//屏蔽门店、码上点餐、第三方插件接口的虚拟商品
            ->get();
    }

    /**
     * @param $goodsId
     * @return mixed
     */
    public static function updatedComment($goodsId)
    {

        return self::where('id', $goodsId)
            ->update(['comment_num' => DB::raw('`comment_num` + 1')]);
    }

    /**
     * @author shenyang
     * 减库存
     * @param $num
     * @throws AppException
     */
    public function reduceStock($num)
    {
        if ($this->reduce_stock_method != 2) {
            if (!$this->stockEnough($num)) {
                throw new AppException('下单失败,商品:' . $this->title . ' 库存不足');

            }
            $this->stock -= $num;

        }

    }

    /**
     * 库存是否充足
     * @author shenyang
     * @param $num
     * @return bool
     */
    public function stockEnough($num)
    {
        if ($this->reduce_stock_method != 2) {
            if ($this->stock - $num < 0) {
                return false;
            }
        }
        return true;
    }

    /**
     * 增加销量
     * @author shenyang
     * @param $num
     */
    public function addSales($num)
    {
        $this->real_sales += $num;
        $this->show_sales += $num;
    }

    /**
     * 判断实物
     * @author shenyang
     * @return bool
     */
    public function isRealGoods()
    {
        if (!isset($this->type)) {
            return false;
        }
        return $this->type == self::REAL_GOODS;
    }

    /**
     * 推广商品
     * @param $goodsIds
     * @return array
     */
    public static function getPushGoods($goodsIds)
    {
        return self::select('id', 'title', 'thumb', 'price')->whereIn('id', $goodsIds)->where('status', 1)->get()->toArray();
    }

    public static function boot()
    {
        parent::boot();

        static::addGlobalScope(function (BaseModel $builder) {
            $builder->uniacid();
        });
    }

    public static function getGoodsByIdAll($goodsId)
    {
        $model = static::where('id', $goodsId);


        return $model;
    }
    public function getStatusNameAttribute(){

        return [0=>'下架',1=>'上架'][$this->status];
    }

    /**
     * 商品购买验证
     * @param Member $member
     * @param $total
     * @throws AppException
     */
    public function generalValidate(Member $member,$total)
    {
        if (empty($this->status)) {
            throw new AppException('(ID:' . $this->id . ')商品已下架');
        }
//        if (!isset($this->hasOneSale)) {
//            throw new AppException('(ID:' . $this->id . ')商品优惠信息数据已损坏');
//        }
//        if (!isset($this->hasOneGoodsDispatch)) {
//            throw new AppException('(ID:' . $this->id . ')商品配送信息数据已损坏');
//        }
        if (isset($this->hasOnePrivilege)) {
            $this->hasOnePrivilege->validate($member,$total);
        }
    }
    /**
     * 获取商品名称
     * @return html
    */
    public static function getSearchOrder()
    {
        $keyword = \YunShop::request()->keyword;
        return Goods::select(['id','title', 'thumb', 'plugin_id'])->pluginId()->where('title', 'like', '%'.$keyword.'%')->get();
    }
}
