<?php
if (!defined('IN_IA')) {
    exit('Access Denied');
}
global $_W, $_GPC;

$operation = empty($_GPC['op']) ? 'display' : $_GPC['op'];
if ($operation == 'display') {
    if ($_W['isajax']) {
        if ($_W['ispost']) {
            $mc = $_GPC['memberdata'];
            $mobile = !empty($mc['mobile']) ? $mc['mobile'] : show_json(0, '手机号不能为空！');
            $password = !empty($mc['password']) ? $mc['password'] : show_json(0, '密码不能为空！');
            $info = pdo_fetch('select * from ' . tablename('sz_yi_member') . ' where  mobile=:mobile and uniacid=:uniacid and pwd=:pwd limit 1', array(
                    ':uniacid' => $_W['uniacid'],
                    ':mobile' => $mobile,
                    ':pwd' => md5($password),
                ));
            //pdo_debug();
            $preUrl = $_COOKIE['preUrl'] ? $_COOKIE['preUrl'] : $this->createMobileUrl('member/info');
            if($info){
                $lifeTime = 24 * 3600 * 3;
                session_set_cookie_params($lifeTime);
                @session_start();
                $cookieid = "__cookie_sz_yi_userid_{$_W['uniacid']}";
                setcookie($cookieid, base64_encode($info['openid']));
                if(!isMobile()){
                    $openid = base64_decode($_COOKIE[$cookieid]);
                    $member_info = pdo_fetch('select realname,nickname from ' . tablename('sz_yi_member') . ' where   uniacid=:uniacid and openid=:openid limit 1', array(
                        ':uniacid' => $_W['uniacid'],
                        ':openid' => $openid,
                    ));
                    $member_name = !empty($member_info['realname']) ? $member_info['realname'] : $member_info['nickname'];
                    $member_name = !empty($member_name) ? $member_name : "未知";
                    setcookie('member_name', base64_encode($member_info['realname']));
                }
                show_json(1, array(
                    'preurl' => $preUrl
                ));
            }
            else{
                show_json(0, "用户名或密码错误！");
            }
        }
    }
}
include $this->template('member/login');