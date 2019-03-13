<?php

namespace app\platform\modules\application\controllers;

use app\platform\controllers\BaseController;
use app\platform\modules\application\models\AppUser;
use app\common\helpers\Cache;
use app\platform\modules\application\models\UniacidApp;
use app\platform\modules\user\models\AdminUser;

class AppuserController extends BaseController
{
	protected $key = 'application_user';
	protected $role = ['owner', 'manager', 'operator', 'founder'];

	public function index()
	{
		$list = AppUser::orderBy('id', 'desc')->paginate()->toArray();

		return $this->successJson('获取成功', $list);
	}

	public function add()
    {
        if (request()->input()) {
            
            $user = new AppUser();
            
            $data = $this->fillData(request()->input());

            if (!is_array($data)) {

            	return $this->errorJson($data);
            }

            $user->fill($data);

            $validator = $user->validator();

            if ($validator->fails()) {
            
                return $this->error($validator->messages());
            
            } else {

                if ($user->save()) {
                    
                    //更新缓存
                    // Cache::put($this->key.':'.$user->insertGetId(),$user->find($this->user->insertGetId()));
                    // Cache::put($this->key.'_num',$user->insertGetId());

                    return $this->successJson('添加成功');

                } else {

                    return $this->errorJson('添加失败');
                }
            }
        }
		 // return View('admin.appuser.form');
    }

	public function delete()
	{	
		$id = request()->id;
        
        $info = AppUser::find($id);

        if (!$id || !$info) {
            return $this->errorJson(0, '请选择要删除的用户');
        }

        $info->delete();

        // Cache::put($this->key.':'.$id, AppUser::find($id));

        return $this->successJson('OK');
	}

	private function fillData($data)
    {
    	$checkUser = AdminUser::find($data['uid']); 
		//用户存在且状态有效, 角色为普通用户时可以添加
        // if (!$checkUser || $checkUser->status != 0 || $checkUser->type != 1) {
        if (!$checkUser ) {
        	return 'uid 无效';
        }
        //检测平台
		if (! UniacidApp::chekcApp($data['uniacid'])) {
        	return '平台id 无效';
		}

		if (!in_array($data['role'], $this->role)) {
			return '权限值非法';
		}

		if(AppUser::where('uniacid', $data['uniacid'])->where('uid', $data['uid'])->first()) {
			return '数据重复';
		}

        return [
         		'uniacid' => $data['uniacid'],
         		'uid' => $data['uid'],
         		'role' => $data['role'] ? : 'manager',
        ];
    }

    public function checkname()
    {
    	$data['search']['realname'] = htmlentities(request()->name);
    	// dd($data);
    	if (!$data) {
    		return $this->errorJson('请输入要查询的用户名');
    	}

    	// return AdminUser::searchUsers($data)->get();
    	
    	return $this->successJson('查询成功', AdminUser::where('name', 'like', '%'.$data['search']['realname'].'%')->get());
    }
}