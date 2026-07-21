<?php

declare(strict_types=1);

namespace Nimbus\Http;

/**
 * An immutable HTTP response. Controllers build one and return it; the kernel
 * sends it. This keeps header()/echo/exit out of application code — there is
 * exactly one place output happens (send()).
 */
final class Response
{
    /** @param array<string,string> $headers */
    private function __construct(
        public readonly int $status,
        public readonly string $body = '',
        public readonly array $headers = [],
    ) {
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($status, $body, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public static function redirect(string $to, int $status = 302): self
    {
        return new self($status, '', ['Location' => $to]);
    }

    public static function json(mixed $data, int $status = 200): self
    {
        return new self($status, json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), ['Content-Type' => 'application/json']);
    }

    public static function download(string $content, string $filename, string $contentType = 'application/octet-stream'): self
    {
        return new self(200, $content, [
            'Content-Type'        => $contentType,
            'Content-Disposition' => 'attachment; filename="' . str_replace('"', '', $filename) . '"',
        ]);
    }

    public function withHeader(string $name, string $value): self
    {
        return new self($this->status, $this->body, [...$this->headers, $name => $value]);
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header("{$name}: {$value}");
            }
        }
        echo $this->body;
    }
}
