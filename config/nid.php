<?php

return [
    'ocr' => [
        'driver' => env('NID_OCR_DRIVER', 'tesseract'),
        'tesseract' => [
            'binary' => env('NID_TESSERACT_BINARY', 'tesseract'),
            'languages' => env('NID_OCR_LANGUAGES', 'eng'),
            'psm' => env('NID_TESSERACT_PSM', '6'),
            'psm_candidates' => env('NID_TESSERACT_PSM_CANDIDATES', '6,11'),
            'preprocess_enabled' => env('NID_OCR_PREPROCESS_ENABLED', true),
            'max_variants' => (int) env('NID_OCR_MAX_VARIANTS', 2),
            'max_runs_per_image' => (int) env('NID_OCR_MAX_RUNS_PER_IMAGE', 2),
            'process_timeout_seconds' => (float) env('NID_OCR_PROCESS_TIMEOUT_SECONDS', 5),
            'process_idle_timeout_seconds' => (float) env('NID_OCR_PROCESS_IDLE_TIMEOUT_SECONDS', 5),
        ],
    ],

    'upload' => [
        'disk' => env('NID_UPLOAD_DISK', 'local'),
        'directory' => env('NID_UPLOAD_DIRECTORY', 'nid-uploads'),
        'max_size_kb' => (int) env('NID_UPLOAD_MAX_SIZE_KB', 10240),
    ],
];
