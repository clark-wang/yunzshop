<?php
if(!pdo_fieldexists('sz_yi_bonus_log', 'type')) {
  pdo_fetchall("ALTER TABLE ".tablename('sz_yi_bonus_log')." ADD `type` tinyint(1) DEFAULT '0';");
}
if(!pdo_fieldexists('sz_yi_bonus', 'bonus_area')) {
  pdo_fetchall("ALTER TABLE ".tablename('sz_yi_bonus')." ADD `bonus_area` tinyint(1) DEFAULT '0';");
}
//9.13添加
//充值记录表中添加进行充值中的记录
if (!pdo_fieldexists('sz_yi_member_log', 'underway')) {
    pdo_fetchall("ALTER TABLE ".tablename('sz_yi_member_log')." ADD `underway` tinyint(1) DEFAULT '0';");
}                                          an

//9.18添加
if (!pdo_fieldexists('sz_yi_goods', 'catch_id')) {
    pdo_fetchall("ALTER TABLE ".tablename('sz_yi_goods')." ADD `catch_id` int(11) DEFAULT '0';");
}
if (!pdo_fieldexists('sz_yi_goods', 'catch_source')) {
    pdo_fetchall("ALTER TABLE ".tablename('sz_yi_goods')." ADD `catch_source` int(11) DEFAULT '0';");
}
if (!pdo_fieldexists('sz_yi_goods', 'catch_url')) {
    pdo_fetchall("ALTER TABLE ".tablename('sz_yi_goods')." ADD `catch_url` int(11) DEFAULT '0';");
}
if (!pdo_fieldexists('sz_yi_goods', 'minprice')) {
    pdo_fetchall("ALTER TABLE ".tablename('sz_yi_goods')." ADD `minprice` decimal(10,2) DEFAULT '0.00';");
}
if (!pdo_fieldexists('sz_yi_goods', 'maxprice')) {
    pdo_fetchall("ALTER TABLE ".tablename('sz_yi_goods')." ADD `maxprice` decimal(10,2) DEFAULT '0.00';");
}
//9.21添加
if(!pdo_fieldexists('sz_yi_bonus_log', 'goodids')) {
  pdo_fetchall("ALTER TABLE ".tablename('sz_yi_bonus_log')." ADD `goodids` text DEFAULT '';");
}
if(!pdo_fieldexists('sz_yi_bonus', 'orderids')) {
  pdo_fetchall("ALTER TABLE ".tablename('sz_yi_bonus')." ADD `orderids` text DEFAULT '';");
}
//9.30添加  yangyang
if(p('channel')){
pdo_fetchall("ALTER TABLE ".tablename('sz_yi_af_channel')." CHANGE `diychannelfields` `diychannelfields` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '自定义表单字段';");
pdo_fetchall("ALTER TABLE ".tablename('sz_yi_af_channel')." CHANGE `diychanneldata` `diychanneldata` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '自定义表单数据';");
pdo_fetchall("ALTER TABLE ".tablename('sz_yi_af_channel')." CHANGE `realname` `realname` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '姓名';");
pdo_fetchall("ALTER TABLE ".tablename('sz_yi_af_supplier')." CHANGE `diymemberfields` `diymemberfields` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '自定义表单字段';");
pdo_fetchall("ALTER TABLE ".tablename('sz_yi_af_supplier')." CHANGE `diymemberdata` `diymemberdata` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '自定义表单数据';");

//11.9 街道分红
if (!pdo_fieldexists('sz_yi_member', 'bonus_street')) {
    pdo_fetchall("ALTER TABLE ".tablename('sz_yi_member')." ADD `bonus_street` varchar(50) DEFAULT '';");
}
echo 1;