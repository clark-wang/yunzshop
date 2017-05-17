<?php

namespace app\backend\modules\coupon\controllers;

use app\common\components\BaseController;
use app\backend\modules\member\models\MemberLevel;
use app\backend\modules\member\models\MemberGroup;
use app\common\models\MemberCoupon;
use app\common\models\McMappingFans;
use app\common\models\Member;
use app\common\models\Coupon;
use app\common\models\CouponLog;
use app\backend\modules\coupon\services\Message;
use EasyWeChat\Message\News;

class SendCouponController extends BaseController
{
    const BY_MEMBERIDS = 1;
    const BY_MEMBER_LEVEL = 2;
    const BY_MEMBER_GROUP = 3;
    const TO_ALL_MEMBERS = 4;

    public $failedSend = []; //发送失败时的记录
    public $adminId; //后台操作者的ID

    public function index()
    {
        $couponId = \YunShop::request()->id;
        $couponModel = Coupon::getCouponById($couponId);

        //获取会员等级列表
        $memberLevels = MemberLevel::getMemberLevelList();

        //获取会员分组列表
        $memberGroups = MemberGroup::getMemberGroupList();

        if($_POST) {

            //获取后台操作者的ID
            $this->adminId = \YunShop::app()->uid;

            //获取会员 Member ID
            $sendType = \YunShop::request()->sendtype;
            switch ($sendType) {
                case self::BY_MEMBERIDS:
                    $membersScope = trim(\YunShop::request()->send_memberid);
                    $patternMatchNumArray = preg_match('/(\d+,)+(\d+,?)/', $membersScope); //匹配比如 "2,3,78"或者"2,3,78,"
                    $patternMatchSingleNum = preg_match('/(\d+)(,)?/',$membersScope); //匹配单个数字
                    if ($patternMatchNumArray || $patternMatchSingleNum) {
                        $patternMatch = true;
                    } else{
                        $patternMatch = false;
                    }
                    $memberIds = explode(',', $membersScope);
                    break;
                case self::BY_MEMBER_LEVEL: //根据"会员等级"获取 Member IDs
                    $sendLevel = \YunShop::request()->send_level;
                    $res = MemberLevel::getMembersByLevel($sendLevel);
                    if($res['member']->isEmpty()){
                        $memberIds = '';
                    } else{
                        $res = $res->toArray();
                        $memberIds = array_column($res['member'], 'member_id'); //提取member_id组成新的数组
                    }
                    break;
                case self::BY_MEMBER_GROUP: //根据"会员分组"获取 Member IDs
                    $sendGroup = \YunShop::request()->send_group;
                    $res = MemberGroup::getMembersByGroupId($sendGroup);
                    if($res['member']->isEmpty()){
                        $memberIds = '';
                    } else{
                        $res = $res->toArray();
                        $memberIds = array_column($res['member'], 'member_id'); //提取member_id组成新的数组
                    }
                    break;
                case self::TO_ALL_MEMBERS:
                    $res = Member::getMembersId();
                    if(!$res){
                        $members = '';
                    } else{
                        $members = $res->toArray();
                    }
                    $memberIds = array_column($members, 'uid');
                    break;
                default:
                    $memberIds = '';
            }

            //获取发放的数量
            $sendTotal = \YunShop::request()->send_total;

            if (empty($memberIds)){
                $this->error('该发放类型下还没有用户');
            } elseif($sendTotal < 1){
                $this->error('发放数量必须为整数, 而且不能小于 1');
            } elseif (isset($patternMatch) && !$patternMatch) {
                $this->error('Member ID 填写不正确, 请重新设置');
            } else{

                //发放优惠券
                $responseData = [
                    'title' => $couponModel->resp_title,
                    'image' => $couponModel->resp_thumb,
                    'description' => $couponModel->resp_desc ?: '你获得了 1 张优惠券 -- "'.$couponModel->name.' "',
                    'url' => $couponModel->resp_url ?: yzAppFullUrl('home'),
                ];
                $res = $this->sendCoupon($couponModel, $memberIds, $sendTotal, $responseData);
                if ($res){
                    return $this->message('手动发送优惠券成功');
                } else{
                    return $this->message('有部分优惠券未能发送, 请检查数据库','','error');
                }
            }
        }

        return view('coupon.send', [
            'send_total' => isset($sendTotal) ? $sendTotal : 0,
            'sendtype' => isset($sendType) ? $sendType : 1,
            'memberLevels' => $memberLevels, //用户等级列表
            'memberGroups' => $memberGroups, //用户分组列表
            'send_level' => isset($sendLevel) ? $sendLevel : 1,
            'memberGroupId' => isset($sendGroup) ? $sendGroup : 1,
            'agentLevelId' => isset($sendLevel) ? $sendLevel : 1,
        ])->render();
    }


    //发放优惠券
    //array $members
    public function sendCoupon($couponModel, $memberIds, $sendTotal, $responseData)
    {
        $data = [
            'uniacid' => \YunShop::app()->uniacid,
            'coupon_id' => $couponModel->id,
            'get_type' => 0,
            'used' => 0,
            'get_time' => strtotime('now'),
        ];

        foreach ($memberIds as $memberId) {

            //获取Openid
            $memberOpenid = McMappingFans::getFansById($memberId)->openid;

            for ($i = 0; $i < $sendTotal; $i++){
                $memberCoupon = new MemberCoupon;
                $data['uid'] = $memberId;
                $res = $memberCoupon->create($data);

                //写入log
                if ($res){ //发放优惠券成功
                    $log = '手动发放优惠券成功: 管理员( ID 为 '.$this->adminId.' )成功发放 '.$sendTotal.' 张优惠券( ID为 '.$couponModel->id.' )给用户( Member ID 为 '.$memberId.' )';
                } else{ //发放优惠券失败
                    $log = '手动发放优惠券失败: 管理员( ID 为 '.$this->adminId.' )发放优惠券( ID为 '.$couponModel->id.' )给用户( Member ID 为 '.$memberId.' )时失败!';
                    $this->failedSend[] = $log; //失败时, 记录 todo 最后需要展示出来
                }
                $this->log($log, $couponModel, $memberId);
            }

            if(!empty($responseData['title']) && $memberOpenid){
                $nickname = Member::getMemberById($memberId)->nickname;
                $dynamicData = [
                    'nickname' => $nickname,
                    'couponname' => $couponModel->name,
                ];
                $responseData['title'] = self::dynamicMsg($responseData['title'], $dynamicData);
                $news = new News($responseData);
                Message::sendNotice($memberOpenid, $news);
            }
        }

        if(empty($this->failedSend)){
            return true;
        } else {
            return false;
        }
    }

    //写入日志
    public function log($log, $couponModel, $memberId)
    {
        $logData = [
            'uniacid' => \YunShop::app()->uniacid,
            'logno' => $log,
            'member_id' => $memberId,
            'couponid' => $couponModel->id,
            'paystatus' => 0, //todo 手动发放的不需要支付?
            'creditstatus' => 0, //todo 手动发放的不需要支付?
            'paytype' => 0, //todo 这个字段什么含义?
            'getfrom' => 0,
            'status' => 0,
            'createtime' => time(),
        ];
        $res = CouponLog::create($logData);
        return $res;
    }

    //动态显示内容
    protected static function dynamicMsg($msg, $data)
    {
        if (preg_match('/\[nickname\]/', $msg)){
            $msg = str_replace('[nickname]', $data['nickname'], $msg);
        }
        if (preg_match('/\[couponname\]/', $msg)){
            $msg = str_replace('[couponname]', $data['couponname'], $msg);
        }
        return $msg;
    }
}
