<?php
include("./includes/common.php");
if(!$islogin2){
    @header('Content-Type: text/html; charset=UTF-8');
    exit("<script>alert('请先登录');window.location.href='./login.php';</script>");
}
$title = '安全设置（2FA） - '.$conf['title'];
include SYSTEM_ROOT.'header.php';
$csrf_token = md5(mt_rand(0,999).time());
$_SESSION['csrf_token'] = $csrf_token;
?>
<div class="container" style="padding-top:40px;">
  <div class="col-xs-12 col-sm-8 col-md-6 col-lg-5 center-block" style="float:none;">
    <div class="panel panel-primary">
      <div class="panel-heading"><h3 class="panel-title">两步验证（TOTP）</h3></div>
      <div class="panel-body">
        <div id="binded" style="display:none;">
          <p>已绑定 2FA。若需更换设备，请先解绑再重新绑定。</p>
          <button class="btn btn-danger" id="btn-disable">解除绑定</button>
        </div>
        <div id="unbound" style="display:none;">
          <p>未绑定 2FA。建议开启以提升账号安全。</p>
          <button class="btn btn-primary" id="btn-init">开始绑定</button>
          <div id="bind-box" style="display:none;margin-top:12px;">
            <div class="text-center"><img id="qr" src="" alt="QR" style="max-width:220px;border:1px solid #eee;padding:6px;"></div>
            <p>或手动输入密钥：<code id="secret"></code></p>
            <div class="form-inline" style="margin-top:6px;">
              <input type="text" id="code" class="form-control" placeholder="输入6位验证码">
              <button class="btn btn-success" id="btn-enable">确认启用</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include SYSTEM_ROOT.'footer.php';?>
<script src="https://s4.zstatic.net/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://s4.zstatic.net/ajax/libs/layer/2.3/layer.js"></script>
<script>
var csrf = '<?php echo $csrf_token?>';
function reload(){
  $.post('ajax.php?act=userTotpStatus', {csrf_token: csrf}, function(d){
    if(d.code==0){
      if(d.binded){ $('#binded').show(); $('#unbound').hide(); }
      else { $('#binded').hide(); $('#unbound').show(); }
    }
  }, 'json');
}
$('#btn-init').click(function(){
  $.post('ajax.php?act=userTotpInit', {csrf_token: csrf}, function(d){
    if(d.code==0){
      $('#bind-box').show();
      $('#secret').text(d.secret);
      $('#qr').attr('src', 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data='+encodeURIComponent(d.otpauth));
    }else{ layer.alert(d.msg,{icon:2}); }
  }, 'json');
});
$('#btn-enable').click(function(){
  $.post('ajax.php?act=userTotpEnable', {csrf_token: csrf, code: $('#code').val()}, function(d){
    if(d.code==0){ layer.alert('已启用',{icon:1}, function(){ location.reload(); }); }
    else { layer.alert(d.msg,{icon:2}); }
  }, 'json');
});
$('#btn-disable').click(function(){
  layer.confirm('确定解除绑定吗？', {btn:['确定','取消']}, function(){
    $.post('ajax.php?act=userTotpDisable', {csrf_token: csrf}, function(d){
      if(d.code==0){ layer.alert('已解除',{icon:1}, function(){ location.reload(); }); }
      else { layer.alert(d.msg,{icon:2}); }
    }, 'json');
  });
});
reload();
</script>
