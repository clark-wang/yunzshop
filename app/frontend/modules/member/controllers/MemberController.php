<?php
/**
 * Created by PhpStorm.
 * User: libaojia
 * Date: 2017/3/1
 * Time: 下午4:39
 */

namespace app\frontend\modules\member\controllers;

use app\backend\modules\member\models\MemberRelation;
use app\common\components\ApiController;
use app\common\models\AccountWechats;
use app\common\models\Goods;
use app\common\models\MemberShopInfo;
use app\common\models\Order;
use app\frontend\modules\member\models\MemberModel;
use app\frontend\modules\member\models\SubMemberModel;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use app\frontend\modules\order\models\OrderListModel;


class MemberController extends ApiController
{

    /**
     * 获取用户信息
     *
     * @return array
     */
    public function getUserInfo()
    {
        //$member_id = \YunShop::request()->uid;

        $member_id = \YunShop::app()->getMemberId();

        if (!empty($member_id)) {
            $member_info = MemberModel::getUserInfos($member_id)->first();

            if (!empty($member_info)) {
                $member_info = $member_info->toArray();

                if (!empty($member_info['yz_member'])) {
                    if (!empty($member_info['yz_member']['group'])) {
                        $member_info['group_id'] = $member_info['yz_member']['group']['id'];
                        $member_info['group_name'] = $member_info['yz_member']['group']['group_name'];
                    }

                    if (!empty($member_info['yz_member']['level'])) {
                        $member_info['level_id'] = $member_info['yz_member']['level']['id'];
                        $member_info['level_name'] = $member_info['yz_member']['level']['level_name'];
                    }
                }

                $order_info = Order::getOrderCountGroupByStatus([Order::WAIT_PAY,Order::WAIT_SEND,Order::WAIT_RECEIVE,Order::COMPLETE]);

                $member_info['order'] = $order_info;
                return $this->successJson('', $member_info);
            } else {
                return $this->errorJson('用户不存在');
            }

        } else {
            return $this->errorJson('缺少访问参数');
        }

    }

