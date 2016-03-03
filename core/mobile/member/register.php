<?php
if (!defined('IN_IA')) {
    exit('Access Denied');
}
global $_W, $_GPC;
 
$preUrl = $_COOKIE['preUrl'];
if ($_W['isajax']) {
    if ($_W['ispost']) {
        $mc = $_GPC['memberdata'];
        $info = pdo_fetch('select * from ' . tablename('sz_yi_member') . ' where mobile=:mobile and uniacid=:uniacid limit 1', array(
                ':uniacid' => $_W['uniacid'],
                ':mobile' => $mc['mobile']
            ));

        if($info){
            show_json(0, array(
                'msg' => '手机号码已存在'
            ));
            exit;
        }

        $member = array(
                'uniacid' => $_W['uniacid'],
                'uid' => 0,
                'openid' => 'u'.md5($mc['mobile']),
                'mobile' => $mc['mobile'],
                'pwd' => md5($mc['password']),   //md5
                'createtime' => time(),
                'status' => 0,
                'regtype' => 2,
            );
        //print_r($member);
        pdo_insert('sz_yi_member', $member);

        $lifeTime = 24 * 3600 * 3;
        session_set_cookie_params($lifeTime);
        @session_start();
        $cookieid = "__cookie_sz_yi_userid_{$_W['uniacid']}";
        setcookie($cookieid, base64_encode($member['openid']));

        show_json(1, array(
            'preurl' => $preUrl
        ));
    }
}
include $this->template('member/register');
