<?php
/**
 * Created by PhpStorm.
 * Author: 芸众商城 www.yunzshop.com
 * Date: 2017/3/27
 * Time: 下午1:52
 */

namespace app\common\models;

use app\backend\models\BackendModel;
use app\backend\modules\finance\services\IncomeService;

class Income extends BackendModel
{
    public $table = 'yz_member_income';

    public $timestamps = true;

    public $widgets = [];

    public $attributes = [];

    protected $guarded = [];

    public $StatusService;

    public $PayStatusService;

    protected $appends = ['status_name', 'pay_status_name'];

    /**
     * @return mixed
     */
    public function getStatusService()
    {
        if (!isset($this->StatusService)) {

            $this->StatusService = IncomeService::createStatusService($this);
        }
        return $this->StatusService;
    }

    /**
     * @return mixed
     */
    public function getStatusNameAttribute()
    {
        return $this->getStatusService();
    }

    public function getPayStatusService()
    {
        if (!isset($this->PayStatusService)) {

            $this->PayStatusService = IncomeService::createPayStatusService($this);
        }
        return $this->PayStatusService;
    }

    public function getPayStatusNameAttribute()
    {
        return $this->getPayStatusService();
    }

    /**
     * @param $id
     * @return mixed
     */
    public static function getIncomeFindId($id)
    {
        return self::find($id);
    }

    /**
     * @param $id
     * @return mixed
     */
    public static function getIncomeById($id)
    {
        return self::uniacid()
            ->where('id', $id);

    }

    /**
     * @param $ids
     * @return mixed
     */
    public static function getIncomeByIds($ids)
    {
        return self::uniacid()
            ->whereIn('id', explode(',', $ids));
    }


    public function incometable()
    {
        return $this->morphTo();
    }

    /**
     * @return mixed
     */
    public static function getIncomes()
    {
        return self::uniacid();
    }

    public static function getIncomeInMonth($search)
    {
        $model = self::select('create_month');
        $model->uniacid();
        $model->with(['hasManyIncome' => function ($query) use ($search) {
            $query->select('id', 'create_month', 'incometable_type', 'type_name', 'amount', 'created_at');
            if ($search['type']) {
                $query->where('incometable_type', $search['type']);
            }
            $query->where('member_id', \YunShop::app()->getMemberId());
            $query->orderBy('id', 'desc');
            return $query->get();
        }]);
        $model->groupBy('create_month');
        $model->orderBy('create_month', 'desc');
        return $model;
    }

    public static function getDetailById($id)
    {
        $model = self::uniacid();
        $model->select('detail');
        $model->where('id', $id);
        return $model;
    }

    public static function getWithdraw($type, $typeId, $status)
    {
        return self::where('type', 'commission')
            ->where('member_id', \YunShop::app()->getMemberId())
            ->whereIn('id', explode(',', $typeId))
            ->update(['status' => $status]);
    }

    public static function updatedWithdraw($type, $typeId, $status)
    {
        return self::where('member_id', \YunShop::app()->getMemberId())
            ->whereIn('id', explode(',', $typeId))
            ->update(['status' => $status]);
    }

    public static function updatedIncomeStatus($type, $typeId, $status)
    {
        return self::where('member_id', \YunShop::app()->getMemberId())
            ->whereIn('id', explode(',', $typeId))
            ->update(['status' => $status]);
    }

    public function hasManyIncome()
    {
        return $this->hasMany(self::class, "create_month", "create_month");
    }

    public static function updatedIncomePayStatus($id, $updatedData)
    {
        return self::where('id', $id)
            ->update($updatedData);
    }

    public static function getIncomesList($search)
    {
        $model = self::uniacid();
        $model->select('id', 'create_month', 'incometable_type', 'type_name', 'amount', 'created_at');
        if ($search['type']) {
            $model->where('incometable_type', $search['type']);
        }
        $model->where('member_id', \YunShop::app()->getMemberId());
        $model->orderBy('id', 'desc');

        return $model;
    }


}