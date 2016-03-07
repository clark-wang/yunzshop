<?php
/*QQ:261753427*/
if (!defined("IN_IA")) {
    print("Access Denied");
}
class ExhelperWeb extends Plugin
{
    public function __construct()
    {
        parent::__construct("exhelper");
    }
    public function index()
    {
        header("location: " . $this->createPluginWebUrl("exhelper/express", array(
            "op" => "list",
            "cate" => 1
        )));
        exit;
    }
    public function api()
    {
        $this->_exec_plugin(__FUNCTION__);
    }
    public function express()
    {
        $this->_exec_plugin(__FUNCTION__);
    }
    public function doprint()
    {
        $this->_exec_plugin(__FUNCTION__);
    }
    public function print_tpl()
    {
        $this->_exec_plugin(__FUNCTION__);
    }
    public function senduser()
    {
        $this->_exec_plugin(__FUNCTION__);
    }
    public function short()
    {
        $this->_exec_plugin(__FUNCTION__);
    }
    public function printset()
    {
        $this->_exec_plugin(__FUNCTION__);
    }
}
?>