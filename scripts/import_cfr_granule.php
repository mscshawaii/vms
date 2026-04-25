<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);
set_time_limit(0);
ini_set('memory_limit', '-1');

require __DIR__ . '/../db_connect.php';

if (php_sapi_name() !== 'cli') {
    exit("This script must be run from CLI.\n");
}

$xmlFile = $argv[1] ?? '';
$titleNumber = isset($argv[2]) ? (int)$argv[2] : 0;
$sourceType = $argv[3] ?? 'cfr_annual';
$editionYear = isset($argv[4]) ? (int)$argv[4] : null;

if ($xmlFile === '' || !file_exists($xmlFile)) {
    exit("XML file not found: {$xmlFile}\n");
}

if ($titleNumber <= 0) {
    exit("Provide a valid title number as argv[2].\n");
}

if (!in_array($sourceType, ['ecfr_current', 'cfr_annual'], true)) {
    exit("Invalid source_type. Use ecfr_current or cfr_annual.\n");
}

echo "----------------------------------------\n";
echo "CFR GRANULE IMPORT START\n";
echo "File: {$xmlFile}\n";
echo "Title: {$titleNumber}\n";
echo "Source Type: {$sourceType}\n";
echo "Edition Year: " . ($editionYear ?: 'NULL') . "\n";
echo "----------------------------------------\n";

libxml_use_internal_errors(true);
$xml = simplexml_load_file($xmlFile);

if ($xml === false) {
    echo "Failed to load XML.\n";
    foreach (libxml_get_errors() as $error) {
        echo trim($error->message) . "\n";
    }
    exit(1);
}

echo "Root node: " . $xml->getName() . "\n";

function cleanText(?string $text): ?string
{
    if ($text === null) {
        return null;
    }

    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = preg_replace("/[ \t]+/u", " ", $text);
    $text = preg_replace("/\n{3,}/u", "\n\n", $text);
    $text = trim($text);

    return $text === '' ? null : $text;
}

function getNodeText(SimpleXMLElement $node): string
{
    return cleanText((string)$node) ?? '';
}

function firstChildText(SimpleXMLElement $node, string $name): ?string
{
    $result = $node->xpath('./' . $name);
    if ($result && isset($result[0])) {
        return cleanText((string)$result[0]);
    }
    return null;
}

function extractSectionNumberFromHead(?string $head): ?string
{
    if (!$head) return null;

    if (preg_match('/§\s*([0-9A-Za-z\.\-]+)/u', $head, $m)) {
        return trim($m[1]);
    }

    return null;
}

function parseParagraphsFromText(string $text): array
{
    $text = trim($text);
    if ($text === '') {
        return [];
    }

    $lines = preg_split("/\n/u", $text);
    $paragraphs = [];

    $currentLabel = null;
    $currentText = '';

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        if (preg_match('/^\(([a-zA-Z0-9ivxIVX]+)\)\s*(.*)$/u', $line, $m)) {
            if ($currentText !== '') {
                $paragraphs[] = [
                    'label' => $currentLabel,
                    'text'  => trim($currentText),
                ];
            }

            $currentLabel = '(' . $m[1] . ')';
            $currentText = trim($m[2]);
        } else {
            if ($currentText === '') {
                $currentText = $line;
            } else {
                $currentText .= ' ' . $line;
            }
        }
    }

    if ($currentText !== '') {
        $paragraphs[] = [
            'label' => $currentLabel,
            'text'  => trim($currentText),
        ];
    }

    if (!$paragraphs) {
        $paragraphs[] = [
            'label' => null,
            'text'  => $text,
        ];
    }

    $out = [];
    $i = 1;
    foreach ($paragraphs as $p) {
        $label = $p['label'];
        $out[] = [
            'paragraph_path'  => $label ?: 'p' . $i,
            'paragraph_label' => $label,
            'sort_key'        => str_pad((string)$i, 5, '0', STR_PAD_LEFT),
            'text_plain'      => $p['text'],
        ];
        $i++;
    }

    return $out;
}

function collectSectionText(SimpleXMLElement $sectionNode): string
{
    $parts = [];

    foreach ($sectionNode->children() as $child) {
        $name = strtoupper($child->getName());

        if (in_array($name, ['SECTNO', 'SUBJECT'], true)) {
            continue;
        }

        $text = cleanText((string)$child);
        if ($text !== null && $text !== '') {
            $parts[] = $text;
        }
    }

    return cleanText(implode("\n", $parts)) ?? '';
}

