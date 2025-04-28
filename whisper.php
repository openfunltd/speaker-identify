<?php

$fp = fopen($_SERVER['argv'][1], 'r');
$sections = [];
$start = null;
$stop = null;
while ($line = fgets($fp)) {
    if (trim($line) == '') {
        continue;
    }
    if (!preg_match('#start=([0-9.]+)s stop=([0-9.]+)s speaker_#', $line, $matches)) {
        continue;
        throw new Exception('Failed to match line: ' . $line);
    }
    $line_start = floatval($matches[1]);
    $line_stop = floatval($matches[2]);
    if (is_null($start)) {
        $start = $line_start;
        $stop = $line_stop;
        continue;
    }

    if ($line_start < $stop or abs($line_start - $stop) < 20) {
        $stop = max($stop, $line_stop);
        continue;
    }
    $sections[] = [$start, $stop];
    $start = $line_start;
    $stop = $line_stop;
}
$sections[] = [$start, $stop];

foreach ($sections as $section) {
    list($start, $stop) = $section;
    // to HH:mm:ss format
    $start_str = sprintf("%02d:%02d:%02d %f", ($start / 3600), ($start / 60 % 60), $start % 60, $start);
    $stop_str = sprintf("%02d:%02d:%02d %f", ($stop / 3600), ($stop / 60 % 60), $stop % 60, $stop);
    $length = floor($stop - $start);
    $terms[] = "{$start},{$stop}";
    //echo "$start_str --> $stop_str ($length)\n";
}
$cmd = sprintf("whisper --model medium --language zh output.wav --output_dir output/dir --clip_timestamps %s", implode(',', $terms));
echo $cmd . "\n";
