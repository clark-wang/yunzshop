<?php
/**
 * Created by PhpStorm.
 * User: PhpStorm
 * Date: 2019/3/18
 * Time: 14:01
 */
namespace app\platform\modules\Application\controllers;

use app\platform\controllers\BaseController;
use app\platform\modules\system\models\SystemSetting;
use app\platform\modules\application\models\CoreAttach;
// use app\common\services\qcloud\Api;
use app\common\services\qcloud\Api;
use app\common\services\aliyunoss\OssClient;
use app\common\services\aliyunoss\Core\OssException;
use app\common\services\ImageZip;


class AllUploadController extends BaseController
{
    protected $proto;
    protected $path;
    protected $pattern;

    public function __construct()
    {
        //本地存放路径协议
        $this->proto = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https://' : 'http://';

        $this->path = config('filesystems.disks.syst')['url'].'/'; //本地图片实际存放路径

        $this->pattern =  '_^(?:(?:https?|ftp)://)(?:\S+(?::\S*)?@)?(?:(?!10(?:\.\d{1,3}){3})(?!127(?:\.\d{1,3}){3})(?!169\.254(?:\.\d{1,3}){2})(?!192\.168(?:\.\d{1,3}){2})(?!172\.(?:1[6-9]|2\d|3[0-1])(?:\.\d{1,3}){2})(?:[1-9]\d?|1\d\d|2[01]\d|22[0-3])(?:\.(?:1?\d{1,2}|2[0-4]\d|25[0-5])){2}(?:\.(?:[1-9]\d?|1\d\d|2[0-4]\d|25[0-4]))|(?:(?:[a-z\x{00a1}-\x{ffff}0-9]+-?)*[a-z\x{00a1}-\x{ffff}0-9]+)(?:\.(?:[a-z\x{00a1}-\x{ffff}0-9]+-?)*[a-z\x{00a1}-\x{ffff}0-9]+)*(?:\.(?:[a-z\x{00a1}-\x{ffff}]{2,})))(?::\d{2,5})?(?:/[^\s]*)?$_iuS';
    }

    public function upload()
    {
        $file = request()->file('file');

        if (count($file) > 6) {
            return $this->errorJson('文件数量过多, 请选择低于6个文件');
        }

        if (count($file) > 1 && count($file) < 7) {
            //多文件上传
            foreach ($file as $k => $v) {
               
                if ($v) {
                    
                    $url = $this->doUpload($v);
                    
                    $success[] = $url;
                    //检验返回的是否是 正确合法链接
                    if (!preg_match($pattern, $url)) {

                        \Log::info('more_upload_fail');

                        $fail[] = $url;

                        unset($success[$k]);
                    }
                }
            }
        } else {

            $success = $this->doUpload($file);
            //检验参数是否为正确的url
            if (!preg_match($this->pattern, $success)) {
                $fail = $success;
                unset($success);
            }
        }
        return $this->successJson('ok', ['success'=>$success, 'fail'=>$fail]);
    }
    
    //视频类型建议使用第三方存储, 本地暂不支持
    public function doUpload($file)
    {
        if (!$file->isValid()) {
            return false;
        }
        $setting = SystemSetting::settingLoad('global', 'system_global');
        //文件大小是否大于所设置的文件最大值
       
        //文件上传最大执行时间
        $ext = $file->getClientOriginalExtension();

        if ($file->getClientSize() > 30*1024) {
            //文件过大时执行本地上传  
                \Log::info('file_size_out');
        }
        
        //默认支持的文件格式类型
        $defaultImgType = [
            'jpg', 'bmp', 'eps', 'gif', 'mif', 'miff', 'png', 'tif',
            'tiff', 'svg', 'wmf', 'jpe', 'jpeg', 'dib', 'ico', 'tga', 'cut', 'pic'
        ];

        $defaultAudioType = ['AVI', 'ASF', 'WMV', 'AVS', 'FLV', 'MKV', 'MOV', '3GP', 'MP4',
            'MPG', 'MPEG', 'DAT', 'OGM', 'VOB', 'RM', 'RMVB', 'TS', 'TP', 'IFO', 'NSV'
        ];

        $defaultVideoType = [
            'MP3', 'AAC', 'WAV', 'WMA', 'CDA', 'FLAC', 'M4A', 'MID', 'MKA', 'MP2',
                'MPA', 'MPC', 'APE', 'OFR', 'OGG', 'RA', 'WV', 'TTA', 'AC3', 'DTS'
        ];

        if (in_array($ext, $defaultImgType)) {
            $file_type = 'syst';
        } elseif (in_array($ext, $defaultAudioType)) {
            $file_type = 'audio';
        } elseif (in_array($ext, $defaultVideoType)) {
            $file_type = 'video';
        }

        if ($setting['type'] == 0) {
            //判断是否属于设置的类型
          
            if (in_array($ext, $defaultImgType)) {

                $file_type = 'syst';

                if ($sett['image_extentions'] && !in_array($ext, $img_type) ) {
                    return '非规定类型的文件格式';
                }

                $defaultImgSize = $setting['img_size'] ? $setting['img_size'] : 10240;

                if ($file->getClientSize() > $defaultImgSize) {
                    return '文件大小超出规定值';
                }

            }

            if (in_array($ext, $defaultAudioType) || in_array($ext, $defaultVideoType)) {

                $file_type = in_array($ext, $defaultVideoType) ? 'video' : 'audio';

                if ($setting['audio_extentions'] && !in_array($ext, $img_type) ) {
                    return '非规定类型的文件格式';
                }
                $defaultAudioSize = $setting['audio_limit'] ? $setting['audio_limit'] : 30702; //音视频最大 30M

                if ($file->getClientSize() > $defaultAudioSize) {
                    return '文件大小超出规定值';
                }
            }

            //执行本地上传
            $res =  $this->uploadLocal($file, $file_type, $setting['zip_percentage']);
        }

        if ($setting['type'] == 2) {
            //阿里OSS
            $res = $this->OssUpload($file, $setting, $file_type);
        }

        if ($setting['type'] == 4) {
            //腾讯cos
            $res = $this->CosUpload($file, $setting, $file_type);
        }

        return $res;
    }

