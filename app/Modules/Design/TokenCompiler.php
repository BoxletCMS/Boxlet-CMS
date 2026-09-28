<?php

namespace App\Modules\Design;

use InvalidArgumentException;
use RuntimeException;

/**
 * Compiles design tokens into a stylesheet of CSS custom properties. The file name
 * carries a hash of its content, tokens.{hash}.css, so a changed design is a new URL
 * that no browser or proxy has cached. tokens.css is never a static file.
 */
final class TokenCompiler
{
    /**
     * Writes tokens.{hash}.css into $directory unless it is already there, removes older
     * token stylesheets, and returns the file name.
     *
     * @param array<mixed> $tokens  group => name => scalar value, emitted as --group-name
     * @param string       $prelude CSS placed before the properties, such as @font-face rules
     */
    public function compile(array $tokens, string $directory, string $prelude = ''): string
    {
        $css = $this->css($tokens, $prelude);
        $file = 'tokens.' . substr(hash('sha256', $css), 0, 12) . '.css';
        $target = $directory . '/' . $file;

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException("Cannot create directory {$directory}");
        }
        if (!is_file($target)) {
            $temporary = $target . '.' . bin2hex(random_bytes(4)) . '.tmp';
            if (file_put_contents($temporary, $css) === false || !rename($temporary, $target)) {
                throw new RuntimeException("Cannot write {$target}");
            }
        }
        foreach (glob($directory . '/tokens*.css') ?: [] as $old) {
            if (basename($old) !== $file) {
                unlink($old);
            }
        }

        return $file;
    }

    /**
     * The stylesheet itself, for compile() and for the admin's live preview.
     *
     * Values are validated rather than trusted from the type: from Slice 4 they come
     * from the design_tokens table.
     *
     * @param array<mixed> $tokens
     */
    public function css(array $tokens, string $prelude = ''): string
    {
        $lines = [];
        foreach ($tokens as $group => $values) {
            if (!is_array($values)) {
                throw new InvalidArgumentException("Token group {$group} must be an array");
            }
            foreach ($values as $name => $value) {
                $property = $group . '-' . $name;
                if (!preg_match('~^[a-z][a-z0-9]*(-[a-z0-9]+)+$~', $property)) {
                    throw new InvalidArgumentException("Invalid token name: {$property}");
                }
                if (!is_scalar($value) || preg_match('~[;{}<>\\\\]~', (string) $value)) {
                    throw new InvalidArgumentException("Invalid value for token {$property}");
                }
                $lines[] = sprintf('  --%s: %s;', $property, $value);
            }
        }

        return "/* Generated from design tokens. Do not edit; changes are overwritten. */\n"
            . $prelude . ":root {\n" . implode("\n", $lines) . "\n}\n";
    }
}
