<?php
namespace app\frontend\modules\goods\controllers;

use app\backend\modules\goods\models\Brand;
use app\common\components\ApiController;
use app\common\exceptions\AppException;
use app\common\facades\Setting;
use app\common\models\Category;
use app\common\models\goods\Privilege;
use app\frontend\models\Member;
use app\frontend\modules\goods\models\Goods;
use app\common\models\GoodsSpecItem;
use app\common\services\goods\SaleGoods;
use app\common\services\goods\VideoDemandCourseGoods;
use app\common\models\MemberShopInfo;
use Illuminate\Support\Facades\DB;
use Yunshop\Commission\Common\Services\GoodsDetailService;
use Yunshop\TeamDividend\Common\Services\TeamDividendGoodsDetailService;
use Yunshop\Commission\models\Agents;
use Yunshop\Love\Common\Models\GoodsLove;
use app\frontend\modules\coupon\models\Coupon;
use app\frontend\modules\coupon\controllers\MemberCouponController;
use app\common\services\goods\LeaseToyGoods;
use Yunshop\Supplier\common\models\SupplierGoods;
use Yunshop\TeamDividend\models\TeamDividendAgencyModel;
use app\common\models\MemberLevel;
use app\common\models\MemberGroup;

/**
 * Created by PhpStorm.
 * Author: 芸众商城 www.yunzshop.com
 * Date: 2017/3/3
 * Time: 22:16
 */
class GoodsController extends ApiController
{
    protected $publicAction = ['getRecommendGoods'];
    protected $ignoreAction = ['getRecommendGoods'];

