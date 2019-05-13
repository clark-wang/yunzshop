<?php

namespace app\frontend\controllers;

use app\backend\modules\member\models\MemberRelation;
use app\common\components\ApiController;
use app\common\facades\Setting;
use app\common\helpers\Cache;
use app\common\models\AccountWechats;
use app\common\models\member\MemberInvitationCodeLog;
use app\common\models\MemberShopInfo;
use app\common\services\popularize\PortType;
use app\frontend\models\Member;
use app\frontend\modules\member\models\MemberModel;
use app\frontend\modules\shop\controllers\IndexController;
use EasyWeChat\Foundation\Application;
use Yunshop\Designer\Common\Services\IndexPageService;
use Yunshop\Designer\Common\Services\OtherPageService;
use Yunshop\Designer\Common\Services\PageTopMenuService;
use Yunshop\Designer\models\Designer;
use Yunshop\Designer\models\DesignerMenu;
use Yunshop\Designer\models\GoodsGroupGoods;
use Yunshop\Love\Common\Models\GoodsLove;
use Yunshop\Love\Common\Services\SetService;

class HomePageController extends ApiController
{
    protected $publicAction = [
        'index',
        'defaultDesign',
        'defaultMenu',
        'defaultMenuStyle',
        'bindMobile',
        'wxapp',
        'isCloseSite',
        'getParams'
    ];
    protected $ignoreAction = [
        'index',
        'defaultDesign',
        'defaultMenu',
        'defaultMenuStyle',
        'bindMobile',
        'wxapp',
        'isCloseSite',
        'getParams'
    ];
    private $pageSize = 16;

