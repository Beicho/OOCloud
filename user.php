<?php
include("./includes/common.php");
if(!$islogin2){
    @header('Content-Type: text/html; charset=UTF-8');
    exit("<script>alert('请先登录');window.location.href='./login.php';</script>");
}
$title = '用户中心 - '.$conf['title'];
include SYSTEM_ROOT.'header.php';
$csrf_token = md5(mt_rand(0,999).time());
$_SESSION['csrf_token'] = $csrf_token;
?>
<div class="container" style="padding-top:30px;">
  <div class="row">
    <div class="col-sm-4">
      <div class="panel panel-primary">
        <div class="panel-heading"><h3 class="panel-title">账户概览</h3></div>
        <div class="panel-body">
          <p>UID：<?php echo $uid?></p>
          <p>昵称：<?php echo htmlspecialchars($userrow['nickname'])?></p>
        </div>
      </div>
      <div class="panel panel-default">
        <div class="panel-heading"><h3 class="panel-title">API Token</h3></div>
        <div class="panel-body">
          <p>你的 Token：<code id="api_token"><?php echo $userrow['api_token']?htmlspecialchars($userrow['api_token']):'未生成'?></code></p>
          <button class="btn btn-primary btn-raised" id="btn-gen">生成/重置</button>
          <p class="text-muted" style="margin-top:6px;">用于本账号 API 调用，请妥善保管。</p>
          <div style="margin-top:8px;">
<pre style="white-space:pre-wrap;word-break:break-all;font-size:12px;">快速示例（上传文件）：
curl -X POST \
  -H "Authorization: Bearer 你的token" \
  -F "file=@/path/to/file.jpg" \
  -F "name=文件名.jpg" \
  <?php echo $siteurl?>api.php/upload

获取文件列表：
curl -H "Authorization: Bearer 你的token" \
  <?php echo $siteurl?>api.php/files?page=1&limit=20

下载文件：
curl -H "Authorization: Bearer 你的token" \
  <?php echo $siteurl?>api.php/download/{hash} -o file.jpg</pre>
            <p><a class="btn btn-primary btn-sm" href="./api-docs.php" target="_blank"><i class="fa fa-book"></i> 查看完整 API 文档</a></p>
          </div>
        </div>
      </div>
      <div class="panel panel-default">
        <div class="panel-heading"><h3 class="panel-title">安全</h3></div>
        <div class="panel-body">
          <a class="btn btn-default btn-raised" href="./2fa.php">两步验证（2FA）</a>
        </div>
      </div>
      <div class="panel panel-default">
        <div class="panel-heading"><h3 class="panel-title">账号绑定</h3></div>
        <div class="panel-body">
          <?php
          // 查询已绑定的账号
          $bindTypes = [];
          if($userrow['bindings']){
            $bindTypes = json_decode($userrow['bindings'], true);
            if(!is_array($bindTypes)) $bindTypes = [];
          }

          // 显示绑定状态
          if($conf['login_qq']){
            if(isset($bindTypes['qq'])){
              echo '<div class="bind-item"><i class="fa fa-qq" style="color:#12b7f5;"></i> QQ: '.htmlspecialchars($bindTypes['qq']['nickname']).' <button class="btn btn-xs btn-danger" onclick="unbind(\'qq\')">解绑</button></div>';
            }else{
              echo '<div class="bind-item"><i class="fa fa-qq" style="color:#12b7f5;"></i> QQ: 未绑定 <button class="btn btn-xs btn-primary" onclick="bindAccount(\'qq\')">绑定</button></div>';
            }
          }

          if($conf['login_wx']){
            if(isset($bindTypes['wx'])){
              echo '<div class="bind-item"><i class="fa fa-wechat" style="color:#09bb07;"></i> 微信: '.htmlspecialchars($bindTypes['wx']['nickname']).' <button class="btn btn-xs btn-danger" onclick="unbind(\'wx\')">解绑</button></div>';
            }else{
              echo '<div class="bind-item"><i class="fa fa-wechat" style="color:#09bb07;"></i> 微信: 未绑定 <button class="btn btn-xs btn-primary" onclick="bindAccount(\'wx\')">绑定</button></div>';
            }
          }

          if($conf['login_linuxdo']){
            if(isset($bindTypes['linuxdo'])){
              echo '<div class="bind-item"><i class="fa fa-linux" style="color:#333;"></i> Linux Do: '.htmlspecialchars($bindTypes['linuxdo']['nickname']).' <button class="btn btn-xs btn-danger" onclick="unbind(\'linuxdo\')">解绑</button></div>';
            }else{
              echo '<div class="bind-item"><i class="fa fa-linux" style="color:#333;"></i> Linux Do: 未绑定 <button class="btn btn-xs btn-primary" onclick="bindAccount(\'linuxdo\')">绑定</button></div>';
            }
          }
          ?>
          <style>
          .bind-item {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            justify-content: space-between;
          }
          .bind-item:last-child {
            border-bottom: none;
          }
          .bind-item i {
            font-size: 18px;
            margin-right: 8px;
          }
          </style>
        </div>
      </div>
    </div>
    <div class="col-sm-8">
      <div class="panel panel-primary">
        <div class="panel-heading"><h3 class="panel-title">我的文件</h3></div>
        <div class="panel-body">
          <div id="toolbar" class="btn-group" style="margin-bottom:8px;">
            <button class="btn btn-default btn-raised" onclick="batchSign()"><i class="fa fa-shield"></i> 批量高级分享</button>
            <button class="btn btn-default btn-raised" onclick="batchRename()"><i class="fa fa-i-cursor"></i> 批量重命名</button>
            <button class="btn btn-danger btn-raised" onclick="batchDelete()"><i class="fa fa-trash"></i> 批量删除</button>
          </div>
          <table id="listTable" class="table table-striped"></table>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include SYSTEM_ROOT.'footer.php';?>
