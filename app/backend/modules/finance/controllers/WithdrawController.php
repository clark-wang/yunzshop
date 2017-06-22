<?php
/**
 * Created by PhpStorm.
 * Author: 芸众商城 www.yunzshop.com
 * Date: 2017/3/31
 * Time: 上午11:28
 */

namespace app\backend\modules\finance\controllers;


use app\backend\modules\finance\models\Withdraw;
use app\backend\modules\finance\services\WithdrawService;
use app\common\components\BaseController;
use app\common\facades\Setting;
use app\common\helpers\PaginationHelper;
use app\common\helpers\Url;
use app\common\models\Income;
use app\common\services\finance\BalanceSet;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class WithdrawController extends BaseController
{
    public function set()
    {
        $set = Setting::get('withdraw.balance');
        $resultModel = \YunShop::request()->withdraw;
        if ($resultModel) {
            $validator = null;
            foreach ($resultModel as $key => $item) {
                $validator = (new Withdraw())->validator($item);
                if ($validator->fails()) {
                    $this->error($validator->messages());
                    break;
                }
            }
            if ($validator && !$validator->fails()) {
                foreach ($resultModel as $key => $item) {
                    Setting::set('withdraw.' . $key, $item);

                }
                return $this->message('设置保存成功', Url::absoluteWeb('finance.withdraw.set'));
            }
        }

        return view('finance.withdraw.withdraw-set', [
            'set' => $set
        ])->render();
    }

    public function index()
    {
        $pageSize = 20;

        $starttime = strtotime('-1 month');
        $endtime = time();

        $requestSearch = \YunShop::request()->search;
        if($requestSearch){

            if ($requestSearch['searchtime']) {
                if ($requestSearch['times']['start'] != '请选择' && $requestSearch['times']['end'] != '请选择') {
                    $requestSearch['times']['start'] = strtotime($requestSearch['times']['start']);
                    $requestSearch['times']['end'] = strtotime($requestSearch['times']['end']);
                    $starttime = strtotime($requestSearch['times']['start']);
                    $endtime = strtotime($requestSearch['times']['end']);
                }else{
                    $requestSearch['times'] = '';
                }
            }else{
                $requestSearch['times'] = '';
            }
            $requestSearch = array_filter($requestSearch, function ($item) {
                return $item !== '';// && $item !== 0;
            });
        }
        $configs = Config::get('income');
        foreach ($configs as $config) {
            $type[] = $config['class'];
        }
        $list = Withdraw::getWithdrawList($requestSearch)
            ->whereIn('type',$type)
            ->orderBy('created_at','desc')
            ->paginate($pageSize);

        $pager = PaginationHelper::show($list['total'], $list['current_page'], $list['per_page']);
        $incomeConfug = Config::get('income');
        if(!$requestSearch['searchtime']){
            $requestSearch['times']['start'] = time();
            $requestSearch['times']['end'] = time();
        }
        return view('finance.withdraw.withdraw-list', [
            'list' => $list,
            'pager' => $pager,
            'search' => $requestSearch,
            'starttime' => $starttime,
            'endtime' => $endtime,
            'types' => $incomeConfug
        ])->render();
    }


    public function info()
    {
        $set = Setting::get('plugin.commission');
        $id = intval(\YunShop::request()->id);
        $withdrawModel = Withdraw::getWithdrawById($id)->first();
        if (!$withdrawModel) {
            return $this->message('数据不存在或已被删除!', '', error);
        }

        return view('finance.withdraw.withdraw-info', [
            'item' => $withdrawModel,
            'set' => $set,
        ])->render();
    }

    public function dealt()
    {
        $resultData = \YunShop::request();
        if (isset($resultData['submit_check'])) {
            //提交审核
            $result = $this->submitCheck($resultData['id'], $resultData['audit']);
            return $this->message($result['msg'], yzWebUrl("finance.withdraw.info", ['id' => $resultData['id']]));

        } elseif (isset($resultData['submit_pay'])) {
            //打款
            $result = $this->submitPay($resultData['id'], $resultData['pay_way']);
            return $this->message($result['msg'], yzWebUrl("finance.withdraw.info", ['id' => $resultData['id']]));

        } elseif (isset($resultData['submit_cancel'])) {
            //重新审核
            $result = $this->submitCancel($resultData['id'], $resultData['audit']);
            return $this->message($result['msg'], yzWebUrl("finance.withdraw.info", ['id' => $resultData['id']]));

        }

    }

    public function submitCheck($withdrawId, $incomeData)
    {

        $withdraw = Withdraw::getWithdrawById($withdrawId)->first();
        if ($withdraw->status != '0') {
            return ['msg' => '审核失败,数据不符合提现规则!'];
        }
        $withdrawStatus = "-1";
        $actual_amounts = 0;
        foreach ($incomeData as $key => $income) {
            if ($income) {
                $actual_amounts += Income::getIncomeById($key)->get()->sum('amount');
                $withdrawStatus = "1";
                Income::updatedIncomePayStatus($key, ['pay_status' => '1']);

            } else {
                Income::updatedIncomePayStatus($key, ['pay_status' => '-1']);
            }
        }
        $actual_poundage = $actual_amounts / 100 * $withdraw['poundage_rate'];
        $updatedData = [
            'status' => $withdrawStatus,
            'actual_amounts' => $actual_amounts - $actual_poundage,
            'actual_poundage' => $actual_poundage,
            'audit_at' => time(),
        ];
        $result = Withdraw::updatedWithdrawStatus($withdrawId, $updatedData);
        if ($result) {
            return ['msg' => '审核成功!'];
        }
        return ['msg' => '审核失败!'];
    }

    public function submitCancel($withdrawId, $incomeData)
    {
        $withdraw = Withdraw::getWithdrawById($withdrawId)->first();
        if ($withdraw->status != '-1') {
            return ['msg' => '审核失败,数据不符合提现规则!'];
        }
        $withdrawStatus = "-1";
        $actual_amounts = 0;
        foreach ($incomeData as $key => $income) {
            if ($income) {
                $actual_amounts += Income::getIncomeById($key)->get()->sum('amount');
                $withdrawStatus = "1";
                Income::updatedIncomePayStatus($key, ['pay_status' => '1']);

            } else {
                Income::updatedIncomePayStatus($key, ['pay_status' => '-1']);
            }
        }
        $actual_poundage = $actual_amounts / 100 * $withdraw['poundage_rate'];
        $updatedData = [
            'status' => $withdrawStatus,
            'actual_amounts' => $actual_amounts - $actual_poundage,
            'actual_poundage' => $actual_poundage,
            'audit_at' => time(),
        ];
        $result = Withdraw::updatedWithdrawStatus($withdrawId, $updatedData);
        if ($result) {
            return ['msg' => '审核成功!'];
        }
        return ['msg' => '审核失败!'];
    }


    public function submitPay($withdrawId, $payWay)
    {
        $withdraw = Withdraw::getWithdrawById($withdrawId)->first();
        if ($withdraw->status != '1') {
            return ['msg' => '打款失败,数据不存在或不符合打款规则!'];
        }
        $remark = '提现打款-' . $withdraw->type_name . '-金额:' . $withdraw->actual_amounts . '元,' .
            '手续费:' . $withdraw->actual_poundage;
        if ($payWay == '3') {
            //余额打款

            $resultPay = WithdrawService::balanceWithdrawPay($withdraw, $remark);
            Log::info('MemberId:' . $withdraw->member_id . ', ' . $remark . "打款到余额中!");

        } elseif ($payWay == '2') {
            //支付宝打款

            $resultPay = WithdrawService::alipayWithdrawPay($withdraw, $remark);
            Log::info('MemberId:' . $withdraw->member_id . ', ' . $remark . "支付宝打款中!");

        } elseif ($payWay == '1') {
            //微信打款

            $resultPay = WithdrawService::wechatWithdrawPay($withdraw, $remark);
            Log::info('MemberId:' . $withdraw->member_id . ', ' . $remark . "微信打款中!");

        } elseif ($payWay == '4') {
            //手动打款
            $resultPay = true;
            Log::info('MemberId:' . $withdraw->member_id . ', ' . $remark . "手动打款!");

        }

        if ($resultPay) {
            $updatedData = ['pay_at' => time()];
            Withdraw::updatedWithdrawStatus($withdrawId, $updatedData);
            $result = WithdrawService::otherWithdrawSuccess($withdrawId);
            if ($result) {
                Log::info('打款完成!');
                return ['msg' => '提现打款成功!'];
            }
        }
        return ['msg' => $resultPay];
    }


}