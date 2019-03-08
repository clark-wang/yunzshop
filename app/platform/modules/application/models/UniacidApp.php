<?php

namespace app\platform\modules\application\models;

use app\common\models\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Database\Eloquent\SoftDeletes;

class UniacidApp extends BaseModel
{
	use SoftDeletes;
	
	protected $table = 'uniacid_app';
	protected $search_fields = ['name', 'validity_time'];
  	protected $guarded = [''];
  	protected $dates = ['validity_time'];
  	protected $hidden = ['deleted_at', 'updated_at', 'created_at',
                         'type', 'kind', 'title', 'descr', 'version', 'uniacid'];


  	public function scopeSearch($query, $keyword)
  	{
  		$query = $query->where('status', 1);

  		if ($keyword['name']) {
  			$query = $query->where('name', 'like', '%'.$keyword['name'].'%');
  		}

  		if ($keyword['validity_time']) {
  			$query = $query->where('validity_time', $keyword['validity_time']);
  		}
  		return $query;
  	}

    public function atributeNames() 
    {
        return [
            'img'=> "应用图片",
            'url'=> "应用跳转地址",
            'name' => "应用名称",
            'kind' => "行业分类",
            'title' => "应用标题",
            'descr' => "应用描述",
            'version' => "应用版本",
            'type' => '应用类型',
            'status' => "应用状态",
            'validity_time' => "有效期",
        ];
    }
    
    public function rules()
    {
    	return [
            'img' => 'url',
            'url' => 'url',
            'name' => 'string|max:10',
            'kind' => 'string|max:10',
            'type' => 'numeric',
            'title' => 'string|max:10',
            'descr' => 'string|max:40',
            'status' => 'numeric',
            'version' => '',
            'validity_time' => '',
        ];
    }

}