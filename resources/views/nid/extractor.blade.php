<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} | NID Extractor</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="nid-body">
    <main class="nid-shell" data-nid-extractor-app>
        <section class="nid-hero">
            <p class="nid-eyebrow">Bangladesh NID OCR</p>
            <h1>NID Extraction Console</h1>
            <p>Upload front and back photo. API reads English fields and returns normalized data.</p>
        </section>

        <section class="nid-panel">
            <form class="nid-form" data-nid-form novalidate>
                <div class="nid-grid">
                    <label class="nid-input-card" for="front_image">
                        <span class="nid-input-label">Front Side</span>
                        <input id="front_image" name="front_image" type="file" accept=".heic,.heif,.jpg,.jpeg,.jepg,.png,.webp,image/heic,image/heif,image/jpeg,image/png,image/webp" required>
                        <span class="nid-input-hint">HEIC, HEIF, JPG, JPEG, PNG, WEBP. Max 10MB.</span>
                        <img alt="Front preview" class="nid-preview" data-preview-front>
                    </label>

                    <label class="nid-input-card" for="back_image">
                        <span class="nid-input-label">Back Side</span>
                        <input id="back_image" name="back_image" type="file" accept=".heic,.heif,.jpg,.jpeg,.jepg,.png,.webp,image/heic,image/heif,image/jpeg,image/png,image/webp" required>
                        <span class="nid-input-hint">HEIC, HEIF, JPG, JPEG, PNG, WEBP. Max 10MB.</span>
                        <img alt="Back preview" class="nid-preview" data-preview-back>
                    </label>
                </div>

                <div class="nid-controls">
                    <label class="nid-lang-control" for="ocr_languages">
                        <span>OCR Languages</span>
                        <input id="ocr_languages" name="ocr_languages" type="text" value="eng" maxlength="32" autocomplete="off">
                    </label>

                    <button type="submit" class="nid-submit" data-submit-btn>
                        <span data-submit-text>Extract NID Data</span>
                        <span class="nid-spinner" aria-hidden="true"></span>
                    </button>
                </div>

                <p class="nid-status" data-status role="status" aria-live="polite"></p>
            </form>
        </section>

        <section class="nid-panel nid-result" data-result-section hidden>
            <header class="nid-result-header">
                <h2>Extracted Information</h2>
                <p>Parsed fields from both card sides.</p>
            </header>

            <div class="nid-result-grid" data-result-fields></div>

            <div class="nid-meta-grid">
                <article class="nid-meta-card">
                    <h3>Warnings</h3>
                    <ul data-warning-list></ul>
                </article>

                <article class="nid-meta-card">
                    <h3>Raw OCR Text</h3>
                    <details>
                        <summary>Front Side</summary>
                        <pre data-raw-front></pre>
                    </details>
                    <details>
                        <summary>Back Side</summary>
                        <pre data-raw-back></pre>
                    </details>
                </article>
            </div>
        </section>
    </main>
</body>
</html>