    // 拆分getGoods方法，分离和插件相关的部分，只提取属于商品的信息。和插件相关的部分在getGoods中处理
    protected function _getGoods($id, $integrated = null)
    {
        $member = Member::current()->yzMember;

        $goodsModel = Goods::uniacid()
            ->with([
                'hasManyParams' => function ($query) {
                    return $query->select('goods_id', 'title', 'value')->orderby('displayorder','asc');
                },
                'hasManySpecs' => function ($query) {
                    return $query->select('id', 'goods_id', 'title', 'description');
                },
                'hasManyOptions' => function ($query) {
                    return $query->select('id', 'goods_id', 'title', 'thumb', 'product_price', 'market_price', 'stock', 'specs', 'weight');
                },
                'hasManyDiscount' => function ($query) use ($member) {
                    return $query->where('level_id', $member->level_id);
                },
                'hasOneBrand' => function ($query) {
                    return $query->select('id', 'logo', 'name', 'desc');
                },
                'hasOneShare',
                'hasOneGoodsDispatch',
                'hasOnePrivilege',
                'hasOneSale',
                'hasOneGoodsCoupon',
                'hasOneInvitePage',
                'hasOneGoodsLimitBuy',
                'hasOneGoodsVideo',
            ])
            ->find($id);

        if (!$goodsModel) {
            if(is_null($integrated)){
                return $this->errorJson('商品不存在.');
            }else{
                return show_json(0,'商品不存在.');
            }
        }

        //限时购 todo 后期优化 应该是前端优化
        $current_time = time();
        if (!is_null($goodsModel->hasOneGoodsLimitBuy)) {
            if ($goodsModel->hasOneGoodsLimitBuy->end_time < $current_time && $goodsModel->hasOneGoodsLimitBuy->status == 1) {
                $goodsModel->status = 0;
                $goodsModel->save();
            }
        }

        if (!$goodsModel->status) {
            if(is_null($integrated)){
                return $this->errorJson('商品已下架.');
            }else{
                return show_json(0,'商品已下架.');
            }
        }


        $goodsModel->is_added = \ Setting::get('shop.member.added') ?: 1;


        //验证浏览权限
        $this->validatePrivilege($goodsModel, $member);

        //商品品牌处理
        if ($goodsModel->hasOneBrand) {
            $goodsModel->hasOneBrand->desc = html_entity_decode($goodsModel->hasOneBrand->desc);
            $goodsModel->hasOneBrand->logo = yz_tomedia($goodsModel->hasOneBrand->logo);
        }


        //商品规格图片处理
        if ($goodsModel->hasManyOptions && $goodsModel->hasManyOptions->toArray()) {
            foreach ($goodsModel->hasManyOptions as &$item) {
                $item->thumb = replace_yunshop(yz_tomedia($item->thumb));
            }
        }
        $goodsModel->content = html_entity_decode($goodsModel->content);
        if ($goodsModel->has_option) {
            $goodsModel->min_price = $goodsModel->hasManyOptions->min("product_price");
            $goodsModel->max_price = $goodsModel->hasManyOptions->max("product_price");
            $goodsModel->stock = $goodsModel->hasManyOptions->sum('stock');
        }
        foreach ($goodsModel->hasManySpecs as &$spec) {
            $spec['specitem'] = GoodsSpecItem::select('id', 'title', 'specid', 'thumb')->where('specid', $spec['id'])->get();
            foreach ($spec['specitem'] as &$specitem) {
                $specitem['thumb'] = yz_tomedia($specitem['thumb']);
            }
        }

        $goodsModel->setHidden(
            [
                'deleted_at',
                'created_at',
                'updated_at',
                'cost_price',
                'real_sales',
                'is_deleted',
                'reduce_stock_method',
            ]);

        //商品图片处理
        if ($goodsModel->thumb) {
            $goodsModel->thumb = yz_tomedia($goodsModel->thumb);
        }
        if ($goodsModel->thumb_url) {
            $thumb_url = unserialize($goodsModel->thumb_url);
            foreach ($thumb_url as &$item) {
                $item = yz_tomedia($item);
            }
            $goodsModel->thumb_url = $thumb_url;
        }

        //商品视频处理
        if (!is_null($goodsModel->hasOneGoodsVideo) && $goodsModel->hasOneGoodsVideo->goods_video) {
            $goodsModel->goods_video = yz_tomedia($goodsModel->hasOneGoodsVideo->goods_video);
            $goodsModel->video_image = $goodsModel->hasOneGoodsVideo->video_image ? yz_tomedia($goodsModel->hasOneGoodsVideo->video_image) : yz_tomedia($goodsModel->thumb);
        } else {
            $goodsModel->goods_video = '';
            $goodsModel->video_image = '';
        }

        //商品营销 todo 优化新的
        $goodsModel->goods_sale = $this->getGoodsSaleV2($goodsModel, $member);
        $goodsModel->love_shoppin_gift = $this->loveShoppingGift($goodsModel);

        //商品会员优惠
        $goodsModel->member_discount = $this->getDiscount($goodsModel, $member);

        //商品是否开启领优惠卷
        $goodsModel->availability = $this->couponsMemberLj($member);

        // 商品详情挂件
        if (\Config::get('goods_detail')) {
            foreach (\Config::get('goods_detail') as $key_name => $row) {
                $row_res = $row['class']::{$row['function']}($id, true);
                if ($row_res) {
                    $goodsModel->$key_name = $row_res;
                    //供应商在售商品总数
                    $class = new $row['class']();
                    if (method_exists($class, 'getGoodsIdsBySid')) {
                        $supplier_goods_id = SupplierGoods::getGoodsIdsBySid($goodsModel->supplier->id);
                        $supplier_goods_count = Goods::select('id')
                            ->whereIn('id', $supplier_goods_id)
                            ->where('status', 1)
                            ->count();
                        $goodsModel->supplier_goods_count = $supplier_goods_count;
                    }
                }
            }
        }


        if ($goodsModel->hasOneShare) {
            $goodsModel->hasOneShare->share_thumb = yz_tomedia($goodsModel->hasOneShare->share_thumb);
        }
        /*
        //设置商品相关插件信息
        $this->setGoodsPluginsRelations($goodsModel);
        */
        //该商品下的推广
        $goodsModel->show_push = SaleGoods::getPushGoods($id);
        //销量等于虚拟销量加真实销量
//        $goodsModel->show_sales += $goodsModel->virtual_sales;

        return $goodsModel;
    }

    public function getGoods($request, $integrated = null)
    {
        $id = intval(\YunShop::request()->id);
        if (!$id) {
            if(is_null($integrated)){
                return $this->errorJson('请传入正确参数.');
            }else{
                return show_json(0,'请传入正确参数.');
            }

        }

        $goodsModel = $this->_getGoods($id);
        //设置商品相关插件信息
        $this->setGoodsPluginsRelations($goodsModel);
        //默认供应商店铺名称
        if ($goodsModel->supplier->store_name == 'null') {
            $goodsModel->supplier->store_name = $goodsModel->supplier->user_name;
        }

        //判断该商品是否是视频插件商品
        $videoDemand = new VideoDemandCourseGoods();
        $goodsModel->is_course = $videoDemand->isCourse($id);

        //商城租赁
        //TODO 租赁插件是否开启 $lease_switch
        $lease_switch = LeaseToyGoods::whetherEnabled();
        $this->goods_lease_set($goodsModel, $lease_switch);

        //判断是否酒店商品
        $goodsModel->is_hotel = $goodsModel->plugin_id == 33 ? 1 : 0;
        $goodsModel->is_store = $goodsModel->plugin_id == 32 ? 1 :0;

        if(is_null($integrated)){
            return $this->successJson('成功', $goodsModel);
        }else{
            return show_json(1,$goodsModel);
        }

    }

