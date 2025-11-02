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
if(!$row) exit;
if($row['block']>=1){
    header("Content-type: ".minetype('gif'));
    readfile(ROOT.'assets/img/block.gif');
    exit;
}

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
    $deny = ($t !== $calc) || time() > $e || ($row['count'] - $s) >= $c;
    if(!$deny && $l==1 && !$islogin2) $deny = true;
    if(!$deny && !empty($a)){
        if(!$islogin2) $deny = true; else {
            $allow = array_map('intval', explode(',', $a));
            if(!in_array($uid, $allow)) $deny = true;
        }
    }
    if($deny){
        header("Content-type: ".minetype('gif'));
        readfile(ROOT.'assets/img/block.gif');
        exit;
    }
}

if ($stor->exists($row['hash'])) {
    if(is_view($row['type']))
    {
        $DB->exec("UPDATE `pre_file` SET `lasttime`=NOW(),`count`=`count`+1 WHERE `id`='{$row['id']}'");

        // 是否强制使用服务器中转（用于预览失败时的fallback）
        $force_relay = isset($_GET['relay']) && $_GET['relay'] == '1';
        file_output($hash, $row['type'], $row['size'], $row['name'], true, isset($_GET['greencheck']), $force_relay);
    }
}
