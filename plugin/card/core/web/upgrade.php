<?php
global $_W;
if (!defined('IN_IA')) {
    exit('Access Denied');
}
$result = pdo_fetchcolumn('select id from ' . tablename('sz_yi_plugin') . ' where identity=:identity', array(':identity' => 'card'));
if(empty($result)){
    $displayorder_max = pdo_fetchcolumn('select max(displayorder) from ' . tablename('sz_yi_plugin'));
    $displayorder = $displayorder_max + 1;
    $sql = "INSERT INTO " . tablename('sz_yi_plugin') . " (`displayorder`,`identity`,`name`,`version`,`author`,`status`,`category`) VALUES(". $displayorder .",'card','代金卡','1.0','官方','1','sale');";
  pdo_query($sql);
}
/*$sql = "
CREATE TABLE IF NOT EXISTS ". tablename('sz_yi_chooseagent') . " (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` int(11) DEFAULT NULL,
  `agentname` varchar(255) DEFAULT NULL,
  `isopen` int(11) DEFAULT NULL COMMENT '0为关闭,1为开启',
  `createtime` varchar(255) DEFAULT NULL,
  `savetime` varchar(255) DEFAULT NULL,
  `uniacid` int(11) DEFAULT NULL,
  `pcate` int(11) DEFAULT NULL,
  `ccate` int(11) DEFAULT NULL,
  `tcate` int(11) DEFAULT NULL,
  `pagename` varchar(255) DEFAULT NULL,
  `color` varchar(255) DEFAULT NULL,
  `detail` int(11) DEFAULT  '0',
  `allgoods` int(11) DEFAULT  '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=33 DEFAULT CHARSET=utf8;
";
pdo_query($sql);*/


message('芸众代金卡插件安装成功', $this->createPluginWebUrl('card/index'), 'success');
