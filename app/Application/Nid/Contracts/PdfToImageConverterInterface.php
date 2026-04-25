<?php

namespace App\Application\Nid\Contracts;

interface PdfToImageConverterInterface
{
    /**
     * Returns true if the converter is available on this system.
     */
    public function isAvailable(): bool;

    /**
     * Convert PDF pages to images and return array of absolute image paths.
     * The caller is responsible for cleaning up returned paths.
     *
     * @return array<int, string> Ordered list of image paths (one per page)
     * @throws \RuntimeException if conversion fails
     */
    public function convertToImages(string $pdfPath): array;
}