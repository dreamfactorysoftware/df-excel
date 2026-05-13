<?php

namespace DreamFactory\Core\Excel\Tests\Security;

use PHPUnit\Framework\TestCase;

/**
 * Lock-in test for the phpoffice/phpspreadsheet 1.x -> 5.x bump.
 *
 * The 5.x release fixes 6 advisories including SSRF in IOFactory::load
 * (CVE-2026-34084). The only API method df-excel called that is gone
 * in 5.x is Worksheet::getCellByColumnAndRow(); replaced with getCell().
 */
class PhpSpreadsheet5MigrationTest extends TestCase
{
    private string $wrapperSource;

    protected function setUp(): void
    {
        $this->wrapperSource = file_get_contents(
            __DIR__ . '/../../src/Components/PHPSpreadsheetWrapper.php'
        );
    }

    public function testRemovedApiCallIsGone(): void
    {
        $this->assertStringNotContainsString(
            'getCellByColumnAndRow',
            $this->wrapperSource,
            'getCellByColumnAndRow was removed in phpspreadsheet 5.x; use getCell() instead'
        );
    }

    public function testReplacementApiCallIsPresent(): void
    {
        $this->assertMatchesRegularExpression(
            "/getCell\\(['\"]A1['\"]\\)/",
            $this->wrapperSource,
            'wrapper must call getCell(\'A1\') for the first-cell coordinate lookup'
        );
    }

    public function testComposerPinsAtFiveOrAbove(): void
    {
        $composer = json_decode(
            file_get_contents(__DIR__ . '/../../composer.json'),
            true
        );
        $this->assertSame(
            '^5.0',
            $composer['require']['phpoffice/phpspreadsheet'] ?? null,
            'phpspreadsheet must be pinned at ^5.0 to pick up the SSRF + DoS fixes'
        );
    }
}
