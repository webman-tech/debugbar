# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## 项目概述

[PHP Debugbar](http://phpdebugbar.com/) for webman，为 webman 应用提供调试工具栏。

**核心功能**：
- **零配置启动**：安装后直接访问 `/debugbar/sample` 即可
- **仅 debug 模式启用**：生产环境自动关闭
- **API 支持**：API 请求可在 debugbar 历史记录查看
- **丰富数据收集**：请求、响应、时间线、SQL 查询等

## 开发命令

测试、静态分析等通用命令与根项目一致，详见根目录 [CLAUDE.md](../../CLAUDE.md)。

## 项目架构

### 核心组件
- **DebugBar**：主入口类
- **DataCollector**：各种数据收集器

### 目录结构
- `src/`：源代码
- `copy/`：配置文件模板
- `src/Install.php`：Webman 安装脚本

测试文件位于项目根目录的 `tests/Unit/Debugbar/`。

## 代码风格

与根项目保持一致，详见根目录 [CLAUDE.md](../../CLAUDE.md)。

## 注意事项

1. **仅开发环境**：只在 debug 模式下启用
2. **性能影响**：debug 模式下会收集大量数据，有一定性能开销
3. **API 调试**：API 请求在 debugbar 历史记录中查看
4. **路由冲突**：确保 /debugbar 路由不被占用
5. **测试位置**：单元测试在项目根目录的 `tests/Unit/Debugbar/` 下，而非包内
