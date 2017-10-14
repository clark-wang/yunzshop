<?php
/**
 * Created by PhpStorm.
 * Author: 芸众商城 www.yunzshop.com
 * Date: 18/04/2017
 * Time: 11:13
 */

namespace app\backend\controllers;

use app\common\components\BaseController;
use app\common\facades\Option;
use app\common\facades\Setting;
use app\common\services\AutoUpdate;
use Illuminate\Filesystem\Filesystem;

class UpdateController extends BaseController
{

    public function index()
    {
        $list = [];

        $key = Setting::get('shop.key')['key'];
        $secret = Setting::get('shop.key')['secret'];
        $update = new AutoUpdate(null, null, 300);
        $update->setUpdateFile('check_app.json');
        $update->setCurrentVersion(config('version'));

        if (config('app.debug')) {
            $update->setUpdateUrl('http://yun-yzshop.com/update'); //Replace with your server update directory
        } else {
            $update->setUpdateUrl(config('auto-update.checkUrl')); //Replace with your server update directory
        }

        $update->setBasicAuth($key, $secret);

        $update->checkUpdate();

        if ($update->newVersionAvailable()) {
            $list = $update->getUpdates();
        }

        krsort($list);
        $version = config('version');

        return view('update.upgrad', [
            'list' => count($list),
            'version' => $version,
        ])->render();
    }

    /**
     * footer检测更新
     * @return \Illuminate\Http\JsonResponse
     */
    public function check()
    {
        $result = ['msg' => '', 'last_version' => '', 'updated' => 0];
        $key = Setting::get('shop.key')['key'];
        $secret = Setting::get('shop.key')['secret'];
        if(!$key || !$secret) {
            return;
        }

        $update = new AutoUpdate(null, null, 300);
        $update->setUpdateFile('check_app.json');
        $update->setCurrentVersion(config('version'));
        $update->setUpdateUrl(config('auto-update.checkUrl')); //Replace with your server update directory
        $update->setBasicAuth($key, $secret);
        //$update->setBasicAuth();

        //Check for a new update
        if ($update->checkUpdate() === false) {
            $result['msg'] = 'Could not check for updates! See log file for details.';
            response()->json($result)->send();
            return;
        }

        if ($update->newVersionAvailable()) {
            $result['last_version'] = $update->getLatestVersion()->getVersion();
            $result['updated'] = 1;
            $result['current_version'] = config('version');
        }
        response()->json($result)->send();
        return;
    }


