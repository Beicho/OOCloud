<?php
@header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__.'/includes/common.php';

// REST API 完整版
$path = isset($_SERVER['PATH_INFO']) ? trim($_SERVER['PATH_INFO'], '/') : '';
$seg = $path ? explode('/', $path) : [];
$method = $_SERVER['REQUEST_METHOD'];

// 鉴权：Authorization: Bearer {token} 或 ?token=
function api_user(){
  global $DB;
  $hdr = isset($_SERVER['HTTP_AUTHORIZATION']) ? trim($_SERVER['HTTP_AUTHORIZATION']) : '';
  $token = '';
  if($hdr && stripos($hdr, 'Bearer ') === 0){ $token = substr($hdr, 7); }
  if(!$token && isset($_GET['token'])){ $token = trim($_GET['token']); }
  if(!$token){ http_response_code(401); echo json_encode(['code'=>401,'msg'=>'missing token']); exit; }
  $u = $DB->getRow("SELECT * FROM pre_user WHERE api_token=:t AND enable=1 LIMIT 1", [':t'=>$token]);
  if(!$u){ http_response_code(401); echo json_encode(['code'=>401,'msg'=>'invalid token']); exit; }
  return $u;
}

function j($arr){ echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }

// API 文档
if(!$seg){
  j([
    'code'=>0,
    'msg'=>'OOCloud API',
    'version'=>'1.0',
    'endpoints'=>[
      'POST /upload' => '上传文件',
      'GET /files' => '获取文件列表',
      'GET /file/{hash}' => '获取文件详情',
      'GET /download/{hash}' => '下载文件',
      'PUT /file/{hash}' => '重命名文件',
      'DELETE /file/{hash}' => '删除文件',
      'POST /sign' => '生成签名下载链接',
      'GET /user' => '获取用户信息',
      'GET /quota' => '获取配额信息'
    ]
  ]);
}