    /**
     * @return \Illuminate\Http\JsonResponse 当路由不包含page_id参数时,提供商城首页数据; 当路由包含page_id参数时,提供装修预览数据
     */
    public function index($request, $integrated = null)
    {
        $i         = \YunShop::request()->i;
        $mid       = \YunShop::request()->mid;
        $type      = \YunShop::request()->type;
        $pageId    = (int)\YunShop::request()->page_id ?: 0;
        $member_id = \YunShop::app()->getMemberId();

        //商城设置, 原来接口在 setting.get
        $key = \YunShop::request()->setting_key ? \YunShop::request()->setting_key : 'shop';

        if (!Cache::has('shop_setting')) {
            $setting = Setting::get('shop.' . $key);

            if (!is_null($setting)) {
                Cache::put('shop_setting', $setting, 3600);
            }
        } else {
            $setting = Cache::get('shop_setting');
        }

        if ($setting) {
            $setting['logo'] = replace_yunshop(yz_tomedia($setting['logo']));
            if (!Cache::has('member_relation')) {
                $relation = MemberRelation::getSetInfo()->first();

                if (!is_null($relation)) {
                    Cache::put('member_relation', $relation, 3600);
                }
            } else {
                $relation = Cache::get('member_relation');
            }

            $setting['signimg'] = replace_yunshop(yz_tomedia($setting['signimg']));
            if ($relation) {
                $setting['agent'] = $relation->status ? true : false;
            } else {
                $setting['agent'] = false;
            }

            $setting['diycode'] = html_entity_decode($setting['diycode']);
            $result['mailInfo'] = $setting;
        }

        //强制绑定手机号
        if (!Cache::has('shop_member')) {
            $member_set = Setting::get('shop.member');

            if (!is_null($member_set)) {
                Cache::put('shop_member', $member_set, 4200);
            }
        } else {
            $member_set = Cache::get('shop_member');
        }

        $is_bind_mobile = 0;

        if (!is_null($member_set)) {
            if ((0 < $member_set['is_bind_mobile']) && $member_id && $member_id > 0) {
                if (!Cache::has($member_id . '_member_info')) {
                    $member_model = Member::getMemberById($member_id);
                    if (!is_null($member_model)) {
                        Cache::put($member_id . '_member_info', $member_model, 4200);
                    }
                } else {
                    $member_model = Cache::get($member_id . '_member_info');
                }
                if ($member_model && empty($member_model->mobile)) {
                    $is_bind_mobile = intval($member_set['is_bind_mobile']);
                }
            }
        }
        $result['mailInfo']['is_bind_mobile'] = $is_bind_mobile;
        //用户信息, 原来接口在 member.member.getUserInfo
        if (empty($pageId)) { //如果是请求首页的数据
            if (!empty($member_id)) {
                // TODO
                $member_info = MemberModel::getUserInfos($member_id)->first();

                if (!empty($member_info)) {
                    $member_info = $member_info->toArray();
                    $data        = MemberModel::userData($member_info, $member_info['yz_member']);
                    $data        = MemberModel::addPlugins($data);

                    $result['memberinfo'] = $data;
                }
            }
        }

        //如果安装了装修插件并开启插件
        if (app('plugins')->isEnabled('designer')) {
            $is_love = app('plugins')->isEnabled('love');
           if ($is_love){
               $love_basics_set = SetService::getLoveSet();//获取爱心值基础设置
               $result['designer']['love_name'] = $love_basics_set['name'];
           }
            //系统信息
            // TODO
            if (!Cache::has('designer_system')) {
                $result['system'] = (new \Yunshop\Designer\services\DesignerService())->getSystemInfo();

                Cache::put('designer_system', $result['system'], 4200);
            } else {
                $result['system'] = Cache::get('designer_system');
            }

            $page_id = $pageId;
            if ($page_id) {
                $page = (new OtherPageService())->getOtherPage($page_id);
            } else {
                $page = (new IndexPageService())->getIndexPage();
            }

            if ($page) {
                if (empty($pageId) && Cache::has($member_id . '_designer_default_0')) {
                    $designer = Cache::get($member_id . '_designer_default_0');
                } else {
                    $designer = (new \Yunshop\Designer\services\DesignerService())->getPageForHomePage($page->toArray());
                }
                if ($is_love){
                    foreach ($designer['data'] as &$data){
                        if ($data['temp']=='goods'){
                            foreach ($data['data'] as &$goode_award){
                                $goode_award['award'] = $this->getLoveGoods($goode_award['goodid']);
                            }
                        }
                    }
                }else{
                    foreach ($designer['data'] as &$data){
                        if ($data['temp']=='goods'){
                            foreach ($data['data'] as &$goode_award){
                                $goode_award['award'] = 0;
                            }
                        }
                    }
                }

                if (empty($pageId) && !Cache::has($member_id . '_designer_default_0')) {
                    Cache::put($member_id . '_designer_default_0', $designer, 180);
                }

                $result['item'] = $designer;

                //顶部菜单 todo 加快进度开发，暂时未优化模型，装修数据、顶部菜单、底部导航等应该在一次模型中从数据库获取、编译 Y181031
                if ($designer['pageinfo']['params']['top_menu'] && $designer['pageinfo']['params']['top_menu_id']) {
                    $result['item']['topmenu'] = (new PageTopMenuService())->getTopMenu($designer['pageinfo']['params']['top_menu_id']);
                } else {
                    $result['item']['topmenu'] = [
                        'menus'  => [],
                        'params' => [],
                        'isshow' => false
                    ];
                }

                $footerMenuType = $designer['footertype']; //底部菜单: 0 - 不显示, 1 - 显示系统默认, 2 - 显示选中的自定义菜单
                $footerMenuId   = $designer['footermenu'];
            } elseif (empty($pageId)) { //如果是请求首页的数据, 提供默认值
                $result['default']         = self::defaultDesign();
                $result['item']['data']    = ''; //前端需要该字段
                $footerMenuType            = 1;
                $result['item']['topmenu'] = [
                    'menus'  => [],
                    'params' => [],
                    'isshow' => false
                ];
            } else { //如果是请求预览装修的数据
                $result['item']['data']    = ''; //前端需要该字段
                $footerMenuType            = 0;
                $result['item']['topmenu'] = [
                    'menus'  => [],
                    'params' => [],
                    'isshow' => false
                ];
            }
            //自定义菜单, 原来接口在  plugin.designer.home.index.menu
            switch ($footerMenuType) {
                case 1:
                    $result['item']['menus']     = self::defaultMenu($i, $mid, $type);
                    $result['item']['menustyle'] = self::defaultMenuStyle();
                    break;
                case 2:
                    if (!empty($footerMenuId)) {
                        if (!Cache::has("designer_menu_{$footerMenuId}")) {
                            $menustyle = DesignerMenu::getMenuById($footerMenuId);
                            Cache::put("designer_menu_{$footerMenuId}", $menustyle, 4200);
                        } else {
                            $menustyle = Cache::get("designer_menu_{$footerMenuId}");
                        }

                        if (!empty($menustyle->menus) && !empty($menustyle->params)) {
                            $result['item']['menus']     = json_decode($menustyle->toArray()['menus'], true);
                            $result['item']['menustyle'] = json_decode($menustyle->toArray()['params'], true);
                            //判断是否是商城外部链接
                            foreach ($result['item']['menus'] as $key => $value) {
                                if (!strexists($value['url'], 'addons/yun_shop/')) {
                                    $result['item']['menus'][$key]['is_shop'] = 1;
                                } else {
                                    $result['item']['menus'][$key]['is_shop'] = 0;
                                }
                            }
                        } else {
                            $result['item']['menus']     = self::defaultMenu($i, $mid, $type);
                            $result['item']['menustyle'] = self::defaultMenuStyle();
                        }
                    } else {
                        $result['item']['menus']     = self::defaultMenu($i, $mid, $type);
                        $result['item']['menustyle'] = self::defaultMenuStyle();
                    }
                    break;
                default:
                    $result['item']['menus']     = false;
                    $result['item']['menustyle'] = false;
            }
        } elseif (empty($pageId)) { //如果是请求首页的数据, 但是没有安装"装修插件"或者"装修插件"没有开启, 则提供默认值
            $result['default']           = self::defaultDesign();
            $result['item']['menus']     = self::defaultMenu($i, $mid, $type);
            $result['item']['menustyle'] = self::defaultMenuStyle();
            $result['item']['data']      = ''; //前端需要该字段
            $result['item']['topmenu']   = [
                'menus'  => [],
                'params' => [],
                'isshow' => false
            ];
        }

        //增加验证码功能
        $status = Setting::get('shop.sms.status');
        if (extension_loaded('fileinfo')) {
            if ($status == 1) {
                $captcha                     = self::captchaTest();
                $result['captcha']           = $captcha;
                $result['captcha']['status'] = $status;
            }
        }

        if (is_null($integrated)) {
            return $this->successJson('ok', $result);
        } else {
            return show_json(1, $result);
        }
    }

