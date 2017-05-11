<?php
/**
 * Created by PhpStorm.
 * User: yangyang
 * Date: 2017/5/2
 * Time: 上午11:51
 */

namespace app\backend\modules\goods\services;

use app\backend\modules\goods\models\GoodsParam;
use app\backend\modules\goods\models\Goods;
use app\backend\modules\goods\models\Brand;
use app\backend\modules\goods\models\GoodsSpec;
use app\backend\modules\goods\models\GoodsOption;
use Setting;

class CreateGoodsService
{
    public $params;
    public $brands;
    public $request;
    public $error = null;
    public $catetory_menus;
    public $goods_model;

    public function __construct($request)
    {
        $this->request = $request;
    }

    public function create()
    {
        $goods_data = $this->request->goods;
        $this->params = new GoodsParam();
        $this->goods_model = new Goods();
        $this->brands = Brand::getBrands()->get();

        if ($goods_data) {
            if (isset($goods_data['thumb_url'])) {
                $goods_data['thumb_url'] = serialize(
                    array_map(function ($item) {
                        return tomedia($item);
                    }, $goods_data['thumb_url'])
                );
            }
            $this->goods_model->setRawAttributes($goods_data);
            $this->goods_model->widgets = $this->request->widgets;
            $this->goods_model->uniacid = \YunShop::app()->uniacid;
            dd($this->goods_model);
            exit;
            $validator = $this->goods_model->validator($this->goods_model->getAttributes());
            if ($validator->fails()) {
                $this->error = $validator->messages();
            } else {
                if ($this->goods_model->save()) {
                    GoodsService::saveGoodsCategory($this->goods_model, $this->request->category, Setting::get('shop.category'));
                    GoodsParam::saveParam($this->request, $this->goods_model->id, \YunShop::app()->uniacid);
                    GoodsSpec::saveSpec($this->request, $this->goods_model->id, \YunShop::app()->uniacid);
                    GoodsOption::saveOption($this->request, $this->goods_model->id, GoodsSpec::$spec_items, \YunShop::app()->uniacid);
                    return ['status' => 1];
                } else {
                    return ['status' => -1];
                }
            }
        }

        $this->catetory_menus = CategoryService::getCategoryMenu(['catlevel' => Setting::get('shop.category')['cat_level']]);
    }
}