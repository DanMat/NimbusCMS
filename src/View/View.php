<?php

declare(strict_types=1);

namespace Panelix\View;

use Panelix\Auth\Auth;
use Panelix\Config\CmsConfig;

/**
 * Renders plain-PHP templates in an isolated scope. Templates get the page data
 * plus $config and $auth (for nav + current user), and use View::e() to escape.
 * render() wraps the template in the admin layout; renderBare() does not.
 */
final class View
{
    public function __construct(
        private string $basePath,
        private CmsConfig $config,
        private Auth $auth,
    ) {
    }

    /** Render a template and wrap it in the admin layout. */
    public function render(string $template, array $data = []): string
    {
        $content = $this->partial($template, $data);
        return $this->partial('layout', array_merge($data, ['__content' => $content]));
    }

    /** Render a template with no surrounding layout (login, errors). */
    public function renderBare(string $template, array $data = []): string
    {
        return $this->partial($template, $data);
    }

    public function partial(string $template, array $data = []): string
    {
        $file = $this->basePath . '/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("Template not found: {$template}");
        }
        $config = $this->config;
        $auth   = $this->auth;

        return (static function () use ($file, $data, $config, $auth): string {
            extract($data, EXTR_SKIP);
            ob_start();
            include $file;
            return (string) ob_get_clean();
        })();
    }

    public static function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
