<?php
/**
 * Created by PhpStorm.
 * Author: 芸众商城 www.yunzshop.com
 * Date: 2017/3/28
 * Time: 上午10:49
 */

namespace app\common\components;

use app\backend\modules\member\models\MemberRelation;
use app\common\exceptions\MemberNotLoginException;
use app\common\exceptions\ShopException;
use app\common\exceptions\UniAccountNotFoundException;
use app\common\helpers\Client;
use app\common\helpers\Url;
use app\common\models\Member;
use app\common\models\MemberShopInfo;
use app\common\models\UniAccount;
use app\common\services\Session;
use app\frontend\modules\member\services\MemberService;

class ApiController extends BaseController
{
    protected $publicController = [];
    protected $publicAction = [];
    protected $ignoreAction = [];

    /**
     * @throws ShopException
     * @throws UniAccountNotFoundException
     */
    public function preAction()
    {
        parent::preAction();
        if (!UniAccount::checkIsExistsAccount(\YunShop::app()->uniacid)) {
            throw new UniAccountNotFoundException('无此公众号', ['login_status' => -2]);
        }

        $relaton_set = MemberRelation::getSetInfo()->first();

        $type = \YunShop::request()->type;
        $mid = Member::getMid();
        $mark = \YunShop::request()->mark;
        $mark_id = \YunShop::request()->mark_id;
        \Log::info('名片jilu'.$mark,print_r(\YunShop::request(),true));
        if (self::is_alipay() && $type != 8) {
            $type = 8;
        }
        if ($type == 8 && !(app('plugins')->isEnabled('alipay-onekey-login'))) {
            $type = Client::getType();
        }

        if (!MemberService::isLogged()) {
            if (($relaton_set->status == 1 && !in_array($this->action, $this->ignoreAction))
                || ($relaton_set->status == 0 && !in_array($this->action, $this->publicAction))
            ) {
                $this->jumpUrl($type, $mid);
            }
        } else {
            if (!MemberShopInfo::getMemberShopInfo(\YunShop::app()->getMemberId())) {
                Session::clear('member_id');

                if (($relaton_set->status == 1 && !in_array($this->action, $this->ignoreAction))
                    || ($relaton_set->status == 0 && !in_array($this->action, $this->publicAction))
                ) {
                    $this->jumpUrl($type, $mid);
                }
            }

            if (MemberShopInfo::isBlack(\YunShop::app()->getMemberId())) {
                throw new ShopException('黑名单用户，请联系管理员', ['login_status' => -1]);
            }

            //发展下线
            Member::chkAgent(\YunShop::app()->getMemberId(), $mid, $mark ,$mark_id);
        }
    }
    public static function is_alipay()
    {
        if (!empty($_SERVER['HTTP_USER_AGENT']) && strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'alipay') !== false && (app('plugins')->isEnabled('alipay-onekey-login'))) {
            return true;
        }
        return false;
    }
    /**
     * @param $type
     * @param $mid
     * @throws ShopException
     */
    private function jumpUrl($type, $mid)
    {
        if (empty($type) || $type == 'undefined') {
            $type = Client::getType();
        }
        $queryString = ['type'=>$type,'session_id'=>session_id(), 'i'=>\YunShop::app()->uniacid, 'mid'=>$mid];

        if (5 == $type || 7 == $type) {
            throw new MemberNotLoginException('请登录', ['login_status' => 1, 'login_url' => '', 'type' => $type, 'session_id' => session_id(), 'i' => \YunShop::app()->uniacid, 'mid' => $mid]);
        }

        throw new MemberNotLoginException('请登录', ['login_status' => 0, 'login_url' => Url::absoluteApi('member.login.index', $queryString)]);
    }



}