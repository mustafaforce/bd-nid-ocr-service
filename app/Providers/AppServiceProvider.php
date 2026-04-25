<?php

namespace App\Providers;

use App\Application\Nid\Contracts\OcrEngine;
use App\Infrastructure\Nid\Ocr\DonutOcrEngine;
use App\Infrastructure\Nid\Ocr\FallbackOcrEngine;
use App\Infrastructure\Nid\Ocr\TesseractOcrEngine;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(TesseractOcrEngine::class, function (): TesseractOcrEngine {
            return new TesseractOcrEngine(
                binary: (string) config('nid.ocr.tesseract.binary', 'tesseract'),
                psm: (string) config('nid.ocr.tesseract.psm', '6'),
                psmCandidates: (string) config('nid.ocr.tesseract.psm_candidates', '6,11'),
                preprocessEnabled: (bool) config('nid.ocr.tesseract.preprocess_enabled', true),
                maxVariants: (int) config('nid.ocr.tesseract.max_variants', 2),
                maxRunsPerImage: (int) config('nid.ocr.tesseract.max_runs_per_image', 2),
                processTimeoutSeconds: (float) config('nid.ocr.tesseract.process_timeout_seconds', 5),
                processIdleTimeoutSeconds: (float) config('nid.ocr.tesseract.process_idle_timeout_seconds', 5),
            );
        });

        $this->app->bind(OcrEngine::class, function ($app): OcrEngine {
            $driver = config('nid.ocr.driver');

            return match ($driver) {
                'donut' => $app->make(DonutOcrEngine::class),
                'fallback' => $app->make(FallbackOcrEngine::class),
                default => $app->make(TesseractOcrEngine::class),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
