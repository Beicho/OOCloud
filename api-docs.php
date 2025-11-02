<?php
include("./includes/common.php");
$title = 'API 文档 - '.$conf['title'];
include SYSTEM_ROOT.'header.php';
?>
<style>
.endpoint {
  background: #f8f9fa;
  padding: 15px;
  border-left: 4px solid #2196F3;
  margin: 20px 0;
}
.endpoint h4 {
  margin-top: 0;
  color: #2196F3;
}
.method {
  display: inline-block;
  padding: 3px 8px;
  border-radius: 3px;
  font-weight: bold;
  margin-right: 10px;
}
.method.get { background: #61affe; color: white; }
.method.post { background: #49cc90; color: white; }
.method.put { background: #fca130; color: white; }
.method.delete { background: #f93e3e; color: white; }
pre {
  background: #272822;
  color: #f8f8f2;
  padding: 15px;
  border-radius: 5px;
  overflow-x: auto;
}
.param-table {
  margin: 10px 0;
}
</style>
<div class="container" style="padding-top:20px;">
  <div class="panel panel-primary">
    <div class="panel-heading"><h3 class="panel-title">🚀 OOCloud RESTful API 文档</h3></div>
    <div class="panel-body">
      <h3>📌 基本信息</h3>
      <p><strong>API 基础地址：</strong><code><?php echo $siteurl?>api.php</code></p>
      <p><strong>版本：</strong>v1.0</p>
      <p><strong>响应格式：</strong>JSON</p>

      <h3>🔐 鉴权方式</h3>
      <p>所有接口都需要 API Token 鉴权，支持两种方式：</p>
      <ol>
        <li><strong>Header 方式（推荐）：</strong><code>Authorization: Bearer &lt;your_token&gt;</code></li>
        <li><strong>Query 参数：</strong><code>?token=&lt;your_token&gt;</code></li>
      </ol>
      <p class="text-muted">💡 你可以在"用户中心"生成 API Token</p>

      <hr>

      <!-- API 列表 -->
      <div class="endpoint">
        <h4><span class="method get">GET</span>API 文档</h4>
        <p><strong>路径：</strong><code>/api.php</code></p>
        <p><strong>说明：</strong>获取所有可用的 API 端点列表</p>
        <p><strong>示例：</strong></p>
<pre>curl <?php echo $siteurl?>api.php</pre>
      </div>

      <div class="endpoint">
        <h4><span class="method post">POST</span>上传文件</h4>
        <p><strong>路径：</strong><code>/api.php/upload</code></p>
        <p><strong>Content-Type：</strong><code>multipart/form-data</code></p>
        <p><strong>参数：</strong></p>
        <table class="table table-bordered param-table">
          <thead>
            <tr><th>参数名</th><th>类型</th><th>必填</th><th>说明</th></tr>
          </thead>
          <tbody>
            <tr><td>file</td><td>File</td><td>是</td><td>上传的文件</td></tr>
            <tr><td>name</td><td>String</td><td>否</td><td>自定义文件名（默认使用原文件名）</td></tr>
            <tr><td>show</td><td>Integer</td><td>否</td><td>1=首页显示，0=隐藏（默认1）</td></tr>
            <tr><td>ispwd</td><td>Integer</td><td>否</td><td>是否设置密码（1=是，0=否）</td></tr>
            <tr><td>pwd</td><td>String</td><td>否</td><td>下载密码（字母数字）</td></tr>
          </tbody>
        </table>
        <p><strong>示例：</strong></p>
<pre>curl -X POST \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "file=@/path/to/file.jpg" \
  -F "name=myfile.jpg" \
  -F "show=1" \
  <?php echo $siteurl?>api.php/upload</pre>
        <p><strong>响应：</strong></p>
<pre>{
  "code": 0,
  "exists": 0,
  "id": 123,
  "hash": "abc123def456...",
  "name": "myfile.jpg",
  "size": 102400,
  "type": "jpg",
  "download_url": "<?php echo $siteurl?>api.php/download/abc123..."
}</pre>
      </div>

      <div class="endpoint">
        <h4><span class="method get">GET</span>获取文件列表</h4>
        <p><strong>路径：</strong><code>/api.php/files</code></p>
        <p><strong>参数：</strong></p>
        <table class="table table-bordered param-table">
          <thead>
            <tr><th>参数名</th><th>类型</th><th>必填</th><th>说明</th></tr>
          </thead>
          <tbody>
            <tr><td>page</td><td>Integer</td><td>否</td><td>页码（默认1）</td></tr>
            <tr><td>limit</td><td>Integer</td><td>否</td><td>每页数量（默认20，最大100）</td></tr>
          </tbody>
        </table>
        <p><strong>示例：</strong></p>
<pre>curl -H "Authorization: Bearer YOUR_TOKEN" \
  "<?php echo $siteurl?>api.php/files?page=1&limit=20"</pre>
        <p><strong>响应：</strong></p>
<pre>{
  "code": 0,
  "total": 150,
  "page": 1,
  "limit": 20,
  "files": [
    {
      "id": 123,
      "hash": "abc123...",
      "name": "myfile.jpg",
      "size": 102400,
      "type": "jpg",
      "addtime": "2025-01-01 12:00:00",
      "download_url": "<?php echo $siteurl?>api.php/download/abc123...",
      "has_password": false
    }
  ]
}</pre>
      </div>

      <div class="endpoint">
        <h4><span class="method get">GET</span>获取文件详情</h4>
        <p><strong>路径：</strong><code>/api.php/file/{hash}</code></p>
        <p><strong>说明：</strong>获取单个文件的详细信息</p>
        <p><strong>示例：</strong></p>
<pre>curl -H "Authorization: Bearer YOUR_TOKEN" \
  <?php echo $siteurl?>api.php/file/abc123def456...</pre>
        <p><strong>响应：</strong></p>
<pre>{
  "code": 0,
  "file": {
    "id": 123,
    "hash": "abc123...",
    "name": "myfile.jpg",
    "size": 102400,
    "type": "jpg",
    "addtime": "2025-01-01 12:00:00",
    "lasttime": "2025-01-02 15:30:00",
    "count": 10,
    "download_url": "<?php echo $siteurl?>api.php/download/abc123...",
    "view_url": "<?php echo $siteurl?>file.php?hash=abc123...",
    "has_password": false
  }
}</pre>
      </div>

      <div class="endpoint">
        <h4><span class="method get">GET</span>下载文件</h4>
        <p><strong>路径：</strong><code>/api.php/download/{hash}</code></p>
        <p><strong>参数：</strong></p>
        <table class="table table-bordered param-table">
          <thead>
            <tr><th>参数名</th><th>类型</th><th>必填</th><th>说明</th></tr>
          </thead>
          <tbody>
            <tr><td>pwd</td><td>String</td><td>否</td><td>文件密码（如果文件有密码保护）</td></tr>
          </tbody>
        </table>
        <p><strong>示例：</strong></p>
<pre>curl -H "Authorization: Bearer YOUR_TOKEN" \
  "<?php echo $siteurl?>api.php/download/abc123?pwd=password" \
  -o myfile.jpg</pre>
        <p class="text-muted">💡 本地存储直接下载文件，云存储返回重定向链接</p>
      </div>

      <div class="endpoint">
        <h4><span class="method put">PUT</span>重命名文件</h4>
        <p><strong>路径：</strong><code>/api.php/rename/{hash}</code></p>
        <p><strong>Content-Type：</strong><code>application/json</code></p>
        <p><strong>参数：</strong></p>
        <table class="table table-bordered param-table">
          <thead>
            <tr><th>参数名</th><th>类型</th><th>必填</th><th>说明</th></tr>
          </thead>
          <tbody>
            <tr><td>name</td><td>String</td><td>是</td><td>新文件名</td></tr>
          </tbody>
        </table>
        <p><strong>示例：</strong></p>
<pre>curl -X PUT \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name":"newname.jpg"}' \
  <?php echo $siteurl?>api.php/rename/abc123...</pre>
        <p><strong>响应：</strong></p>
<pre>{"code": 0, "msg": "success", "name": "newname.jpg"}</pre>
      </div>

      <div class="endpoint">
        <h4><span class="method delete">DELETE</span>删除文件</h4>
        <p><strong>路径：</strong><code>/api.php/delete/{hash}</code></p>
        <p><strong>说明：</strong>永久删除文件（包括存储中的文件）</p>
        <p><strong>示例：</strong></p>
<pre>curl -X DELETE \
  -H "Authorization: Bearer YOUR_TOKEN" \
  <?php echo $siteurl?>api.php/delete/abc123...</pre>
        <p><strong>响应：</strong></p>
<pre>{"code": 0, "msg": "deleted"}</pre>
      </div>

      <div class="endpoint">
        <h4><span class="method post">POST</span>生成签名链接</h4>
        <p><strong>路径：</strong><code>/api.php/sign</code></p>
        <p><strong>Content-Type：</strong><code>application/json</code></p>
        <p><strong>说明：</strong>生成带时效和次数限制的下载链接，无需 token 即可下载</p>
        <p><strong>参数：</strong></p>
        <table class="table table-bordered param-table">
          <thead>
            <tr><th>参数名</th><th>类型</th><th>必填</th><th>说明</th></tr>
          </thead>
          <tbody>
            <tr><td>hash</td><td>String</td><td>是</td><td>文件哈希</td></tr>
            <tr><td>expires_in</td><td>Integer</td><td>否</td><td>有效期（秒，默认3600）</td></tr>
            <tr><td>max_downloads</td><td>Integer</td><td>否</td><td>最大下载次数（默认1）</td></tr>
          </tbody>
        </table>
        <p><strong>示例：</strong></p>
<pre>curl -X POST \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"hash":"abc123...","expires_in":3600,"max_downloads":5}' \
  <?php echo $siteurl?>api.php/sign</pre>
        <p><strong>响应：</strong></p>
<pre>{
  "code": 0,
  "download_url": "<?php echo $siteurl?>down.php/abc123.jpg?t=sign&e=timestamp...",
  "expires_at": "2025-01-01 13:00:00",
  "max_downloads": 5
}</pre>
      </div>

      <div class="endpoint">
        <h4><span class="method get">GET</span>获取用户信息</h4>
        <p><strong>路径：</strong><code>/api.php/user</code></p>
        <p><strong>说明：</strong>获取当前用户的基本信息</p>
        <p><strong>示例：</strong></p>
<pre>curl -H "Authorization: Bearer YOUR_TOKEN" \
  <?php echo $siteurl?>api.php/user</pre>
        <p><strong>响应：</strong></p>
<pre>{
  "code": 0,
  "user": {
    "uid": 1,
    "nickname": "用户昵称",
    "type": "qq",
    "level": 0,
    "enable": 1
  }
}</pre>
      </div>

      <div class="endpoint">
        <h4><span class="method get">GET</span>获取配额信息</h4>
        <p><strong>路径：</strong><code>/api.php/quota</code></p>
        <p><strong>说明：</strong>获取存储使用情况和限制</p>
        <p><strong>示例：</strong></p>
<pre>curl -H "Authorization: Bearer YOUR_TOKEN" \
  <?php echo $siteurl?>api.php/quota</pre>
        <p><strong>响应：</strong></p>
<pre>{
  "code": 0,
  "quota": {
    "total_files": 150,
    "total_size": 524288000,
    "total_size_mb": 500.00,
    "today_files": 5,
    "upload_limit": 100,
    "upload_size_limit_mb": 200
  }
}</pre>
      </div>

      <hr>
      <h3>⚠️ 错误码说明</h3>
      <table class="table table-bordered">
        <thead>
          <tr><th>状态码</th><th>说明</th></tr>
        </thead>
        <tbody>
          <tr><td>0</td><td>成功</td></tr>
          <tr><td>-1</td><td>业务错误（详见 msg 字段）</td></tr>
          <tr><td>401</td><td>未授权（缺少或无效 token）</td></tr>
          <tr><td>403</td><td>禁止访问（密码错误等）</td></tr>
          <tr><td>404</td><td>资源不存在</td></tr>
          <tr><td>405</td><td>请求方法不允许</td></tr>
        </tbody>
      </table>

      <div class="alert alert-info">
        <strong>💡 提示：</strong>
        <ul>
          <li>所有接口响应格式统一为 JSON</li>
          <li>上传单文件大小受管理员配置限制</li>
          <li>每日上传次数可能受 IP 或用户级别限制</li>
          <li>云存储下载链接有效期取决于存储提供商</li>
        </ul>
      </div>
    </div>
  </div>
</div>
<?php include SYSTEM_ROOT.'footer.php';?>
