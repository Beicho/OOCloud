<?php
  /**
   * OOCloud 一键更新脚本（独立版）
   * 用法：上传到站点根目录，访问 http(s)://your-domain/oocloud_updater.php
   * 完成后建议删除本文件
   */

  @header('Content-Type: text/plain; charset=UTF-8');
  if (php_sapi_name() !== 'cli') {
    echo "OOCloud Updater\n================\n";
  }

  @set_time_limit(0);
  @ini_set('memory_limit','512M');

  $REPO_OWNER = 'Beicho';
  $REPO_NAME  = 'OOCloud';

  $BASE = realpath(__DIR__);
  $TMP  = $BASE . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'update';
  $BAK  = $BASE . DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR . ('update-'.date('Ymd-His'));

  $PRESERVE = [
    'config.php',
    'file',
    'install/install.lock',
    '.env',
    '.secrets',
    '.git',
  ];

  // ---------- helpers ----------
  function say($msg){ echo $msg . "\n"; @ob_flush(); @flush(); }
  function ensure_dir($d){ if(!is_dir($d)) @mkdir($d, 0777, true); }
  function http_get($url){
    if(function_exists('curl_init')){
      $ch=curl_init($url);
      curl_setopt_array($ch,[
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_FOLLOWLOCATION=>true,
        CURLOPT_USERAGENT=>'OOCloud-Updater',
        CURLOPT_SSL_VERIFYPEER=>false,
        CURLOPT_SSL_VERIFYHOST=>false,
        CURLOPT_TIMEOUT=>60,
      ]);
      $bin=curl_exec($ch);
      $code=curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);
      if($code!=200 || $bin===false) return [null,$code?:0];
      return [$bin,200];
    } else {
      $ctx=stream_context_create(['http'=>['header'=>"User-Agent: OOCloud-Updater\r\n",'timeout'=>60],'ssl'=>['verify_peer'=>false,'verify_peer_name'=>false]]);
      $bin=@file_get_contents($url,false,$ctx);
      $code=0; // 无法轻易取到，先忽略
      if($bin===false) return [null,$code];
      return [$bin,200];
    }
  }
  function write_file($path,$data){
    ensure_dir(dirname($path));
    $fp=fopen($path,'w'); if(!$fp) return false;
    fwrite($fp,$data); fclose($fp); return true;
  }
  function rrmdir($dir){
    if(!is_dir($dir)) return;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach($it as $f){ $f->isDir()? @rmdir($f): @unlink($f); }
    @rmdir($dir);
  }
  function version_norm($v){ return preg_replace('/[^0-9.]/','',(string)$v); }
  function should_skip($relUnix, $preserve){
    foreach($preserve as $p){
      if($relUnix===$p || strpos($relUnix, rtrim($p,'/').'/')===0) return true;
    }
    return false;
  }
  // 递归复制（带备份与保留）
  function copy_recursive($from,$to,$preserve,$bak,$base){
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach($it as $item){
      $rel = ltrim(str_replace($from, '', $item->getPathname()), DIRECTORY_SEPARATOR);
      $relUnix = str_replace(DIRECTORY_SEPARATOR, '/', $rel);
      if(should_skip($relUnix,$preserve)) continue;
      $destAbs = $base . DIRECTORY_SEPARATOR . $rel;
      if($item->isDir()){
        ensure_dir($destAbs);
      }else{
        if(is_file($destAbs)){
          $bakPath = $bak . DIRECTORY_SEPARATOR . $rel;
          ensure_dir(dirname($bakPath));
          @copy($destAbs, $bakPath);
        }
        ensure_dir(dirname($destAbs));
        if(!@copy($item->getPathname(), $destAbs)){
          throw new RuntimeException("写入失败: $destAbs");
        }
      }
    }
  }
  // ---------- run ----------
  say("准备更新 OOCloud ...");
  ensure_dir($TMP);
  ensure_dir(dirname($BAK));
  ensure_dir($BAK);

  // 当前版本
  $curVer = '0';
  $curCommon = $BASE.'/includes/common.php';
  if(is_file($curCommon)){
    $cnt = file_get_contents($curCommon);
    if(preg_match("/define\\('VERSION',\\s*'([0-9.]+)'\\);/",$cnt,$m)) $curVer=$m[1];
  }
  say("当前版本: ".$curVer);

  // 获取最新 Release
  say("获取最新版本信息 ...");
  list($resp,$code) = http_get("https://api.github.com/repos/$REPO_OWNER/$REPO_NAME/releases/latest");
  $tag = null; $zipUrl = null; $notes='';
  if($code === 200 && $resp){
    $j = json_decode($resp,true);
    if(is_array($j) && isset($j['tag_name'])){
      $tag = (string)$j['tag_name'];
      $zipUrl = $j['zipball_url'];
      $notes = isset($j['name']) ? (string)$j['name'] : '';
    }
  }
  if(!$zipUrl){
    say("使用 tags 回退 ...");
    list($resp2,$code2) = http_get("https://api.github.com/repos/$REPO_OWNER/$REPO_NAME/tags");
    if($code2===200 && $resp2){
      $tags = json_decode($resp2,true);
      if(is_array($tags) && isset($tags[0]['name'])){
        $tag = (string)$tags[0]['name'];
        $zipUrl = "https://api.github.com/repos/$REPO_OWNER/$REPO_NAME/zipball/$tag";
      }
    }
  }
  if(!$zipUrl){
    die("无法获取更新包下载地址，请稍后再试。\n");
  }
  say("最新版本标签: ".($tag?:'未知'));

  // 下载 zip
  $zipFile = $TMP.'/oocloud_update.zip';
  say("下载更新包 ...");
  list($bin,$code) = http_get($zipUrl);
  if($code!==200 || !$bin) die("下载失败，HTTP $code\n");
  if(!write_file($zipFile, $bin)) die("无法写入临时文件: $zipFile\n");

  // 解压 zip
  $extractDir = $TMP.'/extracted';
  rrmdir($extractDir);
  ensure_dir($extractDir);

  $rootInside = null;
  if(class_exists('ZipArchive')){
    $zip = new ZipArchive();
    if($zip->open($zipFile)===true){
      $zip->extractTo($extractDir);
      for($i=0;$i<$zip->numFiles;$i++){
        $st = $zip->statIndex($i);
        $n = $st['name'];
        $p = explode('/', $n);
        if(!empty($p[0])){ $rootInside = $p[0]; break; }
      }
      $zip->close();
    } else {
      die("ZipArchive 打开失败，无法解压更新包。\n");
    }
  } else {
    die("服务器未启用 ZipArchive 扩展，无法解压 zip 包。\n");
  }

  $srcDir = $rootInside ? realpath($extractDir.'/'.$rootInside) : $extractDir;
  if(!$srcDir) die("更新包结构异常（缺少顶层目录）。\n");

  // 读取新版本号
  $newVer = '0';
  $newCommon = $srcDir.'/includes/common.php';
  if(is_file($newCommon)){
    $cnt = file_get_contents($newCommon);
    if(preg_match("/define\\('VERSION',\\s*'([0-9.]+)'\\);/",$cnt,$m)) $newVer=$m[1];
  }
  say("最新版本: ".$newVer);

  if(version_compare(version_norm($curVer), version_norm($newVer), '>=')){
    say("当前已是最新，无需更新。");
    exit(0);
  }

  // 复制覆盖（带备份）
  say("备份并覆盖文件 ...");
  try {
    copy_recursive($srcDir, $BASE, $PRESERVE, $BAK, $BASE);
  } catch(Throwable $e){
    die("更新失败：".$e->getMessage()."\n");
  }

  // 清理
  @unlink($zipFile);
  say("更新完成：$curVer -> $newVer");
  say("备份目录：$BAK");
  say("如需数据库升级，请访问 /install/update.php");
  say("完成后建议删除本文件（oocloud_updater.php）。");