switch($seg[0]){
  // ==================== 上传文件 ====================
  case 'upload':
    if($method!=='POST'){ http_response_code(405); j(['code'=>405,'msg'=>'method not allowed']); }
    $u = api_user();
    if(!isset($_FILES['file'])){ http_response_code(400); j(['code'=>-1,'msg'=>'file required']); }
    $tmp = $_FILES['file']['tmp_name'];
    $orig = isset($_FILES['file']['name'])?$_FILES['file']['name']:'file';
    $name = isset($_POST['name']) && $_POST['name']!=='' ? trim($_POST['name']) : $orig;
    $name = str_replace(["/","\\",":","*","\"","<",">","|","?"],'',$name);
    $ispwd = isset($_POST['ispwd'])?intval($_POST['ispwd']):0;
    $pwd = ($ispwd==1 && !empty($_POST['pwd']))?trim($_POST['pwd']):null;
    if($ispwd==1 && $pwd!==null && !preg_match('/^[a-zA-Z0-9]+$/',$pwd)) j(['code'=>-1,'msg'=>'密码仅支持字母数字']);
    $show = isset($_POST['show'])?intval($_POST['show']):1; $hide = $show==1?0:1;
    if(!is_uploaded_file($tmp)){ http_response_code(400); j(['code'=>-1,'msg'=>'no upload']); }
    $size = filesize($tmp); if($size===false) $size = 0;
    $limit_size = intval($conf['upload_size']);
    if($limit_size>0 && $size > $limit_size*1024*1024){ j(['code'=>-1,'msg'=>'size limit '.$limit_size.'MB']); }
    $hash = md5_file($tmp);
    if(!$hash) j(['code'=>-1,'msg'=>'md5 failed']);
    $ext = pathinfo($name, PATHINFO_EXTENSION);
    // 去重
    $ex = $DB->getRow("SELECT id,hash,name,size,type FROM pre_file WHERE hash=:h", [':h'=>$hash]);
    if($ex){ j(['code'=>0,'exists'=>1,'id'=>$ex['id'],'hash'=>$hash,'name'=>$ex['name'],'size'=>intval($ex['size']),'type'=>$ex['type'],'download_url'=>$siteurl.'api.php/download/'.$hash]); }
    // 存储
    global $stor, $clientip, $conf;
    $ctype = $ext? minetype($ext) : null;
    if(session_status()===PHP_SESSION_NONE) session_start();
    $_SESSION['upload']['name'] = $name;
    $ok = $stor->upload($hash, $tmp, $ctype);
    if(!$ok){ j(['code'=>-1,'msg'=>'upload failed','error'=>$stor->errmsg()]); }
    // 入库
    $DB->exec("INSERT INTO pre_file (name,type,size,hash,addtime,ip,hide,pwd,uid) VALUES (:n,:t,:s,:h,NOW(),:ip,:hide,:pwd,:uid)", [
      ':n'=>$name, ':t'=>$ext, ':s'=>$size, ':h'=>$hash, ':ip'=>$clientip, ':hide'=>$hide, ':pwd'=>$pwd, ':uid'=>$u['uid']
    ]);
    $id = $DB->lastInsertId();
    if($conf['storage']=='openapi123' && isset($_SESSION['openapi123'][$hash])){
      $DB->exec("UPDATE pre_file SET cloud_id=:cid WHERE id=:id", [':cid'=>$_SESSION['openapi123'][$hash], ':id'=>$id]);
      unset($_SESSION['openapi123'][$hash]);
    }
    j(['code'=>0,'exists'=>0,'id'=>$id,'hash'=>$hash,'name'=>$name,'size'=>$size,'type'=>$ext,'download_url'=>$siteurl.'api.php/download/'.$hash]);
    break;

  // ==================== 获取文件列表 ====================
  case 'files':
    if($method!=='GET'){ http_response_code(405); j(['code'=>405,'msg'=>'method not allowed']); }
    $u = api_user();
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? min(100, max(1, intval($_GET['limit']))) : 20;
    $offset = ($page - 1) * $limit;

    $total = $DB->getColumn("SELECT count(*) FROM pre_file WHERE uid=:uid", [':uid'=>$u['uid']]);
    $files = $DB->getAll("SELECT id,hash,name,size,type,addtime,hide,pwd FROM pre_file WHERE uid=:uid ORDER BY id DESC LIMIT {$offset},{$limit}", [':uid'=>$u['uid']]);

    foreach($files as &$f){
      $f['download_url'] = $siteurl.'api.php/download/'.$f['hash'];
      $f['has_password'] = !empty($f['pwd']);
      unset($f['pwd']);
    }

    j([
      'code'=>0,
      'total'=>intval($total),
      'page'=>$page,
      'limit'=>$limit,
      'files'=>$files
    ]);
    break;

  // ==================== 获取文件详情 ====================
  case 'file':
    if($method!=='GET'){ http_response_code(405); j(['code'=>405,'msg'=>'method not allowed']); }
    if(!isset($seg[1])){ http_response_code(400); j(['code'=>-1,'msg'=>'hash required']); }
    $u = api_user();
    $hash = trim($seg[1]);
    if(!preg_match('/^[0-9a-z]{32}$/i', $hash)){ j(['code'=>-1,'msg'=>'invalid hash']); }

    $file = $DB->getRow("SELECT id,hash,name,size,type,addtime,lasttime,count,hide,pwd FROM pre_file WHERE hash=:h AND uid=:uid", [':h'=>$hash, ':uid'=>$u['uid']]);
    if(!$file){ http_response_code(404); j(['code'=>404,'msg'=>'file not found']); }

    $file['download_url'] = $siteurl.'api.php/download/'.$hash;
    $file['has_password'] = !empty($file['pwd']);
    $file['view_url'] = $siteurl.'file.php?hash='.$hash;
    unset($file['pwd']);

    j(['code'=>0,'file'=>$file]);
    break;

  // ==================== 下载文件 ====================
  case 'download':
    if(!isset($seg[1])){ http_response_code(400); j(['code'=>-1,'msg'=>'hash required']); }
    $u = api_user();
    $hash = trim($seg[1]);
    if(!preg_match('/^[0-9a-z]{32}$/i', $hash)){ j(['code'=>-1,'msg'=>'invalid hash']); }

    $file = $DB->getRow("SELECT * FROM pre_file WHERE hash=:h AND uid=:uid", [':h'=>$hash, ':uid'=>$u['uid']]);
    if(!$file){ http_response_code(404); j(['code'=>404,'msg'=>'file not found']); }

    // 检查密码
    if(!empty($file['pwd'])){
      $pwd = isset($_GET['pwd']) ? trim($_GET['pwd']) : '';
      if($pwd !== $file['pwd']){
        http_response_code(403);
        j(['code'=>403,'msg'=>'password required or incorrect']);
      }
    }

    // 更新下载统计
    $DB->exec("UPDATE pre_file SET lasttime=NOW(), count=count+1 WHERE id=:id", [':id'=>$file['id']]);

    // 获取下载链接
    global $stor;
    $downurl = $stor->downloadurl($file['hash']);
    if(!$downurl){
      // 本地存储，直接下载
      @header('Content-Type: application/json; charset=UTF-8', false);
      @header('Content-Type: application/octet-stream');
      @header('Content-Disposition: attachment; filename="'.rawurlencode($file['name']).'"');
      @header('Content-Length: '.$file['size']);
      $stor->download($file['hash'], $file['name']);
      exit;
    } else {
      // 云存储，重定向
      header('Location: '.$downurl);
      exit;
    }
    break;

  // ==================== 重命名文件 ====================
  case 'rename':
    if($method!=='PUT' && $method!=='POST'){ http_response_code(405); j(['code'=>405,'msg'=>'method not allowed']); }
    if(!isset($seg[1])){ http_response_code(400); j(['code'=>-1,'msg'=>'hash required']); }
    $u = api_user();
    $hash = trim($seg[1]);
    if(!preg_match('/^[0-9a-z]{32}$/i', $hash)){ j(['code'=>-1,'msg'=>'invalid hash']); }

    // 获取新文件名
    $input = json_decode(file_get_contents('php://input'), true);
    $name = isset($input['name']) ? trim($input['name']) : '';
    if(empty($name)){
      $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    }
    if(empty($name)){ j(['code'=>-1,'msg'=>'name required']); }

    $file = $DB->getRow("SELECT * FROM pre_file WHERE hash=:h AND uid=:uid", [':h'=>$hash, ':uid'=>$u['uid']]);
    if(!$file){ http_response_code(404); j(['code'=>404,'msg'=>'file not found']); }

    $name = str_replace(["/","\\",":","*","\"","<",">","|","?"],'',$name);
    $DB->exec("UPDATE pre_file SET name=:n WHERE id=:id", [':n'=>$name, ':id'=>$file['id']]);

    j(['code'=>0,'msg'=>'success','name'=>$name]);
    break;

  // ==================== 删除文件 ====================
  case 'delete':
    if($method!=='DELETE' && $method!=='POST'){ http_response_code(405); j(['code'=>405,'msg'=>'method not allowed']); }
    if(!isset($seg[1])){ http_response_code(400); j(['code'=>-1,'msg'=>'hash required']); }
    $u = api_user();
    $hash = trim($seg[1]);
    if(!preg_match('/^[0-9a-z]{32}$/i', $hash)){ j(['code'=>-1,'msg'=>'invalid hash']); }

    $file = $DB->getRow("SELECT * FROM pre_file WHERE hash=:h AND uid=:uid", [':h'=>$hash, ':uid'=>$u['uid']]);
    if(!$file){ http_response_code(404); j(['code'=>404,'msg'=>'file not found']); }

    global $stor;
    $stor->delete($file['hash']);
    $DB->exec("DELETE FROM pre_file WHERE id=:id", [':id'=>$file['id']]);

    j(['code'=>0,'msg'=>'deleted']);
    break;

  // ==================== 生成签名链接 ====================
  case 'sign':
    if($method!=='POST'){ http_response_code(405); j(['code'=>405,'msg'=>'method not allowed']); }
    $u = api_user();

    $input = json_decode(file_get_contents('php://input'), true);
    $hash = isset($input['hash']) ? trim($input['hash']) : (isset($_POST['hash']) ? trim($_POST['hash']) : '');
    $expires_in = isset($input['expires_in']) ? intval($input['expires_in']) : (isset($_POST['expires_in']) ? intval($_POST['expires_in']) : 3600);
    $max_downloads = isset($input['max_downloads']) ? intval($input['max_downloads']) : (isset($_POST['max_downloads']) ? intval($_POST['max_downloads']) : 1);

    if(!preg_match('/^[0-9a-z]{32}$/i', $hash)){ j(['code'=>-1,'msg'=>'invalid hash']); }

    $file = $DB->getRow("SELECT * FROM pre_file WHERE hash=:h AND uid=:uid", [':h'=>$hash, ':uid'=>$u['uid']]);
    if(!$file){ http_response_code(404); j(['code'=>404,'msg'=>'file not found']); }

    if($expires_in <= 0 || $expires_in > 86400*365) $expires_in = 3600;
    if($max_downloads < 1) $max_downloads = 1;

    $expire_time = time() + $expires_in;
    $start_count = intval($file['count']);
    $max_count = $start_count + $max_downloads;

    // 生成签名
    $sign_data = $hash.'|'.$expire_time.'|'.$start_count.'|'.$max_count;
    $sign = md5($sign_data.SYS_KEY);

    $download_url = $siteurl.'down.php/'.$hash.'.'.$file['type'].'?t='.$sign.'&e='.$expire_time.'&s='.$start_count.'&c='.$max_count;

    j([
      'code'=>0,
      'download_url'=>$download_url,
      'expires_at'=>date('Y-m-d H:i:s', $expire_time),
      'max_downloads'=>$max_downloads
    ]);
    break;

  // ==================== 获取用户信息 ====================
  case 'user':
    if($method!=='GET'){ http_response_code(405); j(['code'=>405,'msg'=>'method not allowed']); }
    $u = api_user();

    j([
      'code'=>0,
      'user'=>[
        'uid'=>$u['uid'],
        'nickname'=>$u['nickname'],
        'type'=>$u['type'],
        'level'=>intval($u['level']),
        'enable'=>intval($u['enable'])
      ]
    ]);
    break;

  // ==================== 获取配额信息 ====================
  case 'quota':
    if($method!=='GET'){ http_response_code(405); j(['code'=>405,'msg'=>'method not allowed']); }
    $u = api_user();

    $total_files = $DB->getColumn("SELECT count(*) FROM pre_file WHERE uid=:uid", [':uid'=>$u['uid']]);
    $total_size = $DB->getColumn("SELECT SUM(size) FROM pre_file WHERE uid=:uid", [':uid'=>$u['uid']]) ?: 0;

    $today = date("Y-m-d 00:00:00");
    $today_files = $DB->getColumn("SELECT count(*) FROM pre_file WHERE uid=:uid AND addtime>=:time", [':uid'=>$u['uid'], ':time'=>$today]);

    j([
      'code'=>0,
      'quota'=>[
        'total_files'=>intval($total_files),
        'total_size'=>intval($total_size),
        'total_size_mb'=>round($total_size/1024/1024, 2),
        'today_files'=>intval($today_files),
        'upload_limit'=>intval($conf['upload_limit']),
        'upload_size_limit_mb'=>intval($conf['upload_size'])
      ]
    ]);
    break;

  default:
    http_response_code(404);
    j(['code'=>404,'msg'=>'endpoint not found']);
}
