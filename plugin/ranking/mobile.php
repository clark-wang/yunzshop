<?php
if (!defined('IN_IA')) {
    exit('Access Denied');
}

class RankingMobile extends Plugin
{
    protected $set = null;
    public function __construct()
    {
        parent::__construct('ranking');
        $this->set = $this->getSet();
        global $_GPC;
    }

    public function ranking()
    {    
        $this->_exec_plugin(__FUNCTION__, false);
    }


}