    public function getLoveGoods($goods_id)
    {
        $goodsModel = GoodsLove::select('award')->where('uniacid',\Yunshop::app()->uniacid)->where('goods_id',$goods_id)->first();
        $goods = $goodsModel ? $goodsModel->toArray()['award'] : 0;
        return $goods;

    }
    /*
     * 获取分页数据
     */
    public function GetPageGoods()
    {
        if (app('plugins')->isEnabled('designer')) {
            $group_id    = \YunShop::request()->group_id;
            $group_goods = new GoodsGroupGoods();
            $data        = $group_goods->GetPageGoods($group_id);
            $datas       = $data->paginate(12)
                ->toArray();
            foreach ($datas['data'] as $key => $itme) {
                $datas['data'][$key] = unserialize($itme['goods']);//反序列化
            }
            return $this->successJson('ok', $datas);
        }
    }

    //增加验证码功能
    public function captchaTest()
    {
        $captcha        = app('captcha');
        $captcha_base64 = $captcha->create('default', true);

        return $captcha_base64;
    }

    /**
     * 原生小程序首页装修接口
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function wxapp()
    {
        return $this->index();
    }

    /**
     * @return array 默认的首页元素(轮播图 & 商品 & 分类 & 商城设置)
     */
    public static function defaultDesign()
    {
        if (!Cache::has('shop_category')) {
            $set = Setting::get('shop.category');

            Cache::put('shop_category', $set, 4200);
        } else {
            $set = Cache::get('shop_category');
        }

        $set['cat_adv_img'] = replace_yunshop(yz_tomedia($set['cat_adv_img']));
//        $category = (new IndexController())->getRecommentCategoryList();
//        foreach ($category  as &$item){
//            $item['thumb'] = replace_yunshop(yz_tomedia($item['thumb']));
//            $item['adv_img'] = replace_yunshop(yz_tomedia($item['adv_img']));
//        }
        return Array(
            'ads'        => (new IndexController())->getAds(),
            'advs'       => (new IndexController())->getAdv(),
            'brand'      => (new IndexController())->getRecommentBrandList(),
            'category'   => (new IndexController())->getRecommentCategoryList(),
            'time_goods' => (new IndexController())->getTimeLimitGoods(),
            'set'        => $set,
            'goods'      => (new IndexController())->getRecommentGoods(),
        );
    }


