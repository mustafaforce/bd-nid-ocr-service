<?php

declare(strict_types=1);

namespace App\Infrastructure\Nid\Ocr;

use App\Application\Nid\Contracts\OcrEngine;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class DonutOcrEngine implements OcrEngine
{
    private string $baseUrl;

    private int $timeout;

    private int $healthCheckTimeout;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('nid.ocr.donut.url', 'http://127.0.0.1:8100'), '/');
        $this->timeout = (int) config('nid.ocr.donut.timeout', 30);
        $this->healthCheckTimeout = (int) config('nid.ocr.donut.health_check_timeout', 3);
    }

    public function extractText(string $imagePath, string $languages): string
    {
        if (! is_file($imagePath)) {
            throw new RuntimeException('Image file not found for Donut OCR.');
        }

        if (! $this->isServiceHealthy()) {
            throw new RuntimeException('Donut service unavailable');
        }

        $response = Http::timeout($this->timeout)
            ->attach('file', file_get_contents($imagePath), basename($imagePath))
            ->post("{$this->baseUrl}/extract");

        if (! $response->successful()) {
            Log::error('Donut OCR extract request failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException("Donut extract failed with status {$response->status()}");
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new RuntimeException('Invalid response from Donut service.');
        }

        if (($payload['success'] ?? false) !== true) {
            $error = (string) ($payload['error'] ?? 'Donut extraction failed.');
            $raw = $payload['raw'] ?? null;

            // Donut base (not NID-finetuned) can output plain text instead of JSON.
            // In that case, pass raw text forward so existing parser can still attempt extraction.
            if (
                is_string($raw)
                && trim($raw) !== ''
                && str_contains(mb_strtolower($error), 'could not parse model output')
            ) {
                Log::warning('Donut OCR returned non-JSON output. Using raw fallback text.', [
                    'error' => $error,
                ]);

                return trim($raw);
            }

            throw new RuntimeException($error);
        }

        $data = $payload['data'] ?? [];
        if (! is_array($data)) {
            throw new RuntimeException('Invalid data payload from Donut service.');
        }

        $lines = [];

        if (! empty($data['name'])) {
            $lines[] = "Name: {$data['name']}";
        }

        if (! empty($data['father_name'])) {
            $lines[] = "Father: {$data['father_name']}";
        }

        if (! empty($data['mother_name'])) {
            $lines[] = "Mother: {$data['mother_name']}";
        }

        if (! empty($data['dob'])) {
            $lines[] = "Date of Birth: {$data['dob']}";
        }

        if (! empty($data['blood_group'])) {
            $lines[] = "Blood Group: {$data['blood_group']}";
        }

        if (! empty($data['address'])) {
            $lines[] = "Address: {$data['address']}";
        }

        if (! empty($data['nid_number'])) {
            $lines[] = "NID: {$data['nid_number']}";
        }

        if (! empty($data['issue_date'])) {
            $lines[] = "Issue Date: {$data['issue_date']}";
        }

        return implode("\n", $lines);
    }

    private function isServiceHealthy(): bool
    {
        try {
            $response = Http::timeout($this->healthCheckTimeout)->get("{$this->baseUrl}/health");

            if (! $response->successful()) {
                return false;
            }

            $payload = $response->json();
            if (! is_array($payload)) {
                return false;
            }

            $modelLoaded = (bool) ($payload['model_loaded'] ?? false);
            $stubMode = (bool) ($payload['stub_mode'] ?? false);
            $stub = (bool) ($payload['stub'] ?? false);

            return $modelLoaded || $stubMode || $stub;
        } catch (\Throwable $exception) {
            Log::error('Donut OCR health check failed.', [
                'error' => $exception->getMessage(),
                'url' => "{$this->baseUrl}/health",
            ]);

            return false;
        }
    }
}
