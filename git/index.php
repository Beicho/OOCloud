<?php
if (version_compare(PHP_VERSION, '7.1.0', '<')) {
    die('require PHP >= 7.1 !');
}
include("./includes/common.php");

if(isset($_GET['m']) && $_GET['m']=='mine'){
    $title = '我的文件 - ' . $conf['title'];
    $htext = '我上传的文件';
    if($islogin2){
        $sql = " uid='{$uid}'";
    }else{
        if($conf['userlogin']==1){
            $htext .= '<span class="subtitle">根据浏览器缓存记录，<a href="login.php">登录</a>后可永久保留记录</span>';
        }else{
            $htext .= '<span class="subtitle">根据浏览器缓存记录</span>';
        }
        if(isset($_SESSION['fileids']) && count($_SESSION['fileids'])>0){
            $ids = array_reverse($_SESSION['fileids']);
            if(count($ids) > 60){
                $ids = array_splice($ids, 0, 60);
            }
            $ids = implode(',',$ids);
            $sql = " id IN ($ids)";
        }else{
            $sql = " 1=2";
        }
    }
    $link = '&m=mine';
}else{
    $title = $conf['title'];
    $htext = '文件列表';
    $sql = " hide=0";
    $link = '';
}
$kw = isset($_GET['kw'])?daddslashes(trim(strip_tags($_GET['kw']))):null;
if($conf['filesearch']==1 && $kw){
    $sql.=" AND name LIKE '%{$kw}%'";
    $link .= '&kw='.$kw;
}

include SYSTEM_ROOT.'header.php';
?>

<style>
/* 全新首页布局样式 */
.index-hero {
  background: linear-gradient(135deg, #ffffff 0%, #f5f5f5 100%);
  padding: 80px 40px 60px;
  border-bottom: 3px solid #000000;
  position: relative;
  overflow: hidden;
}

.index-hero::before {
  content: '';
  position: absolute;
  top: 0;
  right: 0;
  width: 400px;
  height: 400px;
  background: repeating-linear-gradient(
    45deg,
    transparent,
    transparent 10px,
    rgba(0,0,0,0.02) 10px,
    rgba(0,0,0,0.02) 20px
  );
  pointer-events: none;
}

.index-hero-inner {
  max-width: 1600px;
  margin: 0 auto;
  position: relative;
  z-index: 1;
}

.hero-title {
  font-size: 56px;
  font-weight: 900;
  color: #000000;
  margin: 0 0 15px 0;
  letter-spacing: 4px;
  text-transform: uppercase;
  line-height: 1.2;
  position: relative;
  display: inline-block;
}

.hero-title::after {
  content: '';
  position: absolute;
  bottom: -10px;
  left: 0;
  width: 120px;
  height: 6px;
  background: #000000;
}

.subtitle {
  font-size: 14px;
  color: #666666;
  font-weight: 600;
  letter-spacing: 2px;
  margin-left: 15px;
  text-transform: uppercase;
}

.subtitle a {
  color: #000000;
  border-bottom: 2px solid #000000;
  text-decoration: none;
  font-weight: 700;
}

/* 搜索区域 */
.search-container {
  margin-top: 40px;
  display: flex;
  gap: 15px;
  max-width: 600px;
}

.search-input {
  flex: 1;
  padding: 18px 25px;
  border: 3px solid #000000;
  background: #ffffff;
  font-size: 16px;
  font-weight: 600;
  letter-spacing: 1px;
  transition: all 0.3s;
}

.search-input:focus {
  outline: none;
  box-shadow: 8px 8px 0 rgba(0, 0, 0, 0.1);
  transform: translate(-2px, -2px);
}

.search-btn {
  padding: 18px 40px;
  background: #000000;
  color: #ffffff;
  border: 3px solid #000000;
  font-size: 16px;
  font-weight: 700;
  letter-spacing: 2px;
  text-transform: uppercase;
  cursor: pointer;
  transition: all 0.3s;
}

.search-btn:hover {
  background: #ffffff;
  color: #000000;
  transform: translateY(-3px);
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
}

/* 文件列表容器 */
.files-container {
  max-width: 1600px;
  margin: 0 auto;
  padding: 60px 40px;
}

.files-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 40px;
  padding-bottom: 20px;
  border-bottom: 3px solid #000000;
}

.files-stats {
  font-size: 14px;
  color: #666666;
  font-weight: 700;
  letter-spacing: 2px;
  text-transform: uppercase;
}

.files-stats strong {
  color: #000000;
  font-size: 18px;
  margin: 0 5px;
}

/* 现代化表格设计 */
.modern-table-wrapper {
  background: #ffffff;
  border: 3px solid #000000;
  box-shadow: 10px 10px 0 rgba(0, 0, 0, 0.05);
  overflow: hidden;
}

.modern-table {
  width: 100%;
  border-collapse: collapse;
  margin: 0;
}