    //本地上传
    public function uploadLocal($file, $file_type, $percent)
    {
        if (!$file) {
            return '请传入正确参数';
        }

        if ($file->isValid()) {
            $originalName = $file->getClientOriginalName(); // 文件原名

            $realPath = $file->getRealPath();   //临时文件的绝对路径

            $ext = $file->getClientOriginalExtension(); //文件扩展名

            $newOriginalName = $this->getNewFileName($originalName, $ext);

            $res = \Storage::disk($file_type)->put($newOriginalName, file_get_contents($realPath));

            if ($res) {
                //存储至数据表中
                $core = new \app\platform\modules\application\models\CoreAttach;

                $d = [
                    'uniacid' => \YunShop::app()->uniacid ? : 0,
                    'uid' => \Auth::guard('admin')->user()->uid,
                    'filename' => $originalName,
                    'type' => $file_type == 'syst' ? 1 : 2, //类型1.图片; 2.音乐
                    'attachment' => $newOriginalName
                ];

                $core->fill($d);
                $validate = $core->validator();

                if (!$validate->fails()) {
                    $core->save();
                }
            }
            
            if ($percent) {
                //执行图片压缩
                $imagezip = new ImageZip();

                $zipOrNot = $imagezip->makeThumb(
                    \Storage::disk($file_type)->url().$newOriginalName,
                    \Storage::disk($file_type)->url().$newOriginalName,
                    $percent
                );
            }
           
            return $this->proto.$_SERVER['HTTP_HOST'].$this->path.'/'.$newOriginalName;
        }
    }

    //获取本地已上传图片的列表 需优化搜索
    public function getLocalList()
    {
        $core = new CoreAttach();
        if (request()->search) {
            $core = $core->search(request()->search);
        }
        $list = $core->paginate()->toArray();

        foreach ($list['data'] as $v) {

            if ($v['attachment']) {

                $data[] = $this->proto.$_SERVER['HTTP_HOST'].$this->path.$v['attachment'];
            }
        }

        return $this->successJson('获取成功', $data);
    }

    //腾讯云上传
    public function CosUpload($file, $setting, $file_type)
    {
        if (!$setting) {
            return '请配置参数';
        }

        $config = [
            'app_id'     => $setting['appid'],
            'secret_id'  => $setting['secretid'],
            'secret_key' => $setting['secretkey'],
            'bucket'     => $setting['bucket'],
            'region'     => $setting['url']
        ];
        $cos = new Api($config);

        $originalName = $file->getClientOriginalName(); // 文件原名

        $ext = $file->getClientOriginalExtension(); //文件扩展名

        $realPath = $file->getRealPath();   //临时文件的绝对路径

        \Log::info('cos_upload_file_content:', ['name'=> $originalName, 'ext'=>$ext, 'path'=>$realPath]);

        $truePath = $this->getOsPath($file_type);   // COS服务器 bucket 路径

        $newFileName = $this->getNewFileName($originalName, $ext); //新文件名

        \Log::info('cos_upload_path:', $truePath.$newFileName);

        $res = $cos->upload($setting['bucket'], $realPath.'/'.$originalName, $truePath.$newFileName, '', 1);  //执行上传

        \Log::info('cos_upload_res:', $res);

        //检查 object 及服务器路径 权限
        if ($res['code'] == 0 && $res['message'] == 'SUCCESS') {
            // 自定义域名拼接文件名
            \Log::info('cos_upload_url', $setting['url'].$truePath.$newFileName);
            return $setting['url'].$truePath.$newFileName;
        }
        return '上传失败,请重试';
    }

