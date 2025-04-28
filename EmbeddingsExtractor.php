<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/PyannoteDataParser.php';

use OpenFun\LyTcToolkit\LyTcToolkit;

class EmbeddingsExtractor
{
    public function extract($ivod_id)
    {
        // 取得 pyannote 資訊
        $file_path = __DIR__ . "/ivod-data/pyannote/{$ivod_id}.txt";
        list($annotes, $speaker_embeddings) = PyannoteDataParser::parse($file_path, true);

        // 統計 speaker 對應的人名出現次數
        $speaker_counts = $this->getSpeakerCounts($ivod_id, $annotes);

        // 選出最有可能的 speaker 對應的人名
        $good_speakers = $this->getHighScoreSpeakers($speaker_counts, $this->getChairPerson($ivod_id));
        // print_r($good_speakers);

        // 取得 speaker 對應的 embeddings
        $speaker_name_embeddings = $this->getSpeakerNameEmbeddings($speaker_embeddings, $good_speakers);

        // 把 speaker 對應的 embeddings 寫入檔案
        $this->saveSpeakerNameEmbeddings($speaker_name_embeddings);
    }

    protected function saveSpeakerNameEmbeddings($speaker_name_embeddings)
    {
        $dir = __DIR__ . "/embeddings";
        if (!is_dir($dir)) {
            mkdir($dir);
        }
        foreach ($speaker_name_embeddings as $name => $embedding) {
            if ('' == $name) {
                continue;
            }
            $file = "{$dir}/{$name}.json";
            $embeddings = [];
            if (file_exists($file)) {
                $ori_content = file_get_contents($file);
                $ori_data = json_decode($ori_content);
                if (isset($ori_data->embeddings)) {
                    $embeddings = (array)$ori_data->embeddings;
                }
            }
            $embeddings[] = json_decode($embedding);
            $data = (object)[
                'name' => $name,
                'embeddings' => array_values($embeddings),
            ];
            $content = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            file_put_contents($file, $content);
        }
    }

    protected function getSpeakerNameEmbeddings($speaker_embeddings, $good_speakers)
    {
        $speaker_name_embeddings = [];
        foreach ($good_speakers as $speaker => $name) {
            if (isset($speaker_embeddings[$speaker])) {
                $speaker_name_embeddings[$name] = $speaker_embeddings[$speaker];
            }
        }
        return $speaker_name_embeddings;
    }

    protected function getHighScoreSpeakers($speaker_counts, $chair_person)
    {
        $good_speakers = [];
        $max_total_counts = [];
        foreach ($speaker_counts as $speaker => $counts) {
            $total_count = array_sum($counts);
            if ($total_count < 10) {
                continue;
            }
            $best_speaker_name = null;
            foreach ($counts as $speaker_name => $count) {
                $score = ($count / $total_count) * 100;
                if ($score > 80) {
                    $best_speaker_name = $speaker_name;
                    break;
                }
            }
            if (!$best_speaker_name) {
                continue;
            }
            if ('主席' === $best_speaker_name) {
                $best_speaker_name = $chair_person;
            }
            $max_total_counts[$best_speaker_name] = $max_total_counts[$best_speaker_name] ?? 0;
            if ($total_count > $max_total_counts[$best_speaker_name]) {
                $max_total_counts[$best_speaker_name] = $total_count;
                $good_speakers[$speaker] = $best_speaker_name;
            }
        }
        return $good_speakers;
    }

    protected function getChairPerson($ivod_id)
    {
        $txt_file = __DIR__ . "/ivod-data/txt/{$ivod_id}.txt";
        foreach (file($txt_file) as $line) {
            if (preg_match('/^主　　席　(.+)$/u', $line, $matches)) {
                return $matches[1];
            }
        }
    }

    protected function getSpeakerCounts($ivod_id, $annotes)
    {
        $diff_file = __DIR__ . "/ivod-data/diff/{$ivod_id}.json";

        $diff = json_decode(file_get_contents($diff_file));
        $diffs = $diff[0];
        $speaker = '';

        $speaker_counts = [];
        foreach ($diffs as $diff) {
            $old_lines = [];
            $new_lines = [];
            $timecode = '';
            $annote = '';
            $annote_timecode = '';
            foreach ($diff->old->lines as $line) {
                $line = str_replace('<del>', '', $line);
                $line = str_replace('</del>', '', $line);
                if (preg_match(LyTcToolkit::GAZETTE_SPEAKER_PREG, $line, $matches)) {
                    $speaker = $matches[1];
                    continue;
                }
                $old_lines[] = htmlspecialchars($line);
            }
            foreach ($diff->new->lines as $line) {
                $line = str_replace('<ins>', '', $line);
                $line = str_replace('</ins>', '', $line);
                if (preg_match('#^([0-9:.]+) --&gt; ([0-9:.]+)#', $line, $matches)) {
                    $sterms = explode(':', $matches[1]);
                    $eterms = explode(':', $matches[2]);
                    $start_time = 0;
                    while (count($sterms)) {
                        $start_time = $start_time * 60 + floatval(array_shift($sterms));
                    }
                    $end_time = 0;
                    while (count($eterms)) {
                        $end_time = $end_time * 60 + floatval(array_shift($eterms));
                    }

                    $matched_annotes = [];
                    foreach ($annotes as $annote) {
                        $overlapped = max(0, min($annote['end'], $end_time) - max($annote['start'], $start_time));
                        if ($overlapped < 1) {
                            continue;
                        }
                        $matched_annotes[] = $annote;
                    }
                    usort($matched_annotes, function($a, $b) use ($start_time, $end_time) {
                        $overlapped_a = max(0, min($a['end'], $end_time) - max($a['start'], $start_time));
                        $overlapped_b = max(0, min($b['end'], $end_time) - max($b['start'], $start_time));
                        return $overlapped_b - $overlapped_a;
                    });

                    $overlapped = max(0, min($matched_annotes[0]['end'] ?? 0, $end_time) - max($matched_annotes[0]['start'] ?? 0, $start_time));
                    if ($overlapped > 0) {
                        $annote = $matched_annotes[0]['speaker'];
                        $annote_timecode = "{$matched_annotes[0]['start']} - {$matched_annotes[0]['end']}";
                        if (!isset($speaker_counts[$annote])) {
                            $speaker_counts[$annote] = [];
                        }
                        if (!isset($speaker_counts[$annote][$speaker])) {
                            $speaker_counts[$annote][$speaker] = 0;
                        }
                        $speaker_counts[$annote][$speaker]++;
                    }

                    $timecode = "{$start_time} - {$end_time}";
                    //$timecode = $line;
                    continue;
                }
                $new_lines[] = htmlspecialchars($line);
            }
        }
        foreach ($speaker_counts as $speaker => $count) {
            arsort($count);
            $speaker_counts[$speaker] = $count;
        }

        return $speaker_counts;
    }
}

