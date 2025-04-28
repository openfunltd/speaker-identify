<?php

require_once __DIR__ . '/Asr.php';

header('Content-Type: application/json; charset=utf-8');

// 設定音檔路徑
$audio_file = trim($_GET['url'] ?? $argv[1] ?? null);
if ('http' === substr($audio_file, 0, 4)) {
    $tmp_file = tempnam(__DIR__ . '/tmp', 'audio_');
    $curl = curl_init($audio_file);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($curl, CURLOPT_FILE, fopen($tmp_file, 'w'));
    curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    $audio_file = $tmp_file;
}

// 設定 whisper model
$model = $_GET['model'] ?? $argv[2] ?? 'medium';

// 設定 speaker embeddings 目錄
$embeddings_dir = __DIR__ . '/embeddings/';

// 轉錄
$asr = new Asr(
    audio_file: $audio_file,
    embeddings_dir: $embeddings_dir,
    model: $model,
);

try {
    $result = $asr->run();
    $response = [
        'result' => $result,
    ];
} catch (FileNotExistsException $e) {
    $response = [
        'error' => "File not accessible: {$argv[1]}",
    ];
} catch (Throwable $e) {
    $log_file = __DIR__ . '/logs/asr_error.log';
    file_put_contents($log_file, date('Y-m-d H:i:s') . " " . (string)$e . "\n", FILE_APPEND);
    $response = [
        'error' => 'Unknown error.',
        //'error' => (string)$e,
    ];
}

print_r(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// 刪除 tmp 檔案
if ($tmp_file ?? false) {
    unlink($tmp_file);
}