    /**
     * 检测更新
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyheck()
    {
        set_time_limit(0);

        $filesystem = app(Filesystem::class);
        $update = new AutoUpdate(null, null, 300);

        $filter_file = ['composer.json', 'composer.lock', 'README.md'];
        $plugins_dir = $update->getDirsByPath('plugins', $filesystem);

        $result = ['result' => 0, 'msg' => '网络请求超时', 'last_version' => ''];
        $key = Setting::get('shop.key')['key'];
        $secret = Setting::get('shop.key')['secret'];
        if(!$key || !$secret) {
            return;
        }

        $update = new AutoUpdate(null, null, 300);
        $update->setUpdateFile('backcheck_app.json');
        $update->setCurrentVersion(config('version'));

        if (config('app.debug')) {
            $update->setUpdateUrl('http://yun-yzshop.com/update'); //Replace with your server update directory
        } else {
            $update->setUpdateUrl(config('auto-update.checkUrl')); //Replace with your server update directory
        }

        $update->setBasicAuth($key, $secret);
        //$update->setBasicAuth();

        //Check for a new update
        $ret = $update->checkBackUpdate();

        if (is_array($ret)) {
            if (1 == $ret['result']) {
                $files = [];

                if (!empty($ret['files'])) {
                    foreach ($ret['files'] as $file) {
                        if (in_array($file['path'], $filter_file)) {
                            continue;
                        }

                        //忽略前端样式文件
                        if (preg_match('/^static\/app/', $file['path'])) {
                            continue;
                        }

                        //忽略没有安装的插件
                        if (preg_match('/^plugins/', $file['path'])) {
                            $sub_dir = substr($file['path'], strpos($file['path'], '/')+1);
                            $sub_dir = substr($sub_dir, 0, strpos($sub_dir, '/'));

                            if (!in_array($sub_dir, $plugins_dir)) {
                                continue;
                            }
                        }

                        $entry = base_path() . '/' . $file['path'];
                        //如果本地没有此文件或者文件与服务器不一致
                        if (!is_file($entry) || md5_file($entry) != $file['md5']) {
                            $files[] = array(
                                'path' => $file['path'],
                                'download' => 0
                            );
                            $difffile[] = $file['path'];
                        } else {
                            $samefile[] = $file['path'];
                        }
                    }
                }

                $tmpdir = storage_path('app/public/tmp/'. date('ymd'));
                if (!is_dir($tmpdir)) {
                    $filesystem->makeDirectory($tmpdir, '0777', true);
                }

                $ret['files'] = $files;
                file_put_contents($tmpdir . "/file.txt", json_encode($ret));

                $result = [
                    'result' => 1,
                    'version' => $ret['version'],
                    'files' => $ret['files'],
                    'filecount' => count($files),
                    'log' => str_replace("\r\n", "<br/>", base64_decode($ret['log']))
                ];
            } else {
                preg_match('/"[\d\.]+"/', file_get_contents(base_path('config/') . 'version.php'), $match);
                $version = $match ? trim($match[0], '"') : '1.0.0';

                $result = ['result' => 99, 'msg' => '', 'last_version' => $version];
            }
        }

        return response()->json($result)->send();
    }

    public function fileDownload()
    {
        $filesystem = app(Filesystem::class);

        $tmpdir  = storage_path('app/public/tmp/'. date('ymd'));
        $f       = file_get_contents($tmpdir . "/file.txt");
        $upgrade = json_decode($f, true);
        $files   = $upgrade['files'];
        $path    = "";
        $nofiles = \YunShop::request()->nofiles;
        $status  = 1;

        //找到一个没更新过的文件去更新
        foreach ($files as $f) {
            if (empty($f['download'])) {
                $path = $f['path'];
                break;
            }
        }

        if (!empty($path)) {
            if (!empty($nofiles)) {
                if (in_array($path, $nofiles)) {
                    foreach ($files as &$f) {
                        if ($f['path'] == $path) {
                            $f['download'] = 1;
                            break;
                        }
                    }
                    unset($f);
                    $upgrade['files'] = $files;
                    $tmpdir           = storage_path('app/public/tmp/'. date('ymd'));
                    if (!is_dir($tmpdir)) {
                        $filesystem->makeDirectory($tmpdir, '0777', true);
                    }
                    file_put_contents($tmpdir . "/file.txt", json_encode($upgrade));

                    return response()->json(['result' => 3])->send();
                }
            }

            $key = Setting::get('shop.key')['key'];
            $secret = Setting::get('shop.key')['secret'];
            if(!$key || !$secret) {
                return;
            }

            $update = new AutoUpdate(null, null, 300);
            $update->setUpdateFile('backdownload_app.json');
            $update->setCurrentVersion(config('version'));

            if (config('app.debug')) {
                $update->setUpdateUrl('http://yun-yzshop.com/update'); //Replace with your server update directory
            } else {
                $update->setUpdateUrl(config('auto-update.checkUrl')); //Replace with your server update directory
            }

            $update->setBasicAuth($key, $secret);

            //Check for a new download
            $ret = $update->checkBackDownload([
                'path' => urlencode($path)
            ]);

            if (is_array($ret)) {
                $path    = $ret['path'];
                $dirpath = dirname($path);

                if (!is_dir(base_path($dirpath))) {
                    $filesystem->makeDirectory(base_path($dirpath), '0777', true);
                }

                $content = base64_decode($ret['content']);
                file_put_contents(base_path($path), $content);

                $success = 0;
                foreach ($files as &$f) {
                    if ($f['path'] == $path) {
                        $f['download'] = 1;
                        break;
                    }
                    if ($f['download']) {
                        $success++;
                    }
                }

                unset($f);
                $upgrade['files'] = $files;
                $tmpdir           = storage_path('app/public/tmp/'. date('ymd'));

                if (!is_dir($tmpdir)) {
                    $filesystem->makeDirectory($tmpdir, '0777', true);
                }

                file_put_contents($tmpdir . "/file.txt", json_encode($upgrade));

                if (intval($success + 1) == count($files)) {
                    //更新完执行数据表
                    \Log::debug('----CLI----');
                    $plugins_dir = $this->getMemberPlugins($filesystem);
                    \Artisan::call('update:version' ,['version'=>$plugins_dir]);

                    $status = 2;
                }

                return response()->json([
                    'result' => $status,
                    'total' => count($files),
                    'success' => $success
                ])->send();
            }
        }
    }

    /**
     * 开始下载并更新程序
     * @return \Illuminate\Http\RedirectResponse
     */
    public function startDownload()
    {
        \Cache::flush();
        $resultArr = ['msg'=>'','status'=>0,'data'=>[]];
        set_time_limit(0);

        $key = Setting::get('shop.key')['key'];
        $secret = Setting::get('shop.key')['secret'];
        $update = new AutoUpdate(null, null, 300);
        $update->setUpdateFile('check_app.json');
        $update->setCurrentVersion(config('version'));
        $update->setUpdateUrl(config('auto-update.checkUrl')); //Replace with your server update directory
        Setting::get('auth.key');
        $update->setBasicAuth($key, $secret);

        //Check for a new update
        if ($update->checkUpdate() === false) {
            $resultArr['msg'] = 'Could not check for updates! See log file for details.';
            response()->json($resultArr)->send();
            return;
        }

        if ($update->newVersionAvailable()) {
            $update->onEachUpdateFinish(function($version){
                \Log::debug('----CLI----');
                \Artisan::call('update:version' ,['version'=>$version]);
            });
            $result = $update->update();

            if ($result === true) {
                $resultArr['status'] = 1;
                $resultArr['msg'] = '更新成功';
            } else {
                $resultArr['msg'] = '更新失败: ' . $result;
                if ($result = AutoUpdate::ERROR_SIMULATE) {
                    $resultArr['data'] = $update->getSimulationResults();
                }
            }
        } else {
            $resultArr['msg'] = 'Current Version is up to date';
        }
        response()->json($resultArr)->send();
        return;
    }
}