<?php
/**
 * Created by PhpStorm.
 * Author: 芸众商城 www.yunzshop.com
 * Date: 17/2/23
 * Time: 上午11:20
 */

namespace app\frontend\modules\member\services;

use app\common\services\Session;
use app\frontend\modules\member\services\MemberService;
use app\frontend\modules\member\models\MemberMiniAppModel;
use app\frontend\modules\member\models\MemberUniqueModel;
use app\frontend\modules\member\models\MemberModel;

class MemberMiniAppService extends MemberService
{
    const LOGIN_TYPE    = 2;

    public function __construct()
    {}

    public function login()
    {
        include dirname(__FILE__ ) . "/../vendor/wechat/wxBizDataCrypt.php";

        $uniacid = \YunShop::app()->uniacid;

        if (config('app.debug')) {
            $appid = 'wx31002d5db09a6719';
            $secret = '217ceb372d5e3296f064593fe2e7c01e';
        }

        $para = \YunShop::request();

        $data = array(
            'appid' => $appid,
            'secret' => $secret,
            'js_code' => $para['code'],
            'grant_type' => 'authorization_code',
        );

        $url = 'https://api.weixin.qq.com/sns/jscode2session';

        $res = \Curl::to($url)
            ->withData($data)
            ->asJsonResponse(true)
            ->get();

        $user_info = json_decode($res['content'], true);

        $data = '';  //json

        if (!empty($para['info'])) {
            $json_data = json_decode($para['info'], true);

            $pc = new \WXBizDataCrypt($appid, $user_info['session_key']);
            $errCode = $pc->decryptData($json_data['encryptedData'], $json_data['iv'], $data);
        }

        if ($errCode == 0) {
            $json_user = json_decode($data, true);
        } else {echo 1;
            return show_json(0,'登录认证失败');
        }

        if (!empty($json_user) && !empty($json_user['unionid'])) {
            $UnionidInfo = MemberUniqueModel::getUnionidInfo($uniacid, $json_user['unionid']);

            if (!empty($UnionidInfo['unionid'])) {
                $types = explode('|',$UnionidInfo['type']);
                $member_id = $UnionidInfo['member_id'];

                if (!in_array(self::LOGIN_TYPE, $types)) {
                    //更新ims_yz_member_unique表
                    MemberUniqueModel::updateData(array(
                        'unique_id'=>$UnionidInfo['unique_id'],
                        'type' => $UnionidInfo['type'] . '|' . self::LOGIN_TYPE
                    ));

                    //添加ims_yz_member_mini_app表
                    MemberMiniAppModel::insertData(array(
                        'uniacid' => $uniacid,
                        'member_id' => $UnionidInfo['member_id'],
                        'openid' => $json_user['openid'],
                        'nickname' => $json_user['nickname'],
                        'avatar' => $json_user['headimgurl'],
                        'gender' => $json_user['sex'],
                        'nationality' => $json_user['country'],
                        'resideprovince' => $json_user['province'] . '省',
                        'residecity' => $json_user['city'] . '市',
                        'created_at' => time()
                    ));
                }
            } else {
                //添加ims_mc_member表
                $member_id = MemberModel::insertData(array(
                    'uniacid' => $uniacid,
                    'groupid' => $json_user['unionid'],
                    'createtime' => TIMESTAMP,
                    'nickname' => $json_user['nickname'],
                    'avatar' => $json_user['headimgurl'],
                    'gender' => $json_user['sex'],
                    'nationality' => $json_user['country'],
                    'resideprovince' => $json_user['province'] . '省',
                    'residecity' => $json_user['city'] . '市'
                ));


                //添加ims_yz_member_unique表
                MemberUniqueModel::insertData(array(
                    'uniacid' => $uniacid,
                    'unionid' => $json_user['unionid'],
                    'member_id' => $member_id,
                    'type' => self::LOGIN_TYPE
                ));

                //添加ims_yz_member_mini_app表
                MemberMiniAppModel::insertData(array(
                    'uniacid' => $uniacid,
                    'member_id' => $member_id,
                    'openid' => $json_user['openid'],
                    'nickname' => $json_user['nickname'],
                    'avatar' => $json_user['headimgurl'],
                    'gender' => $json_user['sex'],
                    'nationality' => $json_user['country'],
                    'resideprovince' => $json_user['province'] . '省',
                    'residecity' => $json_user['city'] . '市',
                    'created_at' => time()
                ));
            }

            Session::set('member_id', $member_id);

            $random = $this->wx_app_session($user_info);

            $result = array('session' => $random, 'wx_token' =>session_id(), 'uid' => $member_id);

            return show_json(1, $result);
        } else {
            return show_json(0);
        }
    }

    /**
     * 小程序登录态
     *
     * @param $user_info
     * @return string
     */
    function wx_app_session($user_info)
    {
        if (empty($user_info['session_key']) || empty($user_info['openid'])) {
            return show_json(0,'登录认证失败！');
        }

        $random = md5(uniqid(mt_rand()));

        $_SESSION['wx_app'] = array($random => iserializer(array('session_key'=>$user_info['session_key'], 'openid'=>$user_info['openid'])));

        return $random;
    }
}