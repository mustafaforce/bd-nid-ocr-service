<?php

namespace App\Infrastructure\Nid\Ocr;

use App\Application\Nid\Contracts\OcrEngine;
use App\Application\Nid\Contracts\PdfToImageConverterInterface;
use RuntimeException;

final readonly class PdfAwareOcrEngine implements OcrEngine
{
    public function __construct(
        private OcrEngine $inner,
        private PdfToImageConverterInterface $pdfConverter,
    ) {
    }

    public function extractText(string $imagePath, string $languages): string
    {
        $extension = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));

        if ($extension !== 'pdf') {
            return $this->inner->extractText($imagePath, $languages);
        }

        if (! $this->pdfConverter->isAvailable()) {
            throw new RuntimeException(
                'PDF input requires a PDF-to-image converter. ' .
                'Install pdftoppm (Poppler), ImageMagick (convert), or enable the imagick PHP extension.'
            );
        }

        $pagePaths = $this->pdfConverter->convertToImages($imagePath);
        if ($pagePaths === []) {
            throw new RuntimeException('PDF conversion produced no images.');
        }

        $outputs = [];
        $tmpDir = dirname($pagePaths[0]);

        foreach ($pagePaths as $pagePath) {
            $text = $this->inner->extractText($pagePath, $languages);
            if ($text !== '') {
                $outputs[] = $text;
            }
            @unlink($pagePath);
        }

        @rmdir($tmpDir);

        return $outputs === [] ? '' : implode("\n\n--- Page Break ---\n\n", $outputs);
    }
}