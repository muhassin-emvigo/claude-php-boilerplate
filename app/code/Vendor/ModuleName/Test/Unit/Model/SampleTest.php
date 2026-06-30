<?php
declare(strict_types=1);

namespace Vendor\ModuleName\Test\Unit\Model;

use PHPUnit\Framework\TestCase;

/**
 * Sample unit test demonstrating proper test structure.
 *
 * Replace this with actual tests for your module's models.
 */
class SampleTest extends TestCase
{
    /**
     * Test that true is true — smoke test to verify PHPUnit is working.
     */
    public function testPhpUnitIsWorking_Always_ReturnsTrue(): void
    {
        $this->assertTrue(true, 'PHPUnit is configured correctly.');
    }

    /**
     * Example: testing a simple calculation.
     *
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
     * Data provider for addition test.
     *
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
