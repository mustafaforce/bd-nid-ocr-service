<?php

declare(strict_types=1);

namespace App\Infrastructure\Nid\Ocr;

use App\Application\Nid\Contracts\OcrEngine;
use Exception;
use Illuminate\Support\Facades\Log;

final class FallbackOcrEngine implements OcrEngine
{
    public function __construct(
        private readonly DonutOcrEngine $donut,
        private readonly TesseractOcrEngine $tesseract,
    ) {
    }

    public function extractText(string $imagePath, string $languages): string
    {
        try {
            return $this->donut->extractText($imagePath, $languages);
        } catch (Exception $exception) {
            Log::warning('Donut OCR failed, falling back to Tesseract', [
                'error' => $exception->getMessage(),
                'image' => basename($imagePath),
            ]);

            return $this->tesseract->extractText($imagePath, $languages);
        }
    }
}
