<?php
/**
 * Created by PhpStorm.
 * Author: 芸众商城 www.yunzshop.com
 * Date: 2017/4/26
 * Time: 上午11:32
 */

namespace app\common\models;


class OrderPay extends BaseModel
{
    public $table = 'yz_order_pay';
    protected $guarded = ['id'];
    protected $casts = ['order_ids'=>'json'];
}