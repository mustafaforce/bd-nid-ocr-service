# Bangladesh NID OCR API (Laravel + Donut + Tesseract)

Production-oriented practice project for extracting key fields from Bangladesh NID front/back card images.

## What This Repository Contains

- Laravel API endpoint for NID extraction
- Local OCR engine integration (Tesseract)
- Optional Python FastAPI microservice (Donut)
- Donut-to-Tesseract fallback driver
- Basic frontend page for manual testing

## Architecture

- Laravel API: `POST /api/v1/nid/extract`
- OCR engines (driver-based):
  - `tesseract`
  - `donut`
  - `fallback` (Donut first, then Tesseract)
- Parser normalizes extracted values into fields:
  - `name`
  - `father_name`
  - `mother_name`
  - `address`
  - `nid_number`
  - `date_of_birth`
  - `blood_group`
  - `issue_date`

## Quick Start (Laravel)

```bash
cp .env.example .env
composer install
npm install
php artisan key:generate
php artisan config:clear
php artisan serve
```

Open: `http://127.0.0.1:8000`

## OCR Driver Setup

### Option A: Tesseract only (cheapest)

```env
NID_OCR_DRIVER=tesseract
NID_TESSERACT_BINARY=tesseract
NID_OCR_LANGUAGES=eng
```

### Option B: Donut service only

```env
NID_OCR_DRIVER=donut
NID_DONUT_URL=http://127.0.0.1:8100
```

### Option C: Recommended local reliability

```env
NID_OCR_DRIVER=fallback
NID_DONUT_URL=http://127.0.0.1:8100
```

Then run:

```bash
php artisan config:clear
```

## Donut Microservice

Microservice lives in [`nid-donut-service/`](./nid-donut-service).

See service docs: [`nid-donut-service/README.md`](./nid-donut-service/README.md)

Systemd unit file for Linux deploy: [`deploy/systemd/nid-donut.service`](./deploy/systemd/nid-donut.service)

## API Example

```bash
curl -X POST http://127.0.0.1:8000/api/v1/nid/extract \
  -F "front_image=@/absolute/path/front.jpg" \
  -F "back_image=@/absolute/path/back.jpg"
```

## Privacy and Data Handling

- NID cards contain highly sensitive personal data.
- Do not commit real NID images or raw personal data to git.
- Use synthetic or consented data for testing/training.
- Keep `.env` private; only `.env.example` is meant for repo.

## Current Status

- Good for practice and local experimentation.
- For production-grade accuracy, fine-tune Donut on domain-specific NID data and add stronger validation/monitoring.

## License

MIT. See [`LICENSE`](./LICENSE).