    public function getGoodsPage($request)
    {
        $this->dataIntegrated($this->getGoods($request, true),'get_goods');
        $storeId = $this->apiData['get_goods']->store_goods->store_id;
        if($storeId){
            if(class_exists('\Yunshop\StoreCashier\frontend\store\GetStoreInfoController')){
                $this->dataIntegrated(\Yunshop\StoreCashier\frontend\store\GetStoreInfoController::getInfobyStoreId($request, true,$storeId),'get_store_Info');
                $this->dataIntegrated(\Yunshop\StoreCashier\frontend\shoppingCart\MemberCartController::index($request,true,$storeId),'member_cart');
            }else{
                return $this->errorJson('门店插件未开启');
            }
        }
        if($this->apiData['get_goods']->is_hotel){
            if(class_exists('\Yunshop\Hotel\frontend\hotel\GoodsController')){
                $this->dataIntegrated(\Yunshop\Hotel\frontend\hotel\GoodsController::getGoodsDetailByGoodsId($request,true),'get_hotel_info');
            }else{
                return $this->errorJson('酒店插件未开启');
            }
        }
        $this->dataIntegrated(\app\frontend\controllers\HomePageController::wxJsSdkConfig(),'wx_js_sdk_config');
        $this->dataIntegrated(\app\frontend\modules\member\controllers\MemberHistoryController::store($request, true),'store');
        $this->dataIntegrated(\app\frontend\modules\member\controllers\MemberFavoriteController::isFavorite($request, true),'is_favorite');
        return $this->successJson('', $this->apiData);
    }

    public function getGoodsType($request, $integrated = null)
    {
        $goods_type = 'goods';//通用
        $id = intval(\YunShop::request()->id);
        if (!$id) {
            if(is_null($integrated)){
                return $this->errorJson('请传入正确参数.');
            }else{
                return show_json(0,'请传入正确参数.');
            }

        }

        $goodsModel = Goods::uniacid()->find($id);

        $data['title'] = $goodsModel->title;
        // 商品详情挂件
        if (\Config::get('goods_detail')) {
            foreach (\Config::get('goods_detail') as $key_name => $row) {
                $row_res = $row['class']::{$row['function']}($id, true);
                if ($row_res) {
                    $goodsModel->$key_name = $row_res;
                }
            }
        }
        //判断该商品是否是视频插件商品
        $isCourse = (new VideoDemandCourseGoods())->isCourse($id);
        if ($isCourse) {
            $goods_type = 'course';
        }
        //判断是否酒店商品
        if ($goodsModel->plugin_id == 33) {
            $goods_type = 'hotelGoods';
        }

        //门店商品
        if ($goodsModel->plugin_id == 32 || $goodsModel->store_goods) {
            $goods_type = 'store_goods';
            $store_id = $goodsModel->store_goods->store_id;
            $data['store_id'] = $store_id;
        }


        //供应商商品
        if ($goodsModel->plugin_id == 92 || $goodsModel->supplier) {
            $goods_type = 'supplierGoods';
        }

        $data['goods_type'] = $goods_type;

        if(is_null($integrated)){
            return $this->successJson('成功', $data);
        }else{
            return show_json(1,$data);
        }
    }

    /**
     * @param $goodsModel
     * @param $member
     * @throws \app\common\exceptions\AppException
     */
    public function validatePrivilege($goodsModel, $member)
    {
        Privilege::validatePrivilegeLevel($goodsModel, $member);
        Privilege::validatePrivilegeGroup($goodsModel, $member);
    }

    private function setGoodsPluginsRelations($goods)
    {
        $goodsRelations = app('GoodsManager')->tagged('GoodsRelations');
        collect($goodsRelations)->each(function ($goodsRelation) use ($goods) {
            $goodsRelation->setGoods($goods);
        });
    }