function resolvePartNumberForSection(SimpleXMLElement $sectionNode): ?string
{
    $candidates = $sectionNode->xpath('./ancestor::PART[1]/EAR | ./ancestor::PART[1]/HD');
    if ($candidates) {
        foreach ($candidates as $candidate) {
            $text = cleanText((string)$candidate);
            if (!$text) continue;

            if (preg_match('/Pt\.\s*([0-9A-Za-z\-]+)/u', $text, $m)) {
                return trim($m[1]);
            }

            if (preg_match('/PART\s+([0-9A-Za-z\-]+)/iu', $text, $m)) {
                return trim($m[1]);
            }
        }
    }

    return null;
}

$fileName = basename($xmlFile);
/* Extract subchapter from filename if present */
if (preg_match('/subchap([A-Z0-9]+)/i', $fileName, $m)) {
    $subchapterCode = strtoupper($m[1]);
}
$fileHash = hash_file('sha256', $xmlFile);

$issueDate = null;
$issueDateRaw = firstChildText($xml->FDSYS ?? $xml, 'DATE');
if ($issueDateRaw && preg_match('/^\d{4}-\d{2}-\d{2}$/', $issueDateRaw)) {
    $issueDate = $issueDateRaw;
}

$stmtSource = $pdo->prepare("
    INSERT INTO regulation_sources (
        source_type,
        title_number,
        edition_year,
        issue_date,
        source_url,
        file_name,
        sha256_hash,
        imported_at,
        is_active
    ) VALUES (
        :source_type,
        :title_number,
        :edition_year,
        :issue_date,
        NULL,
        :file_name,
        :sha256_hash,
        NOW(),
        1
    )
");

$stmtSource->execute([
    ':source_type'  => $sourceType,
    ':title_number' => $titleNumber,
    ':edition_year' => $editionYear,
    ':issue_date'   => $issueDate,
    ':file_name'    => $fileName,
    ':sha256_hash'  => $fileHash,
]);

$regulationSourceId = (int)$pdo->lastInsertId();

$pdo->prepare("
    UPDATE regulation_sources
    SET is_active = 0
    WHERE title_number = :title_number
      AND regulation_source_id <> :current_id
")->execute([
    ':title_number' => $titleNumber,
    ':current_id'   => $regulationSourceId,
]);

echo "Created regulation_source_id: {$regulationSourceId}\n";

