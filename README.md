# 逐字稿發言者識別

pyannote 等工具只能區分發言者（spearker1, speaker2 ... ），這邊想要研究識別發言者

## 測試資料
- 立法院 IVOD 影像
  - ivod:15858 
  - date:2025-04-29
  - meet:委員會-11-1-15-15
- 公報逐字稿
  - [公報記錄](https://ppg.ly.gov.tw/ppg/download/communique1/work/113/36/LCIDC01_1133601_00005.doc)

## 測試流程
- 先將 IVOD 影片轉成 wav 檔，並透過 whisper 轉逐字稿
  - 隨便抓一個內政委員會的影片: https://ivod.ly.gov.tw/Play/Full/1M/15858
  - 從裡面找到 m3u8 的網址(在 js 裡面的 readyPlayer() )，並下載 m3u8
    - yt-dlp {m3u8-url} --legacy-server-connect
  - 用 ffmpeg 將 m3u8 轉成 wav
    - ``` ffmpeg -i  playlist\ \[playlist\].mp4 -ar 16000 -ac 1 -c:a pcm_s16le output.wav ```
    - speed=372x
- 透過 pyannote 取得時間軸
  - python3 pyannote.py > pyannote.txt
    - 時間：4m29.987s
- 產生只有對話區域的時間軸，並執行產生逐字稿
  - php whisper.php pyannote.txt
  - whisper --model medium --language zh output.wav 
  - 時間: 47m14.241s (有在同時跑其他東西)
- 產生純文字逐字稿
  - wget "https://ppg.ly.gov.tw/ppg/download/communique1/work/113/36/LCIDC01_1133601_00005.doc"
  - curl -T LCIDC01_1133601_00005.doc https://tika.openfun.dev/tika -H 'Accept: text/plain' > LCIDC01_1133601_00005.doc.txt
- 用 AI 逐字稿跟純文字逐字稿比較
  - php diff.php 
    - 將 LCIDC01_1133601_00005.txt 和 whispoer.txt 做比較，產生 diff.json
  - php parse-diff.php
    - 處理 diff.json，產生報告


## 大量處理
- php crawl-ivod.php
  - 抓下 ivod 影片轉成 wav 存入 ivod-video/{id}.wav
- php parse-ivod.php
  - 將 ivod wav 處理 pyannote 和 whisper

## 環境建立
### whisper
### pyannote
```
> conda create -n pyannote python=3.11
> conda activate pyannote
> pip install pyannote.audio
```
### diff
```
> composer init
> composer require jfcherng/php-diff
```

### 建立 embeddings 資料庫
> build_embeddings_db.php

### ASR API
```
> git clone git@github.com:openfunltd/whisper-api.git; cd whisper-api
> PORT=31500 python whisper-server.py  # 跑一個 whisper server，PORT 請自行更改；需先安裝 whisper/whisperx/pyannote
> php -S localhost:31600  # 或是用 nginx/apache，一樣自己把 port 改掉
> curl http://localhost:31600/asr.php?url={音檔網址}&model={whisper模型}
```
> 如果沒辦法或不想使用 `openfunltd/whisper-api`，可以寫一個 class extends `Asr`，然後覆寫 `getWhisperxResult()` 及 `getPyannoteResult()`。

## 問題與解答
- whisper 無人講話時會有「请不吝点赞 订阅 转发 打赏支持明镜与点点栏目」之類的腦補

## 參考連結
- [pyannote-audio](https://github.com/pyannote/pyannote-audio)
