<?php
define('IN_ADMIN', true);
include("../includes/common.php");
$title='OOCloud 管理中心';
include './head.php';
if($islogin==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");
?>
<?php
$mysqlversion=$DB->getColumn("select VERSION()");
?>
<link href="../assets/css/admin.css" rel="stylesheet"/>
<div class="container" style="padding-top:70px;">
<div class="col-md-12 col-lg-10 center-block" style="float: none;">
<div id="browser-notice"></div>
<div class="row">
                <div class="col-lg-3 col-md-6">
                    <div class="panel panel-primary">
                        <div class="panel-heading">
                            <div class="row">
                                <div class="col-xs-3">
                                    <i class="fa fa-cloud fa-5x"></i>
                                </div>
                                <div class="col-xs-9 text-right">
                                    <div class="huge" id="count1">0</div>
                                    <div>文件总数</div>
                                </div>
                            </div>
                        </div>
                        <a href="file.php">
                            <div class="panel-footer">
                                <span class="pull-left" herf="file.php">查看详情</span>
                                <span class="pull-right"><i class="fa fa-arrow-circle-right"></i></span>
                                <div class="clearfix"></div>
                            </div>
                        </a>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="panel panel-green">
                        <div class="panel-heading">
                            <div class="row">
                                <div class="col-xs-3">
                                    <i class="fa fa-cloud-upload fa-5x"></i>
                                </div>
                                <div class="col-xs-9 text-right">
                                    <div class="huge" id="count2">0</div>
                                    <div>今日上传文件</div>
                                </div>
                            </div>
                        </div>
                        <a href="file.php">
                            <div class="panel-footer">
                                <span class="pull-left">查看详情</span>
                                <span class="pull-right"><i class="fa fa-arrow-circle-right"></i></span>
                                <div class="clearfix"></div>
                            </div>
                        </a>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="panel panel-yellow">
                        <div class="panel-heading">
                            <div class="row">
                                <div class="col-xs-3">
                                    <i class="fa fa-inbox fa-5x"></i>
                                </div>
                                <div class="col-xs-9 text-right">
                                    <div class="huge" id="count3">0</div>
                                    <div>昨日上传文件</div>
                                </div>
                            </div>
                        </div>
                        <a href="file.php">
                            <div class="panel-footer">
                                <span class="pull-left">查看详情</span>
                                <span class="pull-right"><i class="fa fa-arrow-circle-right"></i></span>
                                <div class="clearfix"></div>
                            </div>
                        </a>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="panel panel-red">
                        <div class="panel-heading">
                            <div class="row">
                                <div class="col-xs-3">
                                    <i class="fa fa-users fa-5x"></i>
                                </div>
                                <div class="col-xs-9 text-right">
                                    <div class="huge" id="count4">0</div>
                                    <div>用户总数</div>
                                </div>
                            </div>
                        </div>
                        <a href="user.php">
                            <div class="panel-footer">
                                <span class="pull-left">查看详情</span>
                                <span class="pull-right"><i class="fa fa-arrow-circle-right"></i></span>
                                <div class="clearfix"></div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
            <!-- /.row -->
        <div class="row">
            <div class="col-md-8 col-sm-12">
                <div class="panel panel-info">
                    <div class="panel-heading">
                        <h3 class="panel-title">服务器信息</h3>
                    </div>
                    <ul class="list-group">
                        <li class="list-group-item">
                            <b>PHP 版本：</b><?php echo phpversion() ?>
                            <?php if(ini_get('safe_mode')) { echo '线程安全'; } else { echo '非线程安全'; } ?>
                        </li>
                        <li class="list-group-item">
                            <b>MySQL 版本：</b><?php echo $mysqlversion ?>
                        </li>
                        <li class="list-group-item">
                            <b>WEB软件：</b><?php echo $_SERVER['SERVER_SOFTWARE'] ?>
                        </li>
                        
                        <li class="list-group-item">
                            <b>服务器时间：</b><?php echo $date ?>
                        </li>
                        <li class="list-group-item">
                            <b>POST许可：</b><?php echo ini_get('post_max_size'); ?>
                        </li>
                        <li class="list-group-item">
                            <b>文件上传许可：</b><?php echo ini_get('upload_max_filesize'); ?>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="col-md-4 col-sm-12">
                <div class="panel panel-success">
                    <div class="panel-heading">
                        <h3 class="panel-title">版本信息</h3>
                    </div>
                    <ul class="list-group text-dark" id="checkupdate"></ul>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
$(document).ready(function(){
    $.ajax({
        type : "GET",
        url : "ajax.php?act=getcount",
        dataType : 'json',
        async: true,
        success : function(data) {
            $('#count1').html(data.count1);
            $('#count2').html(data.count2);
            $('#count3').html(data.count3);
            $('#count4').html(data.count4);
            // 改为调用本地接口检查更新，避免跨域与上游依赖
            $.ajax({
                url: 'ajax.php',
                type: 'get',
                data: { act: 'checkupdate' },
                dataType: 'json'
            }).done(function(res){
                var html = '';
                if(res && res.msg){
                    html = res.msg;
                } else {
                    html = '<li class="list-group-item">更新检查失败</li>';
                }
                // 追加一键更新按钮
                html += '<li class="list-group-item">'
                    + '<button id="btn-self-update" class="btn btn-sm btn-primary">一键更新</button> '
                    + '<span id="upd-status" class="text-muted" style="margin-left:8px;"></span>'
                    + '</li>';
                $("#checkupdate").html(html);
                // 绑定事件
                $('#btn-self-update').on('click', function(){
                    var $btn = $(this), $st = $('#upd-status');
                    if($btn.prop('disabled')) return false;
                    $btn.prop('disabled', true).text('更新中...');
                    $st.text('准备下载更新包...');
                    $.ajax({
                        url: 'ajax.php',
                        type: 'post',
                        data: { act: 'selfUpdate' },
                        dataType: 'json',
                        timeout: 0
                    }).done(function(r){
                        if(r && r.code===0){
                            $st.text('更新完成：'+(r.new_version||''));
                            $btn.text('已更新');
                        } else {
                            $st.text('更新失败：'+(r && r.msg ? r.msg : '未知错误'));
                            $btn.prop('disabled', false).text('重试更新');
                        }
                    }).fail(function(){
                        $st.text('更新失败：网络错误');
                        $btn.prop('disabled', false).text('重试更新');
                    });
                });
            }).fail(function(){
                $("#checkupdate").html('<li class="list-group-item">更新检查失败</li>');
            });
        }
    })
})
</script>
<script>
function speedModeNotice(){
    var ua = window.navigator.userAgent;
    if(ua.indexOf('Windows NT')>-1 && ua.indexOf('Trident/')>-1){
        var html = "<div class=\"panel panel-default\"><div class=\"panel-body\">当前浏览器是兼容模式，为确保后台功能正常使用，请切换到<b style='color:#51b72f'>极速模式</b>！<br>操作方法：点击浏览器地址栏右侧的IE符号<b style='color:#51b72f;'><i class='fa fa-internet-explorer fa-fw'></i></b>→选择“<b style='color:#51b72f;'><i class='fa fa-flash fa-fw'></i></b><b style='color:#51b72f;'>极速模式</b>”</div></div>";
        $("#browser-notice").html(html)
    }
}
speedModeNotice();
</script>
