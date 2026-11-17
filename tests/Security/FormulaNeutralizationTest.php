<?php

namespace DreamFactory\Core\Excel\Tests\Security;

use DreamFactory\Core\Excel\Components\PHPSpreadsheetWrapper;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Security: cell values that look like formulas (=, +, -, @, TAB, CR) get a
 * leading apostrophe so a downstream Excel/CSV consumer renders them as text
 * instead of evaluating them. Plain numbers (including negative numbers
 * returned as formatted strings) must come back unchanged.
 */
class FormulaNeutralizationTest extends TestCase
{
    #[DataProvider('valueProvider')]
    public function testNeutralize($input, $expected): void
    {
        $this->assertSame($expected, PHPSpreadsheetWrapper::neutralizeFormula($input));
    }

    public static function valueProvider(): array
    {
        return [
            // formula-like strings are neutralised
            ['=WEBSERVICE("https://evil/")', "'=WEBSERVICE(\"https://evil/\")"],
            ['=HYPERLINK("https://evil/","click")', "'=HYPERLINK(\"https://evil/\",\"click\")"],
            ['=SUM(A1)', "'=SUM(A1)"],
            ['=1+1', "'=1+1"],
            ['=5', "'=5"],
            ['+1+1', "'+1+1"],
            ['-1+1', "'-1+1"],
            ['-2+3+cmd|\' /C calc\'!A0', "'-2+3+cmd|' /C calc'!A0"],
            ['+cmd|\' /C calc\'!A0', "'+cmd|' /C calc'!A0"],
            ['@SUM(A1:A2)', "'@SUM(A1:A2)"],
            ['-', "'-"],
            ['-1,2,cmd', "'-1,2,cmd"],
            ['-1 (cmd)', "'-1 (cmd)"],
            ["-1\n=cmd", "'-1\n=cmd"],
            ["\tinjected", "'\tinjected"],
            ["\rinjected", "'\rinjected"],
            ["\t=SUM(A1)", "'\t=SUM(A1)"],
            // plain values are untouched
            ['plain text', 'plain text'],
            ['', ''],
            [null, null],
            [true, true],
            [42, 42],
            [3.14, 3.14],
            [-7, -7],
            [-12.5, -12.5],
            // numeric strings are untouched
            ['-12.5', '-12.5'],
            ['-3', '-3'],
            ['+1', '+1'],
            ['-1e5', '-1e5'],
            ['-1E+05', '-1E+05'],
            ['-.5', '-.5'],
            ['-0', '-0'],
            ["\t-3", "\t-3"],
            // formatted numeric strings (default formatted_values=true) are untouched
            ['-1,234.50', '-1,234.50'],
            ['-$1,234.50', '-$1,234.50'],
            ['-12%', '-12%'],
            ['-1 234,50', '-1 234,50'],
            // trailing currency symbol (FORMAT_CURRENCY_EUR, de-DE locale formats)
            ['-1,234.50 €', '-1,234.50 €'],
            ["-1.234,50\u{00A0}€", "-1.234,50\u{00A0}€"],
            ['-1 €', '-1 €'],
            ['-1€', '-1€'],
            // a trailing symbol does not open a gap for formulas
            ['-1€=', "'-1€="],
            ['-1 € cmd', "'-1 € cmd"],
            ['-€-1', "'-€-1"],
            ['-1 €€', "'-1 €€"],
            // conservative: other negative formatted values keep the apostrophe
            ['-1 1/2', "'-1 1/2"],
            ['-1:30:00', "'-1:30:00"],
            ['-1,234.50 zł', "'-1,234.50 zł"],
        ];
    }

    /**
     * End to end through rangeToArray + mapRowContent, with both the default
     * formatted values and raw values, and with the opt-out parameter.
     */
    public function testWorksheetReadKeepsNegativeNumbers(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Data');
        $sheet->fromArray([['amount', 'formatted', 'label', 'eur', 'de']], null, 'A1');
        $sheet->setCellValue('A2', -12.5);
        $sheet->setCellValue('B2', -1234.5);
        $sheet->getStyle('B2')->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->setCellValueExplicit('C2', '=HYPERLINK("https://evil/","x")', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValue('D2', -1234.5);
        $sheet->getStyle('D2')->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_CURRENCY_EUR);
        $sheet->setCellValue('E2', -1234.5);
        $sheet->getStyle('E2')->getNumberFormat()->setFormatCode("#,##0.00 [\$\u{20AC}-407]");
        $sheet->setCellValue('A3', 7);
        $sheet->setCellValue('B3', 0.5);
        $sheet->setCellValueExplicit('C3', '-2+3+cmd|\'/C calc\'!A0', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

        // default params: formatted values, neutralize on
        $rows = $this->wrapper($spreadsheet, [])->getWorksheetData('Data');
        $this->assertSame('-12.5', $rows[0]->amount);
        $this->assertSame('-1,234.50', $rows[0]->formatted);
        $this->assertStringStartsWith('-1,234.50', $rows[0]->eur);
        $this->assertStringEndsWith('€', $rows[0]->eur);
        $this->assertStringStartsWith('-1,234.50', $rows[0]->de);
        $this->assertStringEndsWith('€', $rows[0]->de);
        $this->assertSame("'=HYPERLINK(\"https://evil/\",\"x\")", $rows[0]->label);
        $this->assertSame("'-2+3+cmd|'/C calc'!A0", $rows[1]->label);

        // raw values
        $rows = $this->wrapper($spreadsheet, ['formatted_values' => 'false'])->getWorksheetData('Data');
        $this->assertSame(-12.5, $rows[0]->amount);
        $this->assertSame(-1234.5, $rows[0]->formatted);
        $this->assertSame("'=HYPERLINK(\"https://evil/\",\"x\")", $rows[0]->label);

        // opt-out still returns everything verbatim
        $rows = $this->wrapper($spreadsheet, ['neutralize_formulas' => 'false'])->getWorksheetData('Data');
        $this->assertSame('-12.5', $rows[0]->amount);
        $this->assertSame('=HYPERLINK("https://evil/","x")', $rows[0]->label);
    }

    private function wrapper(Spreadsheet $spreadsheet, array $parameters): PHPSpreadsheetWrapper
    {
        $wrapper = (new \ReflectionClass(PHPSpreadsheetWrapper::class))->newInstanceWithoutConstructor();
        $wrapper->spreadsheet = $spreadsheet;
        $wrapper->spreadsheetName = 'test.xlsx';
        $wrapper->parameters = $parameters;

        return $wrapper;
    }
}
