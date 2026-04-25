<?php
require '../db_connect.php';
require '../session_check.php';

$title = 'CFR Library';
$back_link = '../library.php';
include '../top_nav.php';

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$q = trim($_GET['q'] ?? '');
$part = trim($_GET['part'] ?? '');
$subchapter = trim($_GET['subchapter'] ?? '');
$title_filter = trim($_GET['title'] ?? '46'); // can be 'all' or numeric string

/* Load available imported titles */
$titleStmt = $pdo->query("
    SELECT DISTINCT title_number
    FROM regulation_sections
    ORDER BY title_number
");
$availableTitles = array_map('strval', $titleStmt->fetchAll(PDO::FETCH_COLUMN));

if ($title_filter !== 'all' && !in_array($title_filter, $availableTitles, true)) {
    $title_filter = in_array('46', $availableTitles, true) ? '46' : ($availableTitles[0] ?? 'all');
}

/* Load subchapter options dynamically for selected title */
$subchapterOptions = [];
if ($title_filter !== 'all') {
    $subStmt = $pdo->prepare("
        SELECT DISTINCT subchapter_code
        FROM regulation_sections
        WHERE title_number = ?
          AND subchapter_code IS NOT NULL
          AND subchapter_code <> ''
        ORDER BY subchapter_code
    ");
    $subStmt->execute([(int)$title_filter]);
    $subchapterOptions = $subStmt->fetchAll(PDO::FETCH_COLUMN);
}

$sql = "
    SELECT
        regulation_section_id,
        citation,
        heading,
        title_number,
        chapter_code,
        subchapter_code,
        part_number,
        subpart_code,
        section_number,
        LEFT(text_plain, 1200) AS text_preview
    FROM regulation_sections
    WHERE 1=1
";

$params = [];

if ($title_filter !== 'all') {
    $sql .= " AND title_number = :title_number";
    $params[':title_number'] = (int)$title_filter;
}

if ($subchapter !== '') {
    $sql .= " AND subchapter_code = :subchapter_code";
    $params[':subchapter_code'] = $subchapter;
}

if ($part !== '') {
    $sql .= " AND part_number = :part_number";
    $params[':part_number'] = $part;
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
    $params[':exact_q'] = $q;
    $params[':prefix_q'] = $q . '%';

    $sql .= "
        ORDER BY
            CASE
                WHEN citation = :exact_q THEN 0
                WHEN citation LIKE :prefix_q THEN 1
                WHEN heading LIKE :like_q THEN 2
                ELSE 3
            END,
            title_number ASC,
            part_number ASC,
            section_number ASC,
            citation ASC
        LIMIT 100
    ";
} else {
    $sql .= "
        ORDER BY
            title_number ASC,
            part_number ASC,
            section_number ASC,
            citation ASC
        LIMIT 100
    ";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Load paragraph snippets */
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
    LIMIT 5
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
    LIMIT 3
");

foreach ($results as &$row) {
    $row['paragraphs'] = [];

    if ($q !== '') {
        $paragraphStmtMatch->execute([
            $row['regulation_section_id'],
            '%' . $q . '%'
        ]);
        $paragraphs = $paragraphStmtMatch->fetchAll(PDO::FETCH_ASSOC);

        if (!$paragraphs) {
            $paragraphStmtDefault->execute([$row['regulation_section_id']]);
            $paragraphs = $paragraphStmtDefault->fetchAll(PDO::FETCH_ASSOC);
        }
    } else {
        $paragraphStmtDefault->execute([$row['regulation_section_id']]);
        $paragraphs = $paragraphStmtDefault->fetchAll(PDO::FETCH_ASSOC);
    }

    $row['paragraphs'] = $paragraphs;
}
unset($row);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CFR Library - VMS</title>

    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0d6efd">

    <link rel="icon" type="image/png" sizes="192x192" href="/assets/vms-icon-192.png?v=2">
    <link rel="icon" type="image/png" sizes="512x512" href="/assets/vms-icon-512.png?v=2">
    <link rel="shortcut icon" href="/assets/vms-icon-192.png?v=2">
    <link rel="apple-touch-icon" href="/assets/vms-icon-192.png?v=2">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/vms-mobile.css" rel="stylesheet">

    <style>
        .dash-shell {
            background: var(--vms-bg, #f4f7fb);
            min-height: 100vh;
        }
        .page-top-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            margin-bottom: 14px;
            flex-wrap: wrap;
        }
        .page-top-actions .btn {
            min-height: 40px;
            border-radius: 12px;
        }
        .page-title {
            font-size: 1.65rem;
            font-weight: 700;
            margin: 0 0 4px;
            line-height: 1.15;
        }
        .page-subtitle {
            margin: 0;
            color: var(--vms-muted, #6b7280);
        }
        .search-grid {
            display: grid;
            grid-template-columns: repeat(1, minmax(0, 1fr));
            gap: 10px;
        }
        .preview-box {
            white-space: pre-wrap;
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 12px;
            font-size: 0.95rem;
        }
        .paragraph-box {
            white-space: pre-wrap;
            background: #f8fbff;
            border: 1px solid #d7e6ff;
            border-radius: 10px;
            padding: 10px;
            font-size: 0.9rem;
        }
        @media (min-width: 768px) {
            .search-grid {
                grid-template-columns: repeat(5, minmax(0, 1fr));
                align-items: end;
            }
            .search-grid .search-col-wide {
                grid-column: span 2;
            }
        }
    </style>
</head>
<body>
<div class="dash-shell">
    <div class="app-page">
        <div class="app-container">

            <div class="page-top-actions">
                <a href="../library.php" class="btn btn-outline-secondary btn-sm">← Back to Library</a>
            </div>

            <div class="vms-card mb-3">
                <h1 class="page-title">CFR Library</h1>
                <p class="page-subtitle">
                    Search and browse regulations by citation, keyword, part, or subchapter.
                </p>
            </div>

            <div class="vms-card mb-3">
                <form method="get">
                    <div class="search-grid">
                        <div class="search-col-wide">
                            <label class="form-label">Keyword / Citation</label>
                            <input type="text" name="q" class="form-control" value="<?= h($q) ?>">
                        </div>

                        <div>
                            <label class="form-label">Title</label>
                            <select name="title" id="titleSelect" class="form-select" onchange="this.form.submit()">
                                <option value="all" <?= $title_filter === 'all' ? 'selected' : '' ?>>All Imported Titles</option>
                                <?php foreach ($availableTitles as $t): ?>
                                    <option value="<?= h($t) ?>" <?= $title_filter === $t ? 'selected' : '' ?>>
                                        <?= h($t) ?> CFR
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="form-label">Subchapter</label>
                            <select name="subchapter" class="form-select" <?= $title_filter === 'all' ? 'disabled' : '' ?>>
                                <option value="">All</option>
                                <?php foreach ($subchapterOptions as $sc): ?>
                                    <option value="<?= h($sc) ?>" <?= $subchapter === $sc ? 'selected' : '' ?>>
                                        <?= h($sc) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($title_filter === 'all'): ?>
                                <div class="form-text">Choose a single title to filter by subchapter.</div>
                            <?php endif; ?>
                        </div>

                        <div>
                            <label class="form-label">Part</label>
                            <input type="text" name="part" class="form-control" value="<?= h($part) ?>">
                        </div>

                        <div>
                            <button type="submit" class="btn btn-primary w-100">Search</button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="vms-card mb-3">
                <strong><?= count($results) ?></strong> results found.
            </div>

            <?php foreach ($results as $row): ?>
                <div class="vms-card mb-3">
                    <h5 class="mb-1"><?= h($row['citation']) ?></h5>
                    <h6 class="text-muted mb-2"><?= h($row['heading'] ?? '') ?></h6>

                    <div class="mb-2 d-flex flex-wrap gap-2">
                        <span class="badge bg-secondary">Title <?= h($row['title_number']) ?></span>
                        <span class="badge bg-secondary">Chapter <?= h($row['chapter_code'] ?? '-') ?></span>
                        <span class="badge bg-secondary">Subchapter <?= h($row['subchapter_code'] ?? '-') ?></span>
                        <span class="badge bg-secondary">Part <?= h($row['part_number'] ?? '-') ?></span>
                        <span class="badge bg-secondary">Subpart <?= h($row['subpart_code'] ?? '-') ?></span>
                    </div>

                    <div class="preview-box mb-3"><?= h($row['text_preview'] ?? '') ?></div>

                    <?php if (!empty($row['paragraphs'])): ?>
                        <div class="mt-2">
                            <div class="fw-semibold mb-2">Relevant Paragraphs</div>
                            <?php foreach ($row['paragraphs'] as $p): ?>
                                <div class="paragraph-box mb-2">
                                    <div class="small fw-semibold mb-1">
                                        Paragraph <?= h($p['paragraph_path'] ?: $p['paragraph_label'] ?: '-') ?>
                                    </div>
                                    <div><?= h($p['text_plain'] ?? '') ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

        </div>
    </div>
</div>

<script src="/assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>