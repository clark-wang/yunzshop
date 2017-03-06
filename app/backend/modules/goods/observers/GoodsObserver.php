<?php

namespace app\backend\modules\goods\observers;

use app\backend\modules\goods\models\Discount;
use app\backend\modules\goods\models\Share;
use app\backend\modules\goods\services\DiscountService;
use app\backend\modules\goods\services\Privilege;
use app\backend\modules\goods\services\PrivilegeService;
use app\common\models\Goods;
use Illuminate\Database\Eloquent\Model;


/**
 * Created by PhpStorm.
 * User: luckystar_D
 * Date: 2017/2/28
 * Time: 上午11:24
 */
class GoodsObserver extends \app\common\observers\BaseObserver
{


    public function saving(Model $model)
    {

        if ($model->share) {
            return Share::validator($model->share);
        }
        if ($model->privilege) {
            $model->privilege['show_levels'] = PrivilegeService::arrayToSting($model->privilege['show_levels']);
            return Privilege::validator($model->privilege);
        }
        if ($model->discount) {
            return Discount::validator($model->discount);
        }
        if ($model->notices) {
            return Notices::validator($model->notices);
        }

    }


    public function saved(Model $model)
    {
        if ($model->share) {
            $share = new Share();
            $share->setRawAttributes($model->share);
            $share->save();
        }
        if ($model->privilege) {
            $privilege = new Privilege();
            $model->privilege['show_levels'] = PrivilegeService::stringToArray($model->privilege['show_levels']);
            $privilege->setRawAttributes($model->privilege);
            $privilege->save();
        }
        if ($model->discount) {
            $discounts = DiscountService::resetArray($model->discount);
            foreach ($discounts as $discount) {
                $discountModel = new Discount();
                $discountModel->setRawAttributes($discount);
                $discountModel->save();
            }
        }
        if ($model->notices) {
            $notice = new Notices();
            $notice->setRawAttributes($model->notices);
            $notice->save();
        }
    }

    public function created(Model $model)
    {
//        if ($model->share) {
//            Share::createdShare($model->share);
//        }
//        if ($model->privilege) {
//            $model->privilege['show_levels'] = PrivilegeService::stringToArray($model->privilege['show_levels']);
//            Privilege::createdPrivilege($model->privilege);
//        }
//        if ($model->discount) {
//            $discounts = DiscountService::resetArray($model->discount);
//            foreach ($discounts as $discount) {
//                Discount::createdDiscount($discount);
//            }
//        }
//        if ($model->widgets['notices']) {
//            $notices_data = [
//                'goods_id' => $model->id,
//                'uid' => $model->widgets['notices']['uid']
//            ];
//            foreach ($model->widgets['notices']['type'] as $type) {
//                $notices_data['type'] = $type;
//                Notice::createdNotices($notices_data);
//            }
//        }
//        if (isset($model->widgets['sale']) && $model->widgets['sale']) {
//
//            Sale::relationSave($model->id,$model->widgets['sale']);
//
//        }
        $this->_pluginObserver($model,'created');
        
    }

    public function updating(Model $model)
    {
        if ($model->share) {
            return Share::validator($model->share);
        }
        if ($model->privilege) {
            $model->privilege['show_levels'] = PrivilegeService::arrayToSting($model->privilege['show_levels']);
            return Privilege::validator($model->privilege);
        }
        if ($model->discount) {
            return Discount::validator($model->discount);
        }

    }

    public function updated(Model $model)
    {
//        if ($model->share) {
//            Share::updatedShare($model->share);
//        }
//        if ($model->privilege) {
//            $model->privilege['show_levels'] = PrivilegeService::stringToArray($model->privilege['show_levels']);
//            Privilege::updatedPrivilege($model->privilege);
//        }
//        if ($model->discount) {
//            Discount::deletedDiscount($model->goodsId);
//            $discounts = DiscountService::resetArray($model->discount);
//            foreach ($discounts as $discount) {
//                Discount::createdDiscount($discount);
//            }
//
//        }
//
//        if ($model->widgets['notices']) {
//            Notices::deletedNotices($model->id);
//            $notices_data = [
//                'goods_id' => $model->id,
//                'uid' => $model->widgets['notices']['uid']
//            ];
//            foreach ($model->widgets['notices']['type'] as $type) {
//                $notices_data['type'] = $type;
//                Notice::createdNotices($notices_data);
//            }
//
//            //Notice::relationSave($model->id,$model->widgets['notice'],true);
//        }
//        if (isset($model->widgets['sale']) && $model->widgets['sale']) {
//            Sale::relationSave($model->id,$model->widgets['sale'],true);
//
//        }

        $this->_pluginObserver($model,'updated');
    }

    public function deleted(Model $model)
    {
//        if (!empty(Share::getInfo($model->goodsId))) {
//            Share::deletedShare($model->goodsId);
//        }
//        if (!empty(Privilege::getInfo($model->goodsId))) {
//            Privilege::deletedPrivilege($model->goodsId);
//        }
//        if (!empty(Discount::getInfo($model->goodsId))) {
//            Discount::deletedDiscount($model->goodsId);
//        }
//        if (!empty(Notices::getInfo($model->id))) {
//            Notices::deletedNotices($model->id);
//        }

        $this->_pluginObserver($model,'deleted');
    }

    private function _pluginObserver($model, $operate = 'created')
    {
        $observerGoods = \Config::get('observer.goods');
        if($observerGoods){
            foreach ($observerGoods as $pluginName=>$pluginOperators){
                if(isset($pluginOperators) && $pluginOperators) {
                    $class = array_get($pluginOperators,'class');
                    $function =array_get($pluginOperators,'function');
                    $data = array_get($model->widgets,$pluginName,[]);
                    if(class_exists($class) && method_exists($class,$function)){
                        $class::$function($model->id, $data, $operate);
                    }
                }
            }
        }
    }
}