$stmtSection = $pdo->prepare("
    INSERT INTO regulation_sections (
        regulation_source_id,
        title_number,
        subtitle_code,
        chapter_code,
        subchapter_code,
        part_number,
        subpart_code,
        section_number,
        citation,
        citation_short,
        heading,
        authority_text,
        source_note,
        status,
        xml_identifier,
        version_hash,
        text_plain,
        text_html,
        created_at,
        updated_at
    ) VALUES (
        :regulation_source_id,
        :title_number,
        :subtitle_code,
        :chapter_code,
        :subchapter_code,
        :part_number,
        :subpart_code,
        :section_number,
        :citation,
        :citation_short,
        :heading,
        :authority_text,
        :source_note,
        :status,
        :xml_identifier,
        :version_hash,
        :text_plain,
        :text_html,
        NOW(),
        NOW()
    )
");

$stmtParagraph = $pdo->prepare("
    INSERT INTO regulation_paragraphs (
        regulation_section_id,
        paragraph_path,
        paragraph_label,
        sort_key,
        text_plain,
        created_at
    ) VALUES (
        :regulation_section_id,
        :paragraph_path,
        :paragraph_label,
        :sort_key,
        :text_plain,
        NOW()
    )
");

$chapterCode = null;
$subchapterCode = null;

$ancestorNodes = $xml->xpath('./FDSYS/ANCESTORS/PARENT');
if ($ancestorNodes) {
    foreach ($ancestorNodes as $parent) {
        $heading = strtoupper(cleanText((string)($parent['HEADING'] ?? '')) ?? '');

        if (preg_match('/^CHAPTER\s+([A-Z0-9]+)/u', $heading, $m)) {
            $chapterCode = $m[1];
        }

        if (preg_match('/^SUBCHAPTER\s+([A-Z0-9]+)/u', $heading, $m)) {
            $subchapterCode = $m[1];
        }
    }
}

/* Fallback for subchapter granules like GRANULENUM=A / HEADING=SUBCHAPTER A */
if (!$subchapterCode) {
    $granuleNum = firstChildText($xml->FDSYS ?? $xml, 'GRANULENUM');
    if ($granuleNum && preg_match('/^[A-Z0-9]+$/i', $granuleNum)) {
        $subchapterCode = strtoupper(trim($granuleNum));
    }
}

if (!$subchapterCode) {
    $fdsysHeading = firstChildText($xml->FDSYS ?? $xml, 'HEADING');
    if ($fdsysHeading && preg_match('/SUBCHAPTER\s+([A-Z0-9]+)/iu', $fdsysHeading, $m)) {
        $subchapterCode = strtoupper(trim($m[1]));
    }
}

echo "Resolved context: chapter=" . ($chapterCode ?? 'NULL') .
     " | subchapter=" . ($subchapterCode ?? 'NULL') . "\n";

$sectionCount = 0;
$paragraphCount = 0;

$pdo->beginTransaction();

try {
    $sectionNodes = $xml->xpath('.//SECTION');

    if ($sectionNodes === false) {
        throw new RuntimeException('Failed to locate SECTION nodes.');
    }

    foreach ($sectionNodes as $sectionNode) {
        $sectno = firstChildText($sectionNode, 'SECTNO');
        $subject = firstChildText($sectionNode, 'SUBJECT');

        $sectionNumber = null;
        if ($sectno && preg_match('/§\s*([0-9A-Za-z\.\-]+)/u', $sectno, $m)) {
            $sectionNumber = trim($m[1]);
        }

        if (!$sectionNumber) {
            $sectionNumber = extractSectionNumberFromHead($subject);
        }

        if (!$sectionNumber) {
            continue;
        }

        $heading = $subject ?: $sectno;
        $textPlain = collectSectionText($sectionNode);
        $partNumber = resolvePartNumberForSection($sectionNode);

        if ($textPlain === '' && $heading) {
            $textPlain = $heading;
        }

        $status = (stripos((string)$heading, '[Reserved]') !== false || stripos($textPlain, '[Reserved]') !== false)
            ? 'reserved'
            : 'active';

        $citation = "{$titleNumber} CFR {$sectionNumber}";
        $citationShort = "§ {$sectionNumber}";
        $versionHash = hash('sha256', $citation . '|' . ($heading ?? '') . '|' . $textPlain);

        $stmtSection->execute([
            ':regulation_source_id' => $regulationSourceId,
            ':title_number'         => $titleNumber,
            ':subtitle_code'        => null,
            ':chapter_code'         => $chapterCode,
            ':subchapter_code'      => $subchapterCode,
            ':part_number'          => $partNumber,
            ':subpart_code'         => null,
            ':section_number'       => $sectionNumber,
            ':citation'             => $citation,
            ':citation_short'       => $citationShort,
            ':heading'              => $heading,
            ':authority_text'       => null,
            ':source_note'          => null,
            ':status'               => $status,
            ':xml_identifier'       => null,
            ':version_hash'         => $versionHash,
            ':text_plain'           => $textPlain,
            ':text_html'            => null,
        ]);

        $regulationSectionId = (int)$pdo->lastInsertId();
        $sectionCount++;

        $paragraphs = parseParagraphsFromText($textPlain);
        foreach ($paragraphs as $p) {
            $stmtParagraph->execute([
                ':regulation_section_id' => $regulationSectionId,
                ':paragraph_path'        => $p['paragraph_path'],
                ':paragraph_label'       => $p['paragraph_label'],
                ':sort_key'              => $p['sort_key'],
                ':text_plain'            => $p['text_plain'],
            ]);
            $paragraphCount++;
        }

        echo "Imported: {$citation}";
        if ($heading) {
            echo " - {$heading}";
        }
        echo "\n";
    }

    $pdo->commit();

    echo "----------------------------------------\n";
    echo "IMPORT COMPLETE\n";
    echo "Sections Imported: {$sectionCount}\n";
    echo "Paragraphs Imported: {$paragraphCount}\n";
    echo "regulation_source_id: {$regulationSourceId}\n";
    echo "----------------------------------------\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    echo "Import failed: " . $e->getMessage() . "\n";
    exit(1);
}