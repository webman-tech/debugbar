# kriss/webman-debugbar

[PHP Debugbar](http://phpdebugbar.com/) for webman

## 安装

```bash
composer require kriss/webman-debugbar
```

## 配置

详见： [app.php](src/config/plugin/kriss/webman-debugbar/app.php)

## 使用

安装默认开启（仅 debug 模式下启用），直接访问路由 `{Host}/_debugbar/sample` 即可看到信息

如果是 api 应用，其他接口调用后，可以在 debugbar 的历史记录下看到接口相关的请求
