from dotenv import load_dotenv
load_dotenv('../.env')
HF_TOKEN = os.getenv('HF_TOKEN')
if HF_TOKEN is None:
    raise ValueError("請在 .env 檔案中設定 HF_TOKEN 環境變數")

from pyannote.audio import Pipeline
pipeline = Pipeline.from_pretrained(
    "pyannote/speaker-diarization-3.1",
    use_auth_token=HF_TOKEN,
)

# send pipeline to GPU (when available)
import torch
pipeline.to(torch.device("cuda"))

# input file from argv
import sys
input_file = sys.argv[1]

# apply pretrained pipeline
diarization, embeddings = pipeline(input_file, return_embeddings=True)

# print the result
for turn, _, speaker in diarization.itertracks(yield_label=True):
    print(f"start={turn.start:.1f}s stop={turn.end:.1f}s speaker_{speaker}")

for s, speaker in enumerate(diarization.labels()):
    # print embeddings json
    print(f"speaker_{speaker} embeddings={embeddings[s].tolist()}")
