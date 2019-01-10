<?php

namespace app\common\components;

use app\common\exceptions\AppException;
use app\common\exceptions\ShopException;
use app\common\helpers\WeSession;
use app\common\models\Modules;
use app\common\services\Check;
use app\common\traits\JsonTrait;
use app\common\traits\MessageTrait;
use app\common\traits\PermissionTrait;
use app\common\traits\TemplateTrait;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller;

/**
 * controller基类
 *
 * Author: 芸众商城 www.yunzshop.com
 * Date: 21/02/2017
 * Time: 21:20
 */
class BaseController extends Controller
{
    use DispatchesJobs, MessageTrait, ValidatesRequests, TemplateTrait, PermissionTrait, JsonTrait;

    const COOKIE_EXPIRE = 864000;

    /**
     * controller中执行报错需要回滚的action数组
     * @var array
     */
    public $transactionActions = [];

    public function __construct()
    {
        $this->setCookie();

        $modules = Modules::getModuleName('yun_shop');

        \Config::set('module.name', $modules->title);
    }

    /**
     * 前置action
     */
    public function preAction()
    {
        //strpos(request()->get('route'),'setting.key')!== 0 && Check::app();

        //是否为商城后台管理路径
        strpos(request()->getBaseUrl(), '/web/index.php') === 0 && Check::setKey();
    }

    protected function formatValidationErrors(Validator $validator)
    {
        return $validator->errors()->all();
    }


    /**
     * url参数验证
     * @param array $rules
     * @param \Request|null $request
     * @param array $messages
     * @param array $customAttributes
     * @throws AppException
     */
    public function validate(array $rules, \Request $request = null, array $messages = [], array $customAttributes = [])
    {
        if (!isset($request)) {
            $request = request();
        }
        $validator = $this->getValidationFactory()->make($request->all(), $rules, $messages, $customAttributes);

        if ($validator->fails()) {
            throw new AppException($validator->errors()->first());
        }
    }

    /**
     * 设置Cookie存储
     *
     * @return void
     */
    private function setCookie()
    {
        $session_id = '';
        if (isset(\YunShop::request()->state) && !empty(\YunShop::request()->state) && strpos(\YunShop::request()->state, 'yz-')) {
            $pieces = explode('-', \YunShop::request()->state);
            $session_id = $pieces[1];
            unset($pieces);
        }

        if (isset($_COOKIE[session_name()])) {
            $session_id_1 = $_COOKIE[session_name()];
            session_id($session_id_1);
        }


        if (empty($session_id) && \YunShop::request()->session_id
              && \YunShop::request()->session_id != 'undefined' && \YunShop::request()->session_id != 'null'
        ) {
            $session_id = \YunShop::request()->session_id;
            session_id($session_id);
            setcookie(session_name(), $session_id);
        }

        WeSession::start(\YunShop::app()->uniacid, CLIENT_IP, self::COOKIE_EXPIRE);
    }

    /**
     * 需要回滚
     * @param $action
     * @return bool
     */
    public function needTransaction($action)
    {
        return in_array($action, $this->transactionActions) || in_array('*', $this->transactionActions) || $this->transactionActions == '*';
    }


}
