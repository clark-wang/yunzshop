<?php

use Illuminate\Support\Str;
use app\common\services\PermissionService;
use app\common\models\Menu;
use app\common\services\Session;

//商城根目录
define('SHOP_ROOT', dirname(__FILE__));

class YunShop
{
    private static $_req;
    private static $_app;
    public static $currentItems = [];

    public function __construct()
    {

    }

    public static function run($namespace, $modules, $controllerName, $action, $currentRoutes)
    {
        //检测命名空间
        if (!class_exists($namespace)) {
            abort(404, " 不存在命名空间: " . $namespace);
        }
        //检测controller继承
        $controller = new $namespace;
        if (!$controller instanceof \app\common\components\BaseController) {
            abort(404, '不存在控制器:' . $namespace);
        }

        //设置默认方法
        if (empty($action)) {
            $action = 'index';
            self::app()->action = $action;
            $currentRoutes[] = $action;
        }

        //检测方法是否存在并可执行
        if (!method_exists($namespace, $action) || !is_callable([$namespace, $action])) {
            abort(404, '操作方法不存在: ' . $action);
        }
        $controller->modules = $modules;
        $controller->controller = $controllerName;
        $controller->action = $action;
        $controller->route = implode('.', $currentRoutes);

        if(self::isWeb()){
            //菜单生成
            if(!\Cache::has('db_menu')){
                $dbMenu = Menu::getMenuList();
                \Cache::put('db_menu',$dbMenu,3600);
            }else{
                $dbMenu = \Cache::get('db_menu');
            }
            $menuList =  array_merge($dbMenu, (array)Config::get('menu'));
            Config::set('menu',$menuList);
            $item = Menu::getCurrentItemByRoute($controller->route,$menuList);
            self::$currentItems = array_merge(Menu::getCurrentMenuParents($item, $menuList), [$item]);
            //检测权限
            if (!PermissionService::can($item)) {
                abort(403, '无权限');
            }
        }

        //执行方法
        $controller->preAction();
        $content = $controller->$action(
            Illuminate\Http\Request::capture()
        );

        exit($content);
    }


    /**
     * Configures an object with the initial property values.
     * @param object $object the object to be configured
     * @param array $properties the property initial values given in terms of name-value pairs.
     * @return object the object itself
     */
    public static function configure($object, $properties)
    {
        foreach ($properties as $name => $value) {
            $object->$name = $value;
        }

        return $object;
    }

    public static function getAppNamespace()
    {
        $rootName = 'app';
        if (self::isWeb()) {
            $rootName .= '\\backend';
        }
        if (self::isApp() || self::isApi()) {
            $rootName .= '\\frontend';
        }
        return $rootName;
    }

    public static function getAppPath()
    {
        $path = dirname(__FILE__);
        if (self::isWeb()) {
            $path .= '/backend';
        }
        if (self::isApp() || self::isApi()) {
            $path .= '/frontend';
        }
        return $path;
    }

    public static function isWeb()
    {
        return strpos($_SERVER['PHP_SELF'], '/web/index.php') !== false ? true : false;
    }

    public static function isApp()
    {
        return strpos($_SERVER['PHP_SELF'], '/app/index.php') !== false ? true : false;
    }

    public static function isApi()
    {
        return (strpos($_SERVER['PHP_SELF'], '/addons/') !== false &&
            strpos($_SERVER['PHP_SELF'], '/api.php') !== false) ? true : false;
    }

    /**
     * 是否插件
     * @return bool
     */
    public static function isPlugin()
    {
        return (strpos($_SERVER['PHP_SELF'], '/web/') !== false &&
            strpos($_SERVER['PHP_SELF'], '/plugin.php') !== false) ? true : false;
    }

    public static function isPayment()
    {
        return strpos($_SERVER['PHP_SELF'], '/payment/') > 0 ? true : false;
    }

    public static function request()
    {
        if (self::$_req !== null) {
            return self::$_req;
        } else {
            self::$_req = new YunRequest();
            return self::$_req;
        }
    }

    public static function app()
    {
        if (self::$_app !== null) {
            return self::$_app;
        } else {
            self::$_app = new YunApp();
            return self::$_app;
        }
    }

    /**
     * 解析路由
     *
     * 后台访问  /web/index.php?c=site&a=entry&m=sz_yi&do=xxx&route=module.controller.action
     * 前台      /app/index.php....
     *
     * 多字母的路由用中划线隔开 比如：
     *      TestCacheController
     *          function testClean()
     * 路由写法为   test-cache.test-clean
     *
     */
    public static function parseRoute($requestRoute)
    {
        $routes = explode('.', $requestRoute);

        $path = self::getAppPath();

        $namespace = self::getAppNamespace();
        $action = '';
        $controllerName = '';
        $currentRoutes = [];
        $modules = [];
        if ($routes) {
            $length = count($routes);
            $routeFirst = array_first($routes);
            $countRoute = count($routes);
            if ($routeFirst === 'plugin' || self::isPlugin()) {
                if(self::isPlugin()){
                    $currentRoutes[] = 'plugin';
                    $countRoute += 1;
                }else{
                    $currentRoutes[] = $routeFirst;
                    array_shift($routes);
                }
                $namespace = 'Yunshop';
                $pluginName = array_shift($routes);
                if ($pluginName || plugin($pluginName)) {
                    $currentRoutes[] = $pluginName;
                    $namespace .= '\\' . ucfirst(Str::camel($pluginName));
                    $path = base_path() . '/plugins/' . $pluginName . '/src';
                    $length = $countRoute;

                    self::findRouteFile($controllerName,$action, $routes, $namespace, $path, $length,$currentRoutes, $requestRoute,true);


                } else {
                    abort(404, '无此插件');
                }
            } else {

                self::findRouteFile($controllerName,$action, $routes, $namespace, $path, $length,$currentRoutes, $requestRoute,false);

            }
        }
        //执行run
        static::run($namespace, $modules, $controllerName, $action, $currentRoutes);

    }

