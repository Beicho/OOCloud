<?php
$nosecu = true;
include("./includes/common.php");
$act=isset($_GET['act'])?daddslashes($_GET['act']):null;

if(!checkRefererHost())exit('{"code":403}');

@header('Content-Type: application/json; charset=UTF-8');

if($islogin2 && $userrow['level']>0){
	$conf['upload_limit']=0;
	$conf['videoreview']=0;
	$conf['type_block']=null;
	$conf['name_block']=null;
}

switch($act){
case 'pre_upload':
	if(!$_POST['csrf_token'] || $_POST['csrf_token']!=$_SESSION['csrf_token'])exit('{"code":-1,"msg":"CSRF TOKEN ERROR"}');
	if($conf['forcelogin']==1 && !$islogin2)exit('{"code":-1,"msg":"请先登录"}');
	$name = trim(htmlspecialchars($_POST['name']));
	$hash = trim($_POST['hash']);
	$size = intval($_POST['size']);
	$hide = $_POST['show']==1?0:1;
	$ispwd = intval($_POST['ispwd']);
	$pwd = $ispwd==1?trim(htmlspecialchars($_POST['pwd'])):null;
	$name = str_replace(['/','\\',':','*','"','<','>','|','?'],'',$name);
	if(empty($name))exit('{"code":-1,"msg":"文件名不能为空"}');
	if(!preg_match('/^[0-9a-z]{32}$/i', $hash))exit('{"code":-1,"msg":"hash error"}');
	if($ispwd==1 && !empty($pwd)){
		if (!preg_match('/^[a-zA-Z0-9]+$/', $pwd)) {
			exit('{"code":-1,"msg":"文件密码只能为字母和数字"}');
		}
	}
	$ext=get_file_ext($name);
	if($conf['type_block']){
		$type_block = explode('|',$conf['type_block']);
		if(in_array($ext,$type_block)){
			exit('{"code":-1,"msg":"文件上传失败，不支持上传该格式文件","error":"block"}');
		}
	}
	if($conf['name_block']){
		$name_block = explode('|',$conf['name_block']);
		foreach($name_block as $row){
			if(strpos($name,$row)!==false){
				exit('{"code":-1,"msg":"文件上传失败","error":"block"}');
			}
		}
	}
	$limit_size = intval($conf['upload_size']);
	if($limit_size > 0 && $size > $limit_size * 1024 * 1024){
		exit('{"code":-1,"msg":"上传文件大小限制'.$limit_size.'MB"}');
	}
	if($conf['upload_limit']>0){
		$thisday = date("Y-m-d 00:00:00");
		if($islogin2){
			$ipcount=$DB->getColumn("SELECT count(*) from pre_file WHERE uid='$uid' AND addtime>='".$thisday."'");
		}else{
			$ipcount=$DB->getColumn("SELECT count(*) from pre_file WHERE ip='$clientip' AND addtime>='".$thisday."'");
		}
		if($ipcount>$conf['upload_limit']){
			exit('{"code":-1,"msg":"你今天上传文件的数量已超过限制"}');
		}
	}
	$row = $DB->getRow("SELECT * FROM pre_file WHERE hash=:hash", [':hash'=>$hash]);
	if($row){
		unset($_SESSION['csrf_token']);
		$result = ['code'=>1, 'msg'=>'本站已存在该文件', 'exists'=>1, 'hash'=>$hash, 'name'=>$name, 'size'=>$size, 'type'=>$ext, 'id'=>$row['id']];
		exit(json_encode($result));
	}

    if(\lib\StorHelper::is_cloud() && $conf['uploadfile_type'] == 1){
        $param = $stor->getUploadParam($hash, $name, $limit_size * 1024 * 1024);
        if($param){
            $_SESSION['upload'] = [
                'chunks' => 1,
                'name' => $name,
                'hash' => $hash,
                'size' => $size,
                'ext' => $ext,
                'hide' => $hide,
                'pwd' => $pwd
            ];
            $result = ['code'=>0, 'third'=>true, 'hash'=>$hash, 'url'=>$param['url'], 'post'=>$param['post']];
            exit(json_encode($result));
        }
        // 若存储驱动不支持直传，则回退到分片中转上传
    }
    {
        $chunksize = 8 * 1024 * 1024; //分块上传，每块大小
        $chunks = ceil($size / $chunksize);
        $_SESSION['upload'] = [
            'chunks' => $chunks,
            'name' => $name,
			'hash' => $hash,
			'size' => $size,
			'ext' => $ext,
			'hide' => $hide,
			'pwd' => $pwd
		];
		$result = ['code'=>0, 'third'=>false, 'hash'=>$hash, 'chunksize'=>$chunksize, 'chunks'=>$chunks];
		exit(json_encode($result));
	}
break;

case 'upload_part':
	if(!isset($_FILES['file']))exit('{"code":-1,"msg":"请选择文件"}');
	if(!$_POST['csrf_token'] || $_POST['csrf_token']!=$_SESSION['csrf_token'])exit('{"code":-1,"msg":"CSRF TOKEN ERROR"}');
	if($conf['forcelogin']==1 && !$islogin2)exit('{"code":-1,"msg":"请先登录"}');
	$chunk = intval($_POST['chunk']);
	$hash = trim($_POST['hash']);
	if(!$_SESSION['upload'] || !$_SESSION['upload']['hash'] || $_SESSION['upload']['hash']!=$hash){
		exit('{"code":-1,"msg":"参数校验失败，请刷新页面重试"}');
	}
	if(!preg_match('/^[0-9a-z]{32}$/i', $hash))exit('{"code":-1,"msg":"hash error"}');
	$chunks = intval($_SESSION['upload']['chunks']);
	$ext = $_SESSION['upload']['ext'];
	if($chunks > 1){
		$tempFile = sys_get_temp_dir() . '/' . $hash. '.part'.$chunk;
		if(!move_uploaded_file($_FILES['file']['tmp_name'], $tempFile)){
			exit('{"code":-1,"msg":"文件第'.$chunk.'分块上传失败"}');
		}
		if($chunks == $chunk){
			$savePathTemp = file_part_merge($hash, $chunks);
			$real_hash = md5_file($savePathTemp);
			$real_size = filesize($savePathTemp);
			$result = $stor->savefile($hash, $savePathTemp, minetype($ext));
			if(!$result)exit('{"code":-1,"msg":"文件上传失败","error":"stor","errmsg":"'.$stor->errmsg().'"}');
		}else{
			$result = ['code'=>0, 'chunk'=>$chunk];
			exit(json_encode($result));
		}
	}else{
		$real_hash = md5_file($_FILES['file']['tmp_name']);
		$real_size = filesize($_FILES['file']['tmp_name']);
		$result = $stor->upload($hash, $_FILES['file']['tmp_name'], minetype($ext));
		if(!$result)exit('{"code":-1,"msg":"文件上传失败","error":"stor","errmsg":"'.$stor->errmsg().'"}');
	}

	$size = $_SESSION['upload']['size'];
	if($real_size != $size){
		exit('{"code":-1,"msg":"文件大小校验失败"}');
	}
	if($real_hash != $hash){
		exit('{"code":-1,"msg":"文件MD5校验失败"}');
	}

	$name = $_SESSION['upload']['name'];
	$hide = $_SESSION['upload']['hide'];
	$pwd = $_SESSION['upload']['pwd'];

    $row = $DB->getRow("SELECT * FROM pre_file WHERE hash=:hash", [':hash'=>$hash]);
    if($row){
        // 已存在相同文件：为当前登录用户建立归属映射（避免首页重复，强制隐藏）
        if($islogin2){
            $mine = $DB->getRow("SELECT id FROM pre_file WHERE hash=:h AND uid=:u LIMIT 1", [':h'=>$hash, ':u'=>$uid]);
            if(!$mine){
                $DB->exec("INSERT INTO pre_file (name,type,size,hash,addtime,ip,hide,pwd,uid) VALUES (:n,:t,:s,:h,NOW(),:ip,:hide,:pwd,:uid)", [
                    ':n'=>$name, ':t'=>$ext, ':s'=>$size, ':h'=>$hash, ':ip'=>$clientip, ':hide'=>1, ':pwd'=>$pwd, ':uid'=>$uid
                ]);
            }
        }
        unset($_SESSION['csrf_token']);
        unset($_SESSION['upload']);
        $result = ['code'=>1, 'msg'=>'本站已存在该文件', 'exists'=>1, 'hash'=>$hash, 'name'=>$name, 'size'=>$size, 'type'=>$ext, 'id'=>$row['id']];
        exit(json_encode($result));
    }

	$sds = $DB->exec("INSERT INTO `pre_file` (`name`,`type`,`size`,`hash`,`addtime`,`ip`,`hide`,`pwd`,`uid`) values (:name,:type,:size,:hash,NOW(),:ip,:hide,:pwd,:uid)", [':name'=>$name, ':type'=>$ext, ':size'=>$size, ':hash'=>$hash, ':ip'=>$clientip, ':hide'=>$hide, ':pwd'=>$pwd, ':uid'=>($uid?$uid:0)]);
	if(!$sds)exit('{"code":-1,"msg":"上传失败'.$DB->error().'","error":"database"}');
    $id = $DB->lastInsertId();
    if($conf['storage']=='openapi123' && isset($_SESSION['openapi123'][$hash])){
        $DB->exec("UPDATE pre_file SET cloud_id=:cid WHERE id=:id", [':cid'=>$_SESSION['openapi123'][$hash], ':id'=>$id]);
        unset($_SESSION['openapi123'][$hash]);
    }

	$type_image = explode('|',$conf['type_image']);
	$type_video = explode('|',$conf['type_video']);
	if($conf['green_check']>0 && in_array($ext,$type_image)){
		if(checkImage($hash, $ext)){
			$DB->exec("UPDATE `pre_file` SET `block`=1 WHERE `id`='{$id}' LIMIT 1");
		}
	}
	if($conf['videoreview']==1 && in_array($ext,$type_video)){
		$DB->exec("UPDATE `pre_file` SET `block`=2 WHERE `id`='{$id}' LIMIT 1");
	}
	
	$_SESSION['fileids'][] = $id;
	unset($_SESSION['csrf_token']);
	unset($_SESSION['upload']);
	$result = ['code'=>1, 'msg'=>'文件上传成功！', 'exists'=>0, 'hash'=>$hash, 'name'=>$name, 'size'=>$size, 'type'=>$ext, 'id'=>$id];
	exit(json_encode($result));
break;

case 'complete_upload':
	if(!$_POST['csrf_token'] || $_POST['csrf_token']!=$_SESSION['csrf_token'])exit('{"code":-1,"msg":"CSRF TOKEN ERROR"}');
	if($conf['forcelogin']==1 && !$islogin2)exit('{"code":-1,"msg":"请先登录"}');
	$hash = trim($_POST['hash']);
	if(!$_SESSION['upload'] || !$_SESSION['upload']['hash'] || $_SESSION['upload']['hash']!=$hash){
		exit('{"code":-1,"msg":"参数校验失败，请刷新页面重试"}');
	}
	if(!preg_match('/^[0-9a-z]{32}$/i', $hash))exit('{"code":-1,"msg":"hash error"}');
	
	if(!$stor->exists($hash)){
		exit('{"code":-1,"msg":"文件上传失败","error":"stor","errmsg":"'.$stor->errmsg().'"}');
	}

	$name = $_SESSION['upload']['name'];
	$size = $_SESSION['upload']['size'];
	$ext = $_SESSION['upload']['ext'];
	$hide = $_SESSION['upload']['hide'];
	$pwd = $_SESSION['upload']['pwd'];

	$row = $DB->getRow("SELECT * FROM pre_file WHERE hash=:hash", [':hash'=>$hash]);
	if($row){
		unset($_SESSION['csrf_token']);
		unset($_SESSION['upload']);
		$result = ['code'=>1, 'msg'=>'本站已存在该文件', 'exists'=>1, 'hash'=>$hash, 'name'=>$name, 'size'=>$size, 'type'=>$ext, 'id'=>$row['id']];
		exit(json_encode($result));
	}

	$sds = $DB->exec("INSERT INTO `pre_file` (`name`,`type`,`size`,`hash`,`addtime`,`ip`,`hide`,`pwd`,`uid`) values (:name,:type,:size,:hash,NOW(),:ip,:hide,:pwd,:uid)", [':name'=>$name, ':type'=>$ext, ':size'=>$size, ':hash'=>$hash, ':ip'=>$clientip, ':hide'=>$hide, ':pwd'=>$pwd, ':uid'=>($uid?$uid:0)]);
	if(!$sds)exit('{"code":-1,"msg":"上传失败'.$DB->error().'","error":"database"}');
    $id = $DB->lastInsertId();
    if($conf['storage']=='openapi123' && isset($_SESSION['openapi123'][$hash])){
        $DB->exec("UPDATE pre_file SET cloud_id=:cid WHERE id=:id", [':cid'=>$_SESSION['openapi123'][$hash], ':id'=>$id]);
        unset($_SESSION['openapi123'][$hash]);
    }

	$type_image = explode('|',$conf['type_image']);
	$type_video = explode('|',$conf['type_video']);
	if($conf['green_check']>0 && in_array($ext,$type_image)){
		if(checkImage($hash, $ext)){
			$DB->exec("UPDATE `pre_file` SET `block`=1 WHERE `id`='{$id}' LIMIT 1");
		}
	}
	if($conf['videoreview']==1 && in_array($ext,$type_video)){
		$DB->exec("UPDATE `pre_file` SET `block`=2 WHERE `id`='{$id}' LIMIT 1");
	}
	
	$_SESSION['fileids'][] = $id;
	unset($_SESSION['csrf_token']);
	unset($_SESSION['upload']);
	$result = ['code'=>1, 'msg'=>'文件上传成功！', 'exists'=>0, 'hash'=>$hash, 'name'=>$name, 'size'=>$size, 'type'=>$ext, 'id'=>$id];
    exit(json_encode($result));
    break;

case 'gen_api_token':
    if(!$islogin2) exit('{"code":-1,"msg":"请先登录"}');
    if(!$_POST['csrf_token'] || $_POST['csrf_token']!=$_SESSION['csrf_token'])exit('{"code":-1,"msg":"CSRF TOKEN ERROR"}');
    $tok = bin2hex(random_bytes(16));
    $DB->exec("UPDATE pre_user SET api_token=:t WHERE uid=:u", [':t'=>$tok, ':u'=>$uid]);
    exit(json_encode(['code'=>0,'token'=>$tok]));
    break;

case 'user_files':
    if(!$islogin2) exit('{"total":0,"rows":[]}');
    // bootstrap-table 支持 limit/offset 或 pageNumber/pageSize
    $limit = isset($_POST['limit'])?intval($_POST['limit']):10;
    $offset = isset($_POST['offset'])?intval($_POST['offset']):0;
    if(isset($_POST['pageNumber']) && isset($_POST['pageSize'])){
        $limit = intval($_POST['pageSize']);
        $offset = (intval($_POST['pageNumber'])-1)*$limit;
    }
    $total = $DB->getColumn("SELECT count(*) FROM pre_file WHERE uid=:u", [':u'=>$uid]);
    $rows = $DB->getAll("SELECT id,hash,name,size,type,addtime FROM pre_file WHERE uid=:u ORDER BY id DESC LIMIT {$offset},{$limit}", [':u'=>$uid]);
    exit(json_encode(['total'=>intval($total), 'rows'=>$rows]));
    break;

case 'user_delete':
    if(!$islogin2) exit('{"code":-1,"msg":"请先登录"}');
    if(!$_POST['csrf_token'] || $_POST['csrf_token']!=$_SESSION['csrf_token'])exit('{"code":-1,"msg":"CSRF TOKEN ERROR"}');
    $hash = isset($_POST['hash'])?trim($_POST['hash']):'';
    if(!preg_match('/^[0-9a-z]{32}$/i', $hash))exit('{"code":-1,"msg":"hash error"}');
    $row = $DB->getRow("SELECT * FROM pre_file WHERE hash=:h", [':h'=>$hash]);
    if(!$row) exit('{"code":-1,"msg":"文件不存在"}');
    if($row['uid']!=$uid) exit('{"code":-1,"msg":"无权限"}');
    $ok = $stor->delete($row['hash']);
    $DB->exec("DELETE FROM pre_file WHERE id=:id", [':id'=>$row['id']]);
    exit('{"code":0,"msg":"删除成功"}');
    break;

case 'user_rename':
    if(!$islogin2) exit('{"code":-1,"msg":"请先登录"}');
    $hash = isset($_POST['hash'])?trim($_POST['hash']):'';
    $name = isset($_POST['name'])?trim($_POST['name']):'';
    if(!preg_match('/^[0-9a-z]{32}$/i', $hash))exit('{"code":-1,"msg":"hash error"}');
    if($name==='') exit('{"code":-1,"msg":"文件名不能为空"}');
    $row = $DB->getRow("SELECT * FROM pre_file WHERE hash=:h", [':h'=>$hash]);
    if(!$row) exit('{"code":-1,"msg":"文件不存在"}');
    if($row['uid']!=$uid) exit('{"code":-1,"msg":"无权限"}');
    $name = str_replace(['\\','/','\"',':','*','?','|','>','<'],'',$name);
    $DB->exec("UPDATE pre_file SET name=:n WHERE id=:id", [':n'=>$name, ':id'=>$row['id']]);
    exit('{"code":0,"msg":"ok"}');
    break;

case 'userTotpStatus':
	if(!$islogin2) exit('{"code":-1,"msg":"请先登录"}');
	$binded = !empty($userrow['totp_secret']);
	exit(json_encode(['code'=>0, 'binded'=>$binded]));
break;
case 'userTotpInit':
	if(!$islogin2) exit('{"code":-1,"msg":"请先登录"}');
	if(!$_POST['csrf_token'] || $_POST['csrf_token']!=$_SESSION['csrf_token'])exit('{"code":-1,"msg":"CSRF TOKEN ERROR"}');
	$alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $secret=''; for($i=0;$i<32;$i++){ $secret .= $alphabet[mt_rand(0,strlen($alphabet)-1)]; }
	$_SESSION['totp_secret_pending'] = $secret;
	$issuer = rawurlencode($conf['title']); $account = rawurlencode('user'.$uid);
	$otpauth = 'otpauth://totp/'.$issuer.':'.$account.'?secret='.$secret.'&issuer='.$issuer.'&digits=6&period=30';
	exit(json_encode(['code'=>0,'secret'=>$secret,'otpauth'=>$otpauth]));
break;
case 'userTotpEnable':
	if(!$islogin2) exit('{"code":-1,"msg":"请先登录"}');
	if(!$_POST['csrf_token'] || $_POST['csrf_token']!=$_SESSION['csrf_token'])exit('{"code":-1,"msg":"CSRF TOKEN ERROR"}');
	$secret = isset($_SESSION['totp_secret_pending'])?$_SESSION['totp_secret_pending']:'';
	$code = isset($_POST['code'])?trim($_POST['code']):'';
	if(empty($secret)) exit('{"code":-1,"msg":"请先生成秘钥"}');
	if(!preg_match('/^\d{6}$/',$code)) exit('{"code":-1,"msg":"验证码格式错误"}');
	$verify = (function($secret,$code){
		$alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
		$b32 = strtoupper($secret); $buffer = 0; $bitsLeft = 0; $key='';
		for($i=0;$i<strlen($b32);$i++){ $v = strpos($alphabet, $b32[$i]); if($v===false) continue; $buffer = ($buffer<<5)|$v; $bitsLeft+=5; if($bitsLeft>=8){ $bitsLeft-=8; $key .= chr(($buffer>>$bitsLeft)&0xFF);} }
		$ts = floor(time()/30);
		for($i=-1;$i<=1;$i++){ $bin = pack('N*',0).pack('N*',$ts+$i); $hash = hash_hmac('sha1',$bin,$key,true); $offset = ord(substr($hash,-1)) & 0x0F; $trunc = unpack('N', substr($hash,$offset,4)); $val = $trunc[1] & 0x7FFFFFFF; $otp = str_pad($val % 1000000,6,'0',STR_PAD_LEFT); if(hash_equals($otp,$code)) return true; }
		return false;
	})($secret,$code);
	if(!$verify) exit('{"code":-1,"msg":"验证码错误"}');
	if(!$DB->getRow("SHOW COLUMNS FROM pre_user LIKE 'totp_secret'")){
		$DB->exec("ALTER TABLE `pre_user` ADD COLUMN `totp_secret` varchar(64) NULL AFTER `password`");
	}
	$DB->update('user',['totp_secret'=>$secret],['uid'=>$uid]);
	unset($_SESSION['totp_secret_pending']);
	exit('{"code":0,"msg":"ok"}');
break;
case 'userTotpDisable':
	if(!$islogin2) exit('{"code":-1,"msg":"请先登录"}');
	if(!$_POST['csrf_token'] || $_POST['csrf_token']!=$_SESSION['csrf_token'])exit('{"code":-1,"msg":"CSRF TOKEN ERROR"}');
	if(!$DB->getRow("SHOW COLUMNS FROM pre_user LIKE 'totp_secret'")){
		$DB->exec("ALTER TABLE `pre_user` ADD COLUMN `totp_secret` varchar(64) NULL AFTER `password`");
	}
	$DB->update('user',['totp_secret'=>null],['uid'=>$uid]);
	exit('{"code":0,"msg":"ok"}');
break;

case 'sign_link':
	// 生成受控分享下载链接（签名 + 到期 + 次数限制）
	if(!$_POST['csrf_token'] || $_POST['csrf_token']!=$_SESSION['csrf_token'])exit('{"code":-1,"msg":"CSRF TOKEN ERROR"}');
	$hash = isset($_POST['hash'])?trim($_POST['hash']):'';
    $expires_in = intval($_POST['expires_in']); // seconds
    $max = intval($_POST['max']); // max additional downloads allowed for this link
    $login_only = intval($_POST['login_only']); // 1|0 仅登录可访问
    $allow_uids = isset($_POST['allow_uids'])?trim($_POST['allow_uids']):''; // 指定UID, 逗号分隔
	if(!preg_match('/^[0-9a-z]{32}$/i', $hash))exit('{"code":-1,"msg":"hash error"}');
    // 初始化变量
    $row = null;
    // 优先拿"当前用户"的文件记录，避免多用户同 hash 冲突
    if($islogin2){
        $row = $DB->getRow("SELECT * FROM pre_file WHERE hash=:hash AND uid=:uid", [':hash'=>$hash, ':uid'=>$uid]);
    }
    if(!$row){
        $row = $DB->getRow("SELECT * FROM pre_file WHERE hash=:hash", [':hash'=>$hash]);
    }
	if(!$row) exit('{"code":-1,"msg":"文件不存在"}');
	// 权限检查：文件拥有者或管理员可生成
    $isOwner = $islogin2 && $row['uid'] == $uid;
    $isAdmin = $islogin == 1;
    if(!$isOwner && !$isAdmin){
        exit('{"code":-1,"msg":"无权限"}');
    }
	if($expires_in<=0 || $expires_in>3600*24*365){ // 1年上限
		$expires_in = 3600; // 默认1小时
	}
	if($max<1) $max = 1;
    $e = time() + $expires_in;
    $s = intval($row['count']);
    $c = $max;
    $l = $login_only ? 1 : 0;
    // 规范化 allow_uids：仅保留数字和逗号
    $alist = [];
    if(!empty($allow_uids)){
        foreach(explode(',', $allow_uids) as $u){
            $u = intval($u);
            if($u>0) $alist[] = $u;
        }
    }
    $a = implode(',', $alist);
    $ah = md5($a);
    $t = md5(SYS_KEY.'|'.$hash.'|'.$e.'|'.$c.'|'.$s.'|'.$l.'|'.$ah);
    $base = $siteurl.'down.php/'.$row['hash'].'.'.$row['type'];
    if(!empty($row['pwd'])) $base .= '&'.$row['pwd'];
    $qs = 't='.$t.'&e='.$e.'&c='.$c.'&s='.$s.'&l='.$l;
    if($a!=='') $qs .= '&a='.rawurlencode($a);
    $downurl = $base.'?'.$qs;
    $viewurl = $siteurl.'view.php/'.$row['hash'].'.'.$row['type'].'?'.$qs;
    $result = ['code'=>0, 'downurl'=>$downurl, 'viewurl'=>$viewurl, 'expire_at'=>$e, 'limit'=>$c, 'start'=>$s, 'login_only'=>$l, 'allow_uids'=>$a];
    exit(json_encode($result));
break;

case 'deleteFile':
	$hash = isset($_POST['hash'])?trim($_POST['hash']):exit('{"code":-1,"msg":"no hash"}');
	if(!$_POST['csrf_token'] || $_POST['csrf_token']!=$_SESSION['csrf_token'])exit('{"code":-1,"msg":"CSRF TOKEN ERROR"}');
	if(!preg_match('/^[0-9a-z]{32}$/i', $hash))exit('{"code":-1,"msg":"hash error"}');
	$row = $DB->getRow("SELECT * FROM `pre_file` WHERE `hash`=:hash", [':hash'=>$hash]);
	if(!$row)exit('{"code":-1,"msg":"文件不存在"}');
	if($islogin2 && $row['uid']!=$uid || !$islogin2 && (!isset($_SESSION['fileids']) || !in_array($row['id'], $_SESSION['fileids'])))exit('{"code":-1,"msg":"无权限"}');
	if($row['block']==1)exit('{"code":-1,"msg":"文件已被冻结，无法删除"}');
	if(!$islogin2 && strtotime($row['addtime'])<strtotime("-7 days"))exit('{"code":-1,"msg":"无法删除7天前的文件"}');
	$result = $stor->delete($row['hash']);
	$sql = "DELETE FROM pre_file WHERE id=:id";
	if($DB->exec($sql, [':id'=>$row['id']]))exit('{"code":0,"msg":"删除文件成功！"}');
	else exit('{"code":-1,"msg":"删除文件失败['.$DB->error().']"}');
break;

case 'bind_account':
	if(!$islogin2)exit('{"code":-1,"msg":"请先登录"}');
	if(!$_POST['csrf_token'] || $_POST['csrf_token']!=$_SESSION['csrf_token'])exit('{"code":-1,"msg":"CSRF TOKEN ERROR"}');
	$type = isset($_POST['type'])?trim($_POST['type']):exit('{"code":-1,"msg":"no type"}');

	// 标记为绑定模式
	$_SESSION['bind_mode'] = true;
	$_SESSION['bind_uid'] = $uid;

	// Linux Do OAuth2
	if($type == 'linuxdo'){
		if(!$conf['login_linuxdo_clientid'] || !$conf['login_linuxdo_secret'])exit('{"code":-1,"msg":"未配置好Linux Do登录信息"}');
		$LinuxDoOauth = new \lib\LinuxDoOauth($conf['login_linuxdo_clientid'], $conf['login_linuxdo_secret'], $siteurl.'login.php?linuxdo=1');
		$url = $LinuxDoOauth->getAuthUrl();
		exit(json_encode(['code'=>0, 'url'=>$url]));
	}

	// QQ/微信
	if(!$conf['login_apiurl'] || !$conf['login_appid'] || !$conf['login_appkey'])exit('{"code":-1,"msg":"未配置好快捷登录接口信息"}');
	$Oauth = new \lib\Oauth($conf['login_apiurl'], $conf['login_appid'], $conf['login_appkey']);
	$res = $Oauth->login($type);
	if(isset($res['code']) && $res['code']==0){
		exit(json_encode(['code'=>0, 'url'=>$res['url']]));
	}elseif(isset($res['code'])){
		exit(json_encode(['code'=>-1, 'msg'=>$res['msg']]));
	}else{
		exit('{"code":-1,"msg":"快捷登录接口请求失败"}');
	}
break;

case 'unbind_account':
	if(!$islogin2)exit('{"code":-1,"msg":"请先登录"}');
	if(!$_POST['csrf_token'] || $_POST['csrf_token']!=$_SESSION['csrf_token'])exit('{"code":-1,"msg":"CSRF TOKEN ERROR"}');
	$type = isset($_POST['type'])?trim($_POST['type']):exit('{"code":-1,"msg":"no type"}');

	// 获取当前用户信息
	$currentUser = $DB->find('user', '*', ['uid'=>$uid], null, '1');
	if(!$currentUser){
		exit('{"code":-1,"msg":"用户不存在"}');
	}

	// 解析已有的绑定信息
	$bindings = $currentUser['bindings'] ? json_decode($currentUser['bindings'], true) : [];
	if(!is_array($bindings)) $bindings = [];

	// 检查是否还有其他登录方式（当前登录类型 + 绑定的账号数量）
	if($currentUser['type'] != 'local' && count($bindings) == 0){
		exit('{"code":-1,"msg":"至少需要保留一种登录方式"}');
	}

	// 如果要解绑当前登录方式且没有本地密码，需要至少有一个绑定
	if($currentUser['type'] == $type && empty($currentUser['password']) && count($bindings) <= 1){
		exit('{"code":-1,"msg":"请先绑定其他账号或设置密码后再解绑"}');
	}

	// 删除绑定
	if(isset($bindings[$type])){
		unset($bindings[$type]);
		if($DB->update('user', ['bindings' => json_encode($bindings, JSON_UNESCAPED_UNICODE)], ['uid'=>$uid])){
			exit('{"code":0,"msg":"解绑成功"}');
		}else{
			exit('{"code":-1,"msg":"解绑失败"}');
		}
	}else{
		exit('{"code":-1,"msg":"该账号未绑定"}');
	}
break;

default:
    exit('{"code":-4,"msg":"No Act"}');
break;
}
