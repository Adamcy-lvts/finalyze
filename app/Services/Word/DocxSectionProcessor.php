<?php

namespace App\Services\Word;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class DocxSectionProcessor
{
    private const MARKER_PREFIX = '[[SECTION_BREAK:';
    private const MARKER_SUFFIX = ']]';
    private const WORD_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    private const REL_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    private const PKG_REL_NS = 'http://schemas.openxmlformats.org/package/2006/relationships';

    /**
     * Add section breaks and page numbering to a DOCX file.
     *
     * @param string $docxPath Path to the DOCX file
     * @param array $sectionMarkers Markers indicating where to insert section breaks
     * @return bool Success status
     */
    public function process(string $docxPath, array $sectionMarkers): bool
    {
        if (! class_exists(ZipArchive::class)) {
            return false;
        }

        $zip = new ZipArchive();
        if ($zip->open($docxPath) !== true) {
            return false;
        }

        $documentXml = $zip->getFromName('word/document.xml');
        if (! is_string($documentXml) || $documentXml === '') {
            $zip->close();

            return false;
        }

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        if (! $dom->loadXML($documentXml, LIBXML_NONET)) {
            $zip->close();

            return false;
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', self::WORD_NS);
        $xpath->registerNamespace('r', self::REL_NS);

        $baseSectPr = $this->findBaseSectPr($xpath);
        $footerMap = $this->createFooters($zip, $sectionMarkers);
        $finalSection = null;

        foreach ($sectionMarkers as $section) {
            $marker = $section['marker'] ?? null;

            if ($marker === null || $marker === 'document_end') {
                $finalSection = $section;
                continue;
            }

            $markerText = self::MARKER_PREFIX.$marker.self::MARKER_SUFFIX;
            $paragraph = $this->findMarkerParagraph($xpath, $markerText);

            if (! $paragraph) {
                Log::warning('DOCX section marker not found', ['marker' => $markerText, 'path' => $docxPath]);
                continue;
            }

            $sectPr = $this->buildSectPr($dom, $baseSectPr, $section, $footerMap, true);
            $this->replaceParagraphWithSectPr($paragraph, $sectPr);
        }

        if ($finalSection !== null) {
            $this->applyFinalSection($dom, $xpath, $baseSectPr, $finalSection, $footerMap);
        }

        $zip->addFromString('word/document.xml', $dom->saveXML());
        $zip->close();

        return true;
    }

    private function findBaseSectPr(DOMXPath $xpath): ?DOMElement
    {
        $body = $xpath->query('//w:body')->item(0);
        if ($body instanceof DOMElement) {
            $sectPr = $xpath->query('./w:sectPr', $body)->item(0);
            if ($sectPr instanceof DOMElement) {
                return $sectPr;
            }
        }

        $sectPr = $xpath->query('//w:sectPr')->item(0);

        return $sectPr instanceof DOMElement ? $sectPr : null;
    }

    private function findMarkerParagraph(DOMXPath $xpath, string $markerText): ?DOMElement
    {
        $nodes = $xpath->query(sprintf('//w:t[contains(., "%s")]', $markerText));
        if (! $nodes || $nodes->length === 0) {
            return null;
        }

        $node = $nodes->item(0);
        while ($node instanceof DOMNode && $node->nodeName !== 'w:p') {
            $node = $node->parentNode;
        }

        return $node instanceof DOMElement ? $node : null;
    }

    private function replaceParagraphWithSectPr(DOMElement $paragraph, DOMElement $sectPr): void
    {
        while ($paragraph->firstChild) {
            $paragraph->removeChild($paragraph->firstChild);
        }

        $pPr = $paragraph->ownerDocument->createElementNS(self::WORD_NS, 'w:pPr');
        $pPr->appendChild($sectPr);
        $paragraph->appendChild($pPr);
    }

    private function buildSectPr(
        DOMDocument $dom,
        ?DOMElement $baseSectPr,
        array $section,
        array $footerMap,
        bool $includeType
    ): DOMElement {
        $sectPr = $baseSectPr ? $baseSectPr->cloneNode(true) : $dom->createElementNS(self::WORD_NS, 'w:sectPr');

        foreach ($this->collectChildNodesByName($sectPr, 'pgNumType') as $node) {
            $sectPr->removeChild($node);
        }
        foreach ($this->collectChildNodesByName($sectPr, 'footerReference') as $node) {
            $sectPr->removeChild($node);
        }

        $this->ensurePageMargins($dom, $sectPr);

        if ($includeType) {
            $typeNode = $this->findChildNodeByName($sectPr, 'type');
            if (! $typeNode) {
                $typeNode = $dom->createElementNS(self::WORD_NS, 'w:type');
                $sectPr->insertBefore($typeNode, $sectPr->firstChild);
            }
            $typeNode->setAttributeNS(self::WORD_NS, 'w:val', 'nextPage');
        } else {
            $typeNode = $this->findChildNodeByName($sectPr, 'type');
            if ($typeNode) {
                $sectPr->removeChild($typeNode);
            }
        }

        $format = $section['pageNumberFormat'] ?? null;
        if (is_string($format) && $format !== '') {
            $pgNumType = $dom->createElementNS(self::WORD_NS, 'w:pgNumType');
            $pgNumType->setAttributeNS(self::WORD_NS, 'w:fmt', $format);

            if (isset($section['start'])) {
                $pgNumType->setAttributeNS(self::WORD_NS, 'w:start', (string) $section['start']);
            }

            $sectPr->appendChild($pgNumType);

            $footerRid = $footerMap[$format] ?? null;
            if (is_string($footerRid) && $footerRid !== '') {
                $footerRef = $dom->createElementNS(self::WORD_NS, 'w:footerReference');
                $footerRef->setAttributeNS(self::WORD_NS, 'w:type', 'default');
                $footerRef->setAttributeNS(self::REL_NS, 'r:id', $footerRid);
                $sectPr->appendChild($footerRef);
            }
        }

        return $sectPr;
    }

    private function ensurePageMargins(DOMDocument $dom, DOMElement $sectPr): void
    {
        $pgMar = $this->findChildNodeByName($sectPr, 'pgMar');
        if (! $pgMar) {
            $pgMar = $dom->createElementNS(self::WORD_NS, 'w:pgMar');
            $pgSz = $this->findChildNodeByName($sectPr, 'pgSz');

            if ($pgSz && $pgSz->nextSibling) {
                $sectPr->insertBefore($pgMar, $pgSz->nextSibling);
            } else {
                $sectPr->appendChild($pgMar);
            }
        }

        // 1.0" margins all around in twips.
        $pgMar->setAttributeNS(self::WORD_NS, 'w:top', '1440');
        $pgMar->setAttributeNS(self::WORD_NS, 'w:bottom', '1440');
        $pgMar->setAttributeNS(self::WORD_NS, 'w:left', '1440');
        $pgMar->setAttributeNS(self::WORD_NS, 'w:right', '1440');

        if (! $pgMar->hasAttributeNS(self::WORD_NS, 'w:header')) {
            $pgMar->setAttributeNS(self::WORD_NS, 'w:header', '720');
        }
        if (! $pgMar->hasAttributeNS(self::WORD_NS, 'w:footer')) {
            $pgMar->setAttributeNS(self::WORD_NS, 'w:footer', '720');
        }
        if (! $pgMar->hasAttributeNS(self::WORD_NS, 'w:gutter')) {
            $pgMar->setAttributeNS(self::WORD_NS, 'w:gutter', '0');
        }
    }

    private function applyFinalSection(
        DOMDocument $dom,
        DOMXPath $xpath,
        ?DOMElement $baseSectPr,
        array $section,
        array $footerMap
    ): void {
        $body = $xpath->query('//w:body')->item(0);
        if (! $body instanceof DOMElement) {
            return;
        }

        $sectPr = $this->buildSectPr($dom, $baseSectPr, $section, $footerMap, false);

        $existing = $xpath->query('./w:sectPr', $body)->item(0);
        if ($existing instanceof DOMElement) {
            $body->replaceChild($sectPr, $existing);
        } else {
            $body->appendChild($sectPr);
        }
    }

    private function collectChildNodesByName(DOMElement $parent, string $localName): array
    {
        $nodes = [];
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === $localName) {
                $nodes[] = $child;
            }
        }

