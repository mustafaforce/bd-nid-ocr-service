const app = document.querySelector('[data-nid-extractor-app]');

if (app) {
    bootstrapNidExtractor(app);
}

function bootstrapNidExtractor(root) {
    const form = root.querySelector('[data-nid-form]');
    const statusEl = root.querySelector('[data-status]');
    const submitBtn = root.querySelector('[data-submit-btn]');
    const submitText = root.querySelector('[data-submit-text]');
    const resultSection = root.querySelector('[data-result-section]');
    const fieldsContainer = root.querySelector('[data-result-fields]');
    const warningList = root.querySelector('[data-warning-list]');
    const rawFront = root.querySelector('[data-raw-front]');
    const rawBack = root.querySelector('[data-raw-back]');

    const frontInput = form.querySelector('#front_image');
    const backInput = form.querySelector('#back_image');
    const frontPreview = root.querySelector('[data-preview-front]');
    const backPreview = root.querySelector('[data-preview-back]');

    let activeRequest = null;

    frontInput.addEventListener('change', () => renderImagePreview(frontInput, frontPreview));
    backInput.addEventListener('change', () => renderImagePreview(backInput, backPreview));

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (!frontInput.files?.length || !backInput.files?.length) {
            setStatus(statusEl, 'error', 'Both front and back images required.');
            return;
        }

        if (activeRequest) {
            activeRequest.abort();
        }

        activeRequest = new AbortController();
        setLoadingState(submitBtn, submitText, true);
        setStatus(statusEl, 'loading', 'Running OCR and parsing fields...');

        const formData = new FormData(form);

        try {
            const response = await fetch('/api/v1/nid/extract', {
                method: 'POST',
                body: formData,
                signal: activeRequest.signal,
            });

            const payload = await response.json();

            if (!response.ok) {
                throw new Error(payload.error || payload.message || 'Extraction failed.');
            }

            renderResult({
                fieldsContainer,
                warningList,
                rawFront,
                rawBack,
                resultSection,
                payload,
            });

            setStatus(statusEl, 'success', 'Extraction complete. Review parsed data below.');
        } catch (error) {
            if (error.name === 'AbortError') {
                setStatus(statusEl, 'warning', 'Previous extraction cancelled.');
            } else {
                setStatus(statusEl, 'error', error.message || 'Something went wrong.');
            }
        } finally {
            setLoadingState(submitBtn, submitText, false);
        }
    });
}

function renderImagePreview(input, previewEl) {
    const file = input.files?.[0];

    if (!file) {
        previewEl.removeAttribute('src');
        previewEl.classList.remove('is-visible');
        return;
    }

    const objectUrl = URL.createObjectURL(file);
    previewEl.src = objectUrl;
    previewEl.classList.add('is-visible');

    previewEl.onload = () => {
        URL.revokeObjectURL(objectUrl);
    };

    previewEl.onerror = () => {
        URL.revokeObjectURL(objectUrl);
        previewEl.removeAttribute('src');
        previewEl.classList.remove('is-visible');
    };
}

function setLoadingState(button, label, loading) {
    button.disabled = loading;
    button.classList.toggle('is-loading', loading);
    label.textContent = loading ? 'Processing...' : 'Extract NID Data';
}

function setStatus(statusEl, type, message) {
    statusEl.textContent = message;
    statusEl.dataset.state = type;
}

function renderResult({ fieldsContainer, warningList, rawFront, rawBack, resultSection, payload }) {
    const rawFrontText = payload?.raw_text?.front || '';
    const rawBackText = payload?.raw_text?.back || '';
    const fallback = buildEnglishFallback(rawFrontText, rawBackText);

    const fields = [
        ['Name', payload?.data?.name || fallback.name],
        ['Father Name', payload?.data?.father_name || fallback.fatherName],
        ['Mother Name', payload?.data?.mother_name || fallback.motherName],
        ['Address', payload?.data?.address || fallback.address],
        ['NID Number', payload?.data?.nid_number || fallback.nidNumber],
        ['Date of Birth', payload?.data?.date_of_birth || fallback.dateOfBirth],
        ['Blood Group', payload?.data?.blood_group || fallback.bloodGroup],
        ['Issue Date', payload?.data?.issue_date || fallback.issueDate],
    ];

    fieldsContainer.innerHTML = '';
    fields.forEach(([label, value]) => {
        const article = document.createElement('article');
        article.className = 'nid-field';

        const heading = document.createElement('h3');
        heading.textContent = label;

        const text = document.createElement('p');
        text.textContent = value || 'Not detected';

        article.append(heading, text);
        fieldsContainer.append(article);
    });

    warningList.innerHTML = '';
    const warnings = Array.isArray(payload?.warnings) ? payload.warnings : [];

    if (!warnings.length) {
        const li = document.createElement('li');
        li.textContent = 'No warning.';
        warningList.append(li);
    } else {
        warnings.forEach((warning) => {
            const li = document.createElement('li');
            li.textContent = warning;
            warningList.append(li);
        });
    }

    rawFront.textContent = rawFrontText || 'No raw front text.';
    rawBack.textContent = rawBackText || 'No raw back text.';

    resultSection.hidden = false;
}

