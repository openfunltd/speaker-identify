<?php

@mkdir(__DIR__ . "/ivod-data/doc");
@mkdir(__DIR__ . "/ivod-data/txt");
@mkdir(__DIR__ . "/ivod-data/diff");

foreach (glob(__DIR__ . "/ivod-data/whisper/*/output.txt") as $whisper_file) {
    $ivod_id = basename(dirname($whisper_file));
    $doc_file = __DIR__ . "/ivod-data/doc/{$ivod_id}.doc";
    if (!file_exists($doc_file)) {
        echo "DOwnload $ivod_id\n";
        $ivod_data = file_get_contents("https://ly.govapi.tw/v2/ivod/{$ivod_id}");
        $ivod_data = json_decode($ivod_data)->data;
        $meet_id = $ivod_data->會議資料->會議代碼;
        $meet_date = $ivod_data->日期;
        list($year, $month, $day) = explode('-', $meet_date);

        $meet_data = file_get_contents("https://ly.govapi.tw/v2/meet/{$meet_id}");
        $meet_data = json_decode($meet_data)->data;
        $doc_url = null;
        foreach ($meet_data->議事網資料 as $item) {
            if (strpos(implode('', $item->日期), sprintf("%03d年%02d月%02d日",
                $year - 1911, $month, $day)) === false) {
                continue;
            }

            foreach ($item->連結 as $link) {
                if (stripos($link->標題, '公報紀錄DOC') !== false) {
                    $doc_url = $link->連結;
                    break 2;
                }
            }
        }

        if (is_null($doc_url)) {
            echo "No doc found\n";
            continue;
        }
        $doc_url = str_replace("\\", "/", $doc_url);
        $cmd = sprintf("wget -4 -O %s %s", escapeshellarg($doc_file), escapeshellarg($doc_url));
        system($cmd);
    }

    if (!file_exists($doc_file)) {
        continue;
    }
    $txt_file = __DIR__ . "/ivod-data/txt/{$ivod_id}.txt";
    if (!file_exists($txt_file)) {
        $cmd = sprintf("curl -T %s https://tika.openfun.dev/tika -H 'Accept: text/plain' > %s", escapeshellarg($doc_file), escapeshellarg($txt_file));
        system($cmd);
    }

    if (!file_exists($txt_file)) {
        continue;
    }
    $diff_txt = __DIR__ . "/ivod-data/diff/{$ivod_id}.json";
    if (!file_exists($diff_txt)) {
        $cmd = sprintf("php diff.php %s %s > %s", escapeshellarg($whisper_file), escapeshellarg($txt_file), escapeshellarg($diff_txt));
        system($cmd);
    }
}
