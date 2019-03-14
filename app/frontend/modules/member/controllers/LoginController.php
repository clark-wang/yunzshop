<?php
/**
 * Created by PhpStorm.
 * Author: 芸众商城 www.yunzshop.com
 * Date: 17/2/22
 * Time: 上午11:56
 */

namespace app\frontend\modules\member\controllers;

use app\common\components\ApiController;
use app\common\helpers\Client;
use app\common\helpers\Url;
use app\common\models\Member;
use app\common\services\Session;
use app\frontend\modules\member\services\factory\MemberFactory;
use app\frontend\modules\member\services\MemberService;

class LoginController extends ApiController
{
    protected $publicController = ['Login'];
    protected $publicAction = ['index', 'phoneSetGet', 'chekAccount'];
    protected $ignoreAction = ['index', 'phoneSetGet', 'chekAccount'];

    public function index()
    {
        $type = \YunShop::request()->type ;
        $uniacid = \YunShop::app()->uniacid;
        $mid = Member::getMid();

        if (empty($type) || $type == 'undefined') {
            $type = Client::getType();
        }

        if ($type == 8 && !(app('plugins')->isEnabled('alipay-onekey-login'))) {
            $type = Client::getType();
        }

        if (1 == $type && MemberService::isLogged()) {
            $url = Url::absoluteApp('home', ['i' => $uniacid, 'mid' => $mid]);

            if (Session::get('client_url')) {
                $url = Session::get('client_url');
            }

            //return $this->successJson('ok', ['status'=> 1, 'url' => $url]);
            redirect($url)->send();
        }


        //判断是否开启微信登录
        if (\YunShop::request()->show_wechat_login) {
            return $this->init_login();
        }

        if (!empty($type)) {
                $member = MemberFactory::create($type);

                if ($member !== NULL) {
                    $msg = $member->login();

                    if (!empty($msg)) {
                        if ($msg['status'] == 1) {
                            $url = Url::absoluteApp('member', ['i' => $uniacid, 'mid' => $mid]);

                            if (isset($msg['json']['redirect_url'])) {
                                $url = $msg['json']['redirect_url'];
                            }

                            return $this->successJson($msg['json'], ['status'=> $msg['status'], 'url' => $url]);
                        } else {
                            return $this->errorJson($msg['json'], ['status'=> $msg['status']]);
                        }
                    } else {
                        return $this->errorJson('登录失败', ['status' => 3]);
                    }
                } else {
                    return $this->errorJson('登录异常', ['status'=> 2]);
                }
        } else {
            return $this->errorJson('客户端类型错误', ['status'=> 0]);
        }
    }

    /**
     * 初始化登录，判断是否开启微信登录
     *
     * @return \Illuminate\Http\JsonResponse
     */
    private function init_login () {
        $weixin_oauth = \Setting::get('shop_app.pay.weixin_oauth');
        return $this->successJson('', ['status'=> 1, 'wetach_login' => $weixin_oauth]);
    }

    public function phoneSetGet()
    {
        $phone_oauth = \Setting::get('shop_app.pay.phone_oauth');

        if (empty($phone_oauth)) {
            $phone_oauth = '0';
        }
        return $this->successJson('ok', ['phone_oauth' => $phone_oauth]);
    }

    public function chekAccount()
    {
        $type = \YunShop::request()->type ;

        if (1 == $type) {
            \Log::debug('------chk login2------');
            $member = MemberFactory::create($type);
            $member->chekAccount();
        }
    }
}