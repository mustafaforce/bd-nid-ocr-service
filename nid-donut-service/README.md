# NID Donut OCR Microservice

FastAPI microservice for Donut-based document extraction used by Laravel OCR driver.

## Directory

- `inference_server.py` - service entrypoint
- `requirements.txt` - Python dependencies
- `.env.example` - service config template
- `models/` - local model storage (weights ignored in git)

## Setup

```bash
python -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
cp .env.example .env
```

Edit `.env`:

```env
MODEL_PATH=./models/nid_donut_model
STUB_MODE=false
HOST=0.0.0.0
PORT=8100
```

## Run (Development)

```bash
source .venv/bin/activate
uvicorn inference_server:app --host 0.0.0.0 --port 8100
```

## Health Check

```bash
curl http://127.0.0.1:8100/health
```

Expected keys:

- `status`
- `device`
- `model_loaded`
- `stub_mode`

## Notes on Accuracy

- `donut-base` is a generic pretrained model.
- High Bangladesh NID accuracy requires fine-tuning on task-specific data.
- If Donut output is weak, use Laravel `fallback` driver to auto-fallback to Tesseract.

## Laravel Integration

```env
NID_OCR_DRIVER=fallback
NID_DONUT_URL=http://127.0.0.1:8100
NID_DONUT_TIMEOUT=30
NID_DONUT_HEALTH_TIMEOUT=3
NID_DONUT_FALLBACK=true
```

Then:

```bash
php artisan config:clear
```

## Production (systemd)

Use unit file at `deploy/systemd/nid-donut.service`.

```bash
sudo cp deploy/systemd/nid-donut.service /etc/systemd/system/nid-donut.service
sudo systemctl daemon-reload
sudo systemctl enable nid-donut
sudo systemctl start nid-donut
```