    /**
     * @param $i 公众号ID
     * @param $mid 上级的uid
     * @param $type
     *
     * @return array 默认的底部菜单数据
     */
    public static function defaultMenu($i, $mid, $type)
    {
        app('plugins')->isEnabled('designer') ? $CustomizeMenu = DesignerMenu::getDefaultMenu() : null;
        if(!empty($CustomizeMenu)){
            $CustomizeMenu_list=$CustomizeMenu->toArray();
            if(is_array($CustomizeMenu_list) && !empty($CustomizeMenu_list['menus'])){
                $Menu = json_decode(htmlspecialchars_decode($CustomizeMenu['menus']), true);
                foreach ($Menu as $key=>$value){
                    // $Menu[$key]['name']=$Menu[$key]['id'];
//                    $url = substr($Menu[$key]['url'],strripos($Menu[$key]['url'],"addons")-1);
                    if (strripos($Menu[$key]['url'],"addons") != false) {
                        $url = substr($Menu[$key]['url'],strripos($Menu[$key]['url'],"addons")-1);
                    } else {
                        $url = $Menu[$key]['url'];
                    }
                    $Menu[$key]['url']= $url?:'';

                    //$Menu[$key]['url'] ="/addons/yun_shop/".'?#'.substr($Menu[$key]['url'],strripos($Menu[$key]['url'],"#/")+1)."&mid=" . $mid . "&type=" . $type;
                }
            }
        }
        else {
            //默认菜单
            $Menu = Array(
                Array(
                    "id" => 1,
                    "title" => "首页",
                    "icon" => "fa fa-home",
                    "url" => "/addons/yun_shop/?#/home?i=" . $i . "&mid=" . $mid . "&type=" . $type,
                    "name" => "home",
                    "subMenus" => [],
                    "textcolor" => "#70c10b",
                    "bgcolor" => "#24d7e6",
                    "bordercolor" => "#bfbfbf",
                    "iconcolor" => "#666666"
                ),
                Array(
                    "id" => "menu_1489731310493",
                    "title" => "分类",
                    "icon" => "fa fa-th-large",
                    "url" => "/addons/yun_shop/?#/category?i=" . $i . "&mid=" . $mid . "&type=" . $type,
                    "name" => "category",
                    "subMenus" => [],
                    "textcolor" => "#70c10b",
                    "bgcolor" => "#24d7e6",
                    "iconcolor" => "#666666",
                    "bordercolor" => "#bfbfbf"
                ),
                Array(
                    "id" => "menu_1489735163419",
                    "title" => "购物车",
                    "icon" => "fa fa-cart-plus",
                    "url" => "/addons/yun_shop/?#/cart?i=" . $i . "&mid=" . $mid . "&type=" . $type,
                    "name" => "cart",
                    "subMenus" => [],
                    "textcolor" => "#70c10b",
                    "bgcolor" => "#24d7e6",
                    "iconcolor" => "#666666",
                    "bordercolor" => "#bfbfbf"
                ),
                Array(
                    "id" => "menu_1491619644306",
                    "title" => "会员中心",
                    "icon" => "fa fa-user",
                    "url" => "/addons/yun_shop/?#/member?i=" . $i . "&mid=" . $mid . "&type=" . $type,
                    "name" => "member",
                    "subMenus" => [],
                    "textcolor" => "#70c10b",
                    "bgcolor" => "#24d7e6",
                    "iconcolor" => "#666666",
                    "bordercolor" => "#bfbfbf"
                ),
            );
            $promoteMenu      = Array(
                "id"          => "menu_1489731319695",
                "classt"      => "no",
                "title"       => "推广",
                "icon"        => "fa fa-send",
                "url"         => "/addons/yun_shop/?#/member/extension?i=" . $i . "&mid=" . $mid . "&type=" . $type,
                "name"        => "extension",
                "subMenus"    => [],
                "textcolor"   => "#666666",
                "bgcolor"     => "#837aef",
                "iconcolor"   => "#666666",
                "bordercolor" => "#bfbfbf"
            );
            $extension_status = Setting::get('shop_app.pay.extension_status');
            if (isset($extension_status) && $extension_status == 0) {
                $extension_status = 0;
            } else {
                $extension_status = 1;
            }
            if ($type == 7 && $extension_status == 0) {
                unset($promoteMenu);
            } else {
                //是否显示推广按钮
                if (PortType::popularizeShow($type)) {
                    $Menu[4] = $Menu[3]; //第 5 个按钮改成"会员中心"
                    $Menu[3] = $Menu[2]; //第 4 个按钮改成"购物车"
                    $Menu[2] = $promoteMenu; //在第 3 个按钮的位置加入"推广"
                }
            }
        }

        //如果开启了"会员关系链", 则默认菜单里面添加"推广"菜单
        /*
        if(Cache::has('member_relation')){
            $relation = Cache::get('member_relation');
        } else {
            $relation = MemberRelation::getSetInfo()->first();
        }
        */
        //if($relation->status == 1){

        return $Menu;


    }

