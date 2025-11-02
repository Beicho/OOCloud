<?php
include("./includes/common.php");

if(!$conf['userlogin']){
    @header('Content-Type: text/html; charset=UTF-8');
	exit("<script language='javascript'>alert('未开启登录');window.location.href='./';</script>");
}
if(isset($_GET['logout'])){
	if(!checkRefererHost())exit();
	setcookie("user_token", "", time() - 1, '/');
	@header('Content-Type: text/html; charset=UTF-8');
	exit("<script language='javascript'>alert('您已成功注销本次登录！');window.location.href='./login.php';</script>");
}elseif($islogin2==1 && !isset($_GET['code'])){
	// 如果已登录且不是OAuth回调（绑定模式），则跳转到首页
	@header('Content-Type: text/html; charset=UTF-8');
	exit("<script language='javascript'>alert('您已登录！');window.location.href='./';</script>");
}elseif(isset($_GET['act']) && $_GET['act']=='connect'){
    @header('Content-Type: application/json; charset=UTF-8');
    $type = isset($_POST['type'])?$_POST['type']:exit('{"code":-1,"msg":"no type"}');

    // Linux Do OAuth2
    if($type == 'linuxdo'){
        if(!$conf['login_linuxdo_clientid'] || !$conf['login_linuxdo_secret'])exit('{"code":-1,"msg":"未配置好Linux Do登录信息"}');
        $LinuxDoOauth = new \lib\LinuxDoOauth($conf['login_linuxdo_clientid'], $conf['login_linuxdo_secret'], $siteurl.'login.php?linuxdo=1');
        $url = $LinuxDoOauth->getAuthUrl();
        exit(json_encode(['code'=>0, 'url'=>$url]));
    }

    if(!$conf['login_apiurl'] || !$conf['login_appid'] || !$conf['login_appkey'])exit('{"code":-1,"msg":"未配置好快捷登录接口信息"}');
    $Oauth = new \lib\Oauth($conf['login_apiurl'], $conf['login_appid'], $conf['login_appkey']);
    $res = $Oauth->login($type);
    if(isset($res['code']) && $res['code']==0){
        $result = ['code'=>0, 'url'=>$res['url']];
    }elseif(isset($res['code'])){
        $result = ['code'=>-1, 'msg'=>$res['msg']];
    }else{
        $result = ['code'=>-1, 'msg'=>'快捷登录接口请求失败'];
    }
    exit(json_encode($result));
}elseif($_GET['code'] && isset($_GET['linuxdo']) && $_GET['state']){
    // Linux Do OAuth2 回调处理
    if($_GET['state'] != $_SESSION['LinuxDo_state']){
        sysmsg("<h2>The state does not match. You may be a victim of CSRF.</h2>");
    }

    if(!$conf['login_linuxdo_clientid'] || !$conf['login_linuxdo_secret']){
        sysmsg('未配置好Linux Do登录信息');
    }

    $LinuxDoOauth = new \lib\LinuxDoOauth($conf['login_linuxdo_clientid'], $conf['login_linuxdo_secret'], $siteurl.'login.php?linuxdo=1');
    $arr = $LinuxDoOauth->callback($_GET['code']);

    if(isset($arr['error'])){
        $errorMsg = '<h3>Linux Do 登录错误</h3>';
        $errorMsg .= '<p><strong>错误:</strong> '.$arr['error'].'</p>';
        if(isset($arr['details'])){
            $errorMsg .= '<p><strong>详情:</strong> '.print_r($arr['details'], true).'</p>';
        }
        if(isset($arr['redirect_uri_used'])){
            $errorMsg .= '<p><strong>使用的回调地址:</strong> '.$arr['redirect_uri_used'].'</p>';
            $errorMsg .= '<p style="color:red;"><strong>请确保:</strong> 在 Linux Do 后台配置的回调地址与上述地址完全一致</p>';
        }
        if(isset($arr['http_code'])){
            $errorMsg .= '<p><strong>HTTP状态码:</strong> '.$arr['http_code'].'</p>';
        }
        sysmsg($errorMsg);
    }

    $type = 'linuxdo';
    $openid = strval($arr['social_uid']); // 确保是字符串
    $nickname = trim($arr['nickname']);
    if(empty($nickname)) $nickname = 'Linux Do用户';
    $faceimg = $arr['faceimg'];

    // 检查是否为绑定模式
    if(isset($_SESSION['bind_mode']) && $_SESSION['bind_mode'] === true && isset($_SESSION['bind_uid'])){
        $bind_uid = intval($_SESSION['bind_uid']);
        unset($_SESSION['bind_mode']);
        unset($_SESSION['bind_uid']);

        // 检查该第三方账号是否已被其他用户绑定
        $existing = $DB->find('user','*',['type'=>$type, 'openid'=>$openid], null, '1');
        if($existing && $existing['uid'] != $bind_uid){
            sysmsg('该'.($type=='linuxdo'?'Linux Do':'').'账号已被其他用户绑定');
        }

        // 获取当前用户信息并更新绑定数据
        $currentUser = $DB->find('user', '*', ['uid'=>$bind_uid], null, '1');
        if(!$currentUser){
            sysmsg('用户不存在');
        }

        // 解析已有的绑定信息
        $bindings = $currentUser['bindings'] ? json_decode($currentUser['bindings'], true) : [];
        if(!is_array($bindings)) $bindings = [];

        // 添加新的绑定
        $bindings[$type] = [
            'openid' => $openid,
            'nickname' => $nickname,
            'faceimg' => $faceimg,
            'bind_time' => date('Y-m-d H:i:s')
        ];

        // 更新到数据库
        if(!$DB->update('user', ['bindings' => json_encode($bindings, JSON_UNESCAPED_UNICODE)], ['uid'=>$bind_uid])){
            sysmsg('绑定失败 '.$DB->error());
        }

        ob_clean();
        exit("<script language='javascript'>alert('绑定成功！');window.location.href='./user.php';</script>");
    }

    // 正常登录模式
    $userrow=$DB->find('user','*',['type'=>$type, 'openid'=>$openid], null, '1');

    // 如果没找到，检查是否有用户绑定了该账号
    if(!$userrow){
        $allUsers = $DB->query("SELECT * FROM pre_user WHERE bindings IS NOT NULL AND bindings != ''");
        if($allUsers){
            foreach($allUsers as $user){
                $bindings = json_decode($user['bindings'], true);
                if(is_array($bindings) && isset($bindings[$type]) && $bindings[$type]['openid'] == $openid){
                    $userrow = $user;
                    break;
                }
            }
        }
    }

    if(!$userrow){
        if(!$DB->insert('user', [
            'type' => $type,
            'openid' => $openid,
            'nickname' => $nickname,
            'faceimg' => $faceimg,
            'enable' => 1,
            'regip' => $clientip,
            'loginip' => $clientip,
            'addtime' => 'NOW()',
            'lasttime' => 'NOW()',
        ]))sysmsg('用户注册失败 '.$DB->error());
        $uid = $DB->lastInsertId();
    }else{
        if($userrow['enable']==0){
            $_SESSION['user_block'] = true;
            sysmsg('当前用户已被禁止登录');
        }
        $uid = $userrow['uid'];
        $DB->update('user', ['loginip' => $clientip, 'lasttime'=>'NOW()'], ['uid'=>$uid]);
    }
    if($_SESSION['user_block']){
        $DB->update('user', ['enable' => 0], ['uid'=>$uid]);
        sysmsg('当前用户已被禁止登录');
    }
    if(isset($_SESSION['fileids']) && count($_SESSION['fileids'])>0){
        $ids = array_reverse($_SESSION['fileids']);
        if(count($ids) > 60){
            $ids = array_splice($ids, 0, 60);
        }
        $ids = implode(',',$ids);
        $DB->exec("UPDATE pre_file SET uid='{$uid}' WHERE id IN ({$ids}) AND uid=0");
    }
    // Linux Do 登录：使用用户实际的 type 和 openid 计算 session
    $session=md5($userrow['type'].$userrow['openid'].$password_hash);
    $expiretime=time()+2592000;
    $token=authcode("{$uid}\t{$session}\t{$expiretime}", 'ENCODE', SYS_KEY);
    ob_clean();
    setcookie("user_token", $token, time() + 2592000, '/');
    exit("<script language='javascript'>window.location.href='./';</script>");
}elseif($_GET['code'] && $_GET['type'] && $_GET['state']){
	if($_GET['state'] != $_SESSION['Oauth_state']){
		sysmsg("<h2>The state does not match. You may be a victim of CSRF.</h2>");
	}
	$type = $_GET['type'];
    $typename = $type=='wx'?'微信':'QQ';
	$Oauth = new \lib\Oauth($conf['login_apiurl'], $conf['login_appid'], $conf['login_appkey']);
	$arr = $Oauth->callback();
	if(isset($arr['code']) && $arr['code']==0){
		$openid=$arr['social_uid'];
		$access_token=$arr['access_token'];
		$nickname=trim($arr['nickname']);
        if(empty($nickname) || $nickname=='-') $nickname = $typename.'用户';
		$faceimg=$arr['faceimg'];
	}elseif(isset($arr['code'])){
		sysmsg('<h3>error:</h3>'.$arr['errcode'].'<h3>msg  :</h3>'.$arr['msg']);
	}else{
		sysmsg('获取登录数据失败');
	}

    // 检查是否为绑定模式
    if(isset($_SESSION['bind_mode']) && $_SESSION['bind_mode'] === true && isset($_SESSION['bind_uid'])){
        $bind_uid = intval($_SESSION['bind_uid']);
        unset($_SESSION['bind_mode']);
        unset($_SESSION['bind_uid']);

        // 检查该第三方账号是否已被其他用户绑定
        $existing = $DB->find('user','*',['type'=>$type, 'openid'=>$openid], null, '1');
        if($existing && $existing['uid'] != $bind_uid){
            sysmsg('该'.$typename.'账号已被其他用户绑定');
        }

        // 获取当前用户信息并更新绑定数据
        $currentUser = $DB->find('user', '*', ['uid'=>$bind_uid], null, '1');
        if(!$currentUser){
            sysmsg('用户不存在');
        }

        // 解析已有的绑定信息
        $bindings = $currentUser['bindings'] ? json_decode($currentUser['bindings'], true) : [];
        if(!is_array($bindings)) $bindings = [];

        // 添加新的绑定
        $bindings[$type] = [
            'openid' => $openid,
            'nickname' => $nickname,
            'faceimg' => $faceimg,
            'bind_time' => date('Y-m-d H:i:s')
        ];

        // 更新到数据库
        if(!$DB->update('user', ['bindings' => json_encode($bindings, JSON_UNESCAPED_UNICODE)], ['uid'=>$bind_uid])){
            sysmsg('绑定失败 '.$DB->error());
        }

        ob_clean();
        exit("<script language='javascript'>alert('绑定成功！');window.location.href='./user.php';</script>");
    }

    // 正常登录模式
    $userrow=$DB->find('user','*',['type'=>$type, 'openid'=>$openid], null, '1');
	if(!$userrow){
        if(!$DB->insert('user', [
            'type' => $type,
            'openid' => $openid,
            'nickname' => $nickname,
            'faceimg' => $faceimg,
            'enable' => 1,
            'regip' => $clientip,
            'loginip' => $clientip,
            'addtime' => 'NOW()',
            'lasttime' => 'NOW()',
        ]))sysmsg('用户注册失败 '.$DB->error());
        $uid = $DB->lastInsertId();
	}else{
        if($userrow['enable']==0){
            $_SESSION['user_block'] = true;
            sysmsg('当前用户已被禁止登录');
        }
        $uid = $userrow['uid'];
        $DB->update('user', ['loginip' => $clientip, 'lasttime'=>'NOW()'], ['uid'=>$uid]);
    }
    if($_SESSION['user_block']){
        $DB->update('user', ['enable' => 0], ['uid'=>$uid]);
        sysmsg('当前用户已被禁止登录');
    }
    if(isset($_SESSION['fileids']) && count($_SESSION['fileids'])>0){
        $ids = array_reverse($_SESSION['fileids']);
        if(count($ids) > 60){
            $ids = array_splice($ids, 0, 60);
        }
        $ids = implode(',',$ids);
        $DB->exec("UPDATE pre_file SET uid='{$uid}' WHERE id IN ({$ids}) AND uid=0");
    }
    $session=md5($type.$openid.$password_hash);
    $expiretime=time()+2592000;
    $token=authcode("{$uid}\t{$session}\t{$expiretime}", 'ENCODE', SYS_KEY);
    ob_clean();
    setcookie("user_token", $token, time() + 2592000, '/');
    exit("<script language='javascript'>window.location.href='./';</script>");
}

