<?php

require_once __DIR__ . '/PyannoteDataParser.php';
require_once __DIR__ . '/EmbeddingIdentifier.php';

class Asr
{
    protected $embeddings_extractor;
    protected $embedding_identifier;
    protected $conda_env;
    protected $tmp_dir = __DIR__ . '/tmp';
    protected $log_dir = __DIR__ . '/logs';
    protected $api_url = 'http://localhost:31500/';

    public function __construct(
        protected $audio_file,
        protected $model = 'medium',
        protected $embeddings_dir = '',
        protected $threshold = 0.7,
    ) {
        $this->embedding_identifier = new EmbeddingIdentifier($embeddings_dir);
        $this->conda_env = $_ENV['CONDA_ENV'] ?? 'base';
    }

    public function run()
    {
        // 檢查音訊檔案
        $this->checkAudioFile();

        // 取得 speakers 對應
        $speaker_names = $this->identifySpeakers();

        // whisperx 轉錄
        $whisperx_result_array = json_decode(json_encode($this->getWhisperxResult()), true);

        // 替換 speaker names
        $handled_result_array = $this->replaceSpeakerNames($whisperx_result_array, $speaker_names);

        // 轉換成物件並
        return json_decode(json_encode($handled_result_array));
    }

    protected function checkAudioFile()
    {
        if (!file_exists($this->audio_file)) {
            throw new FileNotExistsException("Audio file not found: {$this->audio_file}");
        }
    }

    protected function getWhisperxResult()
    {
        $id = sprintf("whisper-%s-%s", $this->audio_file, uniqid());
        $data = [
            'id' => $id,
            'method' => 'whisperx',
            'model_id' => $this->model,
            'input' => $this->audio_file,
            'diarize' => true,
        ];
        $cmd = sprintf("curl -H 'Content-Type: application/json' -X POST -d %s %s",
            escapeshellarg(json_encode($data)),
            escapeshellarg($this->api_url)
        );
        $response = json_decode(`$cmd`);
        return $response->output ?? [];
    }

    protected function getPyannoteResult()
    {
        $id = sprintf("pyannote-%s-%s", $this->audio_file, uniqid());
        $data = [
            'id' => $id,
            'method' => 'pyannote',
            'input' => $this->audio_file,
        ];
        $cmd = sprintf("curl -H 'Content-Type: application/json' -X POST -d %s %s",
            escapeshellarg(json_encode($data)),
            escapeshellarg($this->api_url)
        );
        $response = json_decode(`$cmd`);
        return $response;
    }

    protected function identifySpeakers()
    {
        // 取得 speaker embeddings
        $embeddings = $this->getEmbeddings();

        // 由 embedding vector 判斷人名
        $speakers = [];
        foreach ($embeddings as $embedding) {
            $speaker_id = $embedding->speaker;
            $speaker_name = $this->embedding_identifier->identify($embedding->vector, $this->threshold);
            if ($speaker_name) {
                $speakers[$speaker_id] = $speaker_name;
            }
        }
        return $speakers;
    }

    protected function getEmbeddings()
    {
        $pyannote_result = $this->getPyannoteResult();
        return $pyannote_result->vectors ?? [];
    }

    protected function log($message)
    {
        $log_file = $this->log_dir . '/asr.log';
        file_put_contents($log_file, date('Y-m-d H:i:s') . " - " . $message . PHP_EOL, FILE_APPEND);
    }

    protected function replaceSpeakerNames($array, $speaker_names)
    {
        foreach ($array as $key => &$item) {
            if (is_array($item)) {
                $item = $this->replaceSpeakerNames($item, $speaker_names);
            } elseif ('speaker' === $key) {
                $item = $speaker_names[$item] ?? $item;
            }
        }
        return $array;
    }
}


class FileNotExistsException extends Exception
{
    public function __construct($message)
    {
        parent::__construct($message);
    }
}


class EnvException extends Exception
{
    public function __construct($message)
    {
        parent::__construct($message);
    }
}
