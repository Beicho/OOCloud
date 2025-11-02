# 贡献指南

感谢您考虑为 OOCloud 项目做出贡献！

## 如何贡献

### 报告 Bug

如果您发现了 bug，请在 GitHub Issues 中创建一个新问题，并包含：

- 清晰的问题描述
- 复现步骤
- 预期行为
- 实际行为
- 环境信息（PHP 版本、MySQL 版本、操作系统等）
- 错误日志（如果有）
- 截图（如果适用）

### 提出新功能

欢迎提出新功能建议！请在 Issues 中：

- 描述功能的使用场景
- 说明为什么需要这个功能
- 提供示例或原型（如果有）

### 提交代码

1. **Fork 项目**
   ```bash
   # Fork 到您的账号
   # 然后克隆
   git clone https://github.com/your-username/oocloud.git
   cd oocloud
   ```

2. **创建分支**
   ```bash
   git checkout -b feature/your-feature-name
   # 或
   git checkout -b fix/your-bug-fix
   ```

3. **进行修改**
   - 遵循项目的编码规范
   - 添加必要的注释
   - 确保代码可读性

4. **测试您的更改**
   - 在本地测试所有功能
   - 确保没有破坏现有功能
   - 测试边界情况

5. **提交更改**
   ```bash
   git add .
   git commit -m "feat: 添加新功能描述"
   ```

6. **推送到 GitHub**
   ```bash
   git push origin feature/your-feature-name
   ```

7. **创建 Pull Request**
   - 在 GitHub 上创建 PR
   - 清晰描述您的更改
   - 引用相关的 Issues

## 编码规范

### PHP 代码风格

- 缩进：4 个空格
- 文件编码：UTF-8 without BOM
- 命名规范：
  - 函数/变量：`snake_case`
  - 类名：`StudlyCaps`
  - 常量：`UPPER_CASE`

```php
// 好的示例
function get_user_info($uid) {
    $user_data = $DB->find('user', '*', ['uid' => $uid]);
    return $user_data;
}

class LinuxDoOauth {
    private $clientId;

    public function getAuthUrl() {
        // ...
    }
}
```

### 安全原则

- **永远验证用户输入**
  ```php
  $username = daddslashes($_POST['username']);
  if(!preg_match('/^[A-Za-z0-9_]{3,32}$/', $username)) {
      exit('{"code":-1,"msg":"用户名格式错误"}');
  }
  ```

- **使用 CSRF 令牌**
  ```php
  if(!$_POST['csrf_token'] || $_POST['csrf_token']!=$_SESSION['csrf_token']) {
      exit('{"code":-1,"msg":"CSRF TOKEN ERROR"}');
  }
  ```

- **使用预处理语句**
  ```php
  // 好
  $DB->find('user', '*', ['uid' => $uid]);

  // 避免
  $DB->query("SELECT * FROM pre_user WHERE uid='$uid'");
  ```

- **输出转义**
  ```php
  echo htmlspecialchars($user_input);
  ```

### 提交信息规范

使用以下前缀：

- `feat:` - 新功能
  ```
  feat: 添加 Linux Do OAuth2 登录支持
  ```

- `fix:` - Bug 修复
  ```
  fix: 修复绑定账号时的主键冲突错误
  ```

- `refactor:` - 代码重构
  ```
  refactor: 优化登录流程代码结构
  ```

- `docs:` - 文档更新
  ```
  docs: 更新 API 文档
  ```

- `style:` - 代码格式调整（不影响功能）
  ```
  style: 统一代码缩进格式
  ```

- `chore:` - 构建/工具变动
  ```
  chore: 更新 .gitignore
  ```

## Pull Request 流程

1. 确保 PR 只包含相关的更改
2. 更新相关文档
3. 在 PR 描述中说明：
   - 更改的内容
   - 为什么需要这个更改
   - 如何测试
4. 等待代码审查
5. 根据反馈进行调整

## 代码审查标准

审查者会检查：

- ✅ 代码功能是否正确
- ✅ 是否遵循编码规范
- ✅ 是否有安全隐患
- ✅ 是否有充分的错误处理
- ✅ 代码是否易于维护
- ✅ 是否有必要的注释
- ✅ 是否更新了文档

## 添加新的 OAuth2 登录提供商

如果您想添加新的 OAuth2 登录提供商：

1. **创建 OAuth 类**
   - 在 `includes/lib/` 创建新类
   - 继承或参考 `LinuxDoOauth.php`
   - 实现 `getAuthUrl()` 和 `callback()` 方法

2. **修改登录处理**
   - 在 `login.php` 添加回调处理
   - 支持登录和绑定两种模式

3. **添加 AJAX 接口**
   - 在 `ajax.php` 添加 `bind_account` 处理

4. **添加后台配置**
   - 在 `admin/set.php` 添加配置选项
   - 在 `includes/common.php` 添加默认配置

5. **更新文档**
   - 在 `README.md` 中添加配置说明
   - 创建详细的配置文档

6. **测试**
   - 测试登录流程
   - 测试绑定/解绑
   - 测试错误处理

## 常见问题

**Q: 如何设置开发环境？**

A: 参考 README.md 中的"本地开发"部分。

**Q: 修改后如何测试？**

A: 使用 PHP 内置服务器或配置 Apache/Nginx 本地环境。

**Q: 发现安全漏洞怎么办？**

A: 请不要公开提交 Issue，而是私下联系项目维护者。

## 社区行为准则

- 尊重所有贡献者
- 接受建设性的批评
- 关注对社区最有利的事情
- 对其他社区成员表示同理心

## 获得帮助

- 查看现有的 Issues 和 Pull Requests
- 阅读项目文档
- 在 Issues 中提问

## 许可证

通过贡献代码，您同意您的贡献将采用 Apache License 2.0 许可。

---

感谢您的贡献！🎉
