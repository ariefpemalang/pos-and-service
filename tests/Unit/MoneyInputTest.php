<?php

namespace Tests\Unit;

use App\Support\MoneyInput;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MoneyInputTest extends TestCase
{
    #[Test]
    #[DataProvider('amounts')]
    public function it_parses_rupiah_thousand_separators(mixed $input, ?float $expected): void
    {
        $this->assertSame($expected, MoneyInput::parse($input));
    }

    /**
     * @return array<string, array{0: mixed, 1: float|null}>
     */
    public static function amounts(): array
    {
        return [
            'empty' => ['', null],
            'null' => [null, null],
            'plain' => ['1000000', 1000000.0],
            'dot thousands' => ['1.000.000', 1000000.0],
            'comma thousands' => ['1,000,000', 1000000.0],
            'dot hundred thousands' => ['200.000', 200000.0],
            'comma hundred thousands' => ['200,000', 200000.0],
            'single thousand' => ['1.000', 1000.0],
            'id decimal' => ['1.000.000,50', 1000000.5],
            'us decimal' => ['1,000,000.50', 1000000.5],
            'with rp' => ['Rp 1.000.000', 1000000.0],
            'numeric' => [200000, 200000.0],
            'zero' => ['0', 0.0],
        ];
    }
}
