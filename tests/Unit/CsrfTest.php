<?php

declare(strict_types=1);

namespace Nimbus\Tests\Unit;

use Nimbus\Http\Csrf;
use PHPUnit\Framework\TestCase;

final class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function test_valid_token_passes_and_others_fail(): void
    {
        $token = Csrf::token();

        self::assertTrue(Csrf::check($token));
        self::assertFalse(Csrf::check('not-the-token'));
        self::assertFalse(Csrf::check(null));
        self::assertFalse(Csrf::check(''));
    }

    public function test_token_is_stable_within_a_session(): void
    {
        self::assertSame(Csrf::token(), Csrf::token());
    }

    public function test_check_fails_when_no_token_issued(): void
    {
        self::assertFalse(Csrf::check('anything'));
    }
}
