<?php
error_reporting(E_ERROR | E_PARSE | E_COMPILE_ERROR);
if(defined('IN_CRONLITE'))return;
define('IN_CRONLITE', true);
define('SYSTEM_ROOT', dirname(__FILE__).'/');
define('ROOT', dirname(SYSTEM_ROOT).'/');
define('VERSION', '1532');
define('DB_VERSION', '1002');
date_default_timezone_set('Asia/Shanghai');
$date = date("Y-m-d H:i:s");

if(!$nosession)session_start();

include_once(SYSTEM_ROOT.'txprotect.php');
include_once(SYSTEM_ROOT."autoloader.php");
Autoloader::register();

require ROOT.'config.php';

if(!$dbconfig['user']||!$dbconfig['pwd']||!$dbconfig['dbname'])//检测安装1
{
header('Content-type:text/html;charset=utf-8');
echo '你还没安装！<a href="./install/">点此安装</a>';
exit();
}

$DB = new \lib\PdoHelper($dbconfig);

if($DB->query("select * from pre_config where 1")==FALSE)//检测安装2
{
header('Content-type:text/html;charset=utf-8');
echo '你还没安装！<a href="./install/">点此安装</a>';
exit();
}

include_once(SYSTEM_ROOT."functions.php");

$conf=getAllSetting();
define('SYS_KEY', $conf['syskey']);
$password_hash='!@#%!s!0';

if (!$conf['version'] || $conf['version'] < DB_VERSION) {
    if (!$install) {
		header('Content-type:text/html;charset=utf-8');
        echo '请先完成网站升级！<a href="/install/update.php"><font color=red>点此升级</font></a>';
        exit;
    }
}

$scriptpath=str_replace('\\','/',$_SERVER['SCRIPT_NAME']);
$sitepath = substr($scriptpath, 0, strrpos($scriptpath, '/'));
$siteurl = (is_https() ? 'https://' : 'http://').$_SERVER['HTTP_HOST'].$sitepath.'/';

$clientip=real_ip($conf['ip_type']?$conf['ip_type']:0);
if(isset($_COOKIE["admin_token"]))
{
	$token=authcode(daddslashes($_COOKIE['admin_token']), 'DECODE', SYS_KEY);
	if($token){
		list($user, $sid, $expiretime) = explode("\t", $token);
		$session=md5($conf['admin_user'].$conf['admin_pwd'].$password_hash);
		if($session==$sid && $expiretime>time()) {
			$islogin=1;
		}
	}
}
if(isset($_COOKIE["user_token"]))
{
	$token=authcode(daddslashes($_COOKIE['user_token']), 'DECODE', SYS_KEY);
	if($token){
		list($uid, $sid, $expiretime) = explode("\t", $token);
		if($userrow = $DB->getRow("SELECT * FROM pre_user WHERE uid='".intval($uid)."' LIMIT 1")){
			$session=md5($userrow['type'].$userrow['openid'].$password_hash);
			if($session===$sid && $expiretime>time()) {
				if($userrow['enable']==1){
					$islogin2=1;
				}else{
					$_SESSION['user_block'] = true;
				}
			}
		}
	}
}

if(defined('IN_ADMIN')) return;

$denyip = explode('|',$conf['blackip']);
if(in_array($clientip,$denyip) && !$islogin){
	Header("HTTP/1.1 403 Forbidden");
	exit;
}

include_once(SYSTEM_ROOT."vendor/autoload.php");

//加载存储模块
$stor = \lib\StorHelper::getModel($conf['storage']);

// OpenAPI 123: 确保存储所需字段
if($conf['storage']==='openapi123'){
    if(!$DB->getRow("SHOW COLUMNS FROM pre_file LIKE 'cloud_id'")){
        $DB->exec("ALTER TABLE `pre_file` ADD COLUMN `cloud_id` varchar(64) NULL AFTER `hash`;");
        $DB->exec("ALTER TABLE `pre_file` ADD INDEX `cloud_id` (`cloud_id`);");
    }
}

// === 用户扩展列与默认配置 ===
if(!$DB->getRow("SHOW COLUMNS FROM pre_user LIKE 'api_token'")){
    $DB->exec("ALTER TABLE `pre_user` ADD COLUMN `api_token` varchar(64) NULL AFTER `openid`");
}
if(!$DB->getRow("SHOW COLUMNS FROM pre_user LIKE 'totp_secret'")){
    $DB->exec("ALTER TABLE `pre_user` ADD COLUMN `totp_secret` varchar(64) NULL AFTER `password`");
}
// 绑定账号信息（JSON格式存储）
if(!$DB->getRow("SHOW COLUMNS FROM pre_user LIKE 'bindings'")){
    $DB->exec("ALTER TABLE `pre_user` ADD COLUMN `bindings` text NULL AFTER `totp_secret`");
}

// 默认配置键（仅保留必要项）
function ensureConf($k, $v){ global $conf; if(!isset($conf[$k])){ saveSetting($k, (string)$v); $conf[$k]=(string)$v; } }
ensureConf('disable_web_upload', 0);
// 隐私设置
ensureConf('hide_user_ip', '0');  // 是否在前端隐藏用户IP
// Linux Do OAuth2 配置
ensureConf('login_linuxdo', '0');
ensureConf('login_linuxdo_clientid', '');
ensureConf('login_linuxdo_secret', '');
// OpenAPI123 占位
ensureConf('openapi123_base', '');
ensureConf('openapi123_client_id', '');
ensureConf('openapi123_client_secret', '');
ensureConf('openapi123_parent', '0');
ensureConf('openapi123_keep_name', '1');
ensureConf('openapi123_duplicate', '1');
ensureConf('openapi123_domain_strategy', '0');
ensureConf('openapi123_username', '');
ensureConf('openapi123_password', '');
ensureConf('openapi123_use_client_api', '1');
ensureConf('openapi123_s3keyflag', '');
// 性能优化配置
ensureConf('openapi123_cache_metadata', '1');
ensureConf('openapi123_cache_ttl', '604800');
ensureConf('openapi123_rate_limit', '1');
ensureConf('openapi123_rate_max', '60');
ensureConf('openapi123_circuit_breaker', '1');
ensureConf('openapi123_circuit_threshold', '50');
ensureConf('openapi123_circuit_timeout', '300');
ensureConf('openapi123_random_delay', '1');
// WebDAV 占位
ensureConf('webdav_base', '');
ensureConf('webdav_user', '');
ensureConf('webdav_pass', '');

// 无配额逻辑

if (!file_exists(ROOT.'install/install.lock') && file_exists(ROOT.'install/index.php')) {
	sysmsg('<h2>检测到无 install.lock 文件</h2><ul><li><font size="4">如果您尚未安装本程序，请<a href="./install/">前往安装</a></font></li><li><font size="4">如果您已经安装本程序，请手动放置一个空的 install.lock 文件到 /install 文件夹下，<b>为了您站点安全，在您完成它之前我们不会工作。</b></font></li></ul><br/><h4>为什么必须建立 install.lock 文件？</h4>它是安装保护文件，如果检测不到它，就会认为站点还没安装，此时任何人都可以安装/重装你的网站。<br/><br/>');exit;
}
?>
