<?php
define('IN_ADMIN', true);
include("../includes/common.php");
if($islogin==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");
$act=isset($_GET['act'])?daddslashes($_GET['act']):null;

if(!checkRefererHost())exit('{"code":403}');

@header('Content-Type: application/json; charset=UTF-8');

switch($act){
case 'getcount':
	$thtime=date("Y-m-d").' 00:00:00';
	$lastday=date("Y-m-d",strtotime("-1 day")).' 00:00:00';
	$count1=$DB->getColumn("SELECT count(*) from pre_file");
	$count2=$DB->getColumn("SELECT count(*) from pre_file WHERE addtime>='$thtime'");
	$count3=$DB->getColumn("SELECT count(*) from pre_file WHERE addtime>='$lastday' AND addtime<'$thtime'");
	$count4=$DB->getColumn("SELECT count(*) from pre_user");

	$result=["code"=>0,"count1"=>$count1,"count2"=>$count2,"count3"=>$count3,"count4"=>$count4];
	exit(json_encode($result));
break;
case 'set':
	if(isset($_POST['green_label_porn'])){
		$_POST['green_label_porn'] = implode(',',$_POST['green_label_porn']);
	}
	if(isset($_POST['green_label_terrorism'])){
		$_POST['green_label_terrorism'] = implode(',',$_POST['green_label_terrorism']);
	}
	foreach($_POST as $k=>$v){
		saveSetting($k, $v);
	}
	exit('{"code":0,"msg":"succ"}');
break;
case 'iptype':
	$result = [
	['name'=>'0_X_FORWARDED_FOR', 'ip'=>real_ip(0), 'city'=>get_ip_city(real_ip(0))],
	['name'=>'1_X_REAL_IP', 'ip'=>real_ip(1), 'city'=>get_ip_city(real_ip(1))],
	['name'=>'2_REMOTE_ADDR', 'ip'=>real_ip(2), 'city'=>get_ip_city(real_ip(2))]
	];
	exit(json_encode($result));
break;
case 'userList':
	$sql=" 1=1";
	$type_arr = ['qq'=>'QQ','wx'=>'微信','local'=>'用户名'];
	if(isset($_POST['dstatus']) && $_POST['dstatus']>-1) {
		$dstatus = intval($_POST['dstatus']);
		$sql.=" AND `enable`={$dstatus}";
	}
	if(isset($_POST['kw']) && !empty($_POST['kw'])) {
		$type = intval($_POST['type']);
		$kw = trim(daddslashes($_POST['kw']));
		if($type == 1){
			$sql.=" AND `uid`='{$kw}'";
		}elseif($type == 2){
			$sql.=" AND `openid`='{$kw}'";
		}elseif($type == 3){
			$sql.=" AND `nickname` LIKE '%{$kw}%'";
		}elseif($type == 4){
			$sql.=" AND `loginip`='{$kw}'";
		}
	}
	$offset = intval($_POST['offset']);
	$limit = intval($_POST['limit']);
	$total = $DB->getColumn("SELECT count(*) from pre_user WHERE{$sql}");
	$list = $DB->getAll("SELECT * FROM pre_user WHERE{$sql} order by uid desc limit $offset,$limit");
	$list2 = [];
	foreach($list as $row){
		$row['rtype'] = $row['type'];
		$row['type'] = $type_arr[$row['type']];
		$list2[] = $row;
	}

	exit(json_encode(['total'=>$total, 'rows'=>$list2]));
break;
case 'setUserEnable':
	$uid=intval($_POST['uid']);
	$enable=intval($_POST['enable']);
	$sql = "UPDATE pre_user SET enable='$enable' WHERE uid='$uid'";
	if($DB->exec($sql)!==false)exit('{"code":0,"msg":"修改用户成功！"}');
	else exit('{"code":-1,"msg":"修改用户失败['.$DB->error().']"}');
break;
case 'saveUserInfo':
	$uid=intval($_POST['uid']);
	$level=intval($_POST['level']);
	$sql = "UPDATE pre_user SET level='$level' WHERE uid='$uid'";
	if($DB->exec($sql)!==false)exit('{"code":0,"msg":"修改用户成功！"}');
	else exit('{"code":-1,"msg":"修改用户失败['.$DB->error().']"}');
break;
case 'checkupdate':
    // 后台检查更新：改为从 OOCloud 源查询（GitHub Releases / Tags）
    $current = defined('VERSION') ? (string)VERSION : '0';
    $latest = null; $latest_url = 'https://github.com/Beicho/OOCloud/releases'; $note = '';
    // 优先获取最新 Release
    $resp = get_curl('https://api.github.com/repos/Beicho/OOCloud/releases/latest');
    $json = json_decode($resp, true);
    if (is_array($json) && isset($json['tag_name'])) {
        $latest = (string)$json['tag_name'];
        $latest_url = $json['html_url'] ?: $latest_url;
        $note = isset($json['name']) ? (string)$json['name'] : '';
    } else {
        // 无 Release，则回落到 tags 列表
        $resp2 = get_curl('https://api.github.com/repos/Beicho/OOCloud/tags');
        $tags = json_decode($resp2, true);
        if (is_array($tags) && isset($tags[0]['name'])) {
            $latest = (string)$tags[0]['name'];
            $latest_url = 'https://github.com/Beicho/OOCloud/tags';
        }
    }
    // 版本归一化比较
    $norm = function($v){ return preg_replace('/[^0-9.]/', '', (string)$v); };
    $curN = $norm($current) ?: '0';
    $latN = $norm($latest) ?: '';
    $status = '无法获取最新版本信息';
    if ($latest) {
        if ($latN && version_compare($curN, $latN, '<')) {
            $status = '发现新版本，建议更新';
        } else {
            $status = '当前为最新版本';
        }
    }
    // 组装 HTML 列表项
    $html = '';
    $html .= '<li class="list-group-item"><b>当前版本：</b>'.htmlspecialchars($current, ENT_QUOTES, 'UTF-8').'</li>';
    if ($latest) {
        $html .= '<li class="list-group-item"><b>最新版本：</b>'.htmlspecialchars($latest, ENT_QUOTES, 'UTF-8');
        if ($note) $html .= ' <span class="text-muted">('.htmlspecialchars($note, ENT_QUOTES, 'UTF-8').')</span>';
        $html .= ' <a href="'.htmlspecialchars($latest_url, ENT_QUOTES, 'UTF-8').'" target="_blank" rel="noopener">查看</a></li>';
    } else {
        $html .= '<li class="list-group-item">无法连接更新源，请稍后重试</li>';
    }
    $html .= '<li class="list-group-item"><b>状态：</b>'.$status.'</li>';
    $html .= '<li class="list-group-item">开源地址：<a href="https://github.com/Beicho/OOCloud" target="_blank" rel="noopener">GitHub/Beicho/OOCloud</a></li>';
    exit(json_encode(['code'=>0,'msg'=>$html]));
break;
case 'resetLocalPassword':
	$uid=intval($_POST['uid']);
	$pwd=trim($_POST['password']);
	if(strlen($pwd) < 6) exit('{"code":-1,"msg":"密码至少6位"}');
	$row=$DB->getRow("select * from pre_user where uid=:uid", [':uid'=>$uid]);
	if(!$row) exit('{"code":-1,"msg":"用户不存在"}');
	if($row['type']!='local') exit('{"code":-1,"msg":"仅支持本地用户改密"}');
	// ensure password column
	if(!$DB->getRow("SHOW COLUMNS FROM pre_user LIKE 'password'")){
		$DB->exec("ALTER TABLE `pre_user` ADD COLUMN `password` varchar(255) NULL AFTER `faceimg`");
	}
	$hash = password_hash($pwd, PASSWORD_DEFAULT);
	if($DB->update('user', ['password'=>$hash], ['uid'=>$uid])!==false) exit('{"code":0,"msg":"修改密码成功！"}');
	else exit('{"code":-1,"msg":"修改密码失败['.$DB->error().']"}');
break;
case 'setUserTotp':
    $uid=intval($_POST['uid']);
    $secret=trim($_POST['secret']);
    // ensure column
    if(!$DB->getRow("SHOW COLUMNS FROM pre_user LIKE 'totp_secret'")){
        $DB->exec("ALTER TABLE `pre_user` ADD COLUMN `totp_secret` varchar(64) NULL AFTER `password`");
    }
    $row=$DB->getRow("select * from pre_user where uid=:uid", [':uid'=>$uid]);
    if(!$row) exit('{"code":-1,"msg":"用户不存在"}');
    if($row['type']!='local') exit('{"code":-1,"msg":"仅支持本地用户设2FA"}');
    if($DB->update('user', ['totp_secret'=>($secret?:null)], ['uid'=>$uid])!==false) exit('{"code":0,"msg":"设置成功"}');
    else exit('{"code":-1,"msg":"设置失败['.$DB->error().']"}');
break;
case 'delUser':
	$uid=intval($_POST['uid']);
	$row=$DB->getRow("select * from pre_user where uid='$uid' limit 1");
	if(!$row)
		exit('{"code":-1,"msg":"当前用户不存在！"}');
	$sql = "DELETE FROM pre_user WHERE uid='$uid'";
	if($DB->exec($sql))exit('{"code":0,"msg":"删除文件成功！"}');
	else exit('{"code":-1,"msg":"删除文件失败['.$DB->error().']"}');
break;
default:
	exit('{"code":-4,"msg":"No Act"}');
break;
}