// 本地用户名/密码 登录/注册接口
if(isset($_GET['act']) && ($_GET['act']=='local_login' || $_GET['act']=='local_register')){
    @header('Content-Type: application/json; charset=UTF-8');
    if(!checkRefererHost()) exit('{"code":403}');
    $username = trim(daddslashes($_POST['username']));
    $password = $_POST['password'];
    if(empty($username) || empty($password)) exit('{"code":-1,"msg":"请输入用户名和密码"}');
    if(!preg_match('/^[A-Za-z0-9_]{3,32}$/', $username)) exit('{"code":-1,"msg":"用户名需为3-32位字母数字下划线"}');

    // 确保存在 password 列（热迁移）
    $hasPwd = $DB->getRow("SHOW COLUMNS FROM pre_user LIKE 'password'");
    if(!$hasPwd){
        $DB->exec("ALTER TABLE `pre_user` ADD COLUMN `password` varchar(255) NULL AFTER `faceimg`");
    }

    if($_GET['act']=='local_register'){
        if(strlen($password) < 6) exit('{"code":-1,"msg":"密码至少6位"}');
        $exists = $DB->getRow("SELECT uid FROM pre_user WHERE type='local' AND openid=:u LIMIT 1", [':u'=>$username]);
        if($exists) exit('{"code":-1,"msg":"用户名已存在"}');
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $ok = $DB->insert('user', [
            'type' => 'local',
            'openid' => $username,
            'nickname' => $username,
            'faceimg' => null,
            'password' => $hash,
            'enable' => 1,
            'regip' => $clientip,
            'loginip' => $clientip,
            'addtime' => 'NOW()',
            'lasttime' => 'NOW()',
        ]);
        if(!$ok) exit('{"code":-1,"msg":"注册失败: '.$DB->error().'"}');
        $uid = $DB->lastInsertId();
    }else{ // local_login
        $userrow = $DB->getRow("SELECT * FROM pre_user WHERE type='local' AND openid=:u LIMIT 1", [':u'=>$username]);
        if(!$userrow) exit('{"code":-1,"msg":"用户不存在"}');
        if($userrow['enable']==0) exit('{"code":-1,"msg":"当前用户已被禁止登录"}');
        if(empty($userrow['password']) || !password_verify($password, $userrow['password'])){
            exit('{"code":-1,"msg":"用户名或密码错误"}');
        }
        $uid = $userrow['uid'];
        $DB->update('user', ['loginip' => $clientip, 'lasttime'=>'NOW()'], ['uid'=>$uid]);
    }

    // 绑定匿名上传的文件到账号
    if(isset($_SESSION['fileids']) && count($_SESSION['fileids'])>0){
        $ids = array_reverse($_SESSION['fileids']);
        if(count($ids) > 60){
            $ids = array_splice($ids, 0, 60);
        }
        $ids = implode(',',array_map('intval',$ids));
        $DB->exec("UPDATE pre_file SET uid='{$uid}' WHERE id IN ({$ids}) AND uid=0");
    }

    $session=md5('local'.$username.$password_hash);
    $expiretime=time()+2592000;
    $token=authcode("{$uid}\t{$session}\t{$expiretime}", 'ENCODE', SYS_KEY);
    setcookie("user_token", $token, time() + 2592000, '/');
    exit('{"code":0,"msg":"ok"}');
}

