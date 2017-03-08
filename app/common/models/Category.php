<?php

namespace app\common\models;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Created by PhpStorm.
 * User: yanglei
 * Date: 2017/2/22
 * Time: 下午5:54
 */
class Category extends BaseModel
{
    use SoftDeletes;

    public $table = 'yz_category';
    
    public $display_order = '0';
    public $thumb = '';
    public $description = '';
    public $adv_img = '';
    public $adv_url = '';

    /**
     *  不可填充字段.
     *
     * @var array
     */
    protected $guarded = [''];

    protected $fillable = [''];

    /**
     * @param $parent_id
     * @param $pageSize
     * @return mixed
     */
    public static function getCategorys($parentId)
    {
        return $data = self::uniacid()
            ->where('parent_id', $parentId)
            ->orderBy('id', 'asc');
    }

    public function goodsCategories()
    {
        return $this->hasMany('app\common\models\GoodsCategory','category_id','id');
    }



}