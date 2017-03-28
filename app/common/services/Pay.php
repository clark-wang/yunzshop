<?php
/**
 * Created by PhpStorm.
 * User: dingran
 * Date: 2017/3/17
 * Time: 上午9:47
 */

namespace app\common\services;

use app\common\models\PayAccessLog;
use app\common\models\PayLog;
use app\common\models\PayOrder;
use app\common\models\PayWithdrawOrder;
use app\common\models\PayRefundOrder;
use app\common\models\PayRequestDataLog;
use app\common\models\PayResponseDataLog;

abstract class Pay
{
    const INVALID_UNIACID_LENGTH = -1;
    const PAY_TYPE_COST          = 1;
    const PAY_TYPE_RECHARGE      = 2;
    const PAY_MODE_WECHAT        = 1;
    const PAY_MODE_ALIPAY        = 2;
    const PAY_MODE_CREDIT        = 3;
    const PAY_MODE_CASH          = 4;
    const ORDER_STATUS_CREATE    = 0;
    const ORDER_STATUS_WAITPAY   = 1;
    const ORDER_STATUS_COMPLETE   = 2;

    /**
     * 请求的参数
     *
     * @var array
     */
    protected $parameters;

    /**
     * 密钥
     *
     * @var string
     */
    protected $key;

    /**
     * 请求接口
     *
     * @var string
     */
    protected $gateUrl;

    /**
     * 统一公众号
     *
     * @var integer
     */
    protected $uniacid;

    /**
     * url请求地址
     *
     * @var string
     */
    protected $url;

    /**
     * url请求方式
     *
     * @var string
     */
    protected $method;

    /**
     * 访问IP地址
     *
     * @var string
     */
    protected $ip;

    public function __construct()
    {
        $this->init();
    }

    /**
     * 订单支付/充值
     *
     * @param $subject 名称
     * @param $body 详情
     * @param $amount 金额
     * @param $order_no 订单号
     * @param $extra 附加数据
     * @return mixed
     */
    abstract function doPay($data = []);

    /**
     * 退款
     *
     * @param $out_trade_no 订单号
     * @param $out_refund_no 退款单号
     * @param $totalmoney 订单总金额
     * @param $refundmoney 退款金额
     * @return mixed
     */
    abstract function doRefund($out_trade_no, $out_refund_no, $totalmoney, $refundmoney);

    /**
     * 提现
     *
     * @param $member_id 提现者用户ID
     * @param $out_trade_no 提现单号
     * @param $money 提现金额
     * @param $desc 提现说明
     * @param $type 只针对微信 1-企业支付(钱包) 2-红包
     * @return mixed
     */
    abstract function doWithdraw($member_id, $out_trade_no, $money, $desc, $type);

    /**
     * init
     *
     * @var void
     */
    protected function init()
    {
        $this->uniacid = \YunShop::app()->uniacid;
        $this->url = $this->_getHttpURL();
        $this->method = $this->_getHttpMethod();
        $this->ip = $this->getClientIP();
    }

    /**
     * 构造签名
     *
     * @return mixed
     */
    abstract function buildRequestSign();

    /**
     * 获取访问URL
     *
     * @return string
     */
    private function _getHttpURL()
    {
        $url = \URL::current();
        $url .= '?' . $_SERVER['QUERY_STRING'];

        return $url;
    }

    /**
     * 获取HTTP请求方式
     *
     * @return mixed
     */
    private function _getHttpMethod()
    {
        return $_SERVER['REQUEST_METHOD'];
    }

    /**
     * 获取客户端IP
     *
     * @return string
     */
    protected function getClientIP()
    {
        return \Request::getClientIp();
    }

    /**
     * 获取入口地址,不包含参数值
     *
     * @return string
     */
    protected function getGateURL() {
        return $this->gateUrl;
    }

    /**
     * 设置入口地址,不包含参数值
     *
     * @param $gateUrl
     */
    protected function setGateURL($gateUrl) {
        $this->gateUrl = $gateUrl;
    }

    /**
     * 获取参数值
     *
     * @param $parameter
     * @return mixed
     */
    protected function getParameter($parameter) {
        return $this->parameters[$parameter];
    }

    /**
     * 设置参数值
     *
     * @param $parameter
     * @param $parameterValue
     */
    protected function setParameter($parameter, $parameterValue) {
        $this->parameters[$parameter] = $parameterValue;
    }

