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
case 'selfUpdate':
    // 一键自更新：从 GitHub Releases/Tags 下载最新包，备份并覆盖除配置/数据目录外的文件
    if(!defined('IN_ADMIN')) exit('{"code":403,"msg":"forbidden"}');
    if(function_exists('ignore_user_abort')) ignore_user_abort(true);
    @set_time_limit(0);
    @ini_set('memory_limit','512M');

    $base = realpath(dirname(__DIR__)); // 项目根
    $tmp  = $base.DIRECTORY_SEPARATOR.'tmp'.DIRECTORY_SEPARATOR.'update';
    $bak  = $base.DIRECTORY_SEPARATOR.'backup'.DIRECTORY_SEPARATOR.'update-'.date('Ymd-His');
    if(!is_dir($tmp)) @mkdir($tmp, 0777, true);
    if(!is_dir(dirname($bak))) @mkdir(dirname($bak), 0777, true);
    @mkdir($bak, 0777, true);

    // 1) 获取最新版本信息
    $latest = null; $zipUrl = null; $tag = null; $notes = '';
    $resp = get_curl('https://api.github.com/repos/Beicho/OOCloud/releases/latest');
    $json = json_decode($resp,true);
    if(is_array($json) && isset($json['tag_name'])){
        $tag = (string)$json['tag_name'];
        $zipUrl = $json['zipball_url'];
        $notes = isset($json['name']) ? (string)$json['name'] : '';
    }
    if(!$zipUrl){
        // 回退：取 tags 列表
        $resp2 = get_curl('https://api.github.com/repos/Beicho/OOCloud/tags');
        $tags = json_decode($resp2,true);
        if(is_array($tags) && isset($tags[0]['name'])){
            $tag = (string)$tags[0]['name'];
            $zipUrl = 'https://api.github.com/repos/Beicho/OOCloud/zipball/'.$tag;
        }
    }
    if(!$zipUrl){
        exit(json_encode(['code'=>-1,'msg'=>'无法获取更新包下载地址']));
    }

    // 2) 下载 ZIP 包
    $zipFile = $tmp.DIRECTORY_SEPARATOR.'oocloud_update.zip';
    $fp = fopen($zipFile, 'w');
    if(!$fp) exit(json_encode(['code'=>-1,'msg'=>'无法创建临时文件']));
    // 直接用 curl 拿二进制
    $ch = curl_init($zipUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'OOCloud-Updater');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $bin = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if($status != 200 || !$bin){
        exit(json_encode(['code'=>-1,'msg'=>'下载更新包失败，HTTP '.$status]));
    }
    fwrite($fp, $bin); fclose($fp);

    // 3) 解压
    $extractDir = $tmp.DIRECTORY_SEPARATOR.'extracted';
    if(is_dir($extractDir)){
        // 清空旧目录
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($extractDir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach($it as $f){ $f->isDir()? rmdir($f): unlink($f); }
        @rmdir($extractDir);
    }
    @mkdir($extractDir, 0777, true);

    $rootInside = null; // ZIP 内部的顶层目录名
    $ok = false;
    if(class_exists('ZipArchive')){
        $zip = new ZipArchive();
        if($zip->open($zipFile)===true){
            $zip->extractTo($extractDir);
            // 顶层目录
            $rootInside = null;
            for($i=0;$i<$zip->numFiles;$i++){
                $st = $zip->statIndex($i);
                $n = $st['name'];
                $p = explode('/', $n);
                if(!empty($p[0])){ $rootInside = $p[0]; break; }
            }
            $zip->close();
            $ok = true;
        }
    }
    if(!$ok){
        exit(json_encode(['code'=>-1,'msg'=>'服务器未启用 ZipArchive 扩展，无法解压更新包']));
    }
    if(!$rootInside){
        exit(json_encode(['code'=>-1,'msg'=>'更新包结构异常']));
    }
    $srcDir = realpath($extractDir.DIRECTORY_SEPARATOR.$rootInside);
    if(!$srcDir){
        // 某些包不带顶层目录，直接用解压目录
        $srcDir = $extractDir;
    }

    // 4) 读取新版本号，确保更高
    $newCommon = $srcDir.DIRECTORY_SEPARATOR.'includes'.DIRECTORY_SEPARATOR.'common.php';
    $newVer = '0';
    if(file_exists($newCommon)){
        $cnt = file_get_contents($newCommon);
        if(preg_match("/define\('VERSION',\s*'([0-9.]+)'\);/", $cnt, $m)){
            $newVer = $m[1];
        }
    }
    $curVer = defined('VERSION') ? (string)VERSION : '0';
    if(version_compare(preg_replace('/[^0-9.]/','',$curVer), preg_replace('/[^0-9.]/','',$newVer), '>=')){
        exit(json_encode(['code'=>-1,'msg'=>'当前已是最新版本（当前 '.$curVer.' / 远端 '.$newVer.'）']));
    }

    // 5) 拷贝（保留配置与数据）
    $preserve = [
        'config.php',
        'file',
        'install/install.lock',
        '.env',
        '.secrets',
        '.git',
    ];
    $copyRecursive = function($from, $to) use (&$copyRecursive, $preserve, $bak, $base) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach($it as $item){
            $rel = ltrim(str_replace($from, '', $item->getPathname()), DIRECTORY_SEPARATOR);
            $relUnix = str_replace(DIRECTORY_SEPARATOR, '/', $rel);
            // 跳过保留项
            foreach($preserve as $p){
                if($relUnix===$p || strpos($relUnix, rtrim($p,'/').'/')===0){
                    continue 2; // skip this file/dir
                }
            }
            $dest = $to.DIRECTORY_SEPARATOR.$rel;
            if($item->isDir()){
                if(!is_dir($dest)) @mkdir($dest, 0777, true);
            }else{
                // 备份已存在文件
                $destAbs = $base.DIRECTORY_SEPARATOR.$rel;
                if(is_file($destAbs)){
                    $bakPath = $bak.DIRECTORY_SEPARATOR.$rel;
                    if(!is_dir(dirname($bakPath))) @mkdir(dirname($bakPath), 0777, true);
                    @copy($destAbs, $bakPath);
                }
                if(!is_dir(dirname($dest))) @mkdir(dirname($dest), 0777, true);
                @copy($item->getPathname(), $dest);
            }
        }
    };
    $copyRecursive($srcDir, $base);

    // 6) 清理下载与缓存
    @unlink($zipFile);
    // 不强行删 extracted，以便排障

    exit(json_encode(['code'=>0,'msg'=>'更新完成至 '.$newVer,'new_version'=>$newVer,'backup'=>$bak,'notes'=>$notes,'tag'=>$tag]));
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
