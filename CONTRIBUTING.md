# 贡献指南（Contributing）

感谢你愿意为 OOCloud 做出贡献！为了让协作顺畅、高效，请先阅读本指南。

## 行为准则
- 尊重、务实、可复现；讨论以问题为中心，避免人身化。
- 参与即默认遵守本仓库的行为准则与开源精神（友善、包容、透明）。

## 如何提交 Issue
在创建 Issue 前请先搜索是否已有相同问题或提案。

- Bug 报告请包含：
  - 复现步骤（尽可能最小复现）
  - 实际结果 vs 预期结果
  - 运行环境（PHP 版本、Web 服务器、数据库版本、存储类型）
  - 相关日志/截图（敏感信息打码）
- 功能建议请描述：
  - 使用场景与动机
  - 期望的行为与边界
  - 兼容性与风险（如是否涉及数据库/配置变更）
- 安全问题：请不要在公开 Issue 中披露细节，参见 [SECURITY.md](./SECURITY.md)

## 开发环境与运行
- 要求：PHP ≥ 7.1、MySQL ≥ 5.5，建议启用 `curl`、`ZipArchive`
- 快速体验（开发用，可选）
  - 使用 Nginx/Apache 最佳；也可尝试 `php -S 127.0.0.1:8080 -t .`（PATH_INFO 需支持）
  - 浏览器访问根目录进入安装向导
- 目录概览：
  - `admin/` 后台页面与接口（含自更新）
  - `assets/` 前端静态资源
  - `includes/` 公共库/配置/存储驱动
  - `install/` 安装与数据库升级脚本
  - `file/` 本地存储目录（受 .htaccess 保护）

## 提交 Pull Request（PR）
基本流程：Fork → 新建分支 → 提交改动 → 提 PR 到 `main`。

- 分支命名：`feature/<摘要>`、`fix/<摘要>`、`docs/<摘要>`、`chore/<摘要>` 等
- 提交信息：建议遵循 Conventional Commits（示例：`feat(admin): add one‑click self‑update`）
- 变更要求：
  - 兼容性：避免破坏现有行为；涉及重大变更请先开 Issue 讨论
  - 数据库：如需新增/变更字段，请在 `install/update.php` 编写升级逻辑，并考虑回滚/幂等
  - 配置项：通过 `ensureConf('key', 'default')` 提供默认值，避免升级期崩溃
  - 存储适配器：实现 `includes/lib/IStorage.php` 接口，文件放入 `includes/lib/Storage/`
  - 文档：更新 `README.md` 或新增 `docs/xxx.md` 说明用法/风险
  - 前端：尽量保持简洁，不引入重依赖；图片/Logo 使用 `assets/`
- 自检清单（PR 模板也会包含）：
  - [ ] 变更说明充分、影响面清晰
  - [ ] 无敏感信息（密钥、内网地址）
  - [ ] 安全与权限校验完善
  - [ ] 安装/升级/降级路径明确
  - [ ] 文档/界面文案已更新

## 版本与发布
- 版本号：`includes/common.php` 中 `VERSION`
- 发版：提交版本号变更 → 打 Tag（`v<version>`）→ 创建 Release（可生成说明）
- 后台“检查更新”基于 GitHub Releases/Tags；亦可用后台“一键自更新”或独立脚本 `oocloud_updater.php`

## 风险与回滚
- 更新前建议备份（后台/脚本会在 `backup/update-YYYYmmdd-HHMMSS` 生成备份）
- 若出现问题，可用备份回滚覆盖；若涉及数据库变更，需手动回退

## 联系方式
- 项目主页与 Issue：https://github.com/Beicho/OOCloud
- 安全问题：请阅读 [SECURITY.md](./SECURITY.md)

欢迎任何形式的贡献：Bug 反馈、文档改进、代码提交、教程/文章分享等。谢谢！