.modern-table thead tr {
  background: #000000;
}

.modern-table thead th {
  padding: 20px 20px;
  text-align: left;
  color: #ffffff;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 2px;
  text-transform: uppercase;
  border: none;
}

.modern-table tbody tr {
  border-bottom: 1px solid #e0e0e0;
  transition: all 0.3s;
  cursor: pointer;
}

.modern-table tbody tr:last-child {
  border-bottom: none;
}

.modern-table tbody tr:hover {
  background: #000000;
  color: #ffffff;
  transform: scale(1.01);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  position: relative;
  z-index: 10;
}

.modern-table tbody tr:hover td {
  color: #ffffff;
}

.modern-table tbody tr:hover a {
  color: #ffffff;
}

.modern-table tbody td {
  padding: 20px;
  font-size: 14px;
  font-weight: 600;
  border: none;
  transition: all 0.3s;
}

.modern-table tbody td:first-child {
  font-size: 16px;
  font-weight: 900;
  color: #000000;
}

.modern-table tbody tr:hover td:first-child {
  color: #ffffff;
}

.file-actions {
  display: flex;
  gap: 15px;
}

.file-actions a {
  padding: 8px 16px;
  background: #ffffff;
  color: #000000;
  border: 2px solid #000000;
  text-decoration: none;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 1px;
  text-transform: uppercase;
  transition: all 0.3s;
}

.file-actions a:hover {
  background: #000000;
  color: #ffffff;
  transform: translateY(-2px);
}

.modern-table tbody tr:hover .file-actions a {
  background: #000000;
  color: #ffffff;
  border-color: #ffffff;
}

.modern-table tbody tr:hover .file-actions a:hover {
  background: #ffffff;
  color: #000000;
}

.file-icon {
  font-size: 18px;
  margin-right: 10px;
  color: #000000;
}

.modern-table tbody tr:hover .file-icon {
  color: #ffffff;
}

.file-type-badge {
  display: inline-block;
  padding: 4px 10px;
  background: #000000;
  color: #ffffff;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 1.5px;
  text-transform: uppercase;
}

.modern-table tbody tr:hover .file-type-badge {
  background: #ffffff;
  color: #000000;
}

/* 分页 */
.modern-pagination {
  margin-top: 60px;
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 10px;
}

.modern-pagination a,
.modern-pagination span {
  display: inline-block;
  padding: 12px 20px;
  background: #ffffff;
  color: #000000;
  border: 2px solid #000000;
  text-decoration: none;
  font-size: 13px;
  font-weight: 700;
  letter-spacing: 1px;
  transition: all 0.3s;
  min-width: 50px;
  text-align: center;
}

.modern-pagination a:hover {
  background: #000000;
  color: #ffffff;
  transform: translateY(-3px);
  box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
}

.modern-pagination .current {
  background: #000000;
  color: #ffffff;
}

.modern-pagination .disabled {
  background: #f5f5f5;
  color: #999999;
  border-color: #cccccc;
  cursor: not-allowed;
}

.empty-state {
  text-align: center;
  padding: 80px 40px;
}

.empty-state i {
  font-size: 80px;
  color: #cccccc;
  margin-bottom: 30px;
}

.empty-state h3 {
  font-size: 24px;
  font-weight: 900;
  color: #000000;
  letter-spacing: 2px;
  text-transform: uppercase;
  margin-bottom: 15px;
}

.empty-state p {
  font-size: 14px;
  color: #666666;
  letter-spacing: 1px;
}

@media (max-width: 768px) {
  .index-hero {
    padding: 60px 20px 40px;
  }

  .hero-title {
    font-size: 36px;
    letter-spacing: 2px;
  }

  .hero-title::after {
    width: 80px;
    height: 4px;
  }

  .search-container {
    flex-direction: column;
    gap: 10px;
  }

  .files-container {
    padding: 40px 20px;
  }

  .files-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 15px;
  }

  .modern-table-wrapper {
    overflow-x: auto;
  }

  .modern-table {
    min-width: 800px;
  }

  .modern-pagination {
    flex-wrap: wrap;
    gap: 8px;
  }

  .modern-pagination a,
  .modern-pagination span {
    padding: 10px 15px;
    font-size: 12px;
  }
}
</style>

<!-- Hero Section -->
<div class="index-hero">
  <div class="index-hero-inner">
    <h1 class="hero-title"><?php echo $htext?></h1>
    <?php if($conf['filesearch']==1){?>
    <form class="search-container" action="./" method="GET">
      <?php if(isset($_GET['m'])){?><input name="m" type="hidden" value="<?php echo htmlspecialchars($_GET['m'])?>"><?php }?>
      <input name="kw" class="search-input" type="search" placeholder="搜索文件..." value="<?php echo htmlspecialchars($kw)?>" required>
      <button class="search-btn" type="submit">
        <i class="fa fa-search"></i> 搜索
      </button>
    </form>
    <?php }?>
  </div>
