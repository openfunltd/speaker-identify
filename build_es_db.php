<?php

require_once __DIR__ . '/init.inc.php';
require_once __DIR__ . '/vendor/autoload.php';

use Elastic\Elasticsearch\ClientBuilder;


class Builder
{
    protected $client;

    public function __construct()
    {
        // 建立 es 連線
        $this->client = ClientBuilder::create()
            ->setHosts([$_SERVER['ELASTIC_HOST']])
            ->setBasicAuthentication($_SERVER['ELASTIC_USER'], $_SERVER['ELASTIC_PASSWORD'])
            ->build();
    }

    public function build()
    {
        $this->createIndex();
        $this->import();
    }

    protected function import()
    {
        $count = 0;
        $files = glob(__DIR__ . '/embeddings/*.json');
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file));
            $name = $data->name;
            $embeddings = $data->embeddings;
            foreach ($embeddings as $embedding) {
                $id = $this->generateId(json_encode($embedding));
                $doc = [
                    'id' => $id,
                    'vector' => $embedding,
                    'metadata' => [
                        'name' => $name,
                        'created_at' => date('c'),
                    ],
                ];
                $params = [
                    'index' => $this->getIndexName(),
                    'id' => $id,
                    'body' => $doc,
                ];
                try {
                    $this->client->index($params);
                    $count += 1;
                    echo "Indexed document with ID: {$id}\n";
                } catch (Exception $e) {
                    echo "Failed to index document with ID: {$id}. Error: " . $e->getMessage() . "\n";
                    exit;
                }
            }
        }
        echo "Total indexed documents: {$count}\n";
    }

    protected function generateId(string $seed, int $lengthBytes = 16)
    {
        // 將 seed 轉為固定數字，用於 seed
        $hash = crc32($seed);
        mt_srand($hash);

        // 產生指定 bytes 的亂數字串
        $bytes = '';
        for ($i = 0; $i < $lengthBytes; $i++) {
            $bytes .= chr(mt_rand(0, 255));
        }

        // 編碼為 URL-safe Base64
        $base64 = rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
        return $base64;
    }

    protected function getIndexName()
    {
        return $_SERVER['ELASTIC_PREFIX'] . 'speaker_embeddings';
    }

    protected function checkIndexExists($index)
    {
        $response = $this->client->indices()->exists(['index' => $index]);
        return $response->asBool();
    }

    protected function createIndex()
    {
        $index = $this->getIndexName();
        if (!$this->checkIndexExists($index)) {
            $params = [
                'index' => $index,
                'body' => [
                    'settings' => [
                        'number_of_shards' => 1,
                        'number_of_replicas' => 1,
                    ],
                    'mappings' => [
                        'properties' => [
                            'id' => [
                                'type' => 'keyword',
                            ],
                            'vector' => [
                                'type' => 'dense_vector',
                                'dims' => 256,
                            ],
                            'metadata' => [
                                'type' => 'object',
                                'properties' => [
                                    'name' => ['type' => 'text'],
                                    'source_audio' => ['type' => 'keyword'],
                                    'segment_start' => ['type' => 'float'],
                                    'segment_end' => ['type' => 'float'],
                                    'created_at' => ['type' => 'date'],
                                ],
                            ],
                        ],
                    ],
                ],
            ];
            $this->client->indices()->create($params);
            echo "Index '{$index}' created successfully.\n";
        } else {
            echo "Index '{$index}' already exists.\n";
        }
    }

    protected function deleteIndex()
    {
        $index = $this->getIndexName();
        if ($this->checkIndexExists($index)) {
            $params = [
                'index' => $index,
            ];
            $this->client->indices()->delete($params);
            echo "Index '{$index}' deleted successfully.\n";
        } else {
            echo "Index '{$index}' does not exist.\n";
        }
    }
}

$builder = new Builder();
$builder->build();