    /**
     * 检查会员推广资格
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMemberRelationInfo()
    {
        $info = MemberRelation::getSetInfo()->first()->toArray();

        $member_info = SubMemberModel::getMemberShopInfo(\YunShop::app()->getMemberId());

        if (empty($info)) {
            return $this->errorJson('缺少参数');
        }

        if (empty($member_info))
        {
            return $this->errorJson('会员不存在');
        } else {
            $data = $member_info->toArray();
        }

        $account = AccountWechats::getAccountInfoById(\YunShop::app()->uniacid);
        switch ($info['become']) {
            case 0:
            case 1:
                $apply_qualification = 1;
                $mid = \YunShop::request()->mid ? \YunShop::request()->mid : 0;
                $parent_name = '';

                if (empty($mid)) {
                    $parent_name = '总店';
                } else {
                    $parent_model = MemberModel::getMemberById($mid);

                    if (!empty($parent_model)) {
                        $parent_member = $parent_model->toArray();

                        $parent_name = $parent_member['realname'];
                    }
                }

                $member_model = MemberModel::getMemberById(\YunShop::app()->getMemberId());

                if (!empty($member_model)) {
                    $member = $member_model->toArray();
                }
                break;
           case 2:
               $apply_qualification = 2;
               $cost_num  = OrderListModel::getCostTotalNum(\YunShop::app()->getMemberId());

               if ($info['become_check'] && $cost_num >= $info['become_ordercount']) {
                   $apply_qualification = 5;
               }
               break;
           case 3:
               $apply_qualification = 3;
               $cost_price  = OrderListModel::getCostTotalPrice(\YunShop::app()->getMemberId());

               if ($info['become_check'] && $cost_price >= $info['become_moneycount']) {
                   $apply_qualification = 6;
               }
               break;
           case 4:
               $apply_qualification = 4;
               $goods = Goods::getGoodsById($info['become_goods_id']);
               $goods_name = '';

               if (!empty($goods)) {
                   $goods = $goods->toArray();

                   $goods_name = $goods['title'];
               }

               if ($info['become_check'] && MemberRelation::checkOrderGoods($info['become_goods_id'])) {
                   $apply_qualification = 7;
               }
               break;
           default:
               $apply_qualification = 0;
       }

       $relation = [
           'switched' => $info['status'],
           'become' => $apply_qualification,
           'become1' => ['shop_name' => $account['name'],'parent_name' => $parent_name, 'realname' => $member['realname'], 'mobile' => $member['mobile']],
           'become2' => ['shop_name' => $account['name'], 'total' => $info['become_ordercount'], 'cost' => $cost_num],
           'become3' => ['shop_name' => $account['name'], 'total' => $info['become_moneycount'], 'cost' => $cost_price],
           'become4' =>['shop_name' => $account['name'], 'goods_name' => $goods_name, 'goods_id' => $info['become_goods_id']],
           'is_agent' => $data['is_agent'],
           'status' => $data['status'],
           'account' => $account['name']
       ];

        return $this->successJson('', $relation);
    }

    /**
     * 会员是否有推广权限
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function isAgent()
    {
        $uid = \YunShop::app()->getMemberId();

        MemberRelation::checkAgent($uid);

        $member_info = SubMemberModel::getMemberShopInfo($uid);

        if (empty($member_info)) {
            return $this->errorJson('会员不存在');
        } else {
            $data = $member_info->toArray();
        }

        return $this->successJson('', ['is_agent' => $data['is_agent']]);
    }

    /**
     * 会员推广二维码
     *
     * @param $url
     * @param string $extra
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAgentQR($url, $extra='')
    {
        if (empty(\YunShop::app()->getMemberId())) {
            return $this->errorJson('请重新登录');
        }

        $url = $url . '&mid=' . \YunShop::app()->getMemberId();

        if (!empty($extra)) {
            $extra = '_' . $extra;
        }

        $extend = 'png';
        $filename = \YunShop::app()->uniacid . '_' . \YunShop::app()->getMemberId() . $extra . '.' . $extend;

        echo QrCode::format($extend)->size(100)->generate($url, storage_path('qr/') . $filename);

        return $this->successJson('', ['qr' => storage_path('qr/') . $filename]);
    }

    /**
     * 用户推广申请
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function addAgentApply()
    {
        $mid = (\YunShop::request()->mid && \YunShop::request()->mid != 'undefined') ? \YunShop::request()->mid : 0;

        if (!\YunShop::app()->getMemberId()) {
            return $this->errorJson('请重新登录');
        }
        $sub_member_model = SubMemberModel::getMemberShopInfo(\YunShop::app()->getMemberId());

        $sub_member_model->parent_id = $mid;
        $sub_member_model->status = 1;

        if (!$sub_member_model->save()) {
           return $this->errorJson('会员上级信息保存失败');
        }

        $realname = \YunShop::request()->realname;
        $moible =\YunShop::request()->mobile;

        $member_mode = MemberModel::getMemberById(\YunShop::app()->getMemberId());

        $member_mode->realname = $realname;
        $member_mode->mobile = $moible;
        if (!$member_mode->save()) {
            return $this->errorJson('会员信息保存失败');
        }

        return $this->successJson('ok');
    }

    /**
     * 获取我的下线
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMyAgentCount()
    {
         return $this->successJson('', ['count'=>MemberShopInfo::getAgentCount()]);
    }

    /**
     * 我的推荐人
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMyReferral()
    {
        $member_info = MemberModel::getMyReferrerInfo(\YunShop::app()->getMemberId())->first();

        if (!empty($member_info)) {
            $member_info = $member_info->toArray();

            $referrer_info = MemberModel::getUserInfos($member_info['yz_member']['parent_id'])->first();

            if (!empty($referrer_info)) {
                $data = [
                  'uid' => $referrer_info['uid'],
                  'avatar' => $referrer_info['avatar'],
                  'nickname' => $referrer_info['nickname'],
                  'level' => $referrer_info['yz_member']['level']['level_name']
                ];
                return $this->successJson('',$data);
            } else {
                return $this->errorJson('会员不存在');
            }
        } else {
            return $this->errorJson('会员不存在');
        }
    }

    /**
     * 我推荐的人
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMyAgent()
    {
        $agent_ids = [];

        $agent_info = MemberModel::getMyAgentInfo(\YunShop::app()->getMemberId());
        $agent_model = $agent_info->get();

        if (!empty($agent_model)) {
            $agent_data = $agent_model->toArray();

            foreach ($agent_data as $key => $item) {
                $agent_ids[$key] = $item['uid'];
                $agent_data[$key]['agent_total'] = 0;
            }
        } else {
            return $this->errorJson('数据为空');
        }

        $all_count = MemberShopInfo::getAgentAllCount($agent_ids);

        foreach ($all_count as $k => $rows) {
            foreach ($agent_data as $key => $item) {
                if ($rows['parent_id'] == $item['uid']) {
                    $agent_data[$key]['agent_total'] = $rows['total'];

                    break 1;
                }
            }
        }

        $data = [
            'uid' => $agent_data['uid'],
            'avatar' => $agent_data['avatar'],
            'nickname' => $agent_data['nickname'],
            'order_total' => $agent_data['has_one_order']['total'],
            'order_price' => $agent_data['has_one_order']['sum'],
            'agent_total' => $agent_data['agent_total'],
        ];
        return $this->successJson('', $data);
    }
}