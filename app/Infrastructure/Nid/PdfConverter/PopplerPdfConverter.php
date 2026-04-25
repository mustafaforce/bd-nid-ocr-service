<?php

namespace App\Infrastructure\Nid\PdfConverter;

use App\Application\Nid\Contracts\PdfToImageConverterInterface;
use RuntimeException;
use Symfony\Component\Process\Process;

final class PopplerPdfConverter implements PdfToImageConverterInterface
{
    public function __construct(
        private string $binary = 'pdftoppm',
        private int $dpi = 300,
    ) {
    }

    public function isAvailable(): bool
    {
        try {
            $process = new Process([$this->binary, '-V']);
            $process->run();
            return $process->isSuccessful();
        } catch (\Throwable) {
            return false;
        }
    }

    public function convertToImages(string $pdfPath): array
    {
        $tmpDir = sys_get_temp_dir() . '/nid_pdf_' . bin2hex(random_bytes(8));
        if (! mkdir($tmpDir, 0755, true)) {
            throw new RuntimeException('Failed to create temp directory for PDF conversion.');
        }

        try {
            $basename = $tmpDir . '/page';
            $process = new Process([
                $this->binary,
                '-r', (string) $this->dpi,
                '-png',
                '-singlefile',
                $pdfPath,
                $basename,
            ]);

            $process->setTimeout(60);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new RuntimeException('pdftoppm failed: ' . $process->getErrorOutput());
            }

            $pages = glob($tmpDir . '/page*.png');
            if ($pages === false) {
                $pages = [];
            }
            sort($pages);

            return $pages;
        } catch (\Throwable $e) {
            $this->cleanupDir($tmpDir);
            throw $e;
        }
    }

    private function cleanupDir(string $dir): void
    {
        $files = glob($dir . '/*');
        if ($files !== false) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }
        @rmdir($dir);
    }
}