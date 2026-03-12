# WP Localized Auto Translate

一个用于 WordPress 的自动翻译插件：
- 默认根据访客浏览器语言自动翻译页面内容。
- 支持右下角悬浮语言切换器，手动切换其它语种。
- **只翻译页面内容，不翻译 URL 结构**。

## 安装方式（仅源码目录）
请直接使用插件源码目录 `wp-localized-auto-translate`，不要依赖仓库内预打包 zip。

### 推荐流程
1. 从 GitHub 下载整个仓库 ZIP。
2. 解压后，仅保留 `wp-localized-auto-translate/` 目录。
3. 你自行把该目录重新压缩为 `wp-localized-auto-translate.zip`。
4. 在 WordPress 后台 **插件 → 安装插件 → 上传插件** 上传安装。

## 子语言页面格式说明（/es, /ru）
你提到的页面格式：
- `https://xxx.com/es`
- `https://xxx.com/ru`

当前插件的设计边界是：
- ✅ 自动翻译当前页面内容
- ❌ 不创建 `/{lang}` 子路径页面
- ❌ 不处理 URL 路由重写

因此本插件本身不会自动生成 `/es`、`/ru` 这类路径。如果你的网站必须使用这种 URL 结构，需要额外的路由/多站点/多语言 URL 方案来提供这些页面路径，本插件只负责页面内容翻译层。

## 主插件文件要求（已满足）
插件目录中已包含主插件文件：
- `wp-localized-auto-translate/wp-localized-auto-translate.php`

且文件顶部包含有效 WordPress 插件头（Plugin Name / Description / Version 等）。

## 功能说明
- 浏览器自动语言识别（如德语用户自动匹配 `de-DE` / `de-CH`）
- 地区化语种风格（你可配置：`locale|style`）
  - 示例：
    - `de-DE|Standard German for Germany`
    - `de-CH|Swiss High German conventions and vocabulary`
    - `es-ES|Peninsular Spanish for Spain`
    - `es-MX|Mexican Spanish idioms and formal business tone`
- 悬浮语言切换器（可在后台开关）
- 批量翻译缓存（12 小时 transient）

## 计费 / 免费说明
- **本插件代码本身免费**（你可自行部署和修改）。
- 翻译调用第三方 LLM API，通常是按 token 计费（由你的模型服务商收费）。

## 需要输入什么 API？
当前版本使用 **OpenAI 兼容 Chat Completions API**：
- 在后台填写 `API Key`
- 在后台填写 `Model`（例如 `gpt-4o-mini`）

默认请求地址：
- `https://api.openai.com/v1/chat/completions`

## API 能调用哪些模型？
- 任何该接口可用且账号有权限的模型都可以（例如 OpenAI 兼容模型）。
- 推荐优先使用有较强翻译能力的模型，并通过 `locale|style` 提示词强化本地语言习惯（德国德语/瑞士德语、西班牙西语/拉美西语）。

## 后台设置路径
`Settings → Localized Auto Translate`
