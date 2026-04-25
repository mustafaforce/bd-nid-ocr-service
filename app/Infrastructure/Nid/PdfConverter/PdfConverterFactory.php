<?php

namespace App\Infrastructure\Nid\PdfConverter;

use App\Application\Nid\Contracts\PdfToImageConverterInterface;
use RuntimeException;

final class PdfConverterFactory
{
    /** @var array<string, PdfToImageConverterInterface> */
    private array $instances = [];

    public function __construct(
        private PopplerPdfConverter $poppler,
        private ImageMagickPdfConverter $imagemagick,
        private ImagickPdfConverter $imagick,
    ) {
    }

    public function resolve(string $preference = 'auto'): PdfToImageConverterInterface
    {
        if ($preference !== 'auto' && isset($this->instances[$preference])) {
            return $this->instances[$preference];
        }

        if ($preference === 'auto' || $preference === 'poppler') {
            if ($this->poppler->isAvailable()) {
                return $this->instances['poppler'] ??= $this->poppler;
            }
        }

        if ($preference === 'auto' || $preference === 'imagemagick') {
            if ($this->imagemagick->isAvailable()) {
                return $this->instances['imagemagick'] ??= $this->imagemagick;
            }
        }

        if ($preference === 'auto' || $preference === 'imagick') {
            if ($this->imagick->isAvailable()) {
                return $this->instances['imagick'] ??= $this->imagick;
            }
        }

        throw new RuntimeException(
            'No PDF converter available. Install pdftoppm (Poppler), ImageMagick (convert), or enable the imagick PHP extension.'
        );
    }
}