<script src="https://s4.zstatic.net/ajax/libs/bootstrap-table/1.21.4/bootstrap-table.min.js"></script>
<link rel="stylesheet" href="https://s4.zstatic.net/ajax/libs/bootstrap-table/1.21.4/bootstrap-table.min.css">
<script src="https://s4.zstatic.net/ajax/libs/layer/3.1.1/layer.js"></script>
<style>
/* 面板悬停效果 */
.panel {
  transition: all 0.3s ease;
}
.panel:hover {
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
  transform: translateY(-2px);
}

/* API Token 代码块悬停效果 */
#api_token {
  transition: all 0.2s ease;
}
#api_token:hover {
  background: #f0f0f0;
  padding: 4px 8px;
  border-radius: 3px;
}

/* 按钮组动效 */
.btn-group .btn {
  transition: all 0.2s ease;
}
.btn-group .btn:hover {
  transform: scale(1.05);
}

/* 表格行悬停效果 */
.table tbody tr {
  transition: all 0.2s ease;
}
.table tbody tr:hover {
  background-color: #f5f5f5 !important;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

/* 操作按钮优化 */
.btn-group-xs .btn {
  transition: all 0.2s ease;
  margin: 0 2px;
}
.btn-group-xs .btn:hover {
  transform: translateY(-1px);
  box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

/* 复选框动效 */
.table input[type="checkbox"] {
  cursor: pointer;
  transform: scale(1.1);
  transition: transform 0.1s ease;
}
.table input[type="checkbox"]:hover {
  transform: scale(1.3);
}

/* 输入框焦点效果 */
.form-control:focus {
  border-color: #2196F3;
  box-shadow: 0 0 8px rgba(33, 150, 243, 0.3);
  transform: scale(1.01);
  transition: all 0.2s ease;
}

/* 主操作按钮悬停效果 */
#btn-gen {
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;
}
#btn-gen:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(33, 150, 243, 0.4);
}
#btn-gen:active {
  transform: translateY(0);
}

/* 工具栏按钮悬停效果 */
#toolbar .btn {
  transition: all 0.3s ease;
}
#toolbar .btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

/* 弹窗内按钮效果 */
.layui-layer-content .btn {
  transition: all 0.2s ease;
}
.layui-layer-content .btn:hover {
  transform: scale(1.02);
  box-shadow: 0 3px 8px rgba(0,0,0,0.15);
}

/* 标签页淡入动画 */
.panel-body {
  animation: fadeIn 0.4s ease-in;
}
@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* 统计数字跳动效果 */
.panel-body p {
  transition: all 0.2s ease;
}
.panel-body p:hover {
  color: #2196F3;
  transform: translateX(5px);
}

/* 复选框标签悬停效果 */
label[style*="cursor:pointer"]:hover {
  color: #2196F3;
  text-decoration: underline;
}

