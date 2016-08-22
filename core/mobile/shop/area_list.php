<?php
if (!defined('IN_IA')) {
    exit('Access Denied');
}
global $_W, $_GPC;

$url = 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
$operation  = !empty($_GPC['op']) ? $_GPC['op'] : 'index';
$openid     = m('user')->getOpenid();
$uniacid    = $_W['uniacid'];
$set = set_medias(m('common')->getSysset('shop'), array('logo', 'img'));
$commission = p('commission');
$shopset   = m('common')->getSysset('shop');
if ($operation == 'index') {

    $sql = 'SELECT * FROM ' . tablename('sz_yi_category_area') . ' WHERE `uniacid` = :uniacid ORDER BY `parentid`, `displayorder` DESC';
    $category_area = pdo_fetchall($sql, array(':uniacid' => $_W['uniacid']), 'id');
    $parent_area = $children_area = array();
    if (!empty($category_area)) {
        foreach ($category_area as $cid => $cate_area) {
            if (!empty($cate_area['parentid'])) {
                $children_area[$cate_area['parentid']][] = $cate_area;
            } else {
                $parent_area[$cate_area['id']] = $cate_area;
            }
        }
    }
    if (!empty($_GPC['ccate_area'])) {
        $current_category = pdo_fetchall('select * from ' . tablename('sz_yi_category_area') . ' where parentid=:id 
            and uniacid=:uniacid order by displayorder DESC', array(
            ':id' => intval($_GPC['ccate_area']),
            ':uniacid' => $_W['uniacid']
        ));
        $category = pdo_fetch('select * from ' . tablename('sz_yi_category_area') . ' where id=:id 
            and uniacid=:uniacid order by displayorder DESC', array(
            ':id' => intval($_GPC['ccate_area']),
            ':uniacid' => $_W['uniacid']
        ));
    } elseif (!empty($_GPC['pcate_area'])) {
        $current_category = pdo_fetchall('select * from ' . tablename('sz_yi_category_area') . ' where parentid=:id 
            and uniacid=:uniacid order by displayorder DESC', array(
            ':id' => intval($_GPC['pcate_area']),
            ':uniacid' => $_W['uniacid']
        ));
        $category = pdo_fetch('select * from ' . tablename('sz_yi_category_area') . ' where id=:id 
            and uniacid=:uniacid order by displayorder DESC', array(
            ':id' => intval($_GPC['pcate_area']),
            ':uniacid' => $_W['uniacid']
        ));
    }   
}



if ($_W['isajax']) {  
    if (!empty($_GPC['ccate_area'])) {
        $current_category = pdo_fetchall('select * from ' . tablename('sz_yi_category_area') . ' where parentid=:id and uniacid=:uniacid order by displayorder DESC', array(
            ':id' => intval($_GPC['ccate_area']),
            ':uniacid' => $_W['uniacid']
        ));
        $category = pdo_fetch('select * from ' . tablename('sz_yi_category_area') . ' where id=:id 
            and uniacid=:uniacid order by displayorder DESC', array(
            ':id' => intval($_GPC['ccate_area']),
            ':uniacid' => $_W['uniacid']
        ));
    } elseif (!empty($_GPC['pcate_area'])) {
        $current_category = pdo_fetchall('select * from ' . tablename('sz_yi_category_area') . ' where parentid=:id 
            and uniacid=:uniacid order by displayorder DESC', array(
            ':id' => intval($_GPC['pcate_area']),
            ':uniacid' => $_W['uniacid']
        ));
        $category = pdo_fetch('select * from ' . tablename('sz_yi_category_area') . ' where id=:id 
            and uniacid=:uniacid order by displayorder DESC', array(
            ':id' => intval($_GPC['pcate_area']),
            ':uniacid' => $_W['uniacid']
        ));
        $parent = pdo_fetchall('select * from ' . tablename('sz_yi_category_area') . ' where parentid=0 and uniacid=:uniacid order by displayorder DESC', array(':uniacid' => $_W['uniacid'])); 
        $children = pdo_fetchall('select * from ' . tablename('sz_yi_category_area') . ' where parentid=:id and uniacid=:uniacid order by displayorder DESC', array(':uniacid' => $_W['uniacid'], ':id' => intval($_GPC['pcate_area']))); 
    } 
    
    show_json(1, array(
        'category' => $category,
        'current_category' => $current_category,
        'parent' => $parent,
        'children' => $children
    ));
}
include $this->template('shop/area_list');
