<?php
namespace app\frontend\modules\shop\controllers;

use app\api\Base;
use app\common\components\ApiController;
use app\common\facades\Setting;
use app\common\models\Category;
use app\common\models\Goods;
use app\common\models\Slide;
use Illuminate\Support\Facades\DB;

/**
 * Created by PhpStorm.
 * Author: 芸众商城 www.yunzshop.com
 * Date: 2017/3/3
 * Time: 22:16
 */
class IndexController extends ApiController
{
    protected $publicAction = ['getDefaultIndex'];

    public function getDefaultIndex()
    {
        $set = Setting::get('shop.category');
        $set['cat_adv_img'] = tomedia($set['cat_adv_img']);
        $category = $this->getRecommentCategoryList();
        foreach ($category  as &$item){
            $item['thumb'] = replace_yunshop(tomedia($item['thumb']));
            $item['adv_img'] = replace_yunshop(tomedia($item['adv_img']));
        }
        $data = [
            'ads' => $this->getAds(),
            'category' => $category,
            'set' => $set,
            'goods' => $this->getRecommentGoods(),
        ];
        $this->successJson('成功', $data);
    }

    public function getRecommentGoods()
    {
        //$goods = new Goods();
        $field = ['id as goods_id', 'thumb', 'title', 'price', 'market_price'];
        $goodsList = Goods::uniacid()->select(DB::raw(implode(',', $field)))
            ->where("is_recommand", 1)
            ->where("status", 1)
            ->get();

        return $goodsList;
    }

    public function getRecommentCategoryList()
    {

        $request = Category::getRecommentCategoryList()
        ->where('is_home','1')
        ->get();
        foreach ($request as &$item) {
            $item['thumb'] = replace_yunshop(tomedia($item['thumb']));
            $item['adv_img'] = replace_yunshop(tomedia($item['adv_img']));
        }

        return $request;
    }

    /**
     * @param $goods_id
     * @param null $option_id
     * @return bool|\Illuminate\Database\Eloquent\Model|null|static
     */
    public function getAds()
    {
        $slide = [];
        $slide = Slide::getSlidesIsEnabled()->get();
        if($slide){
            $slide = $slide->toArray();
            foreach ($slide as &$item)
            {
                $item['thumb'] = replace_yunshop(tomedia($item['thumb']));
            }
        }
        return $slide;
    }

}