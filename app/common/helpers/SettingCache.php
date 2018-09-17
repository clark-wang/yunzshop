<?php
/**
 * Created by PhpStorm.
 * User: shenyang
 * Date: 2018/9/14
 * Time: 下午5:30
 */

namespace app\common\helpers;


use app\common\facades\Setting;

class SettingCache
{
    private $settingCollection;

    public function loadSettingFromCache($uniacid)
    {
        $this->settingCollection[$uniacid] = \Cache::get($uniacid . '_setting') ?: [];
    }

    public function getSetting()
    {
        if (!in_array(Setting::$uniqueAccountId, array_keys($this->settingCollection))) {
            $this->loadSettingFromCache(Setting::$uniqueAccountId);
        }

        return $this->settingCollection[Setting::$uniqueAccountId];

    }

    /**
     * @param $key
     * @return bool
     */
    public function has($key)
    {
        //dd(array_has($this->getSetting(), $key));
        return array_has($this->getSetting(), $key);
    }

    /**
     * @param $key
     * @param null $default
     * @return mixed
     */
    public function get($key, $default = null)
    {
        return array_get($this->getSetting(), $key, $default);

    }

    /**
     * @param $key
     * @param $value
     * @param null $minutes
     * @return mixed
     */
    public function put($key, $value, $minutes = null)
    {
        $setting = $this->getSetting();
        yz_array_set($setting, $key, $value);
        \Cache::put(Setting::$uniqueAccountId . '_setting', $setting, $minutes);
        $this->loadSettingFromCache(Setting::$uniqueAccountId);

    }


}