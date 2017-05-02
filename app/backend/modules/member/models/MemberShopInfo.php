<?php
/**
 * Created by PhpStorm.
 * User: libaojia
 * Date: 2017/3/2
 * Time: 下午4:16
 */

namespace app\backend\modules\member\models;

use Illuminate\Database\Eloquent\SoftDeletes;

class MemberShopInfo extends \app\common\models\MemberShopInfo
{
    use SoftDeletes;

    public function group()
    {
        return $this->belongsTo('app\backend\modules\member\models\MemberGroup');
    }

    public function level()
    {
        return $this->belongsTo('app\backend\modules\member\models\MemberLevel', 'level_id', 'id');
    }

    public function agent()
    {
        return $this->belongsTo('app\backend\modules\member\models\Member', 'parent_id', 'uid');
    }

    /**
     * 更新会员信息
     *
     * @param $data
     * @param $id
     * @return mixed
     */
    public static function updateMemberInfoById($data, $id)
    {
        return self::uniacid()
            ->where('member_id', $id)
            ->update($data);
    }

    /**
     * 删除会员信息
     *
     * @param $id
     */
    public static function  deleteMemberInfoById($id)
    {
        return self::uniacid()
            ->where('member_id', $id)
            ->delete();
    }

    /**
     * 设置会员黑名单
     *
     * @param $id
     * @param $data
     * @return mixed
     */
    public static function setMemberBlack($id, $data)
    {
        return self::uniacid()
            ->where('member_id', $id)
            ->update($data);
    }

    public static function getMemberLevel($memberId)
    {
        return self::uniacid()->select(['member_id','level_id'])->where('member_id', $memberId)
            ->with(['level' => function($query) {
                return $query->select('id','level','level_name')->uniacid();
            }])->first();
    }
}
