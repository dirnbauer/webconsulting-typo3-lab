<?php

declare(strict_types=1);

namespace Webconsulting\SitePackage\Tests\Unit\Service;

use Hn\Agent\Service\DocumentExtractorService;
use PhpOffice\PhpPresentation\IOFactory as PresentationIOFactory;
use PhpOffice\PhpPresentation\PhpPresentation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Resource\File;

final class AgentSpreadsheetCompatibilityTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function spreadsheetFormats(): iterable
    {
        yield 'Excel' => ['Xlsx'];
        yield 'OpenDocument' => ['Ods'];
        yield 'CSV' => ['Csv'];
    }

    #[DataProvider('spreadsheetFormats')]
    public function testAgentReadsAttachmentsWithPowermailsSpreadsheetVersion(string $format): void
    {
        $path = tempnam(sys_get_temp_dir(), 'agent-spreadsheet-');
        self::assertNotFalse($path);
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray([
            ['Item', 'Quantity'],
            ['Example', 3],
        ]);

        try {
            IOFactory::createWriter($spreadsheet, $format)->save($path);
            $file = $this->createStub(File::class);
            $file->method('getForLocalProcessing')->willReturn($path);
            $extractor = new DocumentExtractorService();

            $outline = $extractor->getSpreadsheetOutline($file);
            self::assertCount(1, $outline['sheets']);
            self::assertSame(2, $outline['sheets'][0]['rows']);
            self::assertSame(2, $outline['sheets'][0]['cols']);

            $range = $extractor->extractSpreadsheetRange($file, 0, 'A1:B2');
            self::assertSame("Item\tQuantity\nExample\t3", $range['text']);
            self::assertSame('A1:B2', $range['rangeUsed']);
        } finally {
            $spreadsheet->disconnectWorksheets();
            unlink($path);
        }
    }

    public function testAgentReadsPresentationAttachmentsWithTheSharedDependencies(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'agent-presentation-');
        self::assertNotFalse($path);
        $presentation = new PhpPresentation();
        $presentation->getActiveSlide()->createRichTextShape()->createTextRun('Agent presentation example');
        try {
            PresentationIOFactory::createWriter($presentation, 'PowerPoint2007')->save($path);
            $file = $this->createStub(File::class);
            $file->method('getForLocalProcessing')->willReturn($path);
            $file->method('getMimeType')->willReturn('application/vnd.openxmlformats-officedocument.presentationml.presentation');
            $extractor = new DocumentExtractorService();
            self::assertCount(1, $extractor->getPresentationOutline($file)['slides']);
            $slides = $extractor->extractPresentationSlides($file, 1, 1);
            self::assertSame(1, $slides['slideCount']);
            self::assertStringContainsString('Agent presentation example', $slides['text']);
        } finally {
            unlink($path);
        }
    }
}
