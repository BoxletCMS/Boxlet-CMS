<?php

namespace App\Modules\Ai;

use App\Support\RichText;
use Closure;

/**
 * WORDS FROM ONE LANGUAGE INTO ANOTHER (PLAN.md D-248): a page's texts as numbered entries in one
 * JSON object, the same object back with each entry translated.
 *
 * WHAT COMES BACK IS CHECKED, entry by entry, never trusted: the same keys and nothing else; rich
 * text through RichText::sanitize, the boundary every word the owner types passes; plain text
 * with no markup in it; every {{tag}} the original had (D-201) still there; and no word mixing
 * two alphabets (D-257). An entry that
 * fails a check is left out — its original words stay, and the caller says so — rather than
 * written half-right.
 */
final class AiTranslator
{
    /**
     * @param array<string, array{text: string, html: bool}> $texts what to translate, by a key of the caller's
     * @param array{keep: string, voice: string} $words the owner's words never to translate, and how to write
     * @param Closure(string, string): array{text: string, in: int, out: int} $ask one question to the model
     * @return array{done: array<string, string>, failed: list<string>, in: int, out: int}
     */
    public static function translate(array $texts, string $from, string $to, array $words, Closure $ask): array
    {
        $texts = array_filter($texts, static fn (array $t): bool => trim(strip_tags($t['text'])) !== '');
        if ($texts === []) {
            return ['done' => [], 'failed' => [], 'in' => 0, 'out' => 0];
        }
        $numbered = [];
        $keys = [];
        foreach (array_keys($texts) as $n => $key) {
            $numbered[(string) ($n + 1)] = $texts[$key]['text'];
            $keys[(string) ($n + 1)] = $key;
        }
        $answer = $ask(self::system($from, $to, $words), json_encode($numbered, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $back = self::json($answer['text']);
        if ($back === null) {
            throw new AiFailure(t('ai.error.not_json'));
        }

        $done = [];
        $failed = [];
        foreach ($keys as $n => $key) {
            $value = $back[$n] ?? null;
            $original = $texts[$key];
            $clean = is_string($value) ? self::checked($value, $original) : null;
            if ($clean === null) {
                $failed[] = $key;
            } else {
                $done[$key] = $clean;
            }
        }

        return ['done' => $done, 'failed' => $failed, 'in' => $answer['in'], 'out' => $answer['out']];
    }

    /**
     * What the model is told. Its job is the words only: the markup, the {{tags}}, addresses and
     * the owner's own words stay as they are, and nothing is added or left out.
     *
     * @param array{keep: string, voice: string} $words
     */
    public static function system(string $from, string $to, array $words): string
    {
        $lines = [
            "You translate the words of a website from {$from} into {$to}.",
            'You are given a JSON object of numbered texts. Answer with the same JSON object, the same keys, each value translated, and nothing else: no explanation, no code fence.',
            'Some values are HTML. Keep every tag and attribute exactly as it is and translate only the text between them.',
            'Keep every {{…}} tag, web address, email address, phone number and piece of code exactly as written.',
            'Translate the meaning, as a native speaker would write it for this website, in the same register; do not add or leave anything out.',
            'Keep a value that is a name, a brand or a number as it is.',
        ];
        $keep = trim($words['keep']);
        if ($keep !== '') {
            $lines[] = 'Never translate these words and names: ' . preg_replace('~\s*\n\s*~', ', ', $keep) . '.';
        }
        $voice = trim($words['voice']);
        if ($voice !== '') {
            $lines[] = 'How to write: ' . $voice;
        }

        return implode("\n", $lines);
    }

    /**
     * The object in the answer, or null. A model sometimes wraps it in a code fence or a sentence
     * despite being asked not to; and sometimes answers, notices a slip, and answers again —
     * `{…}` "Wait, correcting for typo:" `{…}` (Sonnet 5.5, measured, D-257). Every whole object
     * in the text is found, strings and escapes respected, and the last that reads is the answer:
     * the model's own correction.
     *
     * @return array<int|string, mixed>|null
     */
    private static function json(string $text): ?array
    {
        $objects = [];
        $depth = 0;
        $start = 0;
        $inString = false;
        $length = strlen($text);
        for ($i = 0; $i < $length; $i++) {
            $char = $text[$i];
            if ($inString) {
                if ($char === '\\') {
                    $i++;
                } elseif ($char === '"') {
                    $inString = false;
                }
            } elseif ($char === '"' && $depth > 0) {
                $inString = true;
            } elseif ($char === '{') {
                if ($depth === 0) {
                    $start = $i;
                }
                $depth++;
            } elseif ($char === '}' && $depth > 0) {
                $depth--;
                if ($depth === 0) {
                    $objects[] = substr($text, $start, $i - $start + 1);
                }
            }
        }
        foreach (array_reverse($objects) as $object) {
            $data = json_decode($object, true);
            if (is_array($data)) {
                return $data;
            }
        }

        return null;
    }

    /**
     * The translation as it may be stored, or null when it fails a check.
     *
     * @param array{text: string, html: bool} $original
     */
    private static function checked(string $value, array $original): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        preg_match_all('~\{\{[^}]+\}\}~', $original['text'], $was);
        preg_match_all('~\{\{[^}]+\}\}~', $value, $is);
        $tags = $was[0];
        $now = $is[0];
        sort($tags);
        sort($now);
        if ($tags !== $now) {
            return null;
        }
        // A word half in one alphabet and half in another is a slip, never a translation:
        // "Velики val" for "Veliki val" (Sonnet 5.5, measured, D-257).
        if (preg_match('~\b(?=\p{L}*\p{Latin})(?=\p{L}*[\p{Cyrillic}\p{Greek}])\p{L}+~u', strip_tags($value)) === 1) {
            return null;
        }
        if ($original['html']) {
            return RichText::sanitize($value);
        }

        return $value === strip_tags($value) ? $value : null;
    }
}
