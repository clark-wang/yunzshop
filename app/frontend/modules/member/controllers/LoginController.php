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
use app\frontend\modules\member\services\factory\MemberFactory;

class LoginController extends ApiController
{
    protected $publicController = ['Login'];
    protected $publicAction = ['index'];
    protected $ignoreAction = ['index'];

    public function index()
    {
        $type = \YunShop::request()->type ;

        if (empty($type) || $type == 'undefined') {
            $type = Client::getType();
        }
        
        if (!empty($type)) {
                $member = MemberFactory::create($type);

                if ($member !== NULL) {
                    $msg = $member->login();

                    if (!empty($msg)) {
                        if ($msg['status'] == 1) {
                            return $this->successJson($msg['json'], ['status'=> $msg['status'], 'return_url' => $_COOKIE['__cookie_client_url']?:'']);
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
}