$title = '用户登录 - ' . $conf['title'];
include SYSTEM_ROOT.'header.php';
?>
<div class="container">
<div class="col-xs-10 col-sm-8 col-md-6 col-lg-4 center-block" style="float: none;">
    <div class="well bs-component" style="margin-top:50%">
        <div class="row text-center">
        <div class="col-xs-12">
            <h5>用户登录</h5><br/>

            <!-- 快捷登录 -->
            <?php if($conf['login_qq'] || $conf['login_wx'] || $conf['login_linuxdo']){?>
            <div class="panel panel-default">
              <div class="panel-heading">快捷登录</div>
              <div class="panel-body">
                <p class="text-muted" style="margin-bottom:10px;">选择您喜欢的方式登录</p>
                <p>
                    <?php if($conf['login_qq']){?><a href="javascript:connect('qq')" class="btn btn-info btn-fab loginbtn"><i class="fa fa-qq"></i></a><?php }?>
                    <?php if($conf['login_wx']){?><a href="javascript:connect('wx')" class="btn btn-success btn-fab loginbtn"><i class="fa fa-wechat"></i></a><?php }?>
                    <?php if($conf['login_linuxdo']){?><a href="javascript:connect('linuxdo')" class="btn btn-primary btn-fab loginbtn" title="Linux Do"><i class="fa fa-linux"></i></a><?php }?>
                </p>
                <p class="text-muted" style="margin-top:10px;font-size:12px;">新用户登录后会自动注册账号</p>
              </div>
            </div>
            <?php }?>

            <!-- 用户名密码登录 -->
            <div class="panel panel-default">
              <div class="panel-heading">用户名登录</div>
              <div class="panel-body">
                <div class="form-group"><input type="text" id="u_name" class="form-control" placeholder="用户名"></div>
                <div class="form-group"><input type="password" id="u_pass" class="form-control" placeholder="密码"></div>
                <button class="btn btn-primary btn-block" onclick="localLogin()">登 录</button>
                <div style="margin-top:10px;">
                  <a href="./forgot.php">忘记密码？</a>
                  <span style="margin:0 10px;">|</span>
                  <a href="javascript:showRegister()">没有账号？注册</a>
                </div>
              </div>
            </div>

            <!-- 注册面板（默认隐藏） -->
            <div class="panel panel-default" id="register-panel" style="display:none;">
              <div class="panel-heading">
                快速注册
                <a href="javascript:showLogin()" style="float:right;color:#fff;font-size:12px;">返回登录</a>
              </div>
              <div class="panel-body">
                <div class="form-group"><input type="text" id="r_name" class="form-control" placeholder="用户名(3-32位字母数字下划线)"></div>
                <div class="form-group"><input type="password" id="r_pass" class="form-control" placeholder="密码(至少6位)"></div>
                <div class="form-group"><input type="password" id="r_pass2" class="form-control" placeholder="确认密码"></div>
                <button class="btn btn-success btn-block" onclick="localRegister()">注 册</button>
              </div>
            </div>
        </div>
        </div>
    </div>
