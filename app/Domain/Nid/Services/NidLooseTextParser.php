<?php

namespace App\Domain\Nid\Services;

final class NidLooseTextParser
{
    /**
     * @return array{name:?string,father_name:?string,mother_name:?string,address:?string,nid_number:?string,date_of_birth:?string,blood_group:?string,issue_date:?string}
     */
    public function parse(string $rawText): array
    {
        $lines = $this->normalizeToLines($rawText);

        return [
            'name' => $this->extractName($lines),
            'father_name' => $this->extractFatherName($lines),
            'mother_name' => $this->extractMotherName($lines),
            'address' => $this->extractAddress($lines),
            'nid_number' => $this->extractNidNumber($lines),
            'date_of_birth' => $this->extractDateOfBirth($lines),
            'blood_group' => $this->extractBloodGroup($lines),
            'issue_date' => $this->extractIssueDate($lines),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function normalizeToLines(string $text): array
    {
        $text = str_replace(
            ['০','১','২','৩','৪','৫','৬','৭','৮','৯'],
            ['0','1','2','3','4','5','6','7','8','9'],
            $text,
        );

        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = str_replace(['।।', '|', '\\', '—', '–', '−'], ['.', '', ' ', '-', '-', '-'], $text);
        $text = preg_replace('/[_=~`]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;

        $lines = array_map('trim', explode("\n", $text));

        return array_values(array_filter($lines, static fn (string $l): bool => $l !== ''));
    }

    /**
     * @param  array<int, string>  $lines
     * @param  array<int, string>  $patterns
     */
    private function tryPatterns(array $lines, array $patterns): ?string
    {
        foreach ($patterns as $pattern) {
            foreach ($lines as $i => $line) {
                if (! preg_match($pattern, $line, $m)) {
                    continue;
                }

                $v = trim($m[1] ?? '');
                if ($v !== '') {
                    return $v;
                }

                if (isset($lines[$i + 1])) {
                    $nextLine = trim($lines[$i + 1]);
                    if ($nextLine !== '' && ! $this->isFieldLabel($nextLine)) {
                        return $nextLine;
                    }
                }
            }
        }

        return null;
    }

    private function isFieldLabel(string $line): bool
    {
        return (bool) preg_match('/^(?:নাম|পিতা|মাতা|ঠিকানা|জন্ম|NID|এনআইডি|blood|রক্ত|issue|father|mother|address|date|name)/iu', trim($line));
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function findLineIndex(array $lines, string $pattern): ?int
    {
        foreach ($lines as $i => $line) {
            if (preg_match($pattern, $line)) {
                return $i;
            }
        }

        return null;
    }

    private function cleanPersonName(string $raw): string
    {
        $name = trim($raw);
        $name = preg_replace('/^(?:মোঃ|মো:|মো\.|মিঃ|Md\.?|MD|Mr\.?|Mrs\.?|Ms\.?|Dr\.?)\s*/iu', '', $name) ?? $name;
        $name = preg_replace('/^[:ঃ\-.]+/u', '', $name);
        // Allow Bengali letters, combining marks (vowel signs), Arabic numbers, spaces, hyphens, dots, apostrophes
        $name = preg_replace('/[^\p{L}\p{M}\p{N}\s\-\.\'\x{2019}]/u', '', $name) ?? $name;
        $name = trim($name);

        if ($name === mb_strtoupper($name) && preg_match('/[A-Z]/', $name)) {
            $name = mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');
        }

        return $name;
    }

    /** @param array<int, string> $lines */
    private function extractName(array $lines): ?string
    {
        $raw = $this->tryPatterns($lines, [
            '/নাম\s*[:\-ঃ]?\s*([^\n]+)/u',
            '/\bName\s*[:\-]?\s*([^\n]+)/iu',
            '/^NAME[:\s]+([^\n]+)/u',
        ]);

        if ($raw === null) {
            $idx = $this->findLineIndex($lines, '/নাম|name/iu');
            if ($idx !== null && isset($lines[$idx + 1])) {
                $raw = $lines[$idx + 1];
            }
        }

        if ($raw === null) {
            return null;
        }

        $raw = preg_replace('/\s*(?:পিতা|মাতা|father|mother|জন্ম|dob|address|ঠিকানা).*/iu', '', $raw) ?? $raw;
        $clean = $this->cleanPersonName($raw);

        return $clean !== '' ? $clean : null;
    }

    /** @param array<int, string> $lines */
    private function extractFatherName(array $lines): ?string
    {
        $raw = $this->tryPatterns($lines, [
            '/পিতা[র]?\s*(?:নাম)?\s*[:\-ঃ]?\s*(.+)/u',
            '/\bFather[\x{2019}\'`s]*\s*(?:Name)?\s*[:\-]?\s*(.+)/iu',
        ]);

        if ($raw === null) {
            $idx = $this->findLineIndex($lines, '/পিতা|father/iu');
            if ($idx !== null && isset($lines[$idx + 1])) {
                $raw = $lines[$idx + 1];
            }
        }

        if ($raw === null) {
            return null;
        }

        $raw = preg_replace('/\s*(?:মাতা|মা|mother|ঠিকানা|address|জন্ম|dob).*/iu', '', $raw) ?? $raw;
        $clean = $this->cleanPersonName($raw);

        return $clean !== '' ? $clean : null;
    }

    /** @param array<int, string> $lines */
    private function extractMotherName(array $lines): ?string
    {
        $raw = $this->tryPatterns($lines, [
            '/মাতা[র]?\s*(?:নাম)?\s*[:\-ঃ]?\s*(.+)/u',
            '/\bMother[\x{2019}\'`s]*\s*(?:Name)?\s*[:\-]?\s*(.+)/iu',
        ]);

        if ($raw === null) {
            $idx = $this->findLineIndex($lines, '/মাতা|mother/iu');
            if ($idx !== null && isset($lines[$idx + 1])) {
                $raw = $lines[$idx + 1];
            }
        }

        if ($raw === null) {
            return null;
        }

        $raw = preg_replace('/\s*(?:ঠিকানা|address|জন্ম|dob|পিতা|father|blood|রক্ত).*/iu', '', $raw) ?? $raw;
        $clean = $this->cleanPersonName($raw);

        return $clean !== '' ? $clean : null;
    }

    /** @param array<int, string> $lines */
    private function extractAddress(array $lines): ?string
    {
        $raw = $this->tryPatterns($lines, [
            '/ঠিকানা\s*[:\-ঃ]?\s*(.+)/u',
            '/বর্তমান\s*ঠিকানা\s*[:\-ঃ]?\s*(.+)/u',
            '/স্থায়ী\s*ঠিকানা\s*[:\-ঃ]?\s*(.+)/u',
            '/\bAddress\s*[:\-]?\s*(.+)/iu',
        ]);

        if ($raw === null) {
            $idx = $this->findLineIndex($lines, '/ঠিকানা|address/iu');
            if ($idx !== null) {
                $parts = [];
                for ($j = $idx + 1; $j <= $idx + 3 && isset($lines[$j]); $j++) {
                    if (preg_match('/^(?:নাম|পিতা|মাতা|জন্ম|NID|এনআইডি|blood|রক্ত|issue)/iu', $lines[$j])) {
                        break;
                    }
                    $parts[] = trim($lines[$j]);
                }
                $raw = implode(', ', array_filter($parts));
            }
        }

        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $raw = preg_replace('/\s*(?:এনআইডি|NID|জন্ম তারিখ|Date of Birth|DOB|blood|রক্ত|issue).*/iu', '', $raw) ?? $raw;

        return trim(preg_replace('/^[:ঃ\-., ]+/u', '', $raw)) ?: null;
    }

    /** @param array<int, string> $lines */
    private function extractNidNumber(array $lines): ?string
    {
        $patterns = [
            '/এনআইডি\s*(?:নং|নম্বর|নo)?\s*[:\-ঃ]?\s*([0-9]{10,17})/u',
            '/\bNID\s*(?:No\.?|Number|#)?\s*[:\-]?\s*([0-9]{10,17})/iu',
            '/National\s*ID\s*(?:No\.?|Number)?\s*[:\-]?\s*([0-9]{10,17})/iu',
            '/\bID\s*No\.?\s*[:\-]?\s*([0-9]{10,17})/iu',
            '/\b([0-9]{10,17})\b/u',
        ];

        foreach ($patterns as $pattern) {
            foreach ($lines as $line) {
                if (! preg_match($pattern, $line, $m)) {
                    continue;
                }

                $candidate = preg_replace('/\D/', '', $m[1] ?? '') ?? '';
                if (strlen($candidate) >= 10 && strlen($candidate) <= 17) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    /** @param array<int, string> $lines */
    private function extractDateOfBirth(array $lines): ?string
    {
        $raw = $this->tryPatterns($lines, [
            '/জন্ম\s*তারিখ\s*[:\-ঃ]?\s*(\d{1,2}[\s\-\/.]\d{1,2}[\s\-\/.]\d{2,4})/u',
            '/(?:Date\s*of\s*Birth|DOB|D\.O\.B\.?)\s*[:\-]?\s*(\d{1,2}[\s\-\/.]\d{1,2}[\s\-\/.]\d{2,4})/iu',
            '/\b(\d{4}[\-\/.]\d{1,2}[\-\/.]\d{1,2})\b/u',
            '/\b(\d{2}[\-\/.]\d{2}[\-\/.]\d{4})\b/u',
        ]);

        return $raw !== null ? $this->normalizeDateString($raw) : null;
    }

    /** @param array<int, string> $lines */
    private function extractIssueDate(array $lines): ?string
    {
        $raw = $this->tryPatterns($lines, [
            '/ইস্যু\s*তারিখ\s*[:\-ঃ]?\s*(\d{1,2}[\s\-\/.]\d{1,2}[\s\-\/.]\d{2,4})/u',
            '/Issue\s*Date\s*[:\-]?\s*(\d{1,2}[\s\-\/.]\d{1,2}[\s\-\/.]\d{2,4})/iu',
            '/Date\s*of\s*Issue\s*[:\-]?\s*(\d{1,2}[\s\-\/.]\d{1,2}[\s\-\/.]\d{2,4})/iu',
        ]);

        if ($raw === null) {
            $joined = implode(' ', $lines);
            preg_match_all('/\b(\d{1,2}[\-\/.]\d{1,2}[\-\/.]\d{2,4}|\d{4}[\-\/.]\d{1,2}[\-\/.]\d{1,2})\b/u', $joined, $allDates);
            if (isset($allDates[1][1])) {
                $raw = $allDates[1][1];
            }
        }

        return $raw !== null ? $this->normalizeDateString($raw) : null;
    }

    /** @param array<int, string> $lines */
    private function extractBloodGroup(array $lines): ?string
    {
        $raw = $this->tryPatterns($lines, [
            '/রক্তের?\s*গ্রুপ\s*[:\-ঃ]?\s*([ABO]{1,2}[+-])/u',
            '/Blood\s*Group\s*[:\-]?\s*([ABO]{1,2}[+-])/iu',
            '/\bGroup\s*[:\-]?\s*([ABO]{1,2}[+-])/iu',
            '/\b(A[B]?[+-]|B[+-]|O[+-]|AB[+-])\b/i',
        ]);

        if ($raw === null) {
            return null;
        }

        $normalized = strtoupper(trim($raw));
        $normalized = str_replace(['＋', 'Positive', 'positive', '+ve', '-ve', 'AT', 'BT', 'OT', 'ABT'], ['+','+','+','+','-','A+','B+','O+','AB+'], $normalized);

        return in_array($normalized, ['A+','A-','B+','B-','AB+','AB-','O+','O-'], true) ? $normalized : null;
    }

    private function normalizeDateString(string $raw): string
    {
        $raw = preg_replace('/\s*([\-\/.])\s*/u', '$1', trim($raw)) ?? trim($raw);

        if (preg_match('/^(\d{4})[\-\/.](\d{1,2})[\-\/.](\d{1,2})$/', $raw, $m)) {
            return sprintf('%02d/%02d/%04d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }

        if (preg_match('/^(\d{1,2})[\-\/.](\d{1,2})[\-\/.](\d{2})$/', $raw, $m)) {
            $year = (int) $m[3] < 30 ? 2000 + (int) $m[3] : 1900 + (int) $m[3];
            return sprintf('%02d/%02d/%04d', (int) $m[1], (int) $m[2], $year);
        }

        if (preg_match('/^(\d{1,2})[\-\/.](\d{1,2})[\-\/.](\d{4})$/', $raw, $m)) {
            return sprintf('%02d/%02d/%04d', (int) $m[1], (int) $m[2], (int) $m[3]);
        }

        return $raw;
    }
}
