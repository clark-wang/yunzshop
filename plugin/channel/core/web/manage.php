<?php
global $_W, $_GPC;
$operation   = empty($_GPC['op']) ? 'display' : $_GPC['op'];

$channellevels = pdo_fetchall('SELECT * FROM ' . tablename('sz_yi_channel_level') . ' WHERE uniacid = :uniacid ORDER BY level_num DESC',array(':uniacid' => $_W['uniacid']));//渠道商等级
if ($operation == 'display') {
    ca('channel.manage.view');
    $pindex    = max(1, intval($_GPC['page']));
    $psize     = 20; 
    $params    = array();
    $condition = '';
    if (!empty($_GPC['mid'])) {//会员id
        $condition .= ' AND dm.id=:mid';
        $params[':mid'] = intval($_GPC['mid']);
    }
    if (!empty($_GPC['realname'])) {//会员姓名
        $_GPC['realname'] = trim($_GPC['realname']);
        $condition .= ' and ( dm.realname like :realname or dm.nickname like :realname or dm.mobile like :realname)';
        $params[':realname'] = "%{$_GPC['realname']}%";
    }
    if (!empty($_GPC['channel_level'])) {//渠道商等级
        $condition .= ' AND dm.channel_level=' . intval($_GPC['channel_level']);
    }
    $sql = "SELECT dm.*,dm.nickname,dm.avatar,l.level_name,l.level_num,p.nickname AS parentname,p.avatar AS parentavatar FROM " . tablename('sz_yi_member') . " dm " . " left join " . tablename('sz_yi_member') . " p on p.id = dm.channel_id " . " left join " . tablename('sz_yi_channel_level') . " l on l.id = dm.channel_level" . " left join " . tablename('mc_mapping_fans') . "f on f.openid=dm.openid AND f.uniacid={$_W['uniacid']}" . " WHERE dm.uniacid = " . $_W['uniacid'] . " AND dm.ischannel =1  {$condition} ORDER BY dm.channeltime DESC";
    $list  = pdo_fetchall($sql, $params);
    $total = pdo_fetchcolumn("SELECT count(dm.id) FROM" . tablename('sz_yi_member') . " dm  " . " left join " . tablename('sz_yi_member') . " p on p.id = dm.channel_id " . " left join " . tablename('mc_mapping_fans') . "f on f.openid=dm.openid" . " WHERE dm.uniacid =" . $_W['uniacid'] . " AND dm.ischannel =1 {$condition}", $params);
    
    
} else if ($operation == 'detail') {
    ca('channel.manage.view');
    $id = intval($_GPC['id']);
    $member = pdo_fetch('SELECT * FROM ' . tablename('sz_yi_member') . ' WHERE id = :id' , array(':id' => $id));
    if (checksubmit('submit')) {
        ca('channel.manage.edit|channel.manage.check|channel.manage.manageblack');
        $data = is_array($_GPC['data']) ? $_GPC['data'] : array();
        pdo_update('sz_yi_member', $data, array(
            'id' => $id,
            'uniacid' => $_W['uniacid']
        ));
        message('保存成功!', $this->createPluginWebUrl('channel/manage'), 'success');
    }
}
load()->func('tpl');
include $this->template('manage');