    public function searchGoods()
    {
        $requestSearch = \YunShop::request()->search;

        $order_field = \YunShop::request()->order_field;
        if (!in_array($order_field, ['price', 'show_sales', 'comment_num'])) {
            $order_field = 'display_order';
        }
        $order_by = (\YunShop::request()->order_by == 'asc') ? 'asc' : 'desc';

        if ($requestSearch) {
            $requestSearch = array_filter($requestSearch, function ($item) {
                return !empty($item) && $item !== 0 && $item !== "undefined";
            });

            $categorySearch = array_filter(\YunShop::request()->category, function ($item) {
                return !empty($item);
            });

            if ($categorySearch) {
                $requestSearch['category'] = $categorySearch;
            }
        }

//        $list = Goods::Search($requestSearch)->select( '*','yz_goods.id')
//            ->where("status", 1)
//            ->where(function($query) {
//                $query->where("plugin_id", 0)->orWhere('plugin_id', 40)->orWhere('plugin_id', 92);
//            })->groupBy('yz_goods.id')
//            ->orderBy($order_field, $order_by)
//            ->paginate(20)->toArray();


//        $id_arr =  collect($list->get())->map(function($rows) {
//            return $rows['id'];
//        });
        $list = Goods::Search($requestSearch)->select('yz_goods.id')
            ->where("status", 1)
            ->where(function($query) {
//                $query->whereIn('plugin_id', [0,40,92,41]);
                $query->where("plugin_id", 0)->orWhere('plugin_id', 40)->orWhere('plugin_id', 92);
            });

        //todo 为什么要取出id, 如id过多超出in的长度如何处理
        $id_arr = collect($list->get())->map(function ($rows) {
            return $rows['id'];
        });


//        $list = Goods::whereIn('id',$id_arr)->select("*")
//            ->where("status", 1)
//            ->where(function($query) {
//                $query->where("plugin_id", 0)->orWhere('plugin_id', 40)->orWhere('plugin_id', 92);
//            })
//            ->paginate(20)->toArray();

        $list = Goods::whereIn('id',$id_arr)->selectRaw("*, id as goods_id")
            ->orderBy($order_field, $order_by)
            ->paginate(20)->toArray();


//        $list = Goods::whereIn('id',$id_arr)->select("*")
//            ->where("status", 1)
//            ->where(function($query) {
//                $query->whereIn('plugin_id', [0,40,92,41]);
//                //$query->where("plugin_id", 0)->orWhere('plugin_id', 40)->orWhere('plugin_id', 92);
//            })
//            ->paginate(20)->toArray();


        if ($list['total'] > 0) {
            $data = collect($list['data'])->map(function ($rows) {
                return collect($rows)->map(function ($item, $key) {
                    if (($key == 'thumb' && preg_match('/^images/', $item)) || ($key == 'thumb' && preg_match('/^image/', $item))) {
                        return replace_yunshop(yz_tomedia($item));
                    } else {
                        return $item;
                    }
                });
            })->toArray();

            //租赁商品
            //TODO 租赁插件是否开启 $lease_switch
            $lease_switch = LeaseToyGoods::whetherEnabled();
            foreach ($data as &$item) {
                $this->goods_lease_set($item, $lease_switch);
            }

            $list['data'] = $data;
        }

        if (empty($list)) {
            return $this->errorJson('没有找到商品.');
        }
//        foreach ($list["data"] as $key=>$row){
//            $list['data'][$key]['goods_id']=$list['data'][$key]['id'];
//        }
        return $this->successJson('成功', $list);
    }

    public function getGoodsCategoryList()
    {
        $category_id = intval(\YunShop::request()->category_id);

        if (empty($category_id)) {
            return $this->errorJson('请输入正确的商品分类.');
        }

        $order_field = \YunShop::request()->order_field;
        if (!in_array($order_field, ['price', 'show_sales', 'comment_num'])) {
            $order_field = 'display_order';
        }

        $order_by = (\YunShop::request()->order_by == 'asc') ? 'asc' : 'desc';

        $categorys = Category::uniacid()->select("name", "thumb", "id")->where(['id' => $category_id])->first();

        if ($categorys) {
            $categorys->thumb = yz_tomedia($categorys->thumb);
        }

        $goodsList = Goods::uniacid()->select('yz_goods.id', 'yz_goods.id as goods_id', 'title', 'thumb', 'price', 'market_price')
            ->join('yz_goods_category', 'yz_goods_category.goods_id', '=', 'yz_goods.id')
            ->where("category_id", $category_id)
            ->where('status', '1')
            ->orderBy($order_field, $order_by)
            ->paginate(20)->toArray();


        if (empty($goodsList)) {
            return $this->errorJson('此分类下没有商品.');
        }
        $goodsList['data'] = set_medias($goodsList['data'], 'thumb');

        $categorys->goods = $goodsList;

        return $this->successJson('成功', $categorys);
    }

