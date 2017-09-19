<?php
namespace app\backend\modules\goods\models;

/**
 * Created by PhpStorm.
 * Author: 芸众商城 www.yunzshop.com
 * Date: 2017/2/22
 * Time: 下午2:24
 */

use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class Category extends \app\common\models\Category
{
    static protected $needLog = true;

    /**
     * @return mixed
     */
    public static function getAllCategory()
    {
        return self::uniacid()
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * @return mixed
     */
    public static function getAllCategoryGroup()
    {
        $categorys = self::getAllCategory();

        $categoryMenus['parent'] = $categoryMenus['children'] = [];

        foreach ($categorys as $category) {
            !empty($category['parent_id']) ?
                $categoryMenus['children'][$category['parent_id']][] = $category :
                $categoryMenus['parent'][$category['id']] = $category;
        }

        return $categoryMenus;
    }


    /**
     * @param $id
     * @return \Illuminate\Database\Eloquent\Model|null|static
     */
    public static function getCategory($id)
    {
        return self::find($id);
    }

    /**
     * @param $id
     * @return mixed
     */
    public static function daletedCategory($id)
    {
        return self::where('id', $id)
            ->orWhere('parent_id', $id)
            ->delete();
    }

    /**
     *  定义字段名
     * 可使
     * @return array
     */
    public function atributeNames()
    {
        return [
            'name' => '分类名称',
        ];
    }

    /**
     * 字段规则
     * @return array
     */
    public function rules()
    {
        $rule = Rule::unique($this->table);
        return [
            'name' => ['required',  $rule->ignore($this->id)
                ->where('uniacid', \YunShop::app()->uniacid)
                ->where('parent_id', $this->parent_id)
                ->where('deleted_at', null)],
        ];
    }

    /**
     * @param $keyword
     * @return mixed
     */
    public static function getCategorysByName($keyword)
    {
        return static::uniacid()->select('id', 'name', 'thumb')
            ->where('name', 'like', '%' . $keyword . '%')
            ->get();
    }

    //根据商品分类ID获取分类名称
    public static function getCategoryNameByIds($categoryIds){
        if(empty($categoryIds))
        {
            return '';
        }

        if(is_array($categoryIds)){
            $res = static::uniacid()
                ->select('name')
                ->whereIn('id', $categoryIds)
                ->orderByRaw(DB::raw("FIELD(id, ".implode(',', $categoryIds).')')) //必须按照categoryIds的顺序输出分类名称
                ->get()
                ->map(function($goodname){ //遍历
                    return $goodname['name'];
                })
                ->toArray();
        } else{
            $res = static::uniacid()
                ->select('name')
                ->where('id', '=', $categoryIds)
                ->first();
        }
        return $res;
    }
}