/* 加载动画优化 */
.layui-layer-loading .layui-layer-content {
  animation: spin 1s linear infinite;
}
@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}
</style>
<script>
var CSRF_TOKEN = '<?php echo $csrf_token?>';

$(function(){
  // 生成API Token
  $('#btn-gen').on('click', function(e){
    e.preventDefault();
    var loading = layer.load(2, {shade:[0.1,'#fff']});
    $.post('ajax.php?act=gen_api_token', {csrf_token: CSRF_TOKEN}, function(data){
      layer.close(loading);
      if(data.code == 0){
        $('#api_token').text(data.token);
        layer.alert('Token已生成', {icon: 1});
      } else {
        layer.alert(data.msg || '生成失败', {icon: 7});
      }
    }, 'json').fail(function(){
      layer.close(loading);
      layer.msg('服务器错误');
    });
  });

  // 初始化文件列表表格
  $('#listTable').bootstrapTable({
    url: 'ajax.php?act=user_files',
    method: 'post',
    pagination: true,
    sidePagination: 'server',
    pageSize: 10,
    search: false,
    toolbar: '#toolbar',
    clickToSelect: true,
    columns: [
      {checkbox: true},
      {field: 'id', title: 'ID', width: 60},
      {field: 'name', title: '文件名'},
      {field: 'size', title: '大小', formatter: function(value){
        return value ? (value/1024/1024).toFixed(2) + ' MB' : '-';
      }},
      {field: 'type', title: '类型'},
      {field: 'addtime', title: '上传时间'},
      {field: 'hash', title: '操作', formatter: function(hash, row){
        var name = (row.name || '').replace(/'/g, "\\'");
        return '<div class="btn-group btn-group-xs">'+
               '<a class="btn btn-default" href="javascript:shareOne(\''+hash+'\')">高级分享</a>'+
               '<a class="btn btn-default" href="javascript:renameFile(\''+hash+'\',\''+name+'\')">重命名</a>'+
               '<a class="btn btn-danger" href="javascript:deleteFile(\''+hash+'\')">删除</a>'+
               '</div>';
      }}
    ]
  });
});

// 获取选中的文件hash列表
function getSelectedHashes(){
  return $('#listTable').bootstrapTable('getSelections').map(function(row){
    return row.hash;
  });
}

// 重命名单个文件
function renameFile(hash, oldname){
  layer.prompt({title: '新文件名', value: oldname}, function(newname, index){
    layer.close(index);
    if(!newname || newname.trim() === ''){
      layer.msg('文件名不能为空');
      return;
    }
    $.post('ajax.php?act=user_rename', {
      hash: hash,
      name: newname,
      csrf_token: CSRF_TOKEN
    }, function(data){
      if(data.code == 0){
        layer.msg('重命名成功');
        $('#listTable').bootstrapTable('refresh');
      } else {
        layer.alert(data.msg || '重命名失败', {icon: 2});
      }
    }, 'json');
  });
}

// 删除单个文件
function deleteFile(hash){
  layer.confirm('确定删除该文件？', {btn: ['确定', '取消']}, function(index){
    layer.close(index);
    $.post('ajax.php?act=user_delete', {
      hash: hash,
      csrf_token: CSRF_TOKEN
    }, function(data){
      if(data.code == 0){
        layer.msg('删除成功');
        $('#listTable').bootstrapTable('refresh');
      } else {
        layer.alert(data.msg || '删除失败', {icon: 2});
      }
    }, 'json');
  });
}

// 批量删除
function batchDelete(){
  var hashes = getSelectedHashes();
  if(hashes.length == 0){
    layer.msg('请先选择文件');
    return;
  }

  layer.confirm('确定批量删除 ' + hashes.length + ' 个文件？', {btn: ['确定', '取消']}, function(index){
    layer.close(index);
    var loading = layer.load(2);
    var success = 0, failed = 0;

    (async function(){
      for(var i = 0; i < hashes.length; i++){
        try{
          await $.post('ajax.php?act=user_delete', {
            hash: hashes[i],
            csrf_token: CSRF_TOKEN
          }, function(data){
            if(data.code == 0) success++;
            else failed++;
          }, 'json');
        } catch(e){
          failed++;
        }
      }
      layer.close(loading);
      $('#listTable').bootstrapTable('refresh');
      layer.alert('批量删除完成！\n成功: ' + success + ' 个\n失败: ' + failed + ' 个', {icon: 1});
    })();
  });
}

// 单个文件高级分享
function shareOne(hash){
  layer.open({
    type: 1,
    title: '生成签名链接',
    area: ['400px', '380px'],
    content: '<div style="padding:20px;">'+
      '<div class="form-group">'+
      '<label>有效期(分钟)</label>'+
      '<input id="share_expires" class="form-control" value="60" type="number">'+
      '</div>'+
      '<div class="form-group">'+
      '<label>下载次数</label>'+
      '<input id="share_max" class="form-control" value="1" type="number">'+
      '</div>'+
      '<div style="margin:15px 0;">'+
      '<label style="font-weight:normal;cursor:pointer;">'+
      '<input type="checkbox" id="share_login" style="margin-right:8px;"> 仅登录用户可访问'+
      '</label>'+
      '</div>'+
      '<div class="form-group">'+
      '<label>指定UID（用逗号分隔）</label>'+
      '<input id="share_uids" class="form-control" placeholder="留空则所有人可访问">'+
      '</div>'+
      '<button class="btn btn-primary btn-block" id="btn_share_submit">生成链接</button>'+
      '</div>',
    success: function(){
      $('#btn_share_submit').on('click', function(){
        var expires = parseInt($('#share_expires').val(), 10) * 60;
        var max = parseInt($('#share_max').val(), 10);
        var loginOnly = $('#share_login').is(':checked') ? 1 : 0;
        var uids = $('#share_uids').val().trim();

        var loading = layer.load(2);
        $.post('ajax.php?act=sign_link', {
          csrf_token: CSRF_TOKEN,
          hash: hash,
          expires_in: expires,
          max: max,
          login_only: loginOnly,
          allow_uids: uids
        }, function(data){
          layer.close(loading);
          if(data.code == 0){
            layer.alert('链接已生成并复制到剪贴板：<br><textarea class="form-control" style="margin-top:10px;" rows="3">' + data.downurl + '</textarea>', {
              icon: 1,
              area: ['500px', '250px']
            });
            if(navigator.clipboard){
              navigator.clipboard.writeText(data.downurl);
            }
          } else {
            layer.alert(data.msg || '生成失败', {icon: 2});
          }
        }, 'json').fail(function(){
          layer.close(loading);
          layer.msg('服务器错误');
        });
      });
    }
  });
}

// 批量高级分享
function batchSign(){
  var hashes = getSelectedHashes();
  if(hashes.length == 0){
    layer.msg('请先选择文件');
    return;
  }

  layer.open({
    type: 1,
    title: '批量生成签名链接',
    area: ['400px', '380px'],
    content: '<div style="padding:20px;">'+
      '<div class="form-group">'+
      '<label>有效期(分钟)</label>'+
      '<input id="batch_expires" class="form-control" value="60" type="number">'+
      '</div>'+
      '<div class="form-group">'+
      '<label>下载次数</label>'+
      '<input id="batch_max" class="form-control" value="1" type="number">'+
      '</div>'+
      '<div style="margin:15px 0;">'+
      '<label style="font-weight:normal;cursor:pointer;">'+
      '<input type="checkbox" id="batch_login" style="margin-right:8px;"> 仅登录用户可访问'+
      '</label>'+
      '</div>'+
      '<div class="form-group">'+
      '<label>指定UID（用逗号分隔）</label>'+
      '<input id="batch_uids" class="form-control" placeholder="留空则所有人可访问">'+
      '</div>'+
      '<button class="btn btn-primary btn-block" id="btn_batch_submit">批量生成</button>'+
      '</div>',
    success: function(){
      $('#btn_batch_submit').on('click', function(){
        var expires = parseInt($('#batch_expires').val(), 10) * 60;
        var max = parseInt($('#batch_max').val(), 10);
        var loginOnly = $('#batch_login').is(':checked') ? 1 : 0;
        var uids = $('#batch_uids').val().trim();

        var loading = layer.load(2);
        var success = 0, failed = 0;
        var links = [];

        (async function(){
          for(var i = 0; i < hashes.length; i++){
            try{
              await $.post('ajax.php?act=sign_link', {
                csrf_token: CSRF_TOKEN,
                hash: hashes[i],
                expires_in: expires,
                max: max,
                login_only: loginOnly,
                allow_uids: uids
              }, function(data){
                if(data.code == 0){
                  success++;
                  links.push(data.downurl);
                } else {
                  failed++;
                }
              }, 'json');
            } catch(e){
              failed++;
            }
          }
          layer.close(loading);
          layer.alert('批量生成完成！\n成功: ' + success + ' 个\n失败: ' + failed + ' 个\n\n第一条链接已复制到剪贴板', {icon: 1});
          if(links.length > 0 && navigator.clipboard){
            navigator.clipboard.writeText(links[0]);
          }
          layer.closeAll();
        })();
      });
    }
  });
}

// 绑定第三方账号
function bindAccount(type){
  layer.confirm('确定绑定'+type+'账号？', {btn: ['确定', '取消']}, function(index){
    layer.close(index);
    var loading = layer.load(2, {shade:[0.1,'#fff']});
    $.post('ajax.php?act=bind_account', {type: type, csrf_token: CSRF_TOKEN}, function(data){
      layer.close(loading);
      if(data.code == 0){
        window.location.href = data.url;
      } else {
        layer.alert(data.msg || '获取授权链接失败', {icon: 7});
      }
    }, 'json').fail(function(){
      layer.close(loading);
      layer.msg('服务器错误');
    });
  });
}

// 解绑第三方账号
function unbind(type){
  layer.confirm('确定解绑'+type+'账号？解绑后将无法使用该方式登录', {btn: ['确定', '取消']}, function(index){
    layer.close(index);
    var loading = layer.load(2, {shade:[0.1,'#fff']});
    $.post('ajax.php?act=unbind_account', {type: type, csrf_token: CSRF_TOKEN}, function(data){
      layer.close(loading);
      if(data.code == 0){
        layer.msg('解绑成功');
        setTimeout(function(){ location.reload(); }, 1000);
      } else {
        layer.alert(data.msg || '解绑失败', {icon: 7});
      }
    }, 'json').fail(function(){
      layer.close(loading);
      layer.msg('服务器错误');
    });
  });
}

// 批量重命名
function batchRename(){
  var rows = $('#listTable').bootstrapTable('getSelections');
  if(rows.length == 0){
    layer.msg('请先选择文件');
    return;
  }

  layer.open({
    type: 1,
    title: '批量重命名',
    area: ['400px', '320px'],
    content: '<div style="padding:20px;">'+
      '<div class="form-group">'+
      '<label>文件名前缀</label>'+
      '<input id="rename_prefix" class="form-control" placeholder="例如：我的文件-">'+
      '</div>'+
      '<div class="form-group">'+
      '<label>起始编号</label>'+
      '<input id="rename_start" class="form-control" value="1" type="number">'+
      '</div>'+
      '<div style="margin:15px 0;">'+
      '<label style="font-weight:normal;cursor:pointer;">'+
      '<input type="checkbox" id="rename_keep_ext" checked style="margin-right:8px;"> 保留原始扩展名'+
      '</label>'+
      '</div>'+
      '<button class="btn btn-primary btn-block" id="btn_rename_submit">开始重命名</button>'+
      '</div>',
    success: function(){
      $('#btn_rename_submit').on('click', function(){
        var prefix = $('#rename_prefix').val() || '';
        var start = parseInt($('#rename_start').val(), 10) || 1;
        var keepExt = $('#rename_keep_ext').is(':checked');

        var loading = layer.load(2);
        var success = 0, failed = 0;

        (async function(){
          for(var i = 0; i < rows.length; i++){
            var oldName = rows[i].name || '';
            var ext = '';
            if(keepExt && oldName.lastIndexOf('.') > 0){
              ext = oldName.substring(oldName.lastIndexOf('.'));
            }
            var newName = prefix + (start + i) + ext;

            try{
              await $.post('ajax.php?act=user_rename', {
                hash: rows[i].hash,
                name: newName,
                csrf_token: CSRF_TOKEN
              }, function(data){
                if(data.code == 0) success++;
                else failed++;
              }, 'json');
            } catch(e){
              failed++;
            }
          }
          layer.close(loading);
          $('#listTable').bootstrapTable('refresh');
          layer.alert('批量重命名完成！\n成功: ' + success + ' 个\n失败: ' + failed + ' 个', {icon: 1});
          layer.closeAll();
        })();
      });
    }
  });
}
</script>
