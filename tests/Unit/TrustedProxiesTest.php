<?php

declare(strict_types=1);

namespace Nimbus\Tests\Unit;

use Nimbus\Http\Request;
use Nimbus\Http\TrustedProxies;
use PHPUnit\Framework\TestCase;

final class TrustedProxiesTest extends TestCase
{
    /** @param array<string,string> $server */
    private function request(array $server, ?TrustedProxies $proxies = null): Request
    {
        return new Request('GET', '/', [], [], $server, [], $proxies);
    }

    // ------------------------------------------------------------- matching

    public function test_nothing_is_trusted_by_default(): void
    {
        $p = new TrustedProxies();

        self::assertTrue($p->isEmpty());
        self::assertFalse($p->trusts('10.0.0.1'));
        self::assertFalse($p->trusts('127.0.0.1'));
    }

    public function test_exact_ip_match(): void
    {
        $p = new TrustedProxies(['10.0.0.1']);

        self::assertTrue($p->trusts('10.0.0.1'));
        self::assertFalse($p->trusts('10.0.0.2'));
    }

    public function test_cidr_match(): void
    {
        $p = new TrustedProxies(['172.16.0.0/12']);

        self::assertTrue($p->trusts('172.16.0.1'));
        self::assertTrue($p->trusts('172.20.10.5'));
        self::assertTrue($p->trusts('172.31.255.254'));
        self::assertFalse($p->trusts('172.32.0.1'), 'just past the /12 boundary');
        self::assertFalse($p->trusts('192.168.1.1'));
    }

    public function test_non_byte_aligned_prefix(): void
    {
        $p = new TrustedProxies(['192.168.1.0/25']);

        self::assertTrue($p->trusts('192.168.1.127'));
        self::assertFalse($p->trusts('192.168.1.128'));
    }

    public function test_ipv6_and_mixed_families(): void
    {
        $p = new TrustedProxies(['2001:db8::/32']);

        self::assertTrue($p->trusts('2001:db8::1'));
        self::assertFalse($p->trusts('2001:db9::1'));
        self::assertFalse($p->trusts('10.0.0.1'), 'IPv4 must not match an IPv6 range');
    }

    public function test_garbage_never_matches(): void
    {
        $p = new TrustedProxies(['10.0.0.0/8']);

        self::assertFalse($p->trusts('not-an-ip'));
        self::assertFalse($p->trusts(''));
    }

    public function test_from_string_parses_the_env_form(): void
    {
        $p = TrustedProxies::fromString(' 10.0.0.1 , 172.16.0.0/12 ');

        self::assertTrue($p->trusts('10.0.0.1'));
        self::assertTrue($p->trusts('172.16.5.5'));
        self::assertTrue(TrustedProxies::fromString('')->isEmpty());
        self::assertTrue(TrustedProxies::fromString(null)->isEmpty());
    }

    // ----------------------------------------------------------- client IP

    public function test_forwarded_for_is_ignored_without_configured_proxies(): void
    {
        $r = $this->request([
            'REMOTE_ADDR'          => '203.0.113.9',
            'HTTP_X_FORWARDED_FOR' => '1.2.3.4',
        ]);

        self::assertSame('203.0.113.9', $r->ip(), 'a spoofed header must not decide the throttling key');
    }

    public function test_forwarded_for_is_used_behind_a_trusted_proxy(): void
    {
        $r = $this->request(
            ['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '203.0.113.9'],
            new TrustedProxies(['10.0.0.0/8']),
        );

        self::assertSame('203.0.113.9', $r->ip());
    }

    public function test_chain_walks_back_to_the_first_untrusted_hop(): void
    {
        // client -> edge (untrusted, forged) -> our proxies
        $r = $this->request(
            ['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '9.9.9.9, 203.0.113.9, 10.0.0.2'],
            new TrustedProxies(['10.0.0.0/8']),
        );

        self::assertSame('203.0.113.9', $r->ip(), 'skip our own hops, stop at the first we do not control');
    }

    public function test_falls_back_to_remote_addr_when_the_chain_is_all_ours(): void
    {
        $r = $this->request(
            ['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '10.0.0.2, 10.0.0.3'],
            new TrustedProxies(['10.0.0.0/8']),
        );

        self::assertSame('10.0.0.1', $r->ip());
    }

    public function test_port_suffixes_are_stripped(): void
    {
        $r = $this->request(
            ['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '203.0.113.9:51234'],
            new TrustedProxies(['10.0.0.0/8']),
        );

        self::assertSame('203.0.113.9', $r->ip());
    }

    public function test_missing_remote_addr_is_survivable(): void
    {
        self::assertSame('0.0.0.0', $this->request([])->ip());
    }

    // -------------------------------------------------------------- scheme

    public function test_https_detected_from_server_vars(): void
    {
        self::assertTrue($this->request(['HTTPS' => 'on'])->isSecure());
        self::assertTrue($this->request(['SERVER_PORT' => '443'])->isSecure());
        self::assertFalse($this->request(['HTTPS' => 'off'])->isSecure());
        self::assertFalse($this->request([])->isSecure());
    }

    public function test_forwarded_proto_is_ignored_without_configured_proxies(): void
    {
        $r = $this->request([
            'REMOTE_ADDR'            => '203.0.113.9',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ]);

        self::assertFalse($r->isSecure(), 'a client claiming https must not flip the secure cookie flag');
    }

    public function test_forwarded_proto_is_honoured_behind_a_trusted_proxy(): void
    {
        $secure = $this->request(
            ['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_PROTO' => 'https'],
            new TrustedProxies(['10.0.0.0/8']),
        );
        $plain = $this->request(
            ['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_PROTO' => 'http'],
            new TrustedProxies(['10.0.0.0/8']),
        );

        self::assertTrue($secure->isSecure());
        self::assertFalse($plain->isSecure());
    }
}
