<?php
// ===================== 全局核心配置 =====================
$LIBRE_URL = getenv('LIBRE_URL') ?: 'http://172.18.0.9:5000/translate';
$PROXY_PORT = 50116;
$MAX_TEXT_LENGTH = 5000;
$TOKEN_CHECK = false; // true = 强制验 token

// ===================== CORS 跨域 =====================
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=utf-8");

// OPTIONS 预检请求直接返回
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ===================== 语言码清洗 =====================
function clean_lang_code($lang)
{
    if (!$lang) return "auto";
    $lang = strtolower(trim($lang));
    if (in_array($lang, ["zh-cn", "zh-tw", "zh"])) {
        return "zh";
    } elseif ($lang === "en") {
        return "en";
    }
    return "auto";
}

// ===================== 核心翻译接口 =====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SERVER['REQUEST_URI'] === '/translate') {

    // 从 URL 参数中获取 token
    $client_api_key = $_GET['token'] ?? '';

    // Token 校验逻辑
    if ($TOKEN_CHECK) {
        if (empty($client_api_key)) {
            http_response_code(401);
            echo json_encode([
                "translations" => [["text" => "请配置正确的API Token"]],
                "detected_source_lang" => "auto"
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $api_key_param = $client_api_key;
    } else {
        $api_key_param = "";
    }

    // 读取请求体 JSON
    $rawInput = file_get_contents("php://input");
    $reqData = json_decode($rawInput, true);
    error_log(var_export($reqData, true));

    if (!$reqData || !is_array($reqData)) {
        echo json_encode([
            "translations" => [["text" => ""]],
            "detected_source_lang" => "auto"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 解析参数
    $textList = $reqData['text_list'] ?? [];
    $sourceLang = clean_lang_code($reqData['source_lang'] ?? "auto");
    $targetLang = clean_lang_code($reqData['target_lang'] ?? "zh");

    $detectedSourceLang = $sourceLang;
    $translations = [];

    // ===================== 批量翻译 =====================
    foreach ($textList as $singleText) {
        if (!is_string($singleText)) {
            $translations[] = ["text" => ""];
            continue;
        }

        $finalText = mb_substr(trim($singleText), 0, $MAX_TEXT_LENGTH);

        if ($finalText === "") {
            $translations[] = ["text" => $singleText];
            continue;
        }

        // 构造请求体
        $libreReqData = [
            "q" => $finalText,
            "source" => $sourceLang,
            "target" => $targetLang,
            "format" => "text",
            "api_key" => $api_key_param
        ];
        error_log(var_export($libreReqData, true));

        // cURL 请求 LibreTranslate
        $ch = curl_init($LIBRE_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($libreReqData, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => ["Content-Type: application/json; charset=utf-8"],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode >= 400) {
            if ($TOKEN_CHECK) {
                http_response_code(401);
                echo json_encode([
                    "translations" => [["text" => "Token Error"]],
                    "detected_source_lang" => $sourceLang
                ], JSON_UNESCAPED_UNICODE);
                exit;
            } else {
                $translations[] = ["text" => $singleText];
                continue;
            }
        }

        $libreResult = json_decode($response, true);
        $transText = $libreResult['translatedText'] ?? $singleText;

        if ($detectedSourceLang === "auto" && isset($libreResult['detectedLanguage']['language'])) {
            $detectedSourceLang = $libreResult['detectedLanguage']['language'];
        }

        $translations[] = ["text" => $transText];
    }

    // ===================== 返回结果 =====================
    echo json_encode([
        "translations" => $translations,
        "detected_source_lang" => $detectedSourceLang
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ===================== 非 /translate 路径 =====================
http_response_code(404);
echo json_encode(["error" => "Not Found"], JSON_UNESCAPED_UNICODE);
