## 项目概述

[PHP Debugbar](http://phpdebugbar.com/) for webman，为 webman 应用提供调试工具栏。

**核心功能**：
- **零配置启动**：安装后直接访问 `/debugbar/sample` 即可
- **仅 debug 模式启用**：生产环境自动关闭
- **API 支持**：API 请求可在 debugbar 历史记录查看
- **丰富数据收集**：请求、响应、时间线、SQL 查询等

## 开发命令

测试、静态分析等通用命令与根项目一致，详见根目录 [AGENTS.md](../../AGENTS.md)。

## 目录结构
- `src/`：
  - `WebmanDebugBar.php`：Webman 适配入口
  - `DebugBar.php`：核心 DebugBar 类
  - `DataCollector/`：各类数据收集器（Request/Route/Session/Time/Memory/SQL/Redis 等）
  - `Bootstrap/`：启动钩子（Laravel Query/Redis 监听）
  - `Middleware/`：DebugBarMiddleware
  - `Storage/`：FileStorage/AutoCleanFileStorage
  - `Laravel/`：Laravel 兼容层
  - `Traits/`：DebugBarOverwrite
- `copy/`：配置文件模板
- `src/Install.php`：Webman 安装脚本

测试文件位于项目根目录的 `tests/Unit/Debugbar/`。测试环境配置和 Helper 函数详见根目录 [AGENTS.md](../../AGENTS.md) 的测试相关章节。

## 代码风格

与根项目保持一致，详见根目录 [AGENTS.md](../../AGENTS.md)。

## 注意事项

1. **仅开发环境**：只在 debug 模式下启用
2. **性能影响**：debug 模式下会收集大量数据，有一定性能开销
3. **API 调试**：API 请求在 debugbar 历史记录中查看
4. **路由冲突**：确保 /debugbar 路由不被占用
