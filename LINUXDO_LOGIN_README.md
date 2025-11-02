# Linux Do OAuth2 登录集成说明

本项目已成功集成 Linux Do OAuth2 登录功能。

## 配置步骤

### 1. 在 Linux Do 注册 OAuth2 应用
1. 访问 [Linux Do OAuth2 应用注册页面](https://connect.linux.do/)
2. 创建新的 OAuth2 应用
3. **重要**: 设置回调地址为: `http://你的网站地址/login.php?linuxdo=1`
   - 例如: `http://example.com/login.php?linuxdo=1`
   - 或 HTTPS: `https://example.com/login.php?linuxdo=1`
   - **必须包含** `?linuxdo=1` 参数
   - 地址必须与实际访问地址完全一致（包括 http/https、域名、路径）
4. 获取 `Client ID` 和 `Client Secret`

### 2. 在后台配置
1. 登录网站管理后台
2. 进入 **系统设置 → 用户登录设置** (`admin/set.php?mod=user`)
3. 找到 "Linux Do OAuth2 登录" 配置区域
4. 勾选 "启用 Linux Do 登录"
5. 填入您的 `Client ID` 和 `Client Secret`
6. 点击保存

### 3. 测试登录
1. 退出当前登录
2. 访问登录页面 (`login.php`)
3. 点击"快捷登录"区域的 Linux 图标按钮
4. 使用您的 Linux Do 账号授权登录

### 4. 账号绑定
已登录的用户可以在用户中心绑定 Linux Do 账号：
1. 登录后访问用户中心 (`user.php`)
2. 找到"账号绑定"面板
3. 点击 "Linux Do: 未绑定" 旁的"绑定"按钮
4. 跳转到 Linux Do 授权页面完成绑定
5. 绑定成功后，可以使用该账号登录

## 功能特性

- ✅ Linux Do OAuth2 登录
- ✅ 自动注册新用户
- ✅ 账号绑定/解绑
- ✅ 登录与注册界面分离
- ✅ 密码确认验证
- ✅ 防止重复绑定
- ✅ 至少保留一种登录方式

## 技术实现

### 新增文件
- `includes/lib/LinuxDoOauth.php` - Linux Do OAuth2 集成类

### 修改文件
- `login.php` - 添加 Linux Do 登录/绑定处理逻辑，优化登录界面
- `user.php` - 添加账号绑定面板
- `ajax.php` - 添加 bind_account 和 unbind_account 接口
- `admin/set.php` - 添加配置选项
- `includes/common.php` - 添加默认配置项

### 数据库
用户登录后会在 `pre_user` 表中创建记录:
- `type`: `linuxdo`
- `openid`: Linux Do 用户的唯一标识符 (sub)
- `nickname`: 用户昵称
- `faceimg`: 用户头像 URL

## OAuth2 流程
1. 用户点击登录按钮 → 生成授权 URL
2. 跳转到 Linux Do 授权页面
3. 用户授权后回调到 `login.php?linuxdo=1&code=xxx&state=xxx`
4. 使用授权码获取访问令牌
5. 使用访问令牌获取用户信息
6. 创建/更新用户记录并登录

## 常见问题

### "invalid_grant" 错误
如果遇到 `invalid_grant` 错误，通常是以下原因：

1. **回调地址不匹配** (最常见)
   - 检查 Linux Do 后台配置的回调地址是否与代码中使用的完全一致
   - 错误页面会显示"使用的回调地址"，请与 Linux Do 后台配置对比
   - 注意 http/https、域名、端口、路径都必须完全一致

2. **授权码已使用**
   - OAuth2 授权码只能使用一次
   - 不要刷新回调页面，这会导致重复使用已失效的授权码

3. **授权码过期**
   - 授权码有效期很短（通常几分钟）
   - 如果授权后等待太久才回调，可能会过期

4. **Client ID/Secret 错误**
   - 检查后台配置的 Client ID 和 Secret 是否正确

### 调试建议
- 错误页面会显示详细的调试信息
- 重点检查"使用的回调地址"是否与 Linux Do 后台配置一致
- 如果修改了回调地址，需要重新开始授权流程

## 安全性
- CSRF 防护: 使用 state 参数验证回调请求
- SSL 验证: 生产环境建议启用 SSL 证书验证
- 会话管理: 使用加密 token 存储用户会话
- 授权码单次使用: 防止重放攻击
