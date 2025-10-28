<?php

namespace App\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ProductAiContentParser
{
    public static function normalize(string $contentType, mixed $content): mixed
    {
        return match ($contentType) {
            'usps' => self::parseUsps($content),
            'faq' => self::parseFaq($content),
            default => is_string($content) ? trim($content) : $content,
        };
    }

    /**
     * @return array<int, string>
     */
    public static function parseUsps(mixed $content): array
    {
        if (is_array($content)) {
            return self::sanitizeUspsArray($content);
        }

        if (! is_string($content)) {
            return [];
        }

        $content = trim($content);

        if ($content === '') {
            return [];
        }

        if (self::looksLikeJson($content)) {
            $decoded = json_decode($content, true);

            if (is_array($decoded)) {
                return self::sanitizeUspsArray($decoded);
            }
        }

        $lines = preg_split('/\r\n|\r|\n/u', $content) ?: [];

        $items = array_map(function (string $line): string {
            $line = trim($line);
            $line = preg_replace('/^(?:[-*\x{2022}]+|\d+[\.\)])\s*/u', '', $line) ?? $line;
            $line = trim($line);

            return $line;
        }, $lines);

        return self::sanitizeUspsArray($items);
    }

    /**
     * @return array<int, array{question: string, answer: string}>
     */
    public static function parseFaq(mixed $content): array
    {
        if (is_array($content)) {
            return self::sanitizeFaqArray($content);
        }

        if (! is_string($content)) {
            return [];
        }

        $content = trim($content);

        if ($content === '') {
            return [];
        }

        if (self::looksLikeJson($content)) {
            $decoded = json_decode($content, true);

            if (is_array($decoded)) {
                return self::sanitizeFaqArray($decoded);
            }
        }

        $blocks = preg_split("/\n\s*\n/u", $content) ?: [];
        $entries = [];

        foreach ($blocks as $block) {
            $block = trim($block);

            if ($block === '') {
                continue;
            }

            if (preg_match('/^Q:\s*(.+?)(?:\r?\n|\n)A:\s*(.+)$/is', $block, $matches) === 1) {
                $question = trim($matches[1]);
                $answer = trim($matches[2]);
            } else {
                $lines = preg_split('/\r\n|\r|\n/u', $block) ?: [];
                $question = trim((string) array_shift($lines));
                $answer = trim(implode("\n", $lines));
            }

            if ($question === '' && $answer === '') {
                continue;
            }

            $entries[] = [
                'question' => $question !== '' ? $question : 'Question',
                'answer' => $answer !== '' ? $answer : 'Answer forthcoming.',
            ];
        }

        return self::sanitizeFaqArray($entries);
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<int, string>
     */
    protected static function sanitizeUspsArray(array $items): array
    {
        $items = array_map(function ($value): string {
            if (is_array($value)) {
                $value = Arr::first($value) ?? '';
            }

            return trim((string) $value);
        }, $items);

        $items = array_filter($items, fn ($value) => $value !== '');

        $items = array_values(array_unique($items));

        return $items;
    }

    /**
     * @param  array<int, mixed>  $entries
     * @return array<int, array{question: string, answer: string}>
     */
    protected static function sanitizeFaqArray(array $entries): array
    {
        $normalized = [];

        foreach ($entries as $entry) {
            if (is_string($entry)) {
                $entry = ['question' => '', 'answer' => $entry];
            }

            if (! is_array($entry)) {
                continue;
            }

            $question = trim((string) ($entry['question'] ?? $entry['q'] ?? $entry['prompt'] ?? ''));
            $answer = trim((string) ($entry['answer'] ?? $entry['a'] ?? $entry['response'] ?? ''));

            if ($question === '' && $answer === '') {
                continue;
            }

            if ($answer === '') {
                $answer = 'Answer forthcoming.';
            }

            if ($question === '') {
                $question = Str::limit($answer, 120) ?: 'Question';
            }

            $normalized[] = [
                'question' => $question,
                'answer' => $answer,
            ];
        }

        return $normalized;
    }

    protected static function looksLikeJson(string $value): bool
    {
        $value = trim($value);

        if ($value === '' || ! in_array($value[0], ['[', '{'], true)) {
            return false;
        }

        json_decode($value);

        return json_last_error() === JSON_ERROR_NONE;
    }
}
