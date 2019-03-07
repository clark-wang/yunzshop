<?php
/**
 * Created by PhpStorm.
 * User: dingran
 * Date: 2019/2/19
 * Time: 下午5:03
 */

namespace app\platform\models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    protected $table='yz_admin_permissions';

    public function roles()
    {
        return $this->belongsToMany(Role::class,'yz_admin_permission_role','permission_id','role_id');
    }

}