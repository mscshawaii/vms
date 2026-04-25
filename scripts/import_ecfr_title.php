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

$defaultXmlPath = __DIR__ . '/../includes/imports/ecfr/title-46.xml';

$xmlFile = $argv[1] ?? $defaultXmlPath;
$titleNumber = isset($argv[2]) ? (int)$argv[2] : 46;
$sourceType = $argv[3] ?? 'ecfr_current';

$partsFilter = isset($argv[4]) && trim($argv[4]) !== ''
    ? array_map('trim', explode(',', $argv[4]))
    : [];

if (!file_exists($xmlFile)) {
    exit("XML file not found: {$xmlFile}\n");
}

if (!in_array($sourceType, ['ecfr_current', 'cfr_annual'], true)) {
    exit("Invalid source_type. Use ecfr_current or cfr_annual.\n");
}

$GLOBALS['partsFilter'] = $partsFilter;

echo "----------------------------------------\n";
echo "ECFR IMPORT START\n";
echo "File: {$xmlFile}\n";
echo "Title: {$titleNumber}\n";
echo "Source Type: {$sourceType}\n";
echo "----------------------------------------\n";

$fileName = basename($xmlFile);
$fileHash = hash_file('sha256', $xmlFile);

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
echo "Root TYPE: " . (string)($xml['TYPE'] ?? '') . "\n";
echo "Root N: " . (string)($xml['N'] ?? '') . "\n";

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/
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

function getInnerText(SimpleXMLElement $node): string
{
    return trim((string)$node);
}

function normalizeHeading(?string $heading): ?string
{
    $heading = cleanText($heading);
    if (!$heading) {
        return null;
    }
    return $heading;
}

function extractSectionNumberFromHeading(?string $heading): ?string
{
    if (!$heading) {
        return null;
    }

    if (preg_match('/§\s*([0-9A-Za-z\.\-]+)/u', $heading, $m)) {
        return trim($m[1]);
    }

    return null;
}

function collectNodeText(SimpleXMLElement $node): string
{
    $parts = [];

    $directText = cleanText((string)$node);
    if ($directText !== null) {
        $parts[] = $directText;
    }

    foreach ($node->children() as $child) {
        $text = cleanText((string)$child);
        if ($text !== null) {
            $parts[] = $text;
        }
    }

    $joined = implode("\n", array_unique(array_filter($parts)));
    return cleanText($joined) ?? '';
}