    /**
     * @return array 默认的底部菜单样式
     */
    public static function defaultMenuStyle()
    {
        return Array(
            "previewbg"       => "#ef372e",
            "height"          => "49px",
            "textcolor"       => "#666666",
            "textcolorhigh"   => "#ff4949",
            "iconcolor"       => "#666666",
            "iconcolorhigh"   => "#ff4949",
            "bgcolor"         => "#FFF",
            "bgcolorhigh"     => "#FFF",
            "bordercolor"     => "#010101",
            "bordercolorhigh" => "#bfbfbf",
            "showtext"        => 1,
            "showborder"      => "0",
            "showicon"        => 2,
            "textcolor2"      => "#666666",
            "bgcolor2"        => "#fafafa",
            "bordercolor2"    => "#1856f8",
            "showborder2"     => 1,
            "bgalpha"         => ".5",
        );
    }

    public function bindMobile()
    {

        $member_id = \YunShop::app()->getMemberId();
        \Log::info('member_id', $member_id);
        //强制绑定手机号
        if (Cache::has('shop_member')) {
            $member_set = Cache::get('shop_member');
            \Log::info('member_set-1', $member_set);
        } else {
            $member_set = Setting::get('shop.member');
            \Log::info('member_set-2', $member_set);
        }
        //        $is_bind_mobile = 0;
        //
        //        if (!is_null($member_set)) {
        //            if ((1 == $member_set['is_bind_mobile']) && $member_id && $member_id > 0) {
        //                if (Cache::has($member_id . '_member_info')) {
        //                    $member_model = Cache::get($member_id . '_member_info');
        //                } else {
        //                    $member_model = Member::getMemberById($member_id);
        //                }
        //
        //                if ($member_model && empty($member_model->mobile)) {
        //                    $is_bind_mobile = 1;
        //                }
        //            }
        //        }

        $is_bind_mobile = 0;

        if (!is_null($member_set)) {
            \Log::info('not_null_member_set', [$member_set]);
            if ((0 < $member_set['is_bind_mobile']) && $member_id && $member_id > 0) {
                \Log::info('0 < $member_set[is_bind_mobile]) && $member_id && $member_id > 0', [$member_set['is_bind_mobile'], $member_id]);

                if (Cache::has($member_id . '_member_info')) {
                    $member_model = Cache::get($member_id . '_member_info');
                    \Log::info('$member_model-1',$member_model);
                } else {
                    $member_model = Member::getMemberById($member_id);
                    \Log::info('$member_model-2',$member_model);
                }

                if ($member_model && empty($member_model->mobile)) {
                    \Log::info('$member_model && empty($member_model->mobile)',[ $member_model, $member_model->mobile]);
                    $is_bind_mobile = intval($member_set['is_bind_mobile']);
                }
            }
        }
        if (\YunShop::request()->invite_code) {
            \Log::info('绑定手机号填写邀请码');
            //分销关系链
            \app\common\models\Member::createRealtion($member_id);
        }

        $result['is_bind_mobile'] = $is_bind_mobile;

        return $this->successJson('ok', $result);
    }

