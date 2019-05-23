<?php
/**
 * Created by PhpStorm.
 * User: dingran
 * Date: 2019/3/10
 * Time: 下午12:37
 */

namespace app\platform\modules\user\controllers;


use app\common\events\UserActionEvent;
use app\platform\controllers\BaseController;
use app\platform\modules\user\models\AdminUser;
use app\platform\modules\user\models\Role;
use app\platform\modules\user\requests\AdminUserCreateRequest;
use app\platform\modules\user\requests\AdminUserUpdateRequest;
use app\platform\modules\user\models\YzUserProfile;
use app\platform\modules\application\models\UniacidApp;
use app\platform\modules\application\models\AppUser;

class AdminUserController extends BaseController
{
    protected $fields = [
        'name' => '',
        'phone' => '',
        'roles' => [],
    ];

    /**
     * Display a listing of the resource.(显示用户列表.)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $param = request();
        $users = AdminUser::getList($param);

        return $this->successJson('成功', $users);
    }

    /**
     * Show the form for creating a new resource And Store a newly created resource in storage.(添加用户)
     *
     * @return \Illuminate\Http\JsonResponse
     * @throws \app\common\exceptions\AppException
     */
    public function create()
    {
        $user = request()->user;
        if ($user) {
            $validate = $this->validate($this->rules(), $user, $this->message());
            if ($validate) {
                return $validate;
            }
            return $this->check(AdminUser::saveData($user, $user_model = ''));
        }
    }