    //阿里云OSS 文件上传 (支持内网上传, 加强参数权限检验, 暂不支持CDN中转自定义域名)
    public function OssUpload($file, $setting, $file_type)
    {
        if (!$setting['key'] || !$setting['secret'] || !$setting['bucket']) {
            return '请配置参数';
        }

        $o = explode('.', $setting['bucket']); 
        
        if (!$o ) {
            return '请检查参数';
        }

        $setting['endpoint'] = $o[1].'.'.$o[2].'.'.$o[3];

        try{
            $oss = new OssClient($setting['key'], $setting['secret'], $setting['endpoint']);
        
        } catch(OssException $e) {
            return $e->getMessage();
        }
        
        $lists = $oss->listBuckets();

        foreach ($lists as $k => $v) {
            
            if ($v['name'] != $setting['bucket'] || $v['location'] != $setting['endpoint']) {
                return '数据错误,请检查参数';
            }
        }
        //检查bucket中的域名
        $bucketInfo =  $oss->getBucketMeta($setting['bucket']);
        
        $bu = explode('.', $bucketInfo['info']['url']);
    
            \Log::info('oss_upload_check_endpoint:', $bu);
        
        if ($setting['endpoint'].'/' !== $bu[1].'.'.$bu[2].'.'.$bu[3]) {
            return 'endpoint 数据错误';
        }
        unset($bucketInfo, $bu);

        if ($setting['internal'] == 1) {
            //使用内网上传时
            $data = explode('.', $setting['endpoint']); //获取endpoint_internal
            
            $one = $data[0].'-internal';
            
            $domain = $one.'.'.$data[1].'.'.$data[2]; //拼接内网地址

            $oss = new OssClient($setting['key'], $setting['secret'], $domain, $setting['endpoint']);
        } 

        $originalName = $file->getClientOriginalName(); // 文件原名

        $ext = $file->getClientOriginalExtension(); //文件扩展名

        $realPath = $file->getRealPath();   //临时文件的绝对路径

        $content = $realPath.'/'.$originalName;

            \Log::info('oss_upload_file_content:', ['name'=> $originalName, 'ext'=>$ext, 'path'=>$realPath]);

        $truePath = substr($this->getOsPath($file_type), 1);   // OSS服务器 bucket 路径

        $newFileName = $this->getNewFileName($originalName, $ext);

        $object = $truePath.$newFileName;
            \Log::info('oss_upload_path:', $object);

        if ($setting['internal'] != 1) {
            //使用外网上传 域名为
            $domain = $setting['url'] ?  : $setting['endpoint'];
        }

        $res = $oss->putObject($setting['bucket'], $object, file_get_contents($content));
           
            \Log::info('AliOss_res, and Content', [$res, $content]);
        
        if ($res['info']['http_code'] == 200) {
            //检查 object bucket 权限
            if ($oss->getBucketAcl($setting['bucket']) == 'private' || $oss->getObjectAcl($setting['bucket'], $object) == 'private') {
                //私有时访问加签
                $url = $oss->signUrl($setting['bucket'], $object, 3600*24);
                    
                    \Log::info('getBucketOrObjAcl, true');
                
            } else {
                $url  = $res['info']['url']; //公有权限时
                // $url = 'http://'.$setting['bucket'].'.'.$domain.'/'.$object;
            }
            return $url;
        }
        return '上传失败,请重试';       
    }

    /**
     * 生成文件存放路径
     * @param  string $file_type 文件类型:syst图片,audio音频,video视频
     * @return string            路径
     */
    public function getOsPath($file_type)
    {
        $file_type = $file_type == 'syst' ? 'image' : $file_type ;

        $uniacid = \YunShop::app()->uniacid ? : 0 ;

        return '/'.$file_type.'s/'.$uniacid.'/'.date('Y').'/'.date('m').'/';
    }
    /**
     * 获取新文件名
     * @param  string $originalName 原文件名
     * @param  string $ext          文件扩展名
     * @return string               新文件名
     */
    public function getNewFileName($originalName, $ext)
    {
        return date('Ymd').md5($originalName . str_random(6)) . '.' . $ext;
    }

