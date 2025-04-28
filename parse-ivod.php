<?php

if (!file_exists(__DIR__ . "/ivod-data/pyannote")) {
    mkdir(__DIR__ . "/ivod-data/pyannote", 0777, true);
}
if (!file_exists(__DIR__ . "/ivod-data/whisper")) {
    mkdir(__DIR__ . "/ivod-data/whisper", 0777, true);
}
if (!file_exists(__DIR__ . "/ivod-data/log")) {
    mkdir(__DIR__ . "/ivod-data/log", 0777, true);
}
foreach (glob(__DIR__ . "/ivod-video/*.wav") as $wav_file) {
    $ivod_id = basename($wav_file, ".wav");

    $pyannote_file = __DIR__ . "/ivod-data/pyannote/{$ivod_id}.txt";
    $whisper_dir = __DIR__ . "/ivod-data/whisper/{$ivod_id}";
    $log_file = __DIR__ . "/ivod-data/log/{$ivod_id}.txt";

    if (!file_exists($log_file)) {
        file_put_contents($log_file, "");
        $video_length = trim(`ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 {$wav_file}`);
        file_put_contents($log_file, "video_length: $video_length\n", FILE_APPEND);
    }
    if (file_exists($pyannote_file) and filesize($pyannote_file) == 0) {
        unlink($pyannote_file);
    }
    if (!file_exists($pyannote_file)) {
        $start = microtime(true);
        $cmd = sprintf("conda run --name pyannote python3 pyannote.py %s > %s", $wav_file, $pyannote_file);
        error_log($cmd);
        system($cmd);
        $delta = microtime(true) - $start;
        file_put_contents(__DIR__ . "/ivod-data/log/{$ivod_id}.txt", "pyannote: $delta\n", FILE_APPEND);
    }

    if (!file_exists($whisper_dir . "/output.txt")) {
        $cmd = sprintf("php whisper.php %s", $pyannote_file);
        $cmd = trim(`$cmd`);
        $cmd = str_replace("output.wav", $wav_file, $cmd);
        $cmd = str_replace("output/dir", $whisper_dir, $cmd);
        mkdir($whisper_dir, 0777, true);
        $cmd = sprintf("conda run --name whisper %s > %s", $cmd, "{$whisper_dir}/output.txt");
        error_log($cmd);

        $start = microtime(true);
        system($cmd);
        $delta = microtime(true) - $start;
        file_put_contents(__DIR__ . "/ivod-data/log/{$ivod_id}.txt", "whisper: $delta\n", FILE_APPEND);
    }
}
