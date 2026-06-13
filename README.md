# zibll-linuxdo-connect

> 子比主题（Zibll）× Linux DO OAuth 登录集成插件

[![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-blue?logo=wordpress)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple?logo=php)](https://php.net)
[![License](https://img.shields.io/badge/License-GPL%20v2-green)](LICENSE)

---

## 简介

`zibll-linuxdo-connect` 是一个为 **子比（Zibll）主题** 量身定制的功能扩展插件，通过 **Linux DO** 社区的 OAuth 2.0 接口，让用户可以直接使用 Linux DO 账号登录你的 WordPress 站点。

插件会把 Linux DO 登录完整对接到子比主题自带的「第三方登录」体系：新用户是「自动创建」还是「自行绑定 / 创建」，完全跟随子比主题后台的「新用户绑定模式」开关，无需在本插件里重复设置。

适合以 Linux DO 社区为目标用户群的个人博客或技术站点使用。

---

## 功能特性

- ✅ 一键通过 Linux DO 账号登录 / 注册
- ✅ 自动同步用户昵称、头像
- ✅ 与子比主题登录弹窗无缝集成，登录按钮自动注入，无需修改主题模板
- ✅ 已登录用户可绑定 / 解绑 Linux DO 账号（复用子比的绑定面板）
- ✅ 同时在 WordPress 原生登录页（wp-login.php）注入登录入口

---

## 环境要求

| 依赖 | 版本要求 |
|------|----------|
| WordPress | 5.8 + |
| PHP | 7.4 + |
| 子比主题（Zibll） | 最新版 |
| Linux DO OAuth 应用 | 需自行申请 |

> 本插件依赖子比主题的 `zib_oauth_update_user()` 等核心函数，未安装子比主题时第三方登录将无法工作。

---

## 安装方法

### 方式一：直接下载

1. 前往 [Releases](../../releases) 页面下载最新 `.zip` 文件
2. 进入 WordPress 后台 → 插件 → 安装插件 → 上传插件
3. 启用插件

### 方式二：Git 克隆

```bash
cd wp-content/plugins/
git clone https://github.com/loneshu7/zibll-linuxdo-connect.git
```

然后在 WordPress 后台启用插件。

---

## 配置说明

### 第一步：申请 Linux DO OAuth 应用

1. 前往 [Linux DO Connect](https://connect.linux.do) 创建应用
2. 回调地址（Redirect URI）填写：
   `https://你的域名/wp-json/linuxdo-login/v1/callback`
3. 获取 `Client ID` 和 `Client Secret`

> 后台设置页顶部会直接显示当前站点对应的回调地址，复制粘贴即可，无需手动拼接。

### 第二步：填写插件设置

进入 WordPress 后台 → **设置 → Linux DO 登录**，填入：

```
Client ID:     xxxxxxxxxxxxxxxx
Client Secret: xxxxxxxxxxxxxxxx
```

其余的授权地址 / Token 地址 / 用户信息地址 / Scope / 按钮标题均有默认值，一般无需改动。

### 第三步：检查子比主题集成

插件会自动在子比主题的登录弹窗（`.social_loginbar`）中注入「Linux DO 登录」按钮，无需额外操作。

新用户的处理方式由子比主题决定，前往：
**子比主题设置 → 用户&互动 → 第三方登录 → 新用户绑定模式**
- `自动创建新用户`：首次登录自动注册并登录
- `用户自行绑定或创建新用户`：跳转到绑定页，由用户手动绑定已有账号或创建新账号

---

## 工作原理

整个插件是单文件实现（`zibll-linuxdo-connect.php`），主要逻辑如下：

- 注册两个 REST 路由：
  - `GET /wp-json/linuxdo-login/v1/start` —— 发起授权，跳转到 Linux DO
  - `GET /wp-json/linuxdo-login/v1/callback` —— 接收回调，换取 token、拉取用户信息
- 用 `state`（transient，10 分钟有效）防 CSRF 并保存登录前地址
- 拉到用户信息后标准化为子比要求的 `oauth_data`，交给 `zib_oauth_update_user()` 完成登录 / 绑定 / 注册
- 前台样式与脚本均为内联注入，按钮样式基于 `assets/linuxdo-logo.jpg`

---

## 常见问题

**Q: 登录后头像不显示？**
A: 请确认子比主题版本为最新，部分旧版本不支持外部头像源。

**Q: 回调地址报 404？**
A: 进入 WordPress 后台 → 设置 → 固定链接，点一次「保存更改」刷新重写规则。

**Q: 已有账号能绑定 Linux DO 吗？**
A: 可以。在已登录状态下，通过子比的第三方账号绑定面板即可完成关联。

**Q: 回调一直提示「登录状态已过期」？**
A: `state` 有效期为 10 分钟，请在发起登录后尽快完成授权；若服务器时间异常或 transient 被频繁清理也可能导致此问题。

---

## 贡献

欢迎提交 Issue 和 Pull Request！

1. Fork 本仓库
2. 创建你的分支：`git checkout -b feature/xxx`
3. 提交更改：`git commit -m 'feat: 添加 xxx'`
4. 推送分支：`git push origin feature/xxx`
5. 发起 Pull Request

---

## License

本项目基于 [GNU General Public License v2.0](LICENSE) 开源发布，与 WordPress 生态协议保持一致。
