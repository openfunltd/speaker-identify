<?php

$ivod_id = $_GET['ivod_id'];
$pyannote_file = __DIR__ . "/ivod-data/pyannote/{$ivod_id}.txt";
$diff_file = __DIR__ . "/ivod-data/diff/{$ivod_id}.json";

$fp = fopen($pyannote_file, "r");
$annotes= [];
while ($line = fgets($fp)) {
    if (!preg_match('#start=([0-9.]+)s stop=([0-9.]+)s speaker_(SPEAKER_\d+)#', $line, $matches)) {
        continue;
    }
    $speaker = $matches[3];
    $annotes[] = [
        'start' => floatval($matches[1]),
        'end' => floatval($matches[2]),
        'speaker' => $speaker,
    ];
}
fclose($fp);

$diff = json_decode(file_get_contents($diff_file));
$diffs = $diff[0];
$speaker = '';

$records = [];
$speaker_count = [];
foreach ($diffs as $diff) {
    $old_lines = [];
    $new_lines = [];
    $timecode = '';
    $annote = '';
    $annote_timecode = '';
    foreach ($diff->old->lines as $line) {
        $line = str_replace('<del>', '', $line);
        $line = str_replace('</del>', '', $line);
        if (preg_match('#^.+：$#u', $line, $matches)) {
            $speaker = $line;
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

            $overlapped = max(0, min($matched_annotes[0]['end'], $end_time) - max($matched_annotes[0]['start'], $start_time));
            if ($overlapped > 0) {
                $annote = $matched_annotes[0]['speaker'];
                $annote_timecode = "{$matched_annotes[0]['start']} - {$matched_annotes[0]['end']}";
                if (!isset($speaker_count[$annote])) {
                    $speaker_count[$annote] = [];
                }
                if (!isset($speaker_count[$annote][$speaker])) {
                    $speaker_count[$annote][$speaker] = 0;
                }
                $speaker_count[$annote][$speaker]++;
            }

            $timecode = "{$start_time} - {$end_time}";
            //$timecode = $line;
            continue;
        }
        $new_lines[] = htmlspecialchars($line);
    }
    $records[] = [
        'timecode' => $timecode,
        'annote' => $annote,
        'annote_timecode' => $annote_timecode,
        'speaker' => $speaker,
        'old_lines' => $old_lines,
        'new_lines' => $new_lines,
    ];
}
foreach ($speaker_count as $speaker => $count) {
    arsort($count);
    $speaker_count[$speaker] = $count;
}
?>
<h1>發言者</h1>
<table border="1">
    <tr>
        <td>speaker</td>
        <td>count</td>
    </tr>
    <?php foreach ($speaker_count as $speaker => $count) { ?>
    <tr>
        <td>
            <?= $speaker ?>
        </td>
        <td>
            <?php foreach ($count as $speaker => $c) { ?>
            <?= $speaker ?>: <?= $c ?><br>
            <?php } ?>
        </td>
    </tr>
    <?php } ?>
</table>
<h1>對話</h1>
<table border="1">
    <tr>
        <td>timecode</td>
        <td>annote</td>
        <td>speaker</td>
        <td>公報</td>
        <td>whisper</td>
    </tr>
    <?php foreach ($records as $record) { ?>
    <tr>
        <td>
            <?= $record['timecode'] ?>
        </td>
        <td>
            <?= $record['annote'] ?><br>
            <?= $record['annote_timecode'] ?>
        </td>
        <td>
            <?= $record['speaker'] ?>
        </td>
        <td>
            <?= htmlspecialchars(implode('', $record['old_lines'])) ?>
        </td>
        <td>
            <?= htmlspecialchars(implode('', $record['new_lines'])) ?>
        </td>
    </tr>
    <?php } ?>
</table>
