<?php

namespace App\Core;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Plain PHP templates. Every render knows its locale; there is no way to render
 * a page without one.
 */
final class View
{
    /**
     * @param string      $directory       where templates live
     * @param string|null $layoutDirectory where the layout lives, when another module owns it
     */
    public function __construct(
        private readonly string $directory,
        private readonly ?string $layoutDirectory = null,
    ) {
    }

    /**
     * Render $template, then wrap it in $layout (null for no layout). The template's
     * output reaches the layout as $content.
     *
     * @param array<string, mixed> $data
     */
    public function render(string $template, string $locale, array $data = [], ?string $layout = 'layout'): string
    {
        $data = ['locale' => $locale] + $data;
        $content = $this->renderFile($this->directory, $template, $data);

        if ($layout === null) {
            return $content;
        }

        return $this->renderFile($this->layoutDirectory ?? $this->directory, $layout, ['content' => $content] + $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderFile(string $directory, string $template, array $data): string
    {
        if (!preg_match('~^[a-z0-9_-]+(/[a-z0-9_-]+)*$~', $template)) {
            throw new InvalidArgumentException("Invalid template name: {$template}");
        }
        $file = $directory . '/' . $template . '.php';
        if (!is_file($file)) {
            throw new RuntimeException("Template not found: {$template}");
        }

        $render = static function (string $__file, array $__data): void {
            extract($__data, EXTR_SKIP);
            require $__file;
        };

        ob_start();
        try {
            $render($file, $data);
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return (string) ob_get_clean();
    }
}
