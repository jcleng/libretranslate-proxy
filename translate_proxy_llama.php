<?php
// ===================== 全局核心配置 =====================
$LLAMA_URL = getenv('LLAMA_URL') ?: 'http://localhost:4280/v1/chat/completions';
$PROXY_PORT = 50117;
$MAX_TEXT_LENGTH = 5000;
$TARGET_LANG_MAP = [
    "zh" => "中文",
    "zh-cn" => "中文",
    "zh-tw" => "中文",
    "en" => "English",
    "ja" => "日本語",
    "ko" => "한국어",
    "fr" => "Français",
    "de" => "Deutsch",
    "es" => "Español",
    "ru" => "Русский",
    "pt" => "Português",
    "it" => "Italiano",
    "ar" => "العربية",
    "th" => "ไทย",
    "vi" => "Tiếng Việt",
];

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
    return $lang;
}

// ===================== 构造翻译 prompt =====================
function build_translate_prompt($text, $targetLang)
{
    global $TARGET_LANG_MAP;
    $langName = $TARGET_LANG_MAP[$targetLang] ?? $targetLang;
    return "将以下文本翻译为 {$langName}。注意只需要输出翻译后的结果，不要额外解释：{$text}";
}

// ===================== 核心翻译接口 =====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SERVER['REQUEST_URI'] === '/translate') {

    // 读取请求体 JSON
    $rawInput = file_get_contents("php://input");
    $reqData = json_decode($rawInput, true);
    error_log("[llama] " . var_export($reqData, true));

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

        // 构造 llama chat completions 请求体
        $prompt = build_translate_prompt($finalText, $targetLang);
        $llamaReqData = [
            "messages" => [
                [
                    "role" => "user",
                    "content" => $prompt
                ]
            ],
            "temperature" => 0.3,
            "max_tokens" => 2048
        ];
        error_log("[llama] request: " . var_export($llamaReqData, true));

        // cURL 请求 llama 翻译模型
        $ch = curl_init($LLAMA_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($llamaReqData, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => ["Content-Type: application/json; charset=utf-8"],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60 * 5,
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode >= 400) {
            error_log("[llama] cURL error: " . ($curlError ?: "HTTP {$httpCode}"));
            $translations[] = ["text" => $singleText];
            continue;
        }

        $llamaResult = json_decode($response, true);
        error_log("[llama] response: " . var_export($llamaResult, true));

        // 从 chat completions 响应中提取翻译结果
        $transText = $llamaResult['choices'][0]['message']['content'] ?? $singleText;

        // 清理可能的前后空白和引号
        $transText = trim($transText);
        $transText = trim($transText, '"');

        $translations[] = ["text" => $transText];
    }

    // ===================== 返回结果 =====================
    echo json_encode([
        "translations" => $translations,
        "detected_source_lang" => $sourceLang
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ===================== 非 /translate 路径 =====================
http_response_code(404);
echo json_encode(["error" => "Not Found"], JSON_UNESCAPED_UNICODE);