    public function isCloseSite()
    {
        $shop = Setting::get('shop.shop');
        $code = 0;

        if (isset($shop) && isset($shop['close']) && 1 == $shop['close']) {
            $code = -1;
        }

        return $this->successJson('ok', ['code' => $code]);
    }

    public function isBindMobile($member_set, $member_id)
    {
        $is_bind_mobile = 0;

        if ((0 < $member_set['is_bind_mobile']) && $member_id && $member_id > 0) {
            if (Cache::has($member_id . '_member_info')) {
                $member_model = Cache::get($member_id . '_member_info');
            } else {
                $member_model = Member::getMemberById($member_id);
            }

            if ($member_model && empty($member_model->mobile)) {
                $is_bind_mobile = intval($member_set['is_bind_mobile']);
            }
        }
        return $is_bind_mobile;
    }

    public function isValidatePage($request, $integrated = null)
    {
        $member_id = \YunShop::app()->getMemberId();

        //强制绑定手机号
        if (Cache::has('shop_member')) {
            $member_set = Cache::get('shop_member');
        } else {
            $member_set = \Setting::get('shop.member');
        }

        if (!is_null($member_set)) {
            $data = [
                'is_bind_mobile' => $this->isBindMobile($member_set, $member_id),
                'invite_page' => 0,
                'is_invite' => 0,
                'is_login' => 0,
            ];

            if ($data['is_bind_mobile']) {
                if (is_null($integrated)) {
                    return $this->successJson('强制绑定手机开启', $data);
                } else {
                    return show_json(1, $data);
                }
            }

            $type = \YunShop::request()->type;
            $invitation_log = [];
            if ($member_id) {
                $mobile = \app\common\models\Member::where('uid', $member_id)->first();
                if ($mobile->mobile) {
                    $invitation_log = 1;
                } else {
                    $member = MemberShopInfo::uniacid()->where('member_id', $member_id)->first();
                    $invitation_log = MemberInvitationCodeLog::uniacid()->where('member_id', $member->parent_id)->where('mid',$member_id)->first();
                }
            }

            $invite_page = $member_set['invite_page'] ? 1 : 0;
            $data['invite_page'] = $type == 5 ? 0 : $invite_page;

            $data['is_invite'] = $invitation_log ? 1 : 0;
            $data['is_login'] = $member_id ? 1 : 0;

            if (is_null($integrated)) {
                return $this->successJson('邀请页面开关', $data);
            } else {
                return show_json(1, $data);
            }
        }

        return show_json(1, []);
    }

    public function getBalance()
    {
        $shop = \Setting::get('shop.shop');
        $credit=$shop['credit'] ?: '余额';

        return show_json(1, ['balance'=>$credit]);
    }