    /**
     * 获取所有请求的参数
     *
     * @return array
     */
    protected function getAllParameters() {
        return $this->parameters;
    }

    /**
     * 获取密钥
     *
     * @return string
     */
    function getKey() {
        return $this->key;
    }

    /**
     * 设置密钥
     *
     * @param $key
     * @return void
     */
    function setKey($key) {
        $this->key = $key;
    }

    /**
     * 预下单
     *
     * @return array
     */
    protected function preOrder() {
        $params = ksort($this->parameters);
        $params = array2xml($params);

        $response = ihttp_request($this->getGateURL(), $params);

        return $response;
    }

    public function encryption() {}

    protected function decryption() {}

    protected function noticeUrl() {}

    protected function returnUrl() {}

    /**
     * 支付访问日志
     *
     * @var void
     */
    protected function payAccessLog()
    {
        PayAccessLog::create([
            'uniacid' => $this->uniacid,
            'member_id' => \YunShop::app()->getMemberId(),
            'url' => $this->url,
            'http_method' => $this->method,
            'ip' => $this->ip
        ]);
    }

    /**
     * 支付日志
     *
     * @param $type
     * @param $third_type
     * @param $price
     * @param $operation
     */
    protected function payLog($type, $third_type, $price, $operation)
    {
        PayLog::create([
            'uniacid' => $this->uniacid,
            'member_id' => \YunShop::app()->getMemberId(),
            'type' => $type,
            'third_type' => $third_type,
            'price' => $price,
            'operation' => $operation,
            'ip' => $this->ip
        ]);
    }

    /**
     * 支付单
     *
     * @param $out_order_no 订单号
     * @param $status 支付单状态
     * @param $type 支付类型
     * @param $third_type 支付方式
     * @param $price 支付金额
     */
    protected function payOrder($out_order_no, $status, $type, $third_type, $price)
    {
        return PayOrder::create([
            'uniacid' => $this->uniacid,
            'member_id' => \YunShop::app()->getMemberId(),
            'int_order_no' => $this->createPayOrderNo(),
            'out_order_no' => $out_order_no,
            'status' => $status,
            'type' => $type,
            'third_type' => $third_type,
            'price' => $price,
            'ip' => $this->ip
        ]);
    }

    protected function payWithdrawOrder()
    {}

    protected function payRefundOrder()
    {}

    /**
     * 支付请求数据记录
     *
     * @param $order_id
     * @param $type
     * @param $third_type
     * @param $params
     */
    protected function payRequestDataLog($order_id, $type, $third_type, $params)
    {
        PayRequestDataLog::create([
            'uniacid' => $this->uniacid,
            'order_id' => $order_id,
            'type' => $type,
            'third_type' => $third_type,
            'params' => $params
        ]);
    }

    protected function payResponseDataLog()
    {}

    /**
     * 支付单号
     *
     * 格式：P+YYMMDD+31位流水号(数字+字母)
     *
     * @return string
     */
    private function createPayOrderNo()
    {
        return 'P' . date('Ymd', time()) . $this->generate_string(23);
    }

    /**
     * 创建退款/提现订单批次号
     *
     * @param $uniacid
     * @param $strleng
     * @return string
     */
    public function setUniacidNo($uniacid, $strleng)
    {
        $part1 = date('Ymd', time());
        $part2 = $this->generate_string();

        $uniacid_lenght = strlen($uniacid);

        if ($uniacid_lenght > $strleng) {
            return INVALID_UNIACID_LENGTH;
        }

        if ($uniacid_lenght >= 1 && $uniacid_lenght <= $strleng) {
            $part3 = sprintf("%0{$strleng}s", $uniacid);
        } else {
            return INVALID_UNIACID_LENGTH;
        }

        return $part1 . substr($part2, 0, 9) . $part3 . substr($part2, 9);;
    }

    /**
     * 退款/提现流水号
     *
     * @param int $length
     * @return string
     */
    private function generate_string ($length = 19)
    {
        $nps = "";
        for($i=0;$i<$length;$i++)
        {
            $nps .= chr((mt_rand(1, 36) <= 26) ? mt_rand(97, 122) : mt_rand(48, 57 ));
        }
        return $nps;
    }
}