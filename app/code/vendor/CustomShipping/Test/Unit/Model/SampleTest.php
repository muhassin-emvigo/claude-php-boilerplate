<?php
declare(strict_types=1);

namespace vendor\CustomShipping\Test\Unit\Model;

use PHPUnit\Framework\TestCase;

class SampleTest extends TestCase
{
    public function testPhpUnitIsWorking_Always_ReturnsTrue(): void
    {
        $this->assertTrue(true, 'PHPUnit is configured correctly.');
    }

    /**
     * @dataProvider additionProvider
     */
    public function testAddition_WithValidNumbers_ReturnsCorrectSum(
        int $a,
        int $b,
        int $expected
    ): void {
        $this->assertSame($expected, $a + $b);
    }

    /**
     * @return array<string, array{int, int, int}>
     */
    public static function additionProvider(): array
    {
        return [
            'positive numbers' => [1, 2, 3],
            'zero and positive' => [0, 5, 5],
            'negative numbers' => [-1, -2, -3],
            'mixed signs' => [-1, 3, 2],
        ];
    }
}
