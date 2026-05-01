### `libretranslate-proxy`代理[LibreTranslate](https://github.com/LibreTranslate/LibreTranslate)本地翻译给沉浸式翻译使用

- 配置插件

1.插件图标 → 设置 → 左侧下滑找到【开发者设置】→ 开启【启用 Beta 测试特性】→ 保存并刷新插件页面

2.左侧 翻译服务 → 点击【添加自定义翻译服务】→ 选择【自定义API】(只配`http://127.0.0.1:50116/translate`地址,参数默认)

- 立即使用

```shell
docker compose up -d
```