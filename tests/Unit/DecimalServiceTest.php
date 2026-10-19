<?php

namespace Tests\Unit;

use App\Services\DecimalService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DecimalServiceTest extends TestCase
{
    #[DataProvider('lineExamples')]
    public function test_line_amounts_round_half_up_without_floats(string $quantity, string $price, string $expected): void
    {
        $this->assertSame($expected, (new DecimalService)->lineTotal($quantity, $price));
    }

    public static function lineExamples(): array
    {
        return [
            ['2', '25.00', '50.00'],
            ['0.333', '10.00', '3.33'],
            ['1.005', '1.00', '1.01'],
            ['1.004', '1.00', '1.00'],
            ['0.001', '5.00', '0.01'],
            ['0.001', '4.99', '0.00'],
            ['2', '0', '0.00'],
            ['99999999999.999', '1', '100000000000.00'],
        ];
    }

    public function test_totals_sum_rounded_lines_instead_of_rounding_raw_sum(): void
    {
        $service = new DecimalService;
        $this->assertSame('2.02', $service->total([
            $service->lineTotal('1.005', '1.00'),
            $service->lineTotal('1.005', '1.00'),
        ]));
        $this->assertSame('0.00', $service->total([]));
        $this->assertSame('99999999999999.99', $service->total(['99999999999999.98', '0.01']));
    }

    public function test_normalization_and_formatting_keep_all_large_digits_exact(): void
    {
        $service = new DecimalService;
        $this->assertSame('12.50', $service->money('00012.5'));
        $this->assertSame('999999999999.99', $service->money('999999999999.99'));
        $this->assertSame('Q 99,999,999,999,999.99', $service->formatMoney('99999999999999.99'));
        $this->assertSame('Q 0.00', $service->formatMoney('0'));
        $this->assertSame('99999999999.999', $service->quantity('99999999999.999', 3));
        $this->assertSame('1.000', $service->quantity('1.000', 0));
        $this->assertSame('0.500', $service->quantity('0.5', 1, true));
    }

    #[DataProvider('invalidNumbers')]
    public function test_ambiguous_and_invalid_numbers_are_rejected(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new DecimalService)->money($value);
    }

    public static function invalidNumbers(): array
    {
        return array_map(fn ($value) => [$value], ['', '-1', '+1', '1e2', 'NaN', 'INF', '1,234.56', '1,50', ' 1', '1 ', '.5', '1.', '1.001', '1.000', '1000000000000.00', "1\n"]);
    }

    #[DataProvider('incompatibleQuantities')]
    public function test_quantity_precision_and_range_are_enforced(string $value, int $places, bool $positive): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new DecimalService)->quantity($value, $places, $positive);
    }

    public static function incompatibleQuantities(): array
    {
        return [
            ['1.5', 0, false], ['0.001', 2, false], ['1.0001', 3, false],
            ['100000000000', 0, false], ['0', 3, true], ['0.000', 0, true],
            ['-0.1', 3, false], ['1', -1, false], ['1', 4, false],
        ];
    }

    public function test_line_overflow_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new DecimalService)->lineTotal('99999999999.999', '999999999999.99');
    }

    public function test_total_overflow_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new DecimalService)->total(['99999999999999.99', '0.01']);
    }

    public function test_non_string_line_amounts_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new DecimalService)->total([1.25]);
    }

    public function test_line_quantity_must_be_positive(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new DecimalService)->lineTotal('0', '10.00');
    }
}
