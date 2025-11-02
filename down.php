<?php
$nosession=true;
$nosecu=true;
include("./includes/common.php");

$urlarr=explode('/',$_SERVER['PATH_INFO']);
if (($length = count($urlarr)) > 1) {
$url = $urlarr[$length-1];
}
$extension=explode('&',$url);
if (($length = count($extension)) > 1) {
$pwd = $extension[$length-1];
$url = $extension[0];
}

if(strpos($url,".")){
    $hash=substr($url,0,strpos($url,"."));
}else{
    $hash=$url;
}

$row = $DB->getRow("SELECT * FROM `pre_file` WHERE `hash`=:hash limit 1", [':hash'=>$hash]);
if(!$row)exit('404 Not Found');
if($row['block']>=1)exit('File is blocked!');

// 受控分享校验（当提供签名参数时生效）
if(isset($_GET['t']) && isset($_GET['e']) && isset($_GET['c']) && isset($_GET['s'])){
    $t = daddslashes($_GET['t']);
    $e = intval($_GET['e']);
    $c = intval($_GET['c']);
    $s = intval($_GET['s']);
    $l = isset($_GET['l']) ? intval($_GET['l']) : 0;
    $a = isset($_GET['a']) ? daddslashes($_GET['a']) : '';
    $ah = md5($a);
    $calc = md5(SYS_KEY.'|'.$row['hash'].'|'.$e.'|'.$c.'|'.$s.'|'.$l.'|'.$ah);
    if($t !== $calc){
        Header("HTTP/1.1 403 Forbidden");
        exit('签名无效');
    }
    if(time() > $e){
        Header("HTTP/1.1 403 Forbidden");
        exit('链接已过期');
    }
    if(($row['count'] - $s) >= $c){
        Header("HTTP/1.1 403 Forbidden");
        exit('下载次数已用尽');
    }
    if($l==1 && !$islogin2){
        Header("HTTP/1.1 403 Forbidden");
        exit('需要登录');
    }
    if(!empty($a)){
        if(!$islogin2){
            Header("HTTP/1.1 403 Forbidden");
            exit('需要登录');
        }
        $allow = array_map('intval', explode(',', $a));
        if(!in_array($uid, $allow)){
            Header("HTTP/1.1 403 Forbidden");
            exit('无访问权限');
        }
    }
}

if($row['pwd']!=null && $row['pwd']!=$pwd){ ?>
    <meta http-equiv="content-type" content="text/html;charset=utf-8"/>
    <title>请输入密码下载文件</title>
    <script type="text/javascript">
    var pwd=prompt("请输入密码","")
    if (pwd!=null && pwd!="")
    {
        window.location.href='<?php echo $siteurl.'down.php/'.$hash?>&'+pwd
    }
    </script>
    请刷新页面，或[ <a href="javascript:history.back();">返回上一页</a> ]
<?php
    exit;
}

if($stor->exists($hash))
{
    $DB->exec("UPDATE `pre_file` SET `lasttime`=NOW(),`count`=`count`+1 WHERE `id`='{$row['id']}'");

    // 是否强制使用服务器中转下载（绕过云存储直链）
    $force_relay = isset($_GET['relay']) && $_GET['relay'] == '1';
    file_output($hash, $row['type'], $row['size'], $row['name'], false, false, $force_relay);
}
else{
    exit('File Not Found');
}
