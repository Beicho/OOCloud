<?php
@header('Content-Type: text/html; charset=UTF-8');
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="renderer" content="webkit">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title><?php echo $title?></title>
  <meta name="keywords" content="<?php echo $conf['keywords']?>">
  <meta name="description" content="<?php echo $conf['description']?>">
  <meta name="viewport" content="width=device-width,height=device-height,inital-scale=1.0,maximum-scale=1.0,user-scalable=no;">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black">
  <meta name="format-detection" content="telephone=no">

  <link href="https://s4.zstatic.net/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet">
  <link href="https://s4.zstatic.net/ajax/libs/twitter-bootstrap/3.4.1/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://s4.zstatic.net/ajax/libs/bootstrap-material-design/0.5.10/css/bootstrap-material-design.min.css" rel="stylesheet">
  <link href="https://s4.zstatic.net/ajax/libs/bootstrap-material-design/0.5.10/css/ripples.min.css" rel="stylesheet">

  <?php
    $brand_logo_url = !empty($conf['brand_logo_url']) ? $conf['brand_logo_url'] : '/assets/img/oocloud-logo.svg';
    $brand_favicon_url = !empty($conf['brand_favicon_url']) ? $conf['brand_favicon_url'] : '/assets/img/oocloud-favicon.svg';
  ?>
  <link rel="icon" type="image/svg+xml" href="<?php echo htmlspecialchars($brand_favicon_url, ENT_QUOTES, 'UTF-8'); ?>">

  <?php if($is_file){?><link rel="stylesheet" href="https://s4.zstatic.net/ajax/libs/aplayer/1.10.1/APlayer.min.css"><link href="assets/css/ckplayer.css" rel="stylesheet"><?php }?>
  <link href="assets/css/style.css?v=<?php echo VERSION?>" rel="stylesheet">

  <!--[if lt IE 9]>
    <script src="https://s4.zstatic.net/ajax/libs/html5shiv/3.7.3/html5shiv.min.js"></script>
    <script src="https://s4.zstatic.net/ajax/libs/respond.js/1.4.2/respond.min.js"></script>
  <![endif]-->
  <script type="text/javascript" src="https://s4.zstatic.net/ajax/libs/jquery/1.12.4/jquery.min.js"></script>

  <style>
  /* 全新现代化布局样式 */
  body {
    margin: 0;
    padding: 0;
    background: #ffffff;
  }

  /* 全新顶部导航 - 极简设计 */
  .modern-header {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    height: 80px;
    background: #000000;
    z-index: 1000;
    border-bottom: 4px solid #ffffff;
    box-shadow: 0 4px 30px rgba(0, 0, 0, 0.5);
  }

  .modern-header-inner {
    max-width: 1600px;
    margin: 0 auto;
    padding: 0 40px;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
  }

  .modern-logo {
    display: flex;
    align-items: center;
    text-decoration: none;
    transition: all 0.3s;
  }

  .modern-logo:hover {
    transform: scale(1.05);
  }

  .modern-logo img {
    height: 30px;
    width: auto;
    margin-right: 15px;
    filter: invert(1);
  }

  .modern-logo-text {
    font-size: 26px;
    font-weight: 900;
    color: #ffffff;
    letter-spacing: 3px;
    text-transform: uppercase;
  }

  .modern-nav {
    display: flex;
    align-items: center;
    gap: 0;
  }

  .modern-nav-item {
    position: relative;
    padding: 30px 25px;
    color: #ffffff;
    text-decoration: none;
    font-size: 14px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 2px;
    transition: all 0.3s;
    border-left: 1px solid rgba(255, 255, 255, 0.1);
  }

  .modern-nav-item:first-child {
    border-left: none;
  }

  .modern-nav-item:hover {
    background: rgba(255, 255, 255, 0.1);
    color: #ffffff;
  }

  .modern-nav-item.active {
    background: #ffffff;
    color: #000000;
  }

  .modern-nav-item i {
    margin-right: 8px;
    font-size: 16px;
  }

  /* 用户菜单 */
  .modern-user {
    position: relative;
    padding: 30px 25px;
    color: #ffffff;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    cursor: pointer;
    transition: all 0.3s;
    border-left: 1px solid rgba(255, 255, 255, 0.1);
  }

  .modern-user:hover {
    background: rgba(255, 255, 255, 0.1);
  }

  .modern-user-dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    background: #000000;
    border: 2px solid #ffffff;
    min-width: 200px;
    display: none;
    box-shadow: 8px 8px 0 rgba(0, 0, 0, 0.2);
  }

  .modern-user:hover .modern-user-dropdown {
    display: block;
  }

  .modern-user-dropdown a {
    display: block;
    padding: 15px 20px;
    color: #ffffff;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
    letter-spacing: 1px;
    transition: all 0.3s;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  }

  .modern-user-dropdown a:last-child {
    border-bottom: none;
  }

  .modern-user-dropdown a:hover {
    background: #ffffff;
    color: #000000;
    transform: translateX(5px);
  }

  /* 移动端菜单按钮 */
  .mobile-menu-toggle {
    display: none;
    background: none;
    border: 2px solid #ffffff;
    color: #ffffff;
    padding: 10px 15px;
    font-size: 20px;
    cursor: pointer;
    transition: all 0.3s;
  }

  .mobile-menu-toggle:hover {
    background: #ffffff;
    color: #000000;
  }

  /* 主内容区域 */
  .modern-main {
    margin-top: 80px;
    min-height: calc(100vh - 80px - 200px);
  }

  /* 响应式设计 */
  @media (max-width: 991px) {
    .modern-header-inner {
      padding: 0 20px;
    }

    .modern-nav {
      display: none;
      position: absolute;
      top: 80px;
      left: 0;
      right: 0;
      background: #000000;
      flex-direction: column;
      border-top: 2px solid #ffffff;
    }

    .modern-nav.active {
      display: flex;
    }

    .modern-nav-item {
      width: 100%;
      border-left: none;
      border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .modern-user {
      border-left: none;
    }

    .mobile-menu-toggle {
      display: block;
    }
  }

  @media (max-width: 768px) {
    .modern-header {
      height: 70px;
    }

    .modern-main {
      margin-top: 70px;
    }

    .modern-logo-text {
      font-size: 20px;
      letter-spacing: 2px;
    }

    .modern-logo img {
      height: 24px;
    }

    .modern-nav {
      top: 70px;
    }
  }
  </style>
</head>
<body>

<!-- 全新现代化导航栏 -->
<header class="modern-header">
  <div class="modern-header-inner">
    <a href="./" class="modern-logo">
      <img src="<?php echo htmlspecialchars($brand_logo_url, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo $conf['title']?>">
      <span class="modern-logo-text"><?php echo $conf['title']?></span>
    </a>

    <nav class="modern-nav" id="modernNav">
      <a href="./" class="modern-nav-item <?php echo checkIfActive('index,')?>">
        <i class="fa fa-list"></i>文件列表
      </a>
      <a href="./upload.php" class="modern-nav-item <?php echo checkIfActive('upload')?>">
        <i class="fa fa-upload"></i>上传文件
      </a>
      <?php if($is_file){?>
      <a href="" class="modern-nav-item <?php echo checkIfActive('file')?>">
        <i class="fa fa-file"></i>文件查看
      </a>
      <?php }?>
      <a href="./?m=mine" class="modern-nav-item <?php echo checkIfActive('mine')?>">
        <i class="fa fa-folder-open"></i>我的文件
      </a>

      <?php if($conf['userlogin']){?>
        <?php if($islogin2){?>
        <div class="modern-user">
          <i class="fa fa-<?php echo $userrow['type']=='qq'?'qq':'user-circle';?>"></i>
          <?php echo htmlspecialchars($userrow['nickname'])?>
          <div class="modern-user-dropdown">
            <a href="./user.php"><i class="fa fa-user-circle"></i> 用户中心</a>
            <a href="./login.php?logout=1" onclick="return confirm('是否确定退出登录？')"><i class="fa fa-sign-out"></i> 退出登录</a>
          </div>
        </div>
        <?php }else{?>
        <a href="./login.php" class="modern-nav-item <?php echo checkIfActive('login')?>">
          <i class="fa fa-sign-in"></i>登录
        </a>
        <?php }?>
      <?php }?>
    </nav>

    <button class="mobile-menu-toggle" onclick="toggleMobileMenu()">
      <i class="fa fa-bars"></i>
    </button>
  </div>
</header>

<div class="modern-main">

<script>
function toggleMobileMenu() {
  document.getElementById('modernNav').classList.toggle('active');
}
</script>