function getHierarchyCitation(SimpleXMLElement $node): ?string
{
    $attr = (string)($node['hierarchy_metadata'] ?? '');
    if ($attr === '') {
        return null;
    }

    $decoded = html_entity_decode($attr, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $json = json_decode($decoded, true);

    if (is_array($json) && isset($json['citation'])) {
        return cleanText((string)$json['citation']);
    }

    return null;
}

function normalizePartNumber(?string $value): ?string
{
    $value = cleanText($value);
    if (!$value) {
        return null;
    }
    return $value;
}

function parseParagraphsFromSectionText(string $text): array
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

    // If no structured paragraph labels were found, keep one paragraph blob
    if (!$paragraphs) {
        $paragraphs[] = [
            'label' => null,
            'text'  => $text,
        ];
    }

    // Add path/sort_key
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

function importSection(
    PDOStatement $stmtSection,
    PDOStatement $stmtParagraph,
    int $regulationSourceId,
    int $titleNumber,
    ?string $chapterCode,
    ?string $subchapterCode,
    ?string $partNumber,
    ?string $subpartCode,
    SimpleXMLElement $sectionNode,
    int &$sectionCount,
    int &$paragraphCount
): void {
    $sectionN = cleanText((string)($sectionNode['N'] ?? null));
    $heading = null;
    $textParts = [];
    $status = 'active';

    foreach ($sectionNode->children() as $child) {
        $childName = strtoupper($child->getName());
        $childText = cleanText((string)$child);

        switch ($childName) {
            case 'HEAD':
                if ($heading === null) {
                    $heading = normalizeHeading($childText);
                }
                break;

            case 'P':
            case 'FP':
            case 'FP-2':
            case 'NOTE':
            case 'EXTRACT':
            case 'CITA':
            case 'EDNOTE':
            case 'SOURCE':
            case 'AUTH':
            case 'TABLE':
            case 'DIV':
            case 'MATH':
            case 'XREF':
                $blockText = cleanText((string)$child);
                if ($blockText !== null && $blockText !== '') {
                    $textParts[] = $blockText;
                }
                break;

            default:
                if ($childText !== null) {
                    $textParts[] = $childText;
                }
                break;
        }
    }

    $sectionNumber = $sectionN ?: extractSectionNumberFromHeading($heading);
    if (!$sectionNumber) {
        return;
    }

    $partsFilter = $GLOBALS['partsFilter'] ?? [];

    if (!empty($partsFilter) && !in_array((string)$partNumber, $partsFilter, true)) {
        return;
    }

    $citation = "{$titleNumber} CFR {$sectionNumber}";
    $citationShort = "§ {$sectionNumber}";
    $textPlain = cleanText(implode("\n", array_filter($textParts))) ?? '';

    if ($textPlain === '' && $heading) {
        $textPlain = $heading;
    }

    if ($heading && stripos($heading, '[Reserved]') !== false) {
        $status = 'reserved';
    } elseif (stripos($textPlain, '[Reserved]') !== false) {
        $status = 'reserved';
    }

    $xmlIdentifier = getHierarchyCitation($sectionNode);
    $versionHash = hash('sha256', $citation . '|' . ($heading ?? '') . '|' . $textPlain);

    $stmtSection->execute([
        ':regulation_source_id' => $regulationSourceId,
        ':title_number'         => $titleNumber,
        ':subtitle_code'        => null,
        ':chapter_code'         => $chapterCode,
        ':subchapter_code'      => $subchapterCode,
        ':part_number'          => $partNumber,
        ':subpart_code'         => $subpartCode,
        ':section_number'       => $sectionNumber,
        ':citation'             => $citation,
        ':citation_short'       => $citationShort,
        ':heading'              => $heading,
        ':authority_text'       => null,
        ':source_note'          => null,
        ':status'               => $status,
        ':xml_identifier'       => $xmlIdentifier,
        ':version_hash'         => $versionHash,
        ':text_plain'           => $textPlain,
        ':text_html'            => null,
    ]);

    $regulationSectionId = (int)$GLOBALS['pdo']->lastInsertId();
    $sectionCount++;

    $paragraphs = parseParagraphsFromSectionText($textPlain);

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

/*
|--------------------------------------------------------------------------
| Insert regulation source row
|--------------------------------------------------------------------------
*/
$insertSourceSql = "
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
        NULL,
        NULL,
        NULL,
        :file_name,
        :sha256_hash,
        NOW(),
        1
    )
";

$stmtSource = $pdo->prepare($insertSourceSql);
$stmtSource->execute([
    ':source_type'  => $sourceType,
    ':title_number' => $titleNumber,
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

/*
|--------------------------------------------------------------------------
| Prepared inserts
|--------------------------------------------------------------------------
*/
$insertSectionSql = "
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
";

$insertParagraphSql = "
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
";

$stmtSection = $pdo->prepare($insertSectionSql);
$stmtParagraph = $pdo->prepare($insertParagraphSql);

function traverseForSections(
    SimpleXMLElement $node,
    int $titleNumber,
    int $regulationSourceId,
    PDOStatement $stmtSection,
    PDOStatement $stmtParagraph,
    int &$sectionCount,
    int &$paragraphCount,
    array $context = []
): void {
    $nodeType = strtoupper((string)($node['TYPE'] ?? ''));
    $nodeN    = cleanText((string)($node['N'] ?? null));

    // Carry forward context
    $chapterCode    = $context['chapter_code'] ?? null;
    $subchapterCode = $context['subchapter_code'] ?? null;
    $partNumber     = $context['part_number'] ?? null;
    $subpartCode    = $context['subpart_code'] ?? null;

    // Update context based on TYPE, not DIV level
    if ($nodeType === 'CHAPTER') {
        $chapterCode = $nodeN ?: $chapterCode;
    }

    if ($nodeType === 'SUBCHAP') {
        $subchapterCode = $nodeN ?: $subchapterCode;
    }

    if ($nodeType === 'PART') {
        $partNumber = normalizePartNumber($nodeN);
        $subpartCode = null;
    }

    if ($nodeType === 'SUBPART') {
        $subpartCode = $nodeN ?: $subpartCode;
    }

    // Import any SECTION node regardless of DIV number
    if ($nodeType === 'SECTION') {
        echo "SECTION hit | part=" . ($partNumber ?? 'NULL') . " | N=" . ($nodeN ?? 'NULL') . "\n";

        importSection(
            $stmtSection,
            $stmtParagraph,
            $regulationSourceId,
            $titleNumber,
            $chapterCode,
            $subchapterCode,
            $partNumber,
            $subpartCode,
            $node,
            $sectionCount,
            $paragraphCount
        );
        return;
    }

    foreach ($node->children() as $child) {
        traverseForSections(
            $child,
            $titleNumber,
            $regulationSourceId,
            $stmtSection,
            $stmtParagraph,
            $sectionCount,
            $paragraphCount,
            [
                'chapter_code'    => $chapterCode,
                'subchapter_code' => $subchapterCode,
                'part_number'     => $partNumber,
                'subpart_code'    => $subpartCode,
            ]
        );
    }
}

/*
|--------------------------------------------------------------------------
| Traverse XML
|--------------------------------------------------------------------------
*/
$sectionCount = 0;
$paragraphCount = 0;

$pdo->beginTransaction();

try {
    echo "Traversing entire XML document for title {$titleNumber}\n";

    if (!empty($partsFilter)) {
        echo "Part filter: " . implode(',', $partsFilter) . "\n";
    }

    traverseForSections(
        $xml,
        $titleNumber,
        $regulationSourceId,
        $stmtSection,
        $stmtParagraph,
        $sectionCount,
        $paragraphCount,
        []
    );

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