<?php
require 'db_connect.php';

header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
$title = (int)($_GET['title'] ?? 46);
$subchapter = trim($_GET['subchapter'] ?? '');
$part = trim($_GET['part'] ?? '');

$allowedTitles = [33, 46, 47, 49];
if (!in_array($title, $allowedTitles, true)) {
    $title = 46;
}

$sql = "
    SELECT
        regulation_section_id,
        citation,
        heading,
        title_number,
        subchapter_code,
        part_number,
        text_plain
    FROM regulation_sections
    WHERE title_number = :title
";

$params = [
    ':title' => $title
];

if ($subchapter !== '') {
    $sql .= " AND subchapter_code = :subchapter";
    $params[':subchapter'] = $subchapter;
}

if ($part !== '') {
    $sql .= " AND part_number = :part";
    $params[':part'] = $part;
}

if ($q !== '') {
    $sql .= " AND (
        citation LIKE :like_q
        OR heading LIKE :like_q
        OR text_plain LIKE :like_q
        OR MATCH(citation, heading, text_plain) AGAINST (:match_q IN NATURAL LANGUAGE MODE)
    )";

    $params[':like_q'] = '%' . $q . '%';
    $params[':match_q'] = $q;

    $sql .= "
        ORDER BY
            CASE
                WHEN citation = :exact_q THEN 0
                WHEN citation LIKE :prefix_q THEN 1
                WHEN heading LIKE :like_q THEN 2
                ELSE 3
            END,
            part_number ASC,
            citation ASC
        LIMIT 25
    ";

    $params[':exact_q'] = $q;
    $params[':prefix_q'] = $q . '%';
} else {
    $sql .= "
        ORDER BY
            part_number ASC,
            citation ASC
        LIMIT 25
    ";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
 * Load paragraph matches for returned sections.
 * If q exists, prioritize matching paragraphs.
 * Otherwise return first few paragraphs for context.
 */
$paragraphStmtMatch = $pdo->prepare("
    SELECT
        regulation_paragraph_id,
        paragraph_path,
        paragraph_label,
        text_plain
    FROM regulation_paragraphs
    WHERE regulation_section_id = ?
      AND text_plain LIKE ?
    ORDER BY sort_key ASC
    LIMIT 8
");

$paragraphStmtDefault = $pdo->prepare("
    SELECT
        regulation_paragraph_id,
        paragraph_path,
        paragraph_label,
        text_plain
    FROM regulation_paragraphs
    WHERE regulation_section_id = ?
    ORDER BY sort_key ASC
    LIMIT 5
");

foreach ($sections as &$section) {
    $section['paragraphs'] = [];

    if ($q !== '') {
        $paragraphStmtMatch->execute([
            $section['regulation_section_id'],
            '%' . $q . '%'
        ]);
        $paragraphs = $paragraphStmtMatch->fetchAll(PDO::FETCH_ASSOC);

        if (!$paragraphs) {
            $paragraphStmtDefault->execute([$section['regulation_section_id']]);
            $paragraphs = $paragraphStmtDefault->fetchAll(PDO::FETCH_ASSOC);
        }
    } else {
        $paragraphStmtDefault->execute([$section['regulation_section_id']]);
        $paragraphs = $paragraphStmtDefault->fetchAll(PDO::FETCH_ASSOC);
    }

    $section['paragraphs'] = $paragraphs;
}

echo json_encode($sections);