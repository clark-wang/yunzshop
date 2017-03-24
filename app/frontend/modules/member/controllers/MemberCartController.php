<?php
namespace app\frontend\modules\member\controllers;
use app\common\components\BaseController;
use app\frontend\modules\goods\services\GoodsService;
use app\frontend\modules\member\models\MemberCart;

/**
 * Created by PhpStorm.
 * User: libaojia
 * Date: 2017/2/23
 * Time: 上午10:17
 */
class MemberCartController extends BaseController
{
    public function index()
    {
        $memberId = \YunShop::app()->getMemberId();
        $memberId = '9';

        $cartList = MemberCart::getMemberCartList($memberId);
        //dd($cartList);
        foreach ($cartList as $key => $cart) {
            $cartList[$key]['option_str'] = '';
            if (empty($cart['goods'])) {
                //销毁未找到商品的数据
                unset($cartList[$key]);
            } elseif (!empty($cart['goods_option'])) {
                //规格数据替换商品数据
                if ($cart['goods_option']['title']) {
                    $cartList[$key]['option_str'] = $cart['goods_option']['title'];
                }
                if ($cart['goods_option']['thumb']) {
                    $cart['goods']['thumb'] = $cart['goods_option']['thumb'];
                }
                if ($cart['goods_option']['market_price']) {
                    $cart['goods']['price'] = $cart['goods_option']['market_price'];
                }
                if ($cart['goods_option']['market_price']) {
                    $cart['goods']['price'] = $cart['goods_option']['market_price'];
                }
            }
            unset ($cartList[$key]['goods_option']);
        }
        //dd($cartList);

        return $this->successJson('获取列表成功', $cartList);
    }
    /**
     * Add member cart
     */
    public function store()
    {
        $cartModel = new membercart();

        $requestcart = \YunShop::request();
        if($requestcart) {
            $data = array(
                'member_id' => '9',
                'uniacid'   => \YunShop::app()->uniacid,
                'goods_id'  => $requestcart->goods_id,
                'total'     => $requestcart->total,
                'option_id' => $requestcart->option_id ? $requestcart->option_id : '0'
            );


            //验证商品是否存在购物车,存在则修改数量
            $hasGoodsModel = MemberCart::hasGoodsToMemberCart($data);
            if ($hasGoodsModel) {
                $hasGoodsModel->total = $hasGoodsModel->tatal + 1;
                if ($hasGoodsModel->update()){
                    return $this->successJson('添加购物车成功');
                }
                return $this->errorJson('数据更新失败，请重试！');
            }


            //将数据赋值到model
            $cartModel->setRawAttributes($data);
            //字段检测
            $validator = $cartModel->validator($cartModel->getAttributes());
            if ($validator->fails()) {//检测失败
                $this->error($validator->messages());
            } else {
                //数据保存
                if ($cartModel->save()) {
                    //输出
                    $msg = "添加购物车成功";
                    return $this->errorJson($msg);
                }else{
                    $msg = "写入出错，添加购物车失败！！！";
                    return $this->successJson($msg);
                }
            }


        }
        $msg = "接收数据出错，添加购物车失败！";
        return $this->errorJson($msg);
    }
    /*
     *  Update memebr cart
     **/
    public function update()
    {
        //需要判断商品状态、限制数量、商品类型（实体、虚拟）
    }
    /*
     * Delete member cart
     **/
    public function destroy()
    {
        $cart = MemberCart::getMemberCartById(\YunShop::request()->ids);
        if(!$cart) {
            $msg = "未找到商品或已经删除";
            return $this->errorJson($msg);
        }

        $result = MemberCart::destroyMemberCart(\YunShop::request()->ids);
        if($result) {
            $msg = "移除购物车成功。";
            return $this->successJson($msg);
        }
        $msg = "写入出错，移除购物车失败！";
        return $this->errorJson($msg);
    }

}
