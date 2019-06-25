<?php

namespace app\common\modules\goods;

use app\common\models\Goods;
use app\common\modules\goods\dealPrice\BaseDealPrice;

class GoodsPriceManager
{
    /**
     * @var Goods $goods
     */
    private $goods;
    private $detailPrice;

    public function __construct(Goods $goods)
    {
        $this->goods = $goods;
    }

    public function getDealPrice()
    {
        if (!isset($this->detailPrice)) {
            $this->detailPrice = $this->_getDealPrice();
        }
        return $this->detailPrice;
    }

    private function _getDealPrice()
    {
        $dealPrices = collect(config('shop-foundation.goods.dealPrice'))->map(function (array $dealPriceStrategy) {
            return call_user_func($dealPriceStrategy['class'], $this->goods);
        })->sort(function (BaseDealPrice $dealPrice) {
            return $dealPrice->getWeight();
        });
        $dealPrices->each(function (){
            dump();
        });
        /**
         * @var BaseDealPrice $dealPrice
         */
        $dealPrice = $dealPrices->first(function (BaseDealPrice $dealPrice) {
            return $dealPrice->enable();
        });

        return $dealPrice->getDealPrice();
    }
}