    public function getLangSetting()
    {
        $lang = \Setting::get('shop.lang.lang');

        $data = [
            'test' => [],
            'commission' => [
                'title' => '',
                'commission' => '',
                'agent' => '',
                'level_name' => '',
                'commission_order' => '',
                'commission_amount' => '',
            ],
            'single_return' => [
                'title' => '',
                'single_return' => '',
                'return_name' => '',
                'return_queue' => '',
                'return_log' => '',
                'return_detail' => '',
                'return_amount' => '',
            ],
            'team_return' => [
                'title' => '',
                'team_return' => '',
                'return_name' => '',
                'team_level' => '',
                'return_log' => '',
                'return_detail' => '',
                'return_amount' => '',
                'return_rate' => '',
                'team_name' => '',
                'return_time' => '',
            ],
            'full_return' => [
                'title' => '',
                'full_return' => '',
                'return_name' => '',
                'full_return_log' => '',
                'return_detail' => '',
                'return_amount' => '',
            ],
            'team_dividend' => [
                'title' => '',
                'team_dividend' => '',
                'team_agent_centre' => '',
                'dividend' => '',
                'flat_prize' => '',
                'award_gratitude' => '',
                'dividend_amount' => '',
            ],
            'area_dividend' => [
                'area_dividend_center' => '',
                'area_dividend' => '',
                'dividend_amount' => '',
            ]
        ];

        $langData = \Setting::get('shop.lang.' . $lang, $data);

        if (is_null($langData)) {
            $langData = $data;
        }

        return show_json(1, $langData);
    }

    protected function moRen()
    {
        return [
            'wechat' => [
                'vue_route' =>[],
                'url' => '',
            ],
            'mini' => [
                'vue_route' => [],
                'url' => '',
            ],
            'wap' => [
                'vue_route' => [],
                'url' => '',
            ],
            'app' => [
                'vue_route' => [],
                'url' => '',
            ],
            'alipay' => [
                'vue_route' => [],
                'url' => '',
            ],
        ];
    }

    public function wxJsSdkConfig()
    {
        $member = \Setting::get('shop.member');

        if (isset($member['wechat_login_mode']) && 1 == $member['wechat_login_mode']) {
            return show_json(1, []);
        }

        $url = \YunShop::request()->url;
        $account = AccountWechats::getAccountByUniacid(\YunShop::app()->uniacid);

        $options = [
            'app_id' => $account->key,
            'secret' => $account->secret
        ];

        $app = new Application($options);

        $js = $app->js;
        $js->setUrl($url);

        $config = $js->config(array(
            'onMenuShareTimeline',
            'onMenuShareAppMessage',
            'showOptionMenu',
            'scanQRCode',
            'updateAppMessageShareData',
            'updateTimelineShareData'
        ));
        $config = json_decode($config, 1);

        $info = [];

        if (\YunShop::app()->getMemberId()) {
            $info = Member::getUserInfos(\YunShop::app()->getMemberId())->first();

            if (!empty($info)) {
                $info = $info->toArray();
            }
        }

        $share = \Setting::get('shop.share');

        if ($share) {
            if ($share['icon']) {
                $share['icon'] = replace_yunshop(yz_tomedia($share['icon']));
            }
        }

        $shop = \Setting::get('shop');
        $shop['icon'] = replace_yunshop(yz_tomedia($shop['logo']));

        if (!is_null(\Config('customer_service'))) {
            $class    = array_get(\Config('customer_service'), 'class');
            $function = array_get(\Config('customer_service'), 'function');
            $ret      = $class::$function(request()->goods_id);
            if ($ret) {
                $shop['cservice'] = $ret;
            }
        }
        if (is_null($share) && is_null($shop)) {
            $share = [
                'title' => '商家分享',
                'icon'  => '#',
                'desc'  => '商家分享'
            ];
        }

        $data = [
            'config' => $config,
            'info'   => $info,   //商城设置
            'shop'   => $shop,
            'share'  => $share   //分享设置
        ];

        return show_json(1, $data);
    }

    public function getParams($request)
    {
        $this->dataIntegrated($this->index($request, true), 'home');
        $this->dataIntegrated($this->isValidatePage($request, true), 'page');
        $this->dataIntegrated($this->getBalance(), 'balance');
        $this->dataIntegrated($this->getLangSetting(), 'lang');
        $this->dataIntegrated($this->wxJsSdkConfig(), 'config');

        return $this->successJson('', $this->apiData);
    }

}
