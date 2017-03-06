<?php

namespace app\common\components;

use app\common\traits\MessageTrait;
use app\common\traits\TemplateTrait;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller;
use Setting;
use Validator;
use Response;

/**
 * controller基类
 *
 * User: jan
 * Date: 21/02/2017
 * Time: 21:20
 */
class BaseController extends Controller
{
    use DispatchesJobs, MessageTrait, ValidatesRequests, TemplateTrait;


    public function __construct()
    {
        Setting::$uniqueAccountId = \YunShop::app()->uniacid;
    }

    protected function formatValidationErrors(Validator $validator)
    {
        return $validator->errors()->all();
    }

    /**
     * 显示信息并跳转
     *
     * @param $message
     * @param string $redirect
     * @param string $status success  error danger warning  info
     * @return mixed
     */
    public function message($message, $redirect = '', $status = 'success')
    {
        return $this->render('web/message', [
            'redirect' => $redirect,
            'message' => $message,
            'status' => $status
        ]);
    }


    /**
     * 接口返回成功 JSON格式
     * @param array $data
     * @return \Illuminate\Http\JsonResponse
     */
    protected function successJson($data = [])
    {
        Response::json([
            'result' => 1,
            'data' => $data
        ])->send();
        return;
    }

    /**
     * 接口返回错误JSON 格式
     * @param string $message
     * @param array $data
     * @return \Illuminate\Http\JsonResponse
     */
    protected function errorJson($message = '', $data = [])
    {
         response()->json([
            'result' => 0,
            'msg' => $message,
            'data' => $data
        ])->send();
        return;
    }


}