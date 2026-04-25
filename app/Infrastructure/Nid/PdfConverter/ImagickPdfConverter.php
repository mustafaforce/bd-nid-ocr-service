<?php

namespace App\Infrastructure\Nid\PdfConverter;

use App\Application\Nid\Contracts\PdfToImageConverterInterface;
use RuntimeException;

final class ImagickPdfConverter implements PdfToImageConverterInterface
{
    public function __construct(
        private int $dpi = 300,
    ) {
    }

    public function isAvailable(): bool
    {
        return extension_loaded('imagick');
    }

    public function convertToImages(string $pdfPath): array
    {
        $imagick = new \Imagick();
        try {
            $imagick->setResolution($this->dpi, $this->dpi);
            $imagick->readImage($pdfPath);

            $tmpDir = sys_get_temp_dir() . '/nid_pdf_' . bin2hex(random_bytes(8));
            if (! mkdir($tmpDir, 0755, true)) {
                throw new RuntimeException('Failed to create temp directory for PDF conversion.');
            }

            $pages = [];
            foreach ($imagick as $i => $page) {
                $page->setImageFormat('png');
                $path = $tmpDir . '/page-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT) . '.png';
                $page->writeImage($path);
                $pages[] = $path;
            }

            return $pages;
        } finally {
            $imagick->destroy();
        }
    }
}