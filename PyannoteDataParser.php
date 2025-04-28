<?php

class PyannoteDataParser
{
    public static function parse($file_path, $as_string = false)
    {
        $fp = fopen($file_path, "r");
        $annotes = [];
        $speaker_embeddings = [];
        while ($line = fgets($fp)) {
            if (preg_match('#start=([0-9.]+)s stop=([0-9.]+)s speaker_(SPEAKER_\d+)#', $line, $matches)) {
                $speaker = $matches[3];
                $annotes[] = [
                    'start' => floatval($matches[1]),
                    'end' => floatval($matches[2]),
                    'speaker' => $speaker,
                ];
            } elseif (preg_match('#speaker_(SPEAKER_\d+) embeddings=(.+)#', $line, $matches)) {
                $embeddings = $as_string ? $matches[2] : json_decode($matches[2], true);
                $speaker_embeddings[$matches[1]] = $embeddings;
            }
        }
        fclose($fp);

        return [$annotes, $speaker_embeddings];
    }
}
