<?php

declare(strict_types=1);

namespace Nimbus\Http;

/**
 * Carries a Response to be sent instead of continuing. Used for auth/permission
 * short-circuits (redirects, 403s) so guard/require helpers can bail out of an
 * action without threading nullable returns through every call site. The kernel
 * catches it and sends the response.
 */
final class HttpException extends \RuntimeException
{
    public function __construct(public readonly Response $response)
    {
        parent::__construct('HTTP ' . $response->status);
    }

    public static function redirect(string $to): self
    {
        return new self(Response::redirect($to));
    }
}
