<?php
/**
 * API接口基类
 *
 *
 * @package   API
 * @author    shenyang<shenyang@yunzshop.com>
 * @version   v1.0
 */
namespace api;
class Base
{
    /**
     * 解密后的参数
     *
     * @var Array
     */
    protected $para;
    /**
     * PHP报错信息
     *
     * @var Array
     */
    protected $error_info;
    /**
     * 载入aes加密模块,解密参数
     */
    public function __construct()
    {
        /*if(!IS_POST){
            exit('提交方式不正确');
        }*/
        $this->aes = new \Common\Org\Aes();
        $this->para = json_decode(urldecode($this->aes->siyuan_aes_decode(str_replace(" ", "+", $_POST['para']))), TRUE);//
        //dump($this->para);
        $this->addLog();
    }
    /**
     * 返回解密的参数
     *
     *
     * @return array 解密的参数数组
     */
    public function getPara()
    {
        return $this->para;
    }
    /**
     * 成功时返回加密过的json字符串
     *
     * 详细描述（略）
     * @param array $data 传递给APP的自定义数据
     * @param string $msg 提示信息
     * @return void
     */
    public function returnSuccess($data = [], $msg = '成功')
    {
        if(is_array($data)){
            array_walk_recursive($data,function(&$item){
                if(is_null($item)){
                    $item = '';
                }
                if(is_float($item) || is_int($item)){
                    $item = (string)$item;
                }
            });
        }elseif(is_null($data)){
            $data = '';
        }

        $res = array('result' => '1',
            'msg' => $msg,
            'data' => $data);
        if (defined("IS_API_DOC")) {
            exit(json_encode($res, JSON_UNESCAPED_UNICODE));
        } elseif (is_test()) {
            exit(json_encode($res, JSON_UNESCAPED_UNICODE));
        } else {
            $this->callBackByAes($res);
        }
    }
    /**
     * 失败时返回加密过的json字符串
     *
     * 详细描述（略）
     * @param string $msg 提示信息
     * @return void
     */
    public function returnError($msg = '网络繁忙')
    {
        $res = array('result' => '0',
            'msg' => $msg,
            'data' => []);
        if (defined("IS_API_DOC")) {
            exit(json_encode($res, JSON_UNESCAPED_UNICODE));
        } elseif (is_test()) {
            exit(json_encode($res, JSON_UNESCAPED_UNICODE));
        } else {
            $this->callBackByAes($res);
        }
    }
    /**
     * 验证请求参数是否完整
     *
     * 详细描述（略）
     * @param string $expect_keys ','连接的参数名
     * @return bool 参数是否完整
     */
    public function validate($expect_keys)
    {
        $expect_keys = explode(',', $expect_keys);
        if (is_array($this->para)) {
            $para_keys = array_keys($this->para);
            $missing_paras = array_diff($expect_keys, $para_keys);
        }
        if (count($missing_paras) > 0) {
            $missing_paras = implode(',', $missing_paras);
            $this->returnError("缺少参数:{$missing_paras}");
        }
        return true;
    }

    /**
     * 返回加密过的json串
     *
     * 详细描述（略）
     * @param array $json_data 要返回的全部数组
     * @return void
     */
    protected function callBackByAes($json_data)
    {
        if (isset($_GET['is_test'])) {
            dump($json_data);
        }

        $return_data = str_replace('"', '', $this->aes->siyuan_aes_encode(json_encode($json_data, JSON_UNESCAPED_UNICODE)));
        //dump($json_data);
        //dump($this->getSqlLog());
        exit($return_data);
    }
    /**
     * 将访问的参数记录的数据库
     *    todo 建立访问日志表
     * 详细描述（略）
     * @return void
     */
    protected function addLog()
    {
        $data['para'] = $this->para == 'null' ? '' : json_encode($this->para, JSON_UNESCAPED_UNICODE);
        $data['api'] = $_GET['api'];
        $data['client_ip'] = $this->getClientIp();
        $data['error_info'] = "";
        $data['is_error'] = "";
        $data['date_added'] = date('Y-m-d H:i:s');

        pdo_insert("sz_yi_api_log", $data);
    }
    /**
     * php错误回调函数
     *
     * 详细描述（略）
     * @param int $errno 错误码
     * @param string $errstr 错误信息
     * @param string $errfile 发生错误的文件
     * @param int $errline 发生错误的行数
     * @return void
     */
    public function setErrorInfo($errno, $errstr, $errfile, $errline)
    {
        switch ($errno) {
            case E_NOTICE:
            case E_USER_NOTICE:
                $error = 'Notice';
                break;
            case E_WARNING:
            case E_USER_WARNING:
                $error = 'Warning';
                break;
            case E_ERROR:
            case E_USER_ERROR:
                $error = 'Fatal Error';
                break;
            default:
                $error = 'Unknown';
                break;
        }
        $this->error_info[] = 'PHP ' . $error . ':  ' . $errstr . ' in ' . $errfile . ' on line ' . $errline;
        if (is_test()) {
            dump($this->error_info);
        }
    }
    /**
     * 获取访问客户端的IP
     *
     * 详细描述（略）
     * @return string 访问客户端的IP
     */
    protected function getClientIp()
    {
        if (!empty($_SERVER["HTTP_CLIENT_IP"])) {
            $cip = $_SERVER["HTTP_CLIENT_IP"];
        } elseif (!empty($_SERVER["HTTP_X_FORWARDED_FOR"])) {
            $cip = $_SERVER["HTTP_X_FORWARDED_FOR"];
        } elseif (!empty($_SERVER["REMOTE_ADDR"])) {
            $cip = $_SERVER["REMOTE_ADDR"];
        }
        return $cip;
    }
}