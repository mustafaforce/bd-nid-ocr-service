import json
import logging
import os
import re
from io import BytesIO
from pathlib import Path
from typing import Any

import torch
from dotenv import load_dotenv
from fastapi import FastAPI, File, UploadFile
from fastapi.responses import JSONResponse
from PIL import Image
from transformers import DonutProcessor, VisionEncoderDecoderModel

load_dotenv()

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger("nid-donut-service")

app = FastAPI(title="NID Donut OCR Service", version="1.0.0")

MODEL_PATH = os.getenv("MODEL_PATH", "./models/nid_donut_model")
DEVICE = "cuda" if torch.cuda.is_available() else "cpu"

processor: DonutProcessor | None = None
model: VisionEncoderDecoderModel | None = None


def _to_bool(value: str | None, default: bool = False) -> bool:
    if value is None:
        return default
    return value.strip().lower() in {"1", "true", "yes", "on"}


def _stub_mode_default() -> bool:
    env_value = os.getenv("STUB_MODE")
    if env_value is not None:
        return _to_bool(env_value, default=False)
    return model is None


def _resolve_decoder_start_token_id() -> int:
    if processor is None or model is None:
        raise RuntimeError("Model or processor not initialized")

    vocab = processor.tokenizer.get_vocab()
    task_start_token_id = vocab.get("<s_nid>")
    if task_start_token_id is not None:
        return int(task_start_token_id)

    generation_start = getattr(model.generation_config, "decoder_start_token_id", None)
    if generation_start is not None:
        return int(generation_start)

    top_level_start = getattr(model.config, "decoder_start_token_id", None)
    if top_level_start is not None:
        return int(top_level_start)

    decoder_cfg = getattr(model.config, "decoder", None)
    if decoder_cfg is not None:
        nested_start = getattr(decoder_cfg, "decoder_start_token_id", None)
        if nested_start is not None:
            return int(nested_start)

        nested_bos = getattr(decoder_cfg, "bos_token_id", None)
        if nested_bos is not None:
            return int(nested_bos)

    tokenizer_bos = getattr(processor.tokenizer, "bos_token_id", None)
    if tokenizer_bos is not None:
        return int(tokenizer_bos)

    tokenizer_cls = getattr(processor.tokenizer, "cls_token_id", None)
    if tokenizer_cls is not None:
        return int(tokenizer_cls)

    raise RuntimeError("Could not resolve decoder start token id")


@app.on_event("startup")
def startup_event() -> None:
    global processor, model

    model_path = Path(MODEL_PATH)

    if not model_path.exists():
        processor = None
        model = None
        logger.warning("Model path does not exist: %s", model_path)
        logger.info("Device: %s | model_loaded: false", DEVICE)
        return

    try:
        processor = DonutProcessor.from_pretrained(str(model_path))
        model = VisionEncoderDecoderModel.from_pretrained(str(model_path))
        model.to(DEVICE)
        model.eval()
        logger.info("Donut model loaded from %s", model_path)
        logger.info("Device: %s | model_loaded: true", DEVICE)
    except Exception as exc:
        processor = None
        model = None
        logger.exception("Failed to load model from %s: %s", model_path, exc)
        logger.info("Device: %s | model_loaded: false", DEVICE)


@app.get("/health")
def health() -> dict[str, Any]:
    return {
        "status": "ok",
        "device": DEVICE,
        "model_loaded": model is not None and processor is not None,
        "stub_mode": _stub_mode_default(),
    }


@app.post("/extract")
async def extract(file: UploadFile = File(...)) -> dict[str, Any]:
    try:
        if not file.content_type or not file.content_type.startswith("image/"):
            return JSONResponse(
                status_code=400,
                content={"success": False, "error": "File must be an image"},
            )

        if processor is None or model is None:
            if _stub_mode_default():
                return {
                    "success": True,
                    "stub": True,
                    "data": {
                        "name": "STUB - model not trained yet",
                        "father_name": None,
                        "mother_name": None,
                        "dob": "01/01/2000",
                        "blood_group": None,
                        "address": None,
                        "nid_number": "0000000000",
                        "issue_date": None,
                    },
                }

            return JSONResponse(
                status_code=503,
                content={"success": False, "error": "Model not loaded"},
            )

        image_bytes = await file.read()
        image = Image.open(BytesIO(image_bytes)).convert("RGB")

        pixel_values = processor(image, return_tensors="pt").pixel_values.to(DEVICE)

        task_start_token_id = _resolve_decoder_start_token_id()

        outputs = model.generate(
            pixel_values,
            decoder_start_token_id=task_start_token_id,
            max_length=512,
            num_beams=4,
            early_stopping=True,
        )

        raw_output = processor.batch_decode(outputs, skip_special_tokens=True)[0]

        json_match = re.search(r"\{.*\}", raw_output, re.DOTALL)
        if not json_match:
            return {
                "success": False,
                "error": "Could not parse model output",
                "raw": raw_output,
            }

        try:
            parsed = json.loads(json_match.group(0))
        except json.JSONDecodeError:
            return {
                "success": False,
                "error": "Could not parse model output",
                "raw": raw_output,
            }

        return {
            "success": True,
            "data": {
                "name": parsed.get("name"),
                "father_name": parsed.get("father_name"),
                "mother_name": parsed.get("mother_name"),
                "dob": parsed.get("dob"),
                "blood_group": parsed.get("blood_group"),
                "address": parsed.get("address"),
                "nid_number": parsed.get("nid_number"),
                "issue_date": parsed.get("issue_date"),
            },
        }

    except Exception as exc:
        logger.exception("Unexpected extraction error: %s", exc)
        return JSONResponse(
            status_code=500,
            content={"success": False, "error": "Unexpected extraction failure"},
        )