    public function getGoodsBrandList()
    {
        $brand_id = intval(\YunShop::request()->brand_id);
        $order_field = \YunShop::request()->order_field;
        if (!in_array($order_field, ['price', 'show_sales', 'comment_num'])) {
            $order_field = 'display_order';
        }

        $order_by = (\YunShop::request()->order_by == 'asc') ? 'asc' : 'desc';


        if (empty($brand_id)) {
            return $this->errorJson('请输入正确的品牌id.');
        }

        $brand = Brand::uniacid()->select("name", "logo", "id")->where(['id' => $brand_id])->first();

        if (!$brand) {
            return $this->errorJson('没有此品牌.');
        }

        $brand->logo = yz_tomedia($brand->logo);

        $goodsList = Goods::uniacid()->select('id', 'id as goods_id', 'title', 'thumb', 'price', 'market_price')
            ->where('status', '1')
            ->where('brand_id', $brand_id)
            ->where(function ($query) {
                $query->whereIn('plugin_id', [0, 40, 92, 41]);
                //$query->where("plugin_id", 0)->orWhere('plugin_id', 40)->orWhere('plugin_id', 92);
            })->orderBy($order_field, $order_by)
            ->paginate(20)->toArray();

        if (empty($goodsList)) {
            return $this->errorJson('此品牌下没有商品.');
        }

        $goodsList['data'] = set_medias($goodsList['data'], 'thumb');

        $brand->goods = $goodsList;

        return $this->successJson('成功', $brand);
    }

    public function getRecommendGoods()
    {
        $list = Goods::uniacid()
            ->select('id', 'id as goods_id', 'title', 'thumb', 'price', 'market_price')
            ->where('is_recommand', '1')
            ->whereStatus('1')
            ->orderBy('id', 'desc')
            ->get();

        if (!$list->isEmpty()) {
            $list = set_medias($list->toArray(), 'thumb');
        }

        return $this->successJson('获取推荐商品成功', $list);
    }

    /**
     * 会员折扣后的价格
     * @param Goods $goodsModel
     * @param  [type] $discountModel [description]
     * @return array [type]                [description]
     */
    public function getDiscount(Goods $goodsModel, $memberModel)
    {
        if ($goodsModel->vip_price === null) {
            return [];
        }
        $discount_switch = Setting::get('shop.member.discount');
        if ($memberModel->level) {
            $data = [
                'level_name' => $memberModel->level->level_name,
                'discount_value' => $goodsModel->vip_price,
                'discount' => $discount_switch,
            ];
        } else {
            $level = Setting::get('shop.member.level_name');
            $level_name = $level ?: '普通会员';

            $data = [
                'level_name' => $level_name,
                'discount_value' => $goodsModel->vip_price,
                'discount' => $discount_switch,
            ];
        }

        return $data;
    }

