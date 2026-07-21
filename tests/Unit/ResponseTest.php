<?php

declare(strict_types=1);

namespace Nimbus\Tests\Unit;

use Nimbus\Http\Response;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    public function test_html(): void
    {
        $r = Response::html('<h1>Hi</h1>', 201);
        self::assertSame(201, $r->status);
        self::assertSame('<h1>Hi</h1>', $r->body);
        self::assertSame('text/html; charset=UTF-8', $r->headers['Content-Type']);
    }

    public function test_redirect(): void
    {
        $r = Response::redirect('/admin');
        self::assertSame(302, $r->status);
        self::assertSame('/admin', $r->headers['Location']);
        self::assertSame('', $r->body);
    }

    public function test_json(): void
    {
        $r = Response::json(['a' => 1, 'url' => '/x/y']);
        self::assertSame('application/json', $r->headers['Content-Type']);
        self::assertSame('{"a":1,"url":"/x/y"}', $r->body);
    }

    public function test_download(): void
    {
        $r = Response::download('col1,col2', 'export.csv', 'text/csv');
        self::assertSame('text/csv', $r->headers['Content-Type']);
        self::assertStringContainsString('attachment; filename="export.csv"', $r->headers['Content-Disposition']);
    }

    public function test_with_header_is_immutable(): void
    {
        $r  = Response::html('x');
        $r2 = $r->withHeader('X-Test', '1');
        self::assertArrayNotHasKey('X-Test', $r->headers);
        self::assertSame('1', $r2->headers['X-Test']);
    }
}
