<?php
namespace app\api\controller\supplier;
@session_start();
use app\api\YZ;
use app\api\controller\order;
use yii\helpers\ArrayHelper;

class Index extends YZ
{
    private $json;

    public function __construct()
    {
        parent::__construct();
        $result = $this->callPlugin('supplier/orderj');
        $this->json = $result;
    }

    public function index()
    {
        $block_list = $this->_getSupplierBlockList();
        $res = ['block_list' => $block_list];
        $this->json['json'] += $res;
        $this->returnSuccess($res);
    }

    private function _getSupplierBlockList()
    {
        $list = [
            [
                'id' => 1,
                'icon' => '',
                'title' => '累计未提现金额',
                'value' => $this->json['json']['costmoney_total'],
                'unit' => '元'
            ], [
                'id' => 2,
                'icon' => '',
                'title' => '提现记录',
                'value' => $this->json['json']['commission_total'],
                'unit' => '元'
            ], [
                'id' => 3,
                'icon' => '',
                'title' => '我的订单',
                'value' => $this->json['json']['ordercount'],
                'unit' => '个'
            ]
        ];

        return $list;
    }
}