<?php
namespace app\backend\modules\coupon\controllers;

use app\common\components\BaseController;
use app\backend\modules\coupon\models\Coupon;
use app\common\helpers\PaginationHelper;
use app\common\models\MemberCoupon;
use app\common\helpers\Url;
use app\backend\modules\member\models\MemberLevel;
use app\backend\modules\coupon\models\CouponLog;

/**
 * Created by PhpStorm.
 * User: Rui
 * Date: 2017/3/20
 * Time: 16:20
 */
class CouponController extends BaseController
{
    //优惠券列表
    public function index()
    {
        $keyword = \YunShop::request()->keyword;
        $getType = \YunShop::request()->gettype;
        $timeSearchSwitch = \YunShop::request()->timesearchswtich;
        $timeStart = strtotime(\YunShop::request()->time['start']);
        $timeEnd = strtotime(\YunShop::request()->time['end']);

        $pageSize = 10;
        if (empty($keyword) && empty($getType) && ($timeSearchSwitch == 0)){
            $list = Coupon::uniacid()->orderBy('display_order','desc')->orderBy('updated_at', 'desc')->paginate($pageSize)->toArray();
        } else {
            $list = Coupon::getCouponsBySearch($keyword, $getType, $timeSearchSwitch, $timeStart, $timeEnd)
                        ->orderBy('display_order','desc')
                        ->paginate($pageSize)
                        ->toArray();
        }
        $pager = PaginationHelper::show($list['total'], $list['current_page'], $list['per_page']);

        foreach($list['data'] as &$item){
            $item['gettotal'] = MemberCoupon::uniacid()->where("coupon_id", $item['id'])->count();
            $item['usetotal'] =  MemberCoupon::uniacid()->where("coupon_id", $item['id'])->where("used", 1)->count();
            $item['lasttotal'] = $item['total'] - $item['gettotal'];
        }

        return view('coupon.index', [
            'list' => $list['data'],
            'pager' => $pager,
            'total' => $list['total'],
        ])->render();
    }

    //添加优惠券
    public function create()
    {

        //获取表单提交的值
        $couponRequest = \YunShop::request()->coupon;

        //获取会员等级列表
        $memberLevels = MemberLevel::getMemberLevelList();

        //表单验证
        if($couponRequest){
            $coupon = new Coupon();
            $coupon->uniacid = \YunShop::app()->uniacid;
            $coupon->time_start = strtotime(\YunShop::request()->time['start']);
            $coupon->time_end = strtotime(\YunShop::request()->time['end']);
            $coupon->use_type =\YunShop::request()->usetype;
            $coupon->category_ids = \YunShop::request()->categoryids;
            $coupon->categorynames = \YunShop::request()->categorynames;
            $coupon->goods_ids = \YunShop::request()->goods_ids;
            $coupon->goods_names = \YunShop::request()->goods_names;

            $coupon->fill($couponRequest);
            $validator = $coupon->validator();
            if($validator->fails()){
                $this->error($validator->messages());
            } elseif($coupon->save()) {
                return $this->message('优惠券创建成功', Url::absoluteWeb('coupon.coupon.index'));
            } else{
                $this->error('优惠券创建失败');
            }
        }

        return view('coupon.coupon', [
            'coupon' => $couponRequest,
            'memberlevels' => $memberLevels,
            'timestart' => strtotime(\YunShop::request()->time['start']),
            'timeend' => strtotime(\YunShop::request()->time['end'])
        ])->render();
    }