    public function getGoodsSaleV2($goodsModel, $member)
    {
        $sale = [];
        //商城积分设置
        $set = \Setting::get('point.set');

        //获取商城设置: 判断 积分、余额 是否有自定义名称
        $shopSet = \Setting::get('shop.shop');

        //判断经销商详情活动提成是否开启

        if ($goodsModel->hasOneSale->ed_num || $goodsModel->hasOneSale->ed_money) {
            $data['name'] = '包邮';
            $data['key'] = 'ed_num';
            $data['type'] = 'array';
            if ($goodsModel->hasOneSale->ed_num) {
                $data['value'][] = '本商品满' . $goodsModel->hasOneSale->ed_num . '件包邮';
            }

            if ($goodsModel->hasOneSale->ed_money) {
                $data['value'][] = '本商品满￥' . $goodsModel->hasOneSale->ed_money . '包邮';

            }
            array_push($sale, $data);
            $data = [];
        }
        if($goodsModel->hasOneSale->all_point_deduct && $goodsModel->hasOneSale->has_all_point_deduct){
            $data['name'] = '积分全额抵扣';
            $data['key'] = 'all_point_deduct';
            $data['type'] = 'string';
            $data['value'] = '可使用' . $goodsModel->hasOneSale->all_point_deduct . '个积分全额抵扣购买';
            array_push($sale, $data);
            $data = [];
        }

        if (ceil($goodsModel->hasOneSale->ed_full) && ceil($goodsModel->hasOneSale->ed_reduction)) {
            $data['name'] = '满减';
            $data['key'] = 'ed_full';
            $data['type'] = 'string';
            $data['value'] = '本商品满￥' . $goodsModel->hasOneSale->ed_full . '立减￥' . $goodsModel->hasOneSale->ed_reduction;
            array_push($sale, $data);
            $data = [];
        }

        if ($goodsModel->hasOneSale->award_balance) {
            $data['name'] = $shopSet['credit'] ?: '余额';
            $data['key'] = 'award_balance';
            $data['type'] = 'string';
            $data['value'] = '购买赠送' . $goodsModel->hasOneSale->award_balance . $data['name'];
            array_push($sale, $data);
            $data = [];
        }

        $data['name'] = $shopSet['credit1'] ?: '积分';
        $data['key'] = 'point';
        $data['type'] = 'array';
        if ($goodsModel->hasOneSale->point !== '0') {
            $point = $set['give_point'] ? $set['give_point'] : 0;
            if ($goodsModel->hasOneSale->point) {
                $point = $goodsModel->hasOneSale->point;
            }
            if (!empty($point)) {
                $data['value'][] = '购买赠送' . $point . $data['name'];
            }

        }
        if ($set['point_deduct'] && $goodsModel->hasOneSale->max_point_deduct !== '0') {
            $max_point_deduct = $set['money_max'] ? $set['money_max'] . '%' : 0;
            if ($goodsModel->hasOneSale->max_point_deduct) {
                $max_point_deduct = $goodsModel->hasOneSale->max_point_deduct;
            }
            if (!empty($max_point_deduct)) {
                $data['value'][] = '最高抵扣' . $max_point_deduct . '元';
            }
        }
        if ($set['point_deduct'] && $goodsModel->hasOneSale->min_point_deduct !== '0') {
            $min_point_deduct = $set['money_min'] ? $set['money_min'] . '%' : 0;
            if ($goodsModel->hasOneSale->min_point_deduct) {
                $min_point_deduct = $goodsModel->hasOneSale->min_point_deduct;
            }
            if (!empty($min_point_deduct)) {
                $data['value'][] = '最少抵扣' . $min_point_deduct . '元';
            }
        }
        if (!empty($data['value'])) {
            array_push($sale, $data);
        }
        $data = [];


        if ($goodsModel->hasOneGoodsCoupon->is_give) {
            $data['name'] = '购买返券';
            $data['key'] = 'coupon';
            $data['type'] = 'string';
            $data['value'] = $goodsModel->hasOneGoodsCoupon->send_type ? '商品订单完成返优惠券' : '每月一号返优惠券';
            array_push($sale, $data);
            $data = [];
        }

        //爱心值
        $exist_love = app('plugins')->isEnabled('love');
        if ($exist_love) {
            $love_goods = $this->getLoveSet($goodsModel);
            $data['name'] = $love_goods['name'];
            $data['key'] = 'love';
            $data['type'] = 'array';
            if ($love_goods['deduction']) {
                $data['value'][] = '最高抵扣' . $love_goods['deduction_proportion'] . $data['name'];
            }

            if ($love_goods['award'] && \Setting::get('love.goods_detail_show_love') != 2) {
                $data['value'][] = '购买赠送' . $love_goods['award_proportion'] . $data['name'];
            }

            if (!empty($data['value'])) {
                array_push($sale, $data);
            }
            $data = [];
        }

        //佣金
        $exist_commission = app('plugins')->isEnabled('commission');
        if ($exist_commission) {
            $is_agent = $this->isValidateCommission($member);
            if ($is_agent) {
                $commission_data = (new GoodsDetailService($goodsModel))->getGoodsDetailData();
                if ($commission_data['commission_show'] == 1) {
                    $data['name'] = '佣金';
                    $data['key'] = 'commission';
                    $data['type'] = 'array';

                    if (!empty($commission_data['first_commission']) && ($commission_data['commission_show_level'] > 0)) {
                        $data['value'][] = '一级佣金' . $commission_data['first_commission'] . '元';
                    }
                    if (!empty($commission_data['second_commission']) && ($commission_data['commission_show_level'] > 1)) {
                        $data['value'][] = '二级佣金' . $commission_data['second_commission'] . '元';
                    }
                    if (!empty($commission_data['third_commission']) && ($commission_data['commission_show_level'] > 2)) {
                        $data['value'][] = '三级佣金' . $commission_data['third_commission'] . '元';
                    }
                    array_push($sale, $data);
                    $data = [];
                }
            }
        }

        //经销商提成
        $exist_team_dividend = app('plugins')->isEnabled('team-dividend');
        if($exist_team_dividend){
            //验证是否是经销商及等级
            $is_agent = $this->isValidateTeamDividend($member);
            if ($is_agent) {
                //返回经销商等级奖励比例  商品等级奖励规则
                $team_dividend_data = (new TeamDividendGoodsDetailService($goodsModel))->getGoodsDetailData();
                if ($team_dividend_data['team_dividend_show'] == 1) {
                    $data['name'] = '经销商提成';
                    $data['key'] = 'team-dividend';
                    $data['type'] = 'array';
                    $data['value'][] = '经销商提成' . $team_dividend_data['team_dividend_royalty'];
                    array_unshift($sale, $data);
                    $data = [];
                }
            }

        }
        return [
            'sale_count' => count($sale),
//            'first_strip_key' => $sale ? $sale[rand(0, (count($sale) - 1))] : [],
            'first_strip_key' => $sale ? $sale[0] : [],
            'sale' => $sale,
        ];
    }

