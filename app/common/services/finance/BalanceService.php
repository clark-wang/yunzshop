<?php
/**
 * Created by PhpStorm.
 * User: libaojia
 * Date: 2017/4/13
 * Time: 上午11:54
 */

namespace app\common\services\finance;



use app\backend\modules\member\models\Member;
use app\common\exceptions\AppException;
use app\common\models\finance\Balance;

abstract class BalanceService
{
    protected $memberModel;

    protected $balanceModel;


    protected $data;

    protected $type;

    protected $service_type;




    public function test()
    {

    }

    abstract protected function getNewMoney();

    abstract protected function getMemberInfo();

    abstract protected function attachedType();



    //余额明细记录写入 protected
    protected function updateBalanceRecord()
    {
        $this->balanceModel = new Balance();

        $this->balanceModel->fill($this->getRecordData());
        $validator = $this->balanceModel->validator();
        if ($validator->fails()) {
            throw new AppException($validator->messages());
        }
        if ($this->balanceModel->save()) {
            return $this->updateMemberBalance();
        }
        throw new AppException('余额变动记录写入失败，请联系管理员！');
    }

    //修改会员余额
    protected function updateMemberBalance()
    {
        $this->memberModel->credit2 = $this->getNewMoney();
        if ($this->memberModel->save()) {
            return true;
        }
        throw new AppException('会员余额写入失败，请联系管理员、');
    }




    protected function getRecordData()
    {
        return array(
            'uniacid'       => \YunShop::app()->uniacid,
            'member_id'     => \YunShop::app()->getMemberId(),        // 会员ID
            'old_money'     => $this->memberModel->credit2 ?: 0,
            'change_money'  => $this->data['money'],     // 改变余额值 100 或 -100
            'new_money'     => $this->getNewMoney() > 0 ? $this->getNewMoney() : 0,
            'type'          => $this->type,
            'service_type'  => $this->service_type,
            'serial_number' => $this->data['serial_number'] ?: '',    // 订单号或流水号，有订单号记录的直接写订单号，未做记录的可以为空
            'operator'      => $this->data['operator'],         // 来源，-2会员，-1，订单，0 商城， 1++ 插件ID
            'operator_id'   => $this->data['operator_id'],      // 来源ID，如：文章营销某一篇文章的ID，订单ID，海报ID
            'remark'        => $this->data['remark'],
        );
    }




    // todo 应该移到余额充值
    public static function attachedTypeName($model)
    {
        switch ($model->type)
        {
            case \app\common\services\finance\Balance::INCOME:
                return '收入';
                break;
            case \app\common\services\finance\Balance::EXPENDITURE:
                return '支出';
                break;
            default:
                return '';
        }

    }


}