    public function ossTest()
    {
        $oss = new OssClient(config('filesystems.disks.oss.access_id'), config('filesystems.disks.oss.access_key'), config('filesystems.disks.oss.endpoint'));

        $setting = SystemSetting::settingLoad('global', 'system_global');
        // dd($setting);
         $o = explode('.', 'test-yunshop-com.oss-cn-hangzhou.aliyuncs.com');
            $setting['endpoint'] = $o[1].'.'.$o[2].'.'.$o[3]; dd($setting['endpoint']);

        // $internal_oss = new OssClient(config('filesystems.disks.oss.access_id'), config('filesystems.disks.oss.access_key'), config('filesystems.disks.oss.endpoint_internal')); dd($internal_oss); //内网上传时使用

        // $authOss = new OssClient(config('filesystems.disks.oss.access_id'), config('filesystems.disks.oss.access_key'), config('filesystems.disks.oss.endpoint'), 'false', $token); //bucket或object权限私有时并设置STS作为验签方法时使用

        // $auth = $oss->getObjectAcl(config('filesystems.disks.oss.bucket'), '20190318347e0052aa60ce815f6f58bcd4b15a5e.png'); dd($auth); //获取对象权限

        // $acl = $oss->getBucketAcl(config('filesystems.disks.oss.bucket')); dd($acl); //获取bucket 权限 return string

        // $res = $oss->PutBucketACL('test-yunshop-com', 'public-read'); dd($res); //修改bucket 权限

        // $bucketInfo = $oss->getBucketMeta(config('filesystems.disks.oss.bucket')); dd($bucketInfo);
        // $a = explode('.', $bucketInfo['info']['url']);
        // dd($a, $a[1].'.'.$a[2].'.'.$a[3]); //获取bucket信息

        // $b = $oss->getBucketCname(config('filesystems.disks.oss.bucket')); dd($b);
        $lists = $oss->listBuckets();
        foreach ($lists as $k => $v) {
            if ($v['name'] != config('filesystems.disks.oss.bucket') && $v['location'] != config('filesystems.disks.oss.endpoint')) {
                dd('1');
            }
        }
        // dd('存在');
        // $checkObj = $oss->doesObjectExist(config('filesystems.disks.oss.bucket'),  'test/2a2eae2748b5788ea3a190dd8948e137.png'); dd($$checObj); //检查文件是否存在 true 时存在
        $image = 'videos/0/2019/03/'.md5('2s1sf4s411').'.qsv';
        $res = $oss->putObject(
            config('filesystems.disks.oss.bucket'),
            $image,
            file_get_contents('E:\iqiyi\young\young-1.qsv')
        );
        
        $signUrl = $oss->signUrl(config('filesystems.disks.oss.bucket'), $image, 3600); //dd($signUrl); //加签名 return string

        dd($res, $signUrl);
    }

    public function cosTest()
    {
        $config = config('filesystems.disks.cos');
        unset($config['region']);
        $cos = new Api($config); 
        $check = '/images/0/2019/03/';
        $originalName = 'aaa222www11';
        $ext='png';
        $newFileName = date('Ymd').md5($originalName . str_random(6)) . '.' . $ext;

        $zip = new ImageZip();
        $res = $zip->makeThumb('D:\wamp\www\shop\storage\app\public\201903203974dc2b7ba9eefbe640b5395a8de517.jpeg', 'D:\wamp\www\shop\storage\app\\'.md5('2w43d3').'.jpeg',  '36%');
        dd($res);

        $im = $this->imageDeal('D:\wamp\www\shop\storage\app\public\201903203974dc2b7ba9eefbe640b5395a8de517.jpeg', '50%', $ext);
        dd($im);

        $res = $cos->upload(
            config('filesystems.disks.cos.bucket'),
            'D:\wamp\www\shop\storage\app\public\201903203974dc2b7ba9eefbe640b5395a8de517.jpeg',
            'test/'.date('Y').'/'.$newFileName
        );
        $url = config('filesystems.disks.cos.bucket').'-'.config('filesystems.disks.cos.app_id').'.cos'.config('filesystems.disks.cos.region').'.myqcloud.com/'.$check.'/iamges';
        dd($res, $url);

        $file_type = $file_type == 'syst' ? 'images' : $file_type.'s';

        $uniacid = \YunShop::app()->uniacid ? : 0;

        $Syspath = '/images';
        //查看该目录下有无此文件夹
        $first = $cos->listFolder(config('filesystems.disks.cos.bucket'), $Syspath); dd($first);

        if ($first['code'] == 0 && $first['message'] == 'SUCCESS' && $first['data']['infos']) {

        }
        //查询目录路径
        // $stat = $cos->stat(config('filesystems.disks.cos.bucket'), $Syspath);
        // dd($stat, $Syspath);
        $checkdir = $cos->statFolder(config('filesystems.disks.cos.bucket'), $Syspath); dd($checkdir);
        // $dirLists = $cos->listFolder(config('filesystems.disks.cos.bucket'), '/');
        // dd($dirLists);
        return '/images/'.$uniacid.'/'.date('Y').'/'.date('m');
    }

}