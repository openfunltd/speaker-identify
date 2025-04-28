<?php

class EmbeddingIdentifier
{
    protected $db;

    public function __construct(protected $embeddings_dir)
    {
    }

    public function identify($embedding, $threshold = 0.7)
    {
        $this->loadDb();

        $best_score = INF;
        $best_name = null;

        foreach ($this->db as $name => $vectors) {
            foreach ($vectors as $vec) {
                // 計算餘弦相似度
                $score = $this->cosine_similarity($embedding, $vec);
                if ($score < $best_score) {
                    $best_score = $score;
                    $best_name = $name;
                }
            }
        }

        $reverse_score = 1 - $best_score;
        return $reverse_score > $threshold ? $best_name : null;
    }

    protected function loadDb()
    {
        if (isset($this->db)) {
            return;
        }

        // 遍歷資料夾中的檔案
        $files = glob("{$this->embeddings_dir}/*.json");
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            $name = $data['name'];
            $db[$name] = array_map(function($vec) {
                return json_decode(json_encode($vec), true);  // 將陣列字串轉換為陣列
            }, $data['embeddings']);
        }

        $this->db = $db;
    }

    // 計算兩個向量的餘弦相似度
    protected function cosine_similarity($vec1, $vec2)
    {
        $dot_product = array_sum(array_map(function($a, $b) { return $a * $b; }, $vec1, $vec2));
        $magnitude1 = sqrt(array_sum(array_map(function($a) { return $a * $a; }, $vec1)));
        $magnitude2 = sqrt(array_sum(array_map(function($a) { return $a * $a; }, $vec2)));

        return 1 - ($dot_product / ($magnitude1 * $magnitude2)); // 餘弦相似度
    }
}

