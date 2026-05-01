# -*- coding: utf-8 -*-
import os
import requests
from flask import Flask, request, jsonify
from flask_cors import CORS

# ===================== 全局核心配置=====================
# 从环境变量获取 LIBRE_URL，默认值为 http://172.17.0.1:5000/translate
LIBRE_URL = os.environ.get('LIBRE_URL', 'http://172.17.0.1:5000/translate')
PROXY_PORT = 50116
MAX_TEXT_LENGTH = 5000                          # 文本长度上限
TOKEN_CHECK = False                             # True=强制验token  False=忽略所有token检查

app = Flask(__name__)
CORS(app, resources=r'/*')  # 全网跨域放行，浏览器插件必开

# 语言码清洗适配【仅英中专用】，精简高效
def clean_lang_code(lang):
    if not lang:
        return "auto"
    lang = lang.strip().lower()
    if lang in ["zh-cn", "zh-tw", "zh"]:
        return "zh"
    elif lang == "en":
        return "en"
    else:
        return "auto"

# 核心翻译接口
@app.route('/translate', methods=['POST'])
def translate_proxy():
    # 从请求参数中获取前端传入的token
    client_api_key = request.args.get("token", "")
    api_key_param = ""

    # ===== TOKEN核心校验逻辑 =====
    if TOKEN_CHECK:
        if not client_api_key:  # 空token直接拦截拒绝，返回401未授权+提示语
            return jsonify({"translations": [{"text": "请配置正确的API Token"}],"detected_source_lang": "auto"}), 401
        api_key_param = client_api_key  # 有token则赋值，传给后端做二次校验
    else:
        api_key_param = ""  # 关闭校验时，不传token给后端

    # 获取插件请求数据
    req_data = request.get_json()
    if not req_data:
        return jsonify({"translations": [{"text": ""}], "detected_source_lang": "auto"}), 200

    # 解析参数+清洗语言码
    text_list = req_data.get("text_list", [])
    source_lang = clean_lang_code(req_data.get("source_lang", "auto"))
    target_lang = clean_lang_code(req_data.get("target_lang", "zh"))
    detected_source_lang = source_lang
    translations = []

    # 批量循环处理所有文本，解决部分内容不翻译问题
    for single_text in text_list:
        if not isinstance(single_text, str):
            translations.append({"text": ""})
            continue
        final_text = single_text.strip()[:MAX_TEXT_LENGTH]
        if not final_text:
            translations.append({"text": single_text})
            continue

        # 构造请求体
        libre_req_data = {
            "q": final_text,
            "source": source_lang,
            "target": target_lang,
            "format": "text",
            "api_key": api_key_param  # 传给Libre做后端二次校验的核心参数
        }

        # 请求翻译+异常处理
        try:
            headers = {"Content-Type": "application/json; charset=utf-8"}
            libre_response = requests.post(LIBRE_URL, json=libre_req_data, headers=headers, timeout=20, verify=False)
            libre_response.raise_for_status()
            libre_result = libre_response.json()
            trans_text = libre_result.get("translatedText", single_text)
            if detected_source_lang == "auto" and "detectedLanguage" in libre_result:
                detected_source_lang = libre_result["detectedLanguage"]["language"]
            translations.append({"text": trans_text})
        except Exception as e:
            # TOKEN校验开启时，异常返回【Token无效】提示+401；关闭时返回原文+200
            if TOKEN_CHECK:
                return jsonify({"translations": [{"text": "Token Error"}], "detected_source_lang": source_lang}), 401
            else:
                translations.append({"text": single_text})

    # 返回插件标准格式
    return jsonify({
        "translations": translations,
        "detected_source_lang": detected_source_lang
    })

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=PROXY_PORT, debug=False)