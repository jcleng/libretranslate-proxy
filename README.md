# 推荐[kiss-translator](https://github.com/fishjar/kiss-translator) + [LibreTranslate](https://github.com/LibreTranslate/LibreTranslate)本地翻译

配置url: `http://127.0.0.1:5000/translate`

```js
// request hook
async (args) => {
	console.log(JSON.stringify(args));
	const url = args.url;
	const method = "POST";
	const headers = { "Content-type": "application/json" };
	const body = ({
		q: args.texts[0],
		source: "auto",
		target: ["zh-CN", "zh-TW"].includes(args.toLang) ? 'zh' : args.toLang,
		format: "text",
		alternatives: 3,
		api_key: ""
	})
	return { url, body, headers, method };
};

// response hook
async ({ res }) => {
  return { translations: [[res?.translatedText]] };
};

```

下面的不推荐

### `libretranslate-proxy`代理[LibreTranslate](https://github.com/LibreTranslate/LibreTranslate)本地翻译给沉浸式翻译使用

- 配置插件

1.插件图标 → 设置 → 左侧下滑找到【开发者设置】→ 开启【启用 Beta 测试特性】→ 保存并刷新插件页面

2.左侧 翻译服务 → 点击【添加自定义翻译服务】→ 选择【自定义API】(只配`http://127.0.0.1:50116/translate`地址,参数默认)

- 立即使用

```shell
docker compose up -d
```
- Tencent-Hunyuan 腾讯Hunyuan本地模型翻译

```yml
  models_hunyuan: # https://www.modelscope.cn/models/Tencent-Hunyuan/Hy-MT2-1.8B-GGUF/files
    image: registry.cn-hangzhou.aliyuncs.com/jcleng/llama-server:pr22836 #镜像包
    container_name: models_hunyuan
    restart: unless-stopped
    volumes:
      - ./models_hunyuan:/models #模型目录
    ports:
      - "4280:8080"
    command: >
      -m /models/Hy-MT2-1.8B-Q4_K_M.gguf
      -c 2048
      -t 8
      -tb 8
      --kv-unified
      --host 0.0.0.0
      --parallel 4
      --cont-batching
```

- php版本

```shell

PHP_CLI_SERVER_WORKERS=20 php -S 0.0.0.0:8089 router_llama.php
# 配置地址
http://127.0.0.1:8089/translate

```
