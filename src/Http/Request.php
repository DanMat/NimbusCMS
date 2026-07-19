<?php

declare(strict_types=1);

namespace Panelix\Http;

/** Read-only view over the current request's method + input. */
final class Request
{
    /**
     * @param array<string,mixed> $get
     * @param array<string,mixed> $post
     */
    public function __construct(
        public readonly string $method,
        private array $get,
        private array $post,
    ) {
    }

    public static function fromGlobals(): self
    {
        return new self(strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'), $_GET, $_POST);
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function query(string $key, ?string $default = null): ?string
    {
        return isset($this->get[$key]) && !is_array($this->get[$key]) ? (string) $this->get[$key] : $default;
    }

    public function input(string $key, ?string $default = null): ?string
    {
        return isset($this->post[$key]) && !is_array($this->post[$key]) ? (string) $this->post[$key] : $default;
    }

    /** @return array<string,mixed> */
    public function all(): array
    {
        return $this->post;
    }
}