    public function isValidateCommission($member)
    {
        return Agents::getAgentByMemberId($member->member_id)->first();
    }

    public function isValidateTeamDividend($member)
    {
        return TeamDividendAgencyModel::getAgencyByMemberId($member->member_id)->first();
    }

    /**
     * 商品的营销
     * @param  [type] $goodsModel [description]
     * @return [type]             [description]
     */
    public function getGoodsSale($goodsModel)
    {
        $set = \Setting::get('point.set');

        $shopSet = \Setting::get('shop.shop');

        if (!empty($shopSet['credit1'])) {
            $point_name = $shopSet['credit1'];
        } else {
            $point_name = '积分';
        }

        $data = [
            'first_strip_key' => 0,
            'point_name' => $point_name, //积分名称
            'love_name' => '爱心值',
            'ed_num' => 0,      //满件包邮
            'ed_money' => 0,    //满额包邮
            'ed_full' => 0,      //单品满额
            'ed_reduction' => 0, //单品立减
            'award_balance' => 0, //赠送余额
            'point' => 0,        //赠送积分
            'max_point_deduct' => 0, //积分最大抵扣
            'min_point_deduct' => 0, //积分最小抵扣
            'coupon' => 0,         //商品优惠券赠送
            'deduction_proportion' => 0, //爱心值最高抵扣
            'award_proportion' => 0, //奖励爱心值
            'sale_count' => 0,      //活动总数
        ];


        if (ceil($goodsModel->hasOneSale->ed_full) && ceil($goodsModel->hasOneSale->ed_reduction)) {
            $data['ed_full'] = $goodsModel->hasOneSale->ed_full;
            $data['ed_reduction'] = $goodsModel->hasOneSale->ed_reduction;

            $data['first_strip_key'] = 'ed_full';
            $data['sale_count'] += 1;

        }

        if ($goodsModel->hasOneSale->award_balance) {
            $data['award_balance'] = $goodsModel->hasOneSale->award_balance;

            $data['first_strip_key'] = 'award_balance';
            $data['sale_count'] += 1;

        }

        if ($goodsModel->hasOneSale->point !== '0') {

            $data['point'] = $set['give_point'] ? $set['give_point'] : 0;

            if ($goodsModel->hasOneSale->point) {
                $data['point'] = $goodsModel->hasOneSale->point;
            }

            if (!empty($data['point'])) {
                $data['first_strip_key'] = 'point';
                $data['sale_count'] += 1;
            }

        }

        if ($set['point_deduct'] && $goodsModel->hasOneSale->max_point_deduct !== '0') {

            $data['max_point_deduct'] = $set['money_max'] ? $set['money_max'] . '%' : 0;

            if ($goodsModel->hasOneSale->max_point_deduct) {

                $data['max_point_deduct'] = $goodsModel->hasOneSale->max_point_deduct;
            }
            if (!empty($data['max_point_deduct'])) {
                $data['first_strip_key'] = 'max_point_deduct';
                $data['sale_count'] += 1;
            }
        }
        if ($set['point_deduct'] && $goodsModel->hasOneSale->min_point_deduct !== '0') {

            $data['min_point_deduct'] = $set['money_min'] ? $set['money_min'] . '%' : 0;

            if ($goodsModel->hasOneSale->min_point_deduct) {

                $data['min_point_deduct'] = $goodsModel->hasOneSale->min_point_deduct;
            }
            if (!empty($data['min_point_deduct'])) {
                $data['first_strip_key'] = 'min_point_deduct';
                $data['sale_count'] += 1;
            }
        }
        if ($goodsModel->hasOneGoodsCoupon->is_give) {

            $data['coupon'] = $goodsModel->hasOneGoodsCoupon->send_type ? '商品订单完成返优惠券' : '每月一号返优惠券';

            $data['first_strip_key'] = 'coupon';
            $data['sale_count'] += 1;
        }

        if ($goodsModel->hasOneSale->ed_num) {
            $data['ed_num'] = $goodsModel->hasOneSale->ed_num;

            $data['first_strip_key'] = 'ed_num';
            $data['sale_count'] += 1;
        }

        if ($goodsModel->hasOneSale->ed_money) {
            $data['ed_money'] = $goodsModel->hasOneSale->ed_money;

            $data['first_strip_key'] = 'ed_money';
            $data['sale_count'] += 1;

        }

        $exist_love = app('plugins')->isEnabled('love');
        if ($exist_love) {
            $love_goods = $this->getLoveSet($goodsModel);
            $data['love_name'] = $love_goods['name'];
            if ($love_goods['deduction']) {
                $data['deduction_proportion'] = $love_goods['deduction_proportion'];
                $data['first_strip_key'] = 'deduction_proportion';
                $data['sale_count'] += 1;
            }

            if ($love_goods['award']) {
                $data['award_proportion'] = $love_goods['award_proportion'];
                $data['first_strip_key'] = 'award_proportion';
                $data['sale_count'] += 1;
            }

        }
        $exist_commission = app('plugins')->isEnabled('commission');
        if ($exist_commission) {
            $commission_data = (new GoodsDetailService($goodsModel))->getGoodsDetailData();
            if ($commission_data['commission_show'] == 1) {
                $data['sale_count'] += 1;
                $data['first_strip_key'] = 'commission_show';
            }
            $data = array_merge($data, $commission_data);
        }
        return $data;
    }

