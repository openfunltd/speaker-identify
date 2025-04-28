<?php

require_once __DIR__ . '/EmbeddingsExtractor.php';

$ivod_id = $argv[1] ?? null;

$extractor = new EmbeddingsExtractor();

if ($ivod_id) {
    $extractor->extract($ivod_id);
} else {
    foreach (glob(__DIR__ . "/ivod-data/diff/*.json") as $diff_file) {
        $ivod_id = basename($diff_file, ".json");
        if (filesize($diff_file) === 0) {
            continue;
        }
        if (filesize(__DIR__ . "/ivod-data/txt/{$ivod_id}.txt") === 0) {
            continue;
        }
        echo "Processing {$ivod_id}...\n";
        $extractor->extract($ivod_id);
    }
}
