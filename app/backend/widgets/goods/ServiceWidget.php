<?php
/**
* Author 芸众商城 www.yunzshop.com
* Date: 2018/5/22
*/

namespace app\backend\widgets\goods;

use app\common\components\Widget;
use app\common\facades\Setting;
use app\common\models\goods\GoodsService;
use Yunshop\ServiceFee\models\ServiceFeeModel;

class ServiceWidget extends Widget
{
	
	public function run()
    {

        $service = ServiceFeeModel::select()->ofGoodsId($this->goods_id)->first();
        if ($service->on_shelf_time && $service->lower_shelf_time) {
        	$time['starttime'] = $service->on_shelf_time;
        	$time['endtime'] = $service->lower_shelf_time;
        } else {
        	$time['starttime'] = time();
        	$time['endtime'] = strtotime('1 month');
        }

        return view('goods.widgets.service', [
            'service' =>  $service,
            'time' => $time,
        ])->render();
    }
}