function buildEnglishFallback(rawFrontText, rawBackText) {
    const front = toLines(rawFrontText);
    const back = toLines(rawBackText);
    const all = [...front, ...back];

    return {
        name: extractAfterKeywords(front, ['name']),
        fatherName: extractAfterKeywords(front, ['father']),
        motherName: extractAfterKeywords(front, ['mother']),
        address: extractAfterKeywords(back, ['address'], true),
        nidNumber: extractNumber(all),
        dateOfBirth: extractDate(front, ['date of birth', 'dob']),
        bloodGroup: extractBloodGroup(all),
        issueDate: extractDate(back, ['date of issue', 'issue date', 'issued']),
    };
}

function toLines(text) {
    return String(text || '')
        .split(/\r?\n/)
        .map((line) => line.trim())
        .filter(Boolean);
}

function extractAfterKeywords(lines, keywords, allowMulti = false) {
    const keys = keywords.map((k) => k.toLowerCase());

    for (let i = 0; i < lines.length; i += 1) {
        const line = lines[i];
        const lower = line.toLowerCase();
        if (!keys.some((k) => lower.includes(k))) {
            continue;
        }

        const inline = cleanValue(line.replace(/^.*?[:ঃ-]\s*/u, ''));
        if (inline) {
            return inline;
        }

        const next = cleanValue(lines[i + 1] || '');
        if (!next) {
            continue;
        }

        if (!allowMulti) {
            return next;
        }

        const next2 = cleanValue(lines[i + 2] || '');
        return next2 ? `${next}, ${next2}` : next;
    }

    return null;
}

function extractNumber(lines) {
    const text = lines.join(' ').replace(/(?<=\d)\s+(?=\d)/g, '');
    const list = text.match(/\b\d{10,17}\b/g);
    if (!list?.length) {
        return null;
    }

    return list.sort((a, b) => b.length - a.length)[0];
}

function extractDate(lines, hints) {
    const hintSet = hints.map((h) => h.toLowerCase());

    for (const line of lines) {
        const lower = line.toLowerCase();
        if (!hintSet.some((h) => lower.includes(h))) {
            continue;
        }

        const parsed = parseDate(line);
        if (parsed) {
            return parsed;
        }
    }

    return null;
}

function parseDate(line) {
    const m1 = line.match(/\b(\d{1,2})[./-](\d{1,2})[./-](\d{2,4})\b/);
    if (m1) {
        const d = m1[1].padStart(2, '0');
        const m = m1[2].padStart(2, '0');
        const y = m1[3].length === 2 ? `20${m1[3]}` : m1[3];
        return `${d}/${m}/${y}`;
    }

    const m2 = line.match(/\b(\d{1,2})\s*([A-Za-z]{3,9})\s*(\d{2,4})\b/);
    if (!m2) {
        return null;
    }

    const mm = {
        jan: '01', january: '01',
        feb: '02', february: '02',
        mar: '03', march: '03',
        apr: '04', april: '04',
        may: '05',
        jun: '06', june: '06',
        jul: '07', july: '07',
        aug: '08', august: '08',
        sep: '09', sept: '09', september: '09',
        oct: '10', october: '10',
        nov: '11', november: '11',
        dec: '12', december: '12',
    };

    const mon = mm[m2[2].toLowerCase()];
    if (!mon) {
        return null;
    }

    const d = m2[1].padStart(2, '0');
    const y = m2[3].length === 2 ? `20${m2[3]}` : m2[3];
    return `${d}/${mon}/${y}`;
}

function extractBloodGroup(lines) {
    const text = lines.join(' ').toUpperCase().replace(/ABT/g, 'AB+').replace(/AT/g, 'A+').replace(/BT/g, 'B+').replace(/OT/g, 'O+');
    const m = text.match(/\b(AB|A|B|O)\s*([+-])\b/);
    return m ? `${m[1]}${m[2]}` : null;
}

function cleanValue(value) {
    const cleaned = String(value || '')
        .replace(/^[\s:ঃ\-|>~`]+/u, '')
        .replace(/\s{2,}/g, ' ')
        .trim();

    return cleaned.length >= 2 ? cleaned : null;
}