    //编辑优惠券
    public function edit()
    {
        $coupon_id = intval(\YunShop::request()->id);
        if (!$coupon_id) {
            $this->error('请传入正确参数.');
        }

        //获取会员等级列表
        $memberLevels = MemberLevel::getMemberLevelList();

        $coupon = Coupon::getCouponById($coupon_id);
        $couponRequest = \YunShop::request()->coupon;
        if ($couponRequest) {

            $couponRequest['time_start'] =strtotime(\YunShop::request()->time['start']);
            $couponRequest['time_end'] =strtotime(\YunShop::request()->time['end']);
            $coupon->use_type =\YunShop::request()->usetype;
            $coupon->category_ids = \YunShop::request()->category_ids;
            $coupon->categorynames = \YunShop::request()->category_names;
            $coupon->goods_ids = \YunShop::request()->goods_ids;
            $coupon->goods_names = \YunShop::request()->goods_names;

            //表单验证
            $coupon->fill($couponRequest);
            $validator = $coupon->validator();
            if($validator->fails()){
                $this->error($validator->messages());
            } else{
                if($coupon->save()){
                    return $this->message('优惠券修改成功', Url::absoluteWeb('coupon.coupon.index'));
                } else{
                    $this->error('优惠券修改失败');
                }
            }
        }
        
        return view('coupon.coupon', [
            'coupon' => $coupon->toArray(),
            'usetype' => $coupon->use_type,
            'category_ids' => $coupon->category_ids,
            'categorynames' => $coupon->categorynames,
            'goods_ids' => $coupon->goods_ids,
            'goods_names' => $coupon->goods_names,
            'memberlevels' => $memberLevels,
            'timestart' => $coupon->time_start->timestamp,
            'timeend' => $coupon->time_end->timestamp,
        ])->render();
    }

    //删除优惠券
    public function destory()
    {
        $coupon_id = intval(\YunShop::request()->id);
        if (!$coupon_id) {
            $this->error('请传入正确参数.');
        }

        $coupon = Coupon::getCouponById($coupon_id);
        if (!$coupon) {
            return $this->message('无此记录或者已被删除.', '', 'error');
        }

        $usageCount = Coupon::getUsageCount($coupon_id)->first()->toArray();
        if($usageCount['has_many_member_coupon_count'] > 0){
            return $this->message('优惠券已被领取且尚未使用,因此无法删除', Url::absoluteWeb('coupon.coupon'), 'error');
        }

        $res = Coupon::deleteCouponById($coupon_id);
        if ($res) {
            return $this->message('删除优惠券成功', Url::absoluteWeb('coupon.coupon'));
        } else {
            return $this->message('删除优惠券失败', '', 'error');
        }
    }


    /**
     * 获取搜索优惠券
     * @return html
     */
    public function getSearchCoupons()
    {
        $keyword = \YunShop::request()->keyword;
        $coupons = Coupon::getCouponsByName($keyword);
        return view('coupon.query', [
            'coupons' => $coupons
        ])->render();
    }

    //用于"适用范围"添加商品或者分类
    public function addParam()
    {
        $type = \YunShop::request()->type;
        switch($type){
            case 'goods':
                return view('coupon.tpl.goods')->render();
                break;
            case 'category':
                return view('coupon.tpl.category')->render();
                break;
        }
    }

    //优惠券领取和发放记录
    public function log()
    {
        $couponId = \YunShop::request()->id;
        $couponName = \YunShop::request()->couponname;
        $nickname = \YunShop::request()->nickname;
        $getFrom = \YunShop::request()->getfrom;
        $searchSearchSwitch = \YunShop::request()->timesearchswtich;
        $timeStart = strtotime(\YunShop::request()->time['start']);
        $timeEnd = strtotime(\YunShop::request()->time['end']);

        $pageSize = 15;
        if (empty($couponId) && empty($couponName) && empty($getFrom) && empty($nickname) && ($searchSearchSwitch == 0)){
            $list = CouponLog::getCouponLogs();
        } else {
            $searchData = [];
            if(!empty($couponId)){
                $searchData['coupon_id'] = $couponId;
            }
            if(!empty($couponName)){
                $searchData['coupon_name'] = $couponName;
            }
            if(!empty($nickname)){
                $searchData['nickname'] = $nickname;
            }
            if($getFrom != ''){
                $searchData['get_from'] = $getFrom;
            }
            if($searchSearchSwitch == 1){
                $searchData['time_search_swtich'] = $searchSearchSwitch;
                $searchData['time_start'] = $timeStart;
                $searchData['time_end'] = $timeEnd;
            }
            $list = CouponLog::searchCouponLog($searchData);
        }

        $pager = PaginationHelper::show($list->total(), $list->currentPage(), $list->perPage());

        return view('coupon.log', [
            'list' => $list,
            'pager' => $pager,
            'couponid' => $couponId,
        ])->render();
    }

}