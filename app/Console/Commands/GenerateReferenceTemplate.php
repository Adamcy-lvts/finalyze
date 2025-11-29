<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;

class GenerateReferenceTemplate extends Command
{
    protected $signature = 'export:generate-reference-template';

    protected $description = 'Generate a reference DOCX template for Pandoc exports with academic styling';

    public function handle(): int
    {
        $this->info('Generating reference DOCX template...');

        $phpWord = new PhpWord;

        // Set document properties
        $properties = $phpWord->getDocInfo();
        $properties->setCreator('Finalyze');
        $properties->setTitle('Academic Reference Template');

        // Define professional academic styles
        $phpWord->addParagraphStyle('Title', [
            'alignment' => Jc::CENTER,
            'spaceAfter' => 240,
            'keepNext' => true,
        ]);

        $phpWord->addFontStyle('TitleFont', [
            'name' => 'Times New Roman',
            'size' => 16,
            'bold' => true,
        ]);

        $phpWord->addParagraphStyle('Heading1', [
            'alignment' => Jc::CENTER,
            'spaceAfter' => 240,
            'spaceBefore' => 240,
            'keepNext' => true,
        ]);

        $phpWord->addFontStyle('Heading1Font', [
            'name' => 'Times New Roman',
            'size' => 14,
            'bold' => true,
        ]);

        $phpWord->addParagraphStyle('Heading2', [
            'alignment' => Jc::LEFT,
            'spaceAfter' => 120,
            'spaceBefore' => 240,
            'keepNext' => true,
        ]);

        $phpWord->addFontStyle('Heading2Font', [
            'name' => 'Times New Roman',
            'size' => 13,
            'bold' => true,
        ]);

        $phpWord->addParagraphStyle('Heading3', [
            'alignment' => Jc::LEFT,
            'spaceAfter' => 120,
            'spaceBefore' => 120,
            'keepNext' => true,
        ]);

        $phpWord->addFontStyle('Heading3Font', [
            'name' => 'Times New Roman',
            'size' => 12,
            'bold' => true,
        ]);

        $phpWord->addParagraphStyle('Normal', [
            'alignment' => Jc::BOTH,
            'lineHeight' => 2.0, // Double spacing for academic papers
            'spaceAfter' => 0,
        ]);

        $phpWord->addFontStyle('NormalFont', [
            'name' => 'Times New Roman',
            'size' => 12,
        ]);

        $phpWord->addParagraphStyle('Quote', [
            'alignment' => Jc::BOTH,
            'indentation' => ['left' => 720, 'right' => 720],
            'spaceAfter' => 240,
        ]);

        $phpWord->addFontStyle('QuoteFont', [
            'name' => 'Times New Roman',
            'size' => 11,
            'italic' => true,
        ]);

        // Code block style
        $phpWord->addParagraphStyle('CodeBlock', [
            'alignment' => Jc::LEFT,
            'indentation' => ['left' => 360],
            'spaceAfter' => 120,
        ]);

        $phpWord->addFontStyle('CodeFont', [
            'name' => 'Courier New',
            'size' => 10,
        ]);

        // Table style
        $phpWord->addTableStyle('AcademicTable', [
            'borderSize' => 6,
            'borderColor' => '000000',
            'cellMargin' => 80,
            'alignment' => Jc::CENTER,
        ], [
            'alignment' => Jc::CENTER,
        ]);

        // Create a sample section to demonstrate styles
        $section = $phpWord->addSection([
            'marginTop' => 1440,    // 1 inch
            'marginBottom' => 1440,
            'marginLeft' => 1440,
            'marginRight' => 1440,
        ]);

        $section->addText('ACADEMIC DOCUMENT TITLE', 'TitleFont', 'Title');
        $section->addText('By Author Name', 'NormalFont', 'Normal');
        $section->addTextBreak(2);

        $section->addText('CHAPTER ONE', 'Heading1Font', 'Heading1');
        $section->addText('Introduction', 'Heading2Font', 'Heading2');

        $section->addText(
            'This is a sample paragraph demonstrating the normal text style with double spacing, justified alignment, and Times New Roman 12pt font - standard for academic papers.',
            'NormalFont',
            'Normal'
        );

        $section->addText('Subsection Title', 'Heading3Font', 'Heading3');
        $section->addText(
            'Another paragraph with proper academic formatting. This template ensures consistency across all exported documents.',
            'NormalFont',
            'Normal'
        );

        // Save the reference template
        $templatePath = resource_path('templates/reference.docx');

        try {
            $writer = IOFactory::createWriter($phpWord, 'Word2007');
            $writer->save($templatePath);

            $this->info("✓ Reference template created successfully at: {$templatePath}");
            $this->info('  This template will be used by Pandoc to ensure consistent document styling.');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to create reference template: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