</div>
</div>
<?php include SYSTEM_ROOT.'footer.php';?>
<script src="https://s4.zstatic.net/ajax/libs/layer/2.3/layer.js"></script>
<script>
function connect(type){
    var ii = layer.load(2, {shade:[0.1,'#fff']});
	$.ajax({
		type : "POST",
		url : "login.php?act=connect",
		data : {type:type},
		dataType : 'json',
		success : function(data) {
			layer.close(ii);
			if(data.code == 0){
				window.location.href = data.url;
			}else{
				layer.alert(data.msg, {icon: 7});
			}
		} 
	});
}
function localLogin(){
    var ii = layer.load(2, {shade:[0.1,'#fff']});
    $.post('login.php?act=local_login', {username: $('#u_name').val(), password: $('#u_pass').val()}, function(data){
        layer.close(ii);
        if(data.code==0){ window.location.href='./'; } else { layer.alert(data.msg, {icon:7}); }
    }, 'json').fail(function(){ layer.close(ii); layer.msg('服务器错误'); });
}
function localRegister(){
    var pass1 = $('#r_pass').val();
    var pass2 = $('#r_pass2').val();
    if(pass1 !== pass2){
        layer.alert('两次输入的密码不一致', {icon:7});
        return;
    }
    var ii = layer.load(2, {shade:[0.1,'#fff']});
    $.post('login.php?act=local_register', {username: $('#r_name').val(), password: pass1}, function(data){
        layer.close(ii);
        if(data.code==0){ window.location.href='./'; } else { layer.alert(data.msg, {icon:7}); }
    }, 'json').fail(function(){ layer.close(ii); layer.msg('服务器错误'); });
}
function showRegister(){
    $('#register-panel').slideDown(300);
    $('html, body').animate({scrollTop: $('#register-panel').offset().top - 100}, 300);
}
function showLogin(){
    $('#register-panel').slideUp(300);
    $('html, body').animate({scrollTop: 0}, 300);
}
</script>
</body>
</html>
