<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\MaskedCredentialHint;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MaskedCredentialHintTest extends TestCase
{
    #[Test]
    public function for_returns_empty_for_blank_value(): void
    {
        $this->assertSame('', MaskedCredentialHint::for(''));
        $this->assertSame('', MaskedCredentialHint::for('   '));
    }

    #[Test]
    public function for_masks_short_values_fully(): void
    {
        $this->assertSame('****', MaskedCredentialHint::for('abcd'));
        $this->assertSame('***', MaskedCredentialHint::for('abc', 4));
    }

    #[Test]
    public function for_reveals_tail_with_minimum_mask_length(): void
    {
        $hint = MaskedCredentialHint::for('abcdefghijklmnop', 4);

        $this->assertStringEndsWith('mnop', $hint);
        $this->assertStringStartsWith('****', $hint);
        $this->assertGreaterThanOrEqual(8, strlen($hint));
    }

    #[Test]
    public function leading_and_trailing_masks_short_values(): void
    {
        $this->assertSame('••••••••', MaskedCredentialHint::leadingAndTrailing('12345678'));
    }

    #[Test]
    public function leading_and_trailing_shows_edges_for_long_values(): void
    {
        $this->assertSame('1234…wxyz', MaskedCredentialHint::leadingAndTrailing('1234567890wxyz'));
    }
}
