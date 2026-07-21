<?php

declare(strict_types=1);

namespace Nimbus\Http;

/**
 * Which upstream addresses are allowed to speak for the client via
 * X-Forwarded-* headers.
 *
 * The default trusts nothing: forwarded headers are ignored entirely, because
 * anyone can send them. Deployments behind a load balancer opt in by listing
 * the proxy's address in TRUSTED_PROXIES (plain IPs or CIDR ranges, comma
 * separated) — e.g. the Docker bridge network `172.16.0.0/12`.
 *
 * One decision, used everywhere: session cookie `secure`, login throttling
 * keys, and any future API request logging.
 */
final class TrustedProxies
{
    /** @var string[] plain IPs or CIDR blocks */
    private array $ranges;

    /** @param string[] $ranges */
    public function __construct(array $ranges = [])
    {
        $this->ranges = array_values(array_filter(array_map('trim', $ranges), static fn (string $r): bool => $r !== ''));
    }

    /** Parse the comma-separated TRUSTED_PROXIES form. */
    public static function fromString(?string $raw): self
    {
        return new self($raw === null || trim($raw) === '' ? [] : explode(',', $raw));
    }

    public function isEmpty(): bool
    {
        return $this->ranges === [];
    }

    public function trusts(string $ip): bool
    {
        if ($ip === '' || $this->ranges === []) {
            return false;
        }
        foreach ($this->ranges as $range) {
            if (self::matches($ip, $range)) {
                return true;
            }
        }
        return false;
    }

    private static function matches(string $ip, string $range): bool
    {
        if (!str_contains($range, '/')) {
            return $ip === $range;
        }

        [$subnet, $bits] = explode('/', $range, 2);
        $ipBin     = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);
        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
            return false; // unparseable, or mixing IPv4 with IPv6
        }

        $prefix = (int) $bits;
        if ($prefix < 0 || $prefix > strlen($ipBin) * 8) {
            return false;
        }

        // Compare whole bytes, then the remaining bits of the boundary byte.
        $whole     = intdiv($prefix, 8);
        $remainder = $prefix % 8;
        if ($whole > 0 && substr($ipBin, 0, $whole) !== substr($subnetBin, 0, $whole)) {
            return false;
        }
        if ($remainder === 0) {
            return true;
        }
        $mask = ~((1 << (8 - $remainder)) - 1) & 0xFF;
        return (ord($ipBin[$whole]) & $mask) === (ord($subnetBin[$whole]) & $mask);
    }
}
