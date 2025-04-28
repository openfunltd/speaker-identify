import whisperx
import json
import sys
import os
from dotenv import load_dotenv

load_dotenv('../.env')
HF_TOKEN = os.getenv("HF_TOKEN")
assert HF_TOKEN is not None, "請於 .env 設定 HF_TOKEN 環境變數"


# 如果 whisperx.pid 有其他程式，先 kill 掉
try:
    with open("whisperx.pid", "r") as f:
        pid = int(f.read())
        print(f"Kill PID: {pid}", file=sys.stderr)
        os.kill(pid, 9)
except FileNotFoundError:
    pass    
except ProcessLookupError:
    pass

# 把自己的 pid 寫進 whisperx.pid
with open("whisperx.pid", "w") as f:
    f.write(str(os.getpid()))

model = whisperx.load_model("turbo")
device = "cuda" if torch.cuda.is_available() else "cpu"

# 持續從 stdin 讀取設定，一行一個指令
# stdin 讀取設定，Ex: {"id":"12345","input":"test.mp3", "language":"zh"}
# stdout 輸出結果，Ex: {"id":"12345","output":"whisper輸出結果"}
def process_input():
    try:
        # 讀取一行
        line = input()
        # 解析 JSON
        data = json.loads(line)
        # 取得 id 和 input
        id = data["id"]
        input_file = data["input"]
        language = data.get("language", "zh")
        clip_timestamps = data.get("clip_timestamps", "0")

        # stderr 輸出 id
        print(f"Processing ID: {id}", file=sys.stderr)
        # 使用 Whisper 模型進行轉錄
        audio = whisperx.load_audio(input_file)
        result = model.transcribe(audio, language=language, clip_timestamps=clip_timestamps)

        model_a, metadata = whisperx.load_align_model(language_code=result["language"], device=device)
        result = whisperx.align(result["segments"], model_a, metadata, audio, device, return_char_alignments=False)

        print(f"Result segments: {result['segments']}", file=sys.stderr)

        # Assign speaker labels
        diarize_model = whisperx.DiarizationPipeline(use_auth_token=HF_TOKEN, device=device)
        diarize_segments = diarize_model(audio)

        result = whisperx.assign_word_speakers(diarize_segments, result)

        # 輸出結果
        output = {
            "id": id,
            "output": result
        }
        print(json.dumps(output) + "\n")
    except Exception as e:
        # 輸出錯誤訊息
        print(f"Error: {e}", file=sys.stderr)
        break
        

class WhisperHandler(BaseHTTPRequestHandler):
    def do_POST(self):
        process_input()


if __name__ == "__main__":
    port = 35100
    server = HTTPServer(("127.0.0.1", port), WhisperHandler)
    print(f"🚀 WhisperX server listening on http://localhost:{port}")
    server.serve_forever()