    /**
     * Show the form for editing the specified resource And Update the specified resource in storage.(修改用户)
     *
     * @return \Illuminate\Http\JsonResponse
     * @throws \app\common\exceptions\AppException
     */
    public function edit()
    {
        $uid = request()->uid;
        if (!$uid) {
            return $this->check(AdminUser::returnData('0', AdminUser::PARAM));
        }
        $user = AdminUser::with('hasOneProfile')->find($uid);
        if (!$user) {
            return $this->check(AdminUser::returnData('0', AdminUser::NO_DATA));
        }
        $data = request()->user;

        if($data) {
            $validate  = $this->validate($this->rules($user), $data, $this->message());
            if ($validate) {
                return $validate;
            }
            return $this->check(AdminUser::saveData($data, $user));
        }

        if ($user) {
            return $this->successJson('成功', $user);
        } else {
            return $this->check(AdminUser::returnData('0', AdminUser::FAIL));
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param $uid
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy($uid)
    {
        $tag = AdminUser::find((int)$uid);
        foreach ($tag->roles as $v) {
            $tag->roles()->detach($v);
        }
        if ($tag && $tag->$uid != 1) {
            $tag->delete();
        } else {
            return redirect()->back()
                ->withErrors("删除失败");
        }

        return redirect()->back()
            ->withSuccess("删除成功");
    }

    /**
     * 修改状态
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function status()
    {
        $uid = request()->uid;
        $status = request()->status;
        if (!$uid || !$status) {
            return $this->check(AdminUser::returnData('0', AdminUser::PARAM));
        }
        $result = AdminUser::where('uid', $uid)->update(['status'=>$status]);
        if ($result) {
            return $this->check(AdminUser::returnData('1'));
        } else {
            return $this->check(AdminUser::returnData('0', AdminUser::FAIL));
        }
    }

    /**
     * 修改密码
     *
     * @return \Illuminate\Http\JsonResponse
     * @throws \app\common\exceptions\AppException
     */
    public function change()
    {
        $uid = request()->uid;
        $data = request()->user;
        if (!$uid || !$data) {
            return $this->check(AdminUser::returnData('0', AdminUser::PARAM));
        }

        $user = AdminUser::getData($uid);
        if (!$user) {
            return $this->check(AdminUser::returnData('0', AdminUser::NO_DATA));
        }
        $validate  = $this->validate($this->rules(), $data, $this->message());
        if ($validate) {
            return $validate;
        }

        return $this->check(AdminUser::saveData($data, $user));
    }

    /**
     * 单个用户平台列表
     */
    public function applicationList()
    {
        $uid = request()->uid;
        $page = intval(request()->page);
        $page_size = 15;
        // 如果page小于且等于1 就等于0 (因为offset是从0开始取数据)
        if ($page<=1) {
            $page = 0;
            $offset = ($page)*$page_size;
        } else {
            $offset = ($page-1)*$page_size;
        }

        // 获取与用户关联的平台角色信息
        $user = AdminUser::with(['hasManyAppUser' => function ($query) use ($offset, $page_size) {
            $query->with('hasOneApp');
            $query->offset($offset)->limit($page_size);
        }])->where('uid', $uid)->first();

        $total = AppUser::where('uid', $uid)->count();
        $avg = $page <= 1 ? intval(floor($total / $page_size)) : intval(ceil($total / $page_size));

        // 获取创始人
        $uniacid_app = UniacidApp::where('creator', $uid);
        $user['total'] = $uniacid_app->count();

        $sign = false;
        if ($page >= $avg) {
            $sign = true;
            $offset = 0;
            $rem = $total % $page_size;
            $mod = 0;
            if ($page == $avg) {
                $mod = $rem;
            } else {
                $offset = ($page-$avg)*$page_size;
            }

            $uniacid_apps = $uniacid_app->offset($offset-$rem)->limit($page_size-$mod)->get();
        }
        $user['total'] += $total;

        if (!$user) {
            return $this->errorJson(['未获取到该用户']);
        } elseif ($user->hasManyAppUser->isEmpty() && $uniacid_apps->isEmpty()) {
            return $this->successJson('该用户暂时没有平台');
        }

        $user = $user->toArray();
        if ($sign && !$uniacid_apps->isEmpty()) {
            $uniacid_apps = $uniacid_apps->toArray();
            // 添加创始人数据
            foreach ($uniacid_apps as $item) {
                array_push($user['has_many_app_user'], ['role_name' => '创始人', 'has_one_app' => $item ?  : [] ]);
            }
        }
        $user['current_page'] = $page ? : 1;
        $user['per_page'] = $page_size;

        return $this->successJson('成功', $user);
    }

    /**
     * 店员列表
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function clerkList()
    {
        $parames = request();
        $user = AdminUser::where('type', 3)->searchUsers($parames)->with(['hasOneProfile'])->paginate();
        foreach ($user as &$item) {
            $item['status'] == 2 ? $item['state'] = '有效' : null;
            $item['status'] == 3 ? $item['state'] = '已禁用' : null;
            $item['create_at'] = $item['created_at']->format('Y年m月d日');
            $item->hasOneAppUser['app_name'] = $item->hasOneAppUser->hasOneApp->name;
        }

        return $this->successJson('成功', $user);
    }

    /**
     * 修改当前用户信息
     *
     * @return \Illuminate\Http\JsonResponse
     * @throws \app\common\exceptions\AppException
     */
    public function modifyCurrentUser()
    {
        $data = request()->user;
        if (!$data) {
            return $this->check(AdminUser::returnData('0', AdminUser::PARAM));
        }

        $user = \Auth::guard('admin')->user();

        $validate  = $this->validate($this->rules($user, $data), $data, $this->message());
        if ($validate) {
            return $validate;
        }
        return $this->check(AdminUser::saveData($data, $user));
    }

    /**
     * 处理表单验证
     *
     * @param array $rules
     * @param \Request|null $request
     * @param array $messages
     * @param array $customAttributes
     * @return \Illuminate\Http\JsonResponse
     */
    public function validate(array $rules, \Request $request = null, array $messages = [], array $customAttributes = [])
    {
        if (!isset($request)) {
            $request = request();
        }
        $validator = $this->getValidationFactory()->make($request, $rules, $messages, $customAttributes);

        if ($validator->fails()) {
            return $this->errorJson($validator->errors()->all());
        }
    }

    /**
     * 表单验证规则
     *
     * @param $user
     * @param $data
     * @return array
     */
    public function rules($user = [], $data = [])
    {
        $rules = [];
        if (request()->path() == "admin/user/create") {
            $rules = [
                'username' => 'required|regex:/^[\x{4e00}-\x{9fa5}A-Za-z0-9_\-]{3,30}$/u|unique:yz_admin_users',
                'mobile' => 'required|regex:/^1[34578]\d{9}$/|unique:yz_users_profile',
            ];
        }else if(request()->path() == "admin/user/edit") {
            $rules = [
                'username' => 'required|regex:/^[\x{4e00}-\x{9fa5}A-Za-z0-9_\-]{3,30}$/u|unique:yz_admin_users,username,'.$user['uid'].',uid',
                'mobile' => 'required|regex:/^1[34578]\d{9}$/|unique:yz_users_profile,mobile,'.$user['hasOneProfile']['id'],
            ];
        }

        if (request()->path() != "admin/user/edit") {
            if (request()->path() == "admin/user/modify_user" && !$data['password']) {
                return $rules;
            }
            $rules['password'] = 'required';
            $rules['re_password'] = 'same:password';
        }
        return $rules;
    }

    /**
     * 表单验证自定义错误消息
     *
     * @return array
     */
    public function message()
    {
        return [
            'username.required' => '用户名不能为空',
            'username.regex' => '用户名格式不正确',
            'username.unique' => '用户名已存在',
            'mobile.required' => '手机号不能为空',
            'mobile.regex' => '手机号格式不正确',
            'mobile.unique' => '手机号已存在',
            'password.required' => '密码不能为空',
            're_password.same' => '两次密码不一致',
        ];
    }


    /**
     * 返回 json 信息
     *
     * @param $param
     * @return \Illuminate\Http\JsonResponse
     */
    public function check($param)
    {
        if ($param['sign'] == 1) {
            return $this->successJson('成功');
        } else {
            return $this->errorJson([$param['message']]);
        }
    }
}