<?php
/**
 * Created by PhpStorm.
 * Author: 芸众商城 www.yunzshop.com
 * Date: 03/03/2017
 * Time: 12:19
 */

namespace app\backend\widgets\goods;


use app\common\components\Widget;
use app\backend\modules\goods\models\Discount;
use app\backend\modules\member\models\MemberLevel;
use app\backend\modules\member\models\MemberGroup;

class DiscountWidget extends Widget
{

    public function run()
    {
        $discounts = new Discount();
        $discountValue = array();
        if ($this->goods_id && Discount::getList($this->goods_id)) {
            $discounts = Discount::getList($this->goods_id);
            foreach ($discounts as $key => $discount) {
                $discountValue[$discount['level_id']] =   $discount['discount_value'];
            }
        }
        $levels = MemberLevel::getMemberLevelList();
        $groups = MemberGroup::getMemberGroupList();
        return view('goods.widgets.discount', [
            'discount' => $discounts->toArray(),
            'discountValue' => $discountValue,
            'levels' => $levels,
            'groups' => $groups
        ])->render();
    }
}