        return $nodes;
    }

    private function findChildNodeByName(DOMElement $parent, string $localName): ?DOMElement
    {
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === $localName) {
                return $child;
            }
        }

        return null;
    }

    private function createFooters(ZipArchive $zip, array $sectionMarkers): array
    {
        $formats = [];
        foreach ($sectionMarkers as $section) {
            $format = $section['pageNumberFormat'] ?? null;
            if (is_string($format) && $format !== '') {
                $formats[$format] = true;
            }
        }

        if ($formats === []) {
            return [];
        }

        $relsXml = $zip->getFromName('word/_rels/document.xml.rels');
        $contentTypesXml = $zip->getFromName('[Content_Types].xml');
        if (! is_string($relsXml) || ! is_string($contentTypesXml)) {
            return [];
        }

        $relsDom = new DOMDocument();
        $relsDom->preserveWhiteSpace = false;
        $relsDom->formatOutput = false;
        if (! $relsDom->loadXML($relsXml, LIBXML_NONET)) {
            return [];
        }

        $contentDom = new DOMDocument();
        $contentDom->preserveWhiteSpace = false;
        $contentDom->formatOutput = false;
        if (! $contentDom->loadXML($contentTypesXml, LIBXML_NONET)) {
            return [];
        }

        $footerMap = [];
        $footerIndex = 1;

        foreach (array_keys($formats) as $format) {
            $footerName = $this->nextFooterName($zip, $footerIndex);
            $footerIndex++;

            $zip->addFromString('word/'.$footerName, $this->buildFooterXml());
            $rId = $this->addFooterRelationship($relsDom, $footerName);
            $this->ensureContentTypeOverride($contentDom, $footerName);

            $footerMap[$format] = $rId;
        }

        $zip->addFromString('word/_rels/document.xml.rels', $relsDom->saveXML());
        $zip->addFromString('[Content_Types].xml', $contentDom->saveXML());

        return $footerMap;
    }

    private function nextFooterName(ZipArchive $zip, int $startIndex): string
    {
        $index = $startIndex;
        while (true) {
            $name = 'footer'.$index.'.xml';
            if ($zip->locateName('word/'.$name) === false) {
                return $name;
            }
            $index++;
        }
    }

    private function addFooterRelationship(DOMDocument $relsDom, string $footerName): string
    {
        $root = $relsDom->documentElement;
        if (! $root instanceof DOMElement) {
            return '';
        }

        $existingIds = [];
        foreach ($root->getElementsByTagName('Relationship') as $rel) {
            if ($rel instanceof DOMElement) {
                $id = $rel->getAttribute('Id');
                if ($id !== '') {
                    $existingIds[$id] = true;
                }
            }
        }

        $nextId = 1;
        foreach (array_keys($existingIds) as $id) {
            if (preg_match('/^rId(\d+)$/', $id, $matches)) {
                $nextId = max($nextId, ((int) $matches[1]) + 1);
            }
        }

        $newId = 'rId'.$nextId;
        while (isset($existingIds[$newId])) {
            $nextId++;
            $newId = 'rId'.$nextId;
        }

        $relationship = $relsDom->createElementNS(self::PKG_REL_NS, 'Relationship');
        $relationship->setAttribute('Id', $newId);
        $relationship->setAttribute('Type', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer');
        $relationship->setAttribute('Target', $footerName);
        $root->appendChild($relationship);

        return $newId;
    }

    private function ensureContentTypeOverride(DOMDocument $contentDom, string $footerName): void
    {
        $root = $contentDom->documentElement;
        if (! $root instanceof DOMElement) {
            return;
        }

        $partName = '/word/'.$footerName;
        foreach ($root->getElementsByTagName('Override') as $override) {
            if ($override instanceof DOMElement && $override->getAttribute('PartName') === $partName) {
                return;
            }
        }

        $override = $contentDom->createElementNS($root->namespaceURI, 'Override');
        $override->setAttribute('PartName', $partName);
        $override->setAttribute('ContentType', 'application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml');
        $root->appendChild($override);
    }

    private function buildFooterXml(): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:ftr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:p>
    <w:pPr>
      <w:jc w:val="center"/>
    </w:pPr>
    <w:r>
      <w:fldChar w:fldCharType="begin"/>
    </w:r>
    <w:r>
      <w:instrText>PAGE</w:instrText>
    </w:r>
    <w:r>
      <w:fldChar w:fldCharType="end"/>
    </w:r>
  </w:p>
</w:ftr>
XML;
    }
}
