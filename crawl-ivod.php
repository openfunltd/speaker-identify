<?php

for ($ivod = 15858; $ivod >= 0; $ivod--) {
    $wav_target = __DIR__ . "/ivod-video/{$ivod}.wav";
    if (file_exists($wav_target)) {
        continue;
    }
    $mp4_target = __DIR__ . "/ivod-video/{$ivod}.mp4";
    if (!file_exists($mp4_target)) {
        $url = sprintf("https://ivod.ly.gov.tw/Play/Full/300K/%d", $ivod);
        $cmd = sprintf("curl -4 %s", escapeshellarg($url));
        $content = `$cmd`;

        if (!preg_match('#readyPlayer\("([^"]+)"#', $content, $matches)) {
            continue;
        }
        $url = $matches[1];
        $cmd = sprintf("yt-dlp --legacy-server-connect -o %s %s", escapeshellarg($mp4_target), escapeshellarg($url));
        system($cmd);
    }

    $cmd = sprintf("ffmpeg -i %s -ar 16000 -ac 1 -c:a pcm_s16le %s", escapeshellarg($mp4_target), escapeshellarg($wav_target));
    system($cmd);

    unlink($mp4_target);
}
