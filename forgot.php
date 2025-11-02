<?php
include("./includes/common.php");

$title = '2FA 找回密码 - '.$conf['title'];
include SYSTEM_ROOT.'header.php';

// 动态补表：本地用户密码、2FA字段
if(!$DB->getRow("SHOW COLUMNS FROM pre_user LIKE 'password'")){
    $DB->exec("ALTER TABLE `pre_user` ADD COLUMN `password` varchar(255) NULL AFTER `faceimg`");
}
if(!$DB->getRow("SHOW COLUMNS FROM pre_user LIKE 'totp_secret'")){
    $DB->exec("ALTER TABLE `pre_user` ADD COLUMN `totp_secret` varchar(64) NULL AFTER `password`");
}

function base32_decode_custom($b32){
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $b32 = strtoupper($b32);
    $buffer = 0; $bitsLeft = 0; $result = '';
    for($i=0;$i<strlen($b32);$i++){
        $v = strpos($alphabet, $b32[$i]);
        if($v === false) continue;
        $buffer = ($buffer<<5) | $v; $bitsLeft += 5;
        if($bitsLeft >= 8){ $bitsLeft -= 8; $result .= chr(($buffer>>$bitsLeft) & 0xFF); }
    }
    return $result;
}
function totp_verify($secret, $code, $window=1, $step=30){
    $key = base32_decode_custom($secret);
    $ts = floor(time()/$step);
    for($i=-$window;$i<=$window;$i++){
        $bin = pack('N*', 0) . pack('N*', $ts+$i);
        $hash = hash_hmac('sha1', $bin, $key, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $truncatedHash = unpack('N', substr($hash, $offset, 4));
        $value = $truncatedHash[1] & 0x7FFFFFFF;
        $otp = str_pad($value % 1000000, 6, '0', STR_PAD_LEFT);
        if(hash_equals($otp, $code)) return true;
    }
    return false;
}

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['username'])){
    $username = trim(daddslashes($_POST['username']));
    $code = trim($_POST['code']);
    $pwd = $_POST['password'];
    $user = $DB->getRow("SELECT * FROM pre_user WHERE type='local' AND openid=:u LIMIT 1", [':u'=>$username]);
    if(!$user){
        echo '<div class="container"><div class="alert alert-danger">用户不存在或不是本地账户</div></div>'; include SYSTEM_ROOT.'footer.php'; exit;
    }
    if(empty($user['totp_secret'])){
        echo '<div class="container"><div class="alert alert-danger">未开启2FA，无法通过2FA找回。请联系管理员重置。</div></div>'; include SYSTEM_ROOT.'footer.php'; exit;
    }
    if(strlen($pwd)<6){
        echo '<div class="container"><div class="alert alert-danger">密码至少6位</div></div>'; include SYSTEM_ROOT.'footer.php'; exit;
    }
    if(!preg_match('/^\d{6}$/', $code) || !totp_verify($user['totp_secret'], $code)){
        echo '<div class="container"><div class="alert alert-danger">2FA 验证码错误</div></div>'; include SYSTEM_ROOT.'footer.php'; exit;
    }
    $hash = password_hash($pwd, PASSWORD_DEFAULT);
    $DB->update('user', ['password'=>$hash], ['uid'=>$user['uid']]);
    echo '<div class="container"><div class="alert alert-success">重置成功，请返回登录</div><a class="btn btn-primary" href="./login.php">去登录</a></div>';
    include SYSTEM_ROOT.'footer.php'; exit;
}
?>
<div class="container" style="padding-top:40px;">
  <div class="col-xs-12 col-sm-8 col-md-6 col-lg-4 center-block" style="float:none;">
    <div class="panel panel-primary">
      <div class="panel-heading"><h3 class="panel-title">2FA 找回密码</h3></div>
      <div class="panel-body">
        <form method="post" action="forgot.php">
          <div class="form-group"><input type="text" name="username" class="form-control" placeholder="本地用户名" required></div>
          <div class="form-group"><input type="text" name="code" class="form-control" placeholder="6位2FA验证码" required></div>
          <div class="form-group"><input type="password" name="password" class="form-control" placeholder="新密码(>=6位)" required></div>
          <button type="submit" class="btn btn-primary btn-block">提 交</button>
        </form>
        <p class="text-muted" style="margin-top:10px;">说明：必须先在账号中开启2FA（如谷歌验证器）。</p>
      </div>
    </div>
  </div>
</div>
<?php include SYSTEM_ROOT.'footer.php';?>