</div>

<!-- Files List -->
<div class="files-container">
  <?php
  $numrows=$DB->getColumn("SELECT count(*) from pre_file WHERE{$sql}");
  $pagesize=15;
  $pages=ceil($numrows/$pagesize);
  $page=isset($_GET['page'])?intval($_GET['page']):1;
  $offset=$pagesize*($page - 1);
  ?>

  <div class="files-header">
    <div class="files-stats">
      共有 <strong><?php echo $numrows?></strong> 个文件
      <span style="margin: 0 15px; color: #cccccc;">|</span>
      第 <strong><?php echo $page?></strong> / <?php echo $pages?> 页
    </div>
  </div>

  <div class="modern-table-wrapper">
    <table class="modern-table">
      <thead>
        <tr>
          <th width="60">#</th>
          <th width="150">操作</th>
          <th>文件名</th>
          <th width="120">文件大小</th>
          <th width="100">格式</th>
          <th width="180">上传时间</th>
          <th width="150">上传者</th>
        </tr>
      </thead>
      <tbody>
<?php
$rs=$DB->query("SELECT * FROM pre_file WHERE{$sql} ORDER BY id DESC LIMIT $offset,$pagesize");
$i=1;
while($res = $rs->fetch())
{
    $fileurl = './down.php/'.$res['hash'].'.'.($res['type']?$res['type']:'file');
    $viewurl = './file.php?hash='.$res['hash'];
    echo '<tr>';
    echo '<td><b>'.$i++.'</b></td>';
    echo '<td><div class="file-actions"><a href="'.$fileurl.'">下载</a><a href="'.$viewurl.'">查看</a></div></td>';
    echo '<td><i class="fa '.type_to_icon($res['type']).' file-icon"></i>'.htmlspecialchars($res['name']).'</td>';
    echo '<td>'.size_format($res['size']).'</td>';
    echo '<td><span class="file-type-badge">'.($res['type']?strtoupper($res['type']):'未知').'</span></td>';
    echo '<td>'.$res['addtime'].'</td>';
    echo '<td>'.preg_replace('/\d+$/','***',$res['ip']).'</td>';
    echo '</tr>';
}
if($numrows == 0) {
    echo '<tr><td colspan="7" class="empty-state">';
    echo '<i class="fa fa-inbox"></i>';
    echo '<h3>暂无文件</h3>';
    echo '<p>还没有上传任何文件</p>';
    echo '</td></tr>';
}
?>
      </tbody>
    </table>
  </div>

  <?php if($pages > 1){?>
  <div class="modern-pagination">
<?php
$first=1;
$prev=$page-1;
$next=$page+1;
$last=$pages;
if ($page>1) {
    echo '<a href="index.php?page='.$first.$link.'">首页</a>';
    echo '<a href="index.php?page='.$prev.$link.'"><i class="fa fa-chevron-left"></i></a>';
} else {
    echo '<span class="disabled">首页</span>';
    echo '<span class="disabled"><i class="fa fa-chevron-left"></i></span>';
}
$start=$page-3>1?$page-3:1;
$end=$page+3<$pages?$page+3:$pages;
for ($i=$start;$i<$page;$i++)
    echo '<a href="index.php?page='.$i.$link.'">'.$i.'</a>';
echo '<span class="current">'.$page.'</span>';
for ($i=$page+1;$i<=$end;$i++)
    echo '<a href="index.php?page='.$i.$link.'">'.$i.'</a>';
if ($page<$pages) {
    echo '<a href="index.php?page='.$next.$link.'"><i class="fa fa-chevron-right"></i></a>';
    echo '<a href="index.php?page='.$last.$link.'">尾页</a>';
} else {
    echo '<span class="disabled"><i class="fa fa-chevron-right"></i></span>';
    echo '<span class="disabled">尾页</span>';
}
?>
  </div>
  <?php }?>
</div>

<?php include SYSTEM_ROOT.'footer.php';?>

<?php if(!empty($conf['gonggao'])){?>
<link href="https://s4.zstatic.net/ajax/libs/snackbarjs/1.1.0/snackbar.min.css" rel="stylesheet">
<script src="https://s4.zstatic.net/ajax/libs/snackbarjs/1.1.0/snackbar.min.js"></script>
<script src="https://s4.zstatic.net/ajax/libs/jquery-cookie/1.4.1/jquery.cookie.min.js"></script>
<script>
$(function() {
    if(!$.cookie('gonggao')){
        $.snackbar({content: "<?php echo $conf['gonggao']?>", timeout: 10000});
        var cookietime = new Date();
        cookietime.setTime(cookietime.getTime() + (60*60*1000));
        $.cookie('gonggao', false, { expires: cookietime });
    }
});
</script>
<?php }?>
</body>
</html>
