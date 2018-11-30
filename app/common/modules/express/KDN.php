<?php
/**
 * Created by PhpStorm.
 * User: shenyang
 * Date: 2018/10/23
 * Time: 下午2:34
 */

namespace app\common\modules\express;

use Ixudra\Curl\Facades\Curl;

class KDN
{
    private $eBusinessID;
    private $appKey;
    private $reqURL;

    public function __construct($eBusinessID, $appKey, $reqURL)
    {
        $this->eBusinessID = $eBusinessID;
        $this->appKey = $appKey;
        $this->reqURL = $reqURL;
    }

    public function getTraces($comCode, $expressSn, $orderSn = '')
    {
       //快递鸟1002状态为免费，8001状态为收费
        $express_api = \Setting::get('shop.express_info');

        $requestData = json_encode(
            [
                'OrderCode' => $orderSn,
                'ShipperCode' => $comCode,
                'LogisticCode' => $expressSn,
            ]
        );
        if(empty($express_api['KDN']['express_api'])){//判断如果快递鸟状态为空，默认赋值为1002免费状态
            $express_api['KDN']['express_api'] = 1002;
        }
        if ($express_api['KDN']['express_api'] == 1002 || $express_api['KDN']['express_api'] == 8001 ){//判断如果快递鸟状态为1002或者8001则赋值，不为
            $datas = array(
                'EBusinessID' => $this->eBusinessID,
                'RequestType' => $express_api['KDN']['express_api'],//'1002',//快递鸟1002状态为免费，8001状态为收费
                'RequestData' => urlencode($requestData),
                'DataType' => '2',
            );
        }else{  //不为1002或者8001返回错误
            throw new ShopException("快递鸟状态错误");
        }


        $datas['DataSign'] = $this->encrypt($requestData);

        $response = Curl::to($this->reqURL)->withData($datas)
            ->asJsonResponse(true)->get();

        return $this->format($response);
    }

    private function format($response)
    {
        $result = [];
        foreach ($response['Traces'] as $trace) {
            $result['data'][] = [
                'time' => $trace['AcceptTime'],
                'ftime' => $trace['AcceptTime'],
                'context' => $trace['AcceptStation'],
                'location' => null,
            ];
        }
        $result['state'] = $response['State'];
        return $result;
    }

    private function encrypt($data)
    {
        return urlencode(base64_encode(md5($data . $this->appKey)));
    }
}