    /**
     * 定位路由相关文件
     * @param $controllerName
     * @param $action
     * @param $routes
     * @param $namespace
     * @param $path
     * @param $length
     * @param $requestRoute
     * @param $isPlugin
     */
    public static function findRouteFile(&$controllerName,&$action,$routes, &$namespace, &$path, $length, &$currentRoutes,$requestRoute,$isPlugin)
    {

        foreach ($routes as $k => $r) {
            $ucFirstRoute = ucfirst(Str::camel($r));
            $controllerFile = $path . ($isPlugin ? '/' : '/controllers/' ). $ucFirstRoute . 'Controller.php';
            if (is_file($controllerFile)) {
                $namespace .= ($isPlugin?'':'\\controllers').'\\' . $ucFirstRoute . 'Controller';
                $controllerName = $ucFirstRoute;
                $path = $controllerFile;
                $currentRoutes[] = $r;
            } elseif (is_dir($path .= ($isPlugin?'':'/modules').'/' . $r)) {
                $namespace .= ($isPlugin?'':'\\modules').'\\' . $r;
                $modules[] = $r;
                $currentRoutes[] = $r;
            } else {
                if ($length !== ($isPlugin ? $k + 3 : $k+1)) {
                    abort(404,'no found route:' . $requestRoute);
                }
                $action = strpos($r, '-') === false ? $r : Str::camel($r);
                $currentRoutes[] = $r;
            }

        }

    }

    public static function getUcfirstName($name)
    {
        if (strpos($name, '-')) {
            $names = explode('-', $name);
            $name = '';
            foreach ($names as $v) {
                $name .= ucfirst($v);
            }
        }
        return ucfirst($name);
    }

}

class YunComponent implements ArrayAccess
{
    protected $values = [];

    public function __set($name, $value)
    {
        return $this->values[$name] = $value;
    }

    public function __get($name)
    {
        if (!array_key_exists($name, $this->values)) {
            $this->values[$name] = null;
        }
        return $this->values[$name];
    }

    public function set($name, $value)
    {
        $this->values[$name] = $value;
        return $this;
    }

    public function get($key = null)
    {
        if (isset($key)) {
            $result = json_decode(array_get($this->values, $key, null),true);
            if(@is_array($result)){
                return $result;
            }
            return array_get($this->values, $key, null);
        }
        return $this->values;
    }

    public function offsetUnset($offset)
    {
        unset($this->values[$offset]);
    }

    public function offsetSet($offset, $value)
    {
        $this->values[$offset] = $value;
    }

    public function offsetGet($offset)
    {
        if (isset($this->values[$offset])) {
            return $this->values[$offset];
        }
        return null;
    }

    public function offsetExists($offset)
    {
        if (isset($this->values[$offset])) {
            return true;
        }
        return false;
    }
}

class YunRequest extends YunComponent
{

    public function __construct()
    {
        global $_GPC;
        $this->values = !YunShop::isWeb() ? request()->input() :(array)$_GPC;
    }


}

class YunApp extends YunComponent
{
    protected $values;
    protected $routeList;
    public $currentItems = [];

    public function __construct()
    {
        global $_W;
        $this->values = !YunShop::isWeb() ? $this->getW() : (array)$_W;
        $this->routeList = Config::get('menu');
    }
    
    public function getW()
    {
        return [
            'uniacid'=>request()->get('i'),
            'weid'=>request()->get('i'),
            'acid'=>request()->get('i'),
            'account' => \app\common\models\AccountWechats::getAccountByUniacid(request()->get('i'))?\app\common\models\AccountWechats::getAccountByUniacid(request()->get('i'))->toArray():''
        ];
    }

    /**
     * 通过子路由获取交路径
     * @return mixed
     */
    public function getRoutes()
    {
        $key = 'routes-child-parent';
        $routes = \Cache::get($key);
        if ($routes === null) {
            $routes = $this->allRoutes();
            \Cache::put($key, $routes, 36000);
        }

        return $routes;
    }

    protected function allRoutes($list = [], $parent = [])
    {
        $routes = [];
        !$list && $list = $this->routeList;
        if ($list) {
            foreach ($list as $k => $v) {
                $temp = $v;
                if (isset($temp['child'])) {
                    unset($temp['child']);
                }
                if (isset($v['url'])) {
                    $routes[$v['url']] = array_merge($temp, ['parent' => $parent]);
                    if (isset($v['child']) && $v['child']) {
                        $routes = array_merge($routes,
                            $this->allRoutes($v['child'], array_merge($parent, $routes[$v['url']])));
                    }
                }
            }
        }

        return $routes;
    }

    public function setCurrentItems($items)
    {
        $this->currentItems = $items;
    }

    public function getCurrentItems()
    {
        return $this->currentItems;
    }

    /**
     * @todo set member id from session
     * @return int
     */
    public function getMemberId()
    {
        if (config('app.debug')) {
            if(isset($_GET['test_uid'])){
                return $_GET['test_uid'];
            }
           //return false;
        }

        if (Session::get('member_id')) {
            return Session::get('member_id');
        } else {
            return 0;
        }
    }


}
