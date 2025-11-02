# OOCloud

[![Release](https://img.shields.io/github/v/release/Beicho/OOCloud?sort=semver)](https://github.com/Beicho/OOCloud/releases)
[![License](https://img.shields.io/badge/license-Apache--2.0-blue)](./LICENSE)

OOCloud 是一个基于 PHP 的轻量网盘 / 外链分享服务，支持常见文件上传、生成直链与 UBB/HTML 代码，支持文本/图片/音频/视频在线预览，适配多种对象存储（阿里云 OSS、腾讯云 COS、华为云 OBS、又拍云、七牛云等），可选接入图片违规检测。

本项目为“彩虹外链网盘 (cccyun)”的二次开发与重命名版本。向原作者与社区致敬！

## 演示地址
- https://cloud.224442.xyz/

## 快速开始
- 环境要求：`PHP >= 7.1`、`MySQL >= 5.5`
- 将项目上传到站点根目录，浏览器访问根地址，按安装引导完成初始化
- 初始后台账号密码：`admin / 123456`

## 特性一览
- 多存储支持：阿里云 OSS、腾讯云 COS、华为云 OBS、又拍云、七牛云、本地存储等
- 在线预览：文本/图片/音频/视频 文件在线预览
- 外链分享：生成直链、UBB/HTML 代码，便于论坛/博客嵌入
- 用户系统：可选开启登录；保留上传记录；支持第三方登录
- 安全控制：可配置 IP 黑名单、图片违规检测、登录限制等

## 对比上游新增/改进
与上游“彩虹外链网盘 (cccyun)”相比，OOCloud 在保持兼容的基础上新增/改进了以下能力：

1) 品牌与 UI
- 统一品牌与视觉：新增 SVG Logo 与 SVG Favicon；细化导航条与动效
- 后台/前台可自定义 Logo 与 Favicon（系统设置 → 网站信息设置）
- 导航与图标尺寸优化：移动端响应式缩放、禁用导航悬停放大，避免布局抖动

2) 更新与运维
- 后台“检查更新”改为从 GitHub Releases/Tags 获取，去除对上游服务器依赖
- 新增“一键自更新”按钮：后台首页 → 版本信息，可下载最新包、备份并覆盖更新
- 提供独立自更新脚本 `oocloud_updater.php`（适配旧版环境）

3) 登录与安全（可选）
- 集成 Linux Do OAuth2 登录（可在后台“用户登录设置”开启并配置）
- 支持本地用户密码重置接口（仅限本地用户）
- 预置可选的本地 2FA 字段（TOTP，接口层已准备，后续提供界面）
- 可选“隐藏用户 IP（脱敏显示）”配置项

4) 兼容性与稳定性
- 数据库结构自修复：在部分配置/字段缺失时自动补齐必要列（如 openapi123、用户扩展）
- 默认配置守护：未设置项按需写入默认值，减少安装/升级陷阱
- 更合理的 .gitignore，避免意外提交本地密钥/临时文件

## 安装与配置
1) 上传全部文件到站点根目录
2) 浏览器访问根地址，按引导完成安装
3) 完成后可在后台进行以下配置：
   - 网站信息设置：站点标题、关键字、描述、公告、黑名单等
   - 品牌外观：前台 Logo、后台 Logo、Favicon（支持站内路径或绝对 URL）
   - 用户登录设置：聚合登录 / Linux Do 登录开关与凭据
   - 存储类型设置：配置 OSS/COS/OBS/Upyun/Qiniu 等对象存储
   - 文件上传设置：大小限制、直链策略等

## 更新与发布
- 稳定版本与更新说明见 Releases：https://github.com/Beicho/OOCloud/releases
- 后台自更新：后台首页 → 版本信息 → 一键更新
- 独立自更新脚本（适配旧版）：`oocloud_updater.php`
  - 上传到站点根目录后访问执行，更新完成建议删除该脚本

> 数据库版本升级：如果更新中涉及数据库变更，更新后访问 `/install/update.php` 进行升级

## 常见问题
- 后台一键更新失败？
  - 检查 PHP 是否启用 ZipArchive 扩展
  - 检查站点目录是否具备写权限（含 `tmp/`、`backup/` 等）
  - 可退而使用独立脚本 `oocloud_updater.php`
- Logo/Favicon 未生效？
  - 清空浏览器缓存；或尝试使用绝对路径（以 `/` 开头）
- Linux Do 登录配置？
  - 在后台“用户登录设置”启用 Linux Do 登录并填入 Client ID/Secret

## 目录结构（简要）
- `admin/` 后台页面与接口（含自更新接口）
- `assets/` 静态资源（CSS/JS/图片）
- `includes/` 公共库/配置/存储驱动
- `install/` 安装与数据库升级
- `file/` 本地存储目录（受 .htaccess 保护）

## 贡献
欢迎提交 Issue 与 PR！建议在 PR 中附带：变更说明、测试方法、可能影响范围。

## 许可与致谢
- 许可证：Apache-2.0，详见 `LICENSE`
- 致谢上游与第三方 SDK，详见 `NOTICE`
- 上游项目：
  - 彩虹外链网盘：https://pan.cccyun.cc/
  - 作者博客：https://blog.cccyun.cn/