    /**
     * 获取商品爱心值设置
     */
    public function getLoveSet($goods)
    {


        $data = [
            'name' => \Setting::get('love.name') ?: '爱心值',
            'deduction' => 0, //是否开启爱心值抵扣 0否，1是
            'deduction_proportion' => 0, //爱心值最高抵扣
            'award' => 0, //是否开启爱心值奖励 0否，1是
            'award_proportion' => 0, //奖励爱心值
        ];

        $item = GoodsLove::ofGoodsId($goods->id)->first();
        // dd($item);
        if ($item->deduction) {
            $deduction_proportion = floor($item->deduction_proportion) ? $item->deduction_proportion : \Setting::get('love.deduction_proportion');

            // $price = $goods->price * ($deduction_proportion / 100);

            $data['deduction'] = $item->deduction;
            $data['deduction_proportion'] = $deduction_proportion . '%';
        }

        if ($item->award) {
            $award_proportion = floor($item->award_proportion) ? $item->award_proportion : \Setting::get('love.award_proportion');

            // $award_price = $goods->price * ($award_proportion / 100);

            $data['award'] = $item->award;
            $data['award_proportion'] = $award_proportion . '%';

        }

        return $data;
    }

    /**
     * 是否开启领优惠卷
     * @param $member
     * @return \Illuminate\Http\JsonResponse|int
     */
    public function couponsMemberLj($member)
    {
        if (empty($member)) {
            throw new AppException('没有找到该用户');
        }
        $memberLevel = $member->level_id;

        $now = strtotime('now');
        $coupons = Coupon::getCouponsForMember($member->member_id, $memberLevel, null, $now)
            ->orderBy('display_order', 'desc')
            ->orderBy('updated_at', 'desc')
            ->get();
        if ($coupons->isEmpty()) {
            return 0;
        }

        foreach ($coupons as $v) {
            if (($v->total == MemberCouponController::NO_LIMIT) || ($v->has_many_member_coupon_count < $v->total)) {
                return 1;
            }
        }

        return 0;
    }

    private function goods_lease_set(&$goodsModel, $lease_switch)
    {
        if ($lease_switch) {
            //TODO 商品租赁设置 $id
            if (is_array($goodsModel)) {
                $goodsModel['lease_toy'] = LeaseToyGoods::getDate($goodsModel['id']);

            } else {
                $goodsModel->lease_toy = LeaseToyGoods::getDate($goodsModel->id);
            }

        } else {
            if (is_array($goodsModel)) {

                $goodsModel['lease_toy'] = [
                    'is_lease' => $lease_switch,
                    'is_rights' => 0,
                    'immed_goods_id' => 0,
                ];
            } else {
                $goodsModel->lease_toy = [
                    'is_lease' => $lease_switch,
                    'is_rights' => 0,
                    'immed_goods_id' => 0,
                ];
            }
        }
    }

    public function loveShoppingGift($goodsModel){

        //爱心值
        $exist_love = app('plugins')->isEnabled('love');
        if ($exist_love) {
            $love_goods = $this->getLoveSet($goodsModel);
            $data['name'] = $love_goods['name'];
            $data['key'] = 'love';
            $data['type'] = 'array';

            if ($love_goods['award'] && \Setting::get('love.goods_detail_show_love') == 2) {
                return  '购买赠送' . $love_goods['award_proportion'] . $data['name'];
            }
        }

    }


}