<?php
require 'db_connect.php';
require 'session_check.php';

$title = 'Predefined Notes';
$back_link = 'library.php';
include 'top_nav.php';

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$q = trim($_GET['q'] ?? '');
$icr_id = (int)($_GET['icr_id'] ?? 0);
$note_type = trim($_GET['note_type'] ?? '');
$link_scope = trim($_GET['link_scope'] ?? '');

$valid_note_types = ['general','observation','deficiency','recommendation','disclosure'];
$valid_link_scopes = ['icr','step','substep'];

$icrOptions = $pdo->query("
    SELECT icr_id, icr_number, title
    FROM icrs
    ORDER BY icr_number ASC, title ASC
")->fetchAll(PDO::FETCH_ASSOC);

$sql = "
    SELECT
        pn.note_id,
        pn.note_text,
        pn.note_type,
        pn.usage_count,
        pn.is_active,
        pn.created_at,
        pnl.note_link_id,
        pnl.link_scope,
        pnl.icr_id,
        pnl.master_step_id,
        pnl.master_substep_id,
        i.icr_number,
        i.title AS icr_title,
        s.step_number,
        s.step_description,
        ss.substep_code,
        ss.description AS substep_description
    FROM predefined_notes pn
    LEFT JOIN predefined_note_links pnl
        ON pnl.note_id = pn.note_id
    LEFT JOIN icrs i
        ON i.icr_id = pnl.icr_id
    LEFT JOIN icr_steps s
        ON s.step_id = pnl.master_step_id
    LEFT JOIN icr_substeps ss
        ON ss.substep_id = pnl.master_substep_id
    WHERE pn.is_active = 1
";

$params = [];

if ($q !== '') {
    $sql .= " AND (
        pn.note_text LIKE :q
        OR i.icr_number LIKE :q
        OR i.title LIKE :q
        OR s.step_description LIKE :q
        OR ss.description LIKE :q
    )";
    $params[':q'] = '%' . $q . '%';
}

if ($icr_id > 0) {
    $sql .= " AND pnl.icr_id = :icr_id";
    $params[':icr_id'] = $icr_id;
}

if (in_array($note_type, $valid_note_types, true)) {
    $sql .= " AND pn.note_type = :note_type";
    $params[':note_type'] = $note_type;
}

if (in_array($link_scope, $valid_link_scopes, true)) {
    $sql .= " AND pnl.link_scope = :link_scope";
    $params[':link_scope'] = $link_scope;
}

$sql .= "
    ORDER BY
        i.icr_number ASC,
        s.step_number ASC,
        ss.substep_code ASC,
        pn.note_id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Predefined Notes - VMS</title>

    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0d6efd">

    <link rel="icon" type="image/png" sizes="192x192" href="/assets/vms-icon-192.png?v=2">
    <link rel="icon" type="image/png" sizes="512x512" href="/assets/vms-icon-512.png?v=2">
    <link rel="shortcut icon" href="/assets/vms-icon-192.png?v=2">
    <link rel="apple-touch-icon" href="/assets/vms-icon-192.png?v=2">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/vms-mobile.css" rel="stylesheet">

    <style>
        .notes-shell {
            background: var(--vms-bg, #f4f7fb);
            min-height: 100vh;
        }
        .notes-card {
            background: #fff;
            border: 1px solid #dbe4ee;
            border-radius: 16px;
            box-shadow: var(--vms-shadow, 0 8px 24px rgba(16,24,40,.08));
        }
        .note-item {
            border: 1px solid #e3e8ef;
            border-radius: 14px;
            padding: 14px;
            background: #fff;
        }
        .note-meta {
            font-size: .9rem;
            color: #6b7280;
        }
        .note-text {
            white-space: pre-wrap;
        }
        .filter-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 10px;
        }
        @media (min-width: 992px) {
            .filter-grid {
                grid-template-columns: 2fr 1.2fr 1fr 1fr auto;
                align-items: end;
            }
        }
    </style>
</head>
<body>
<div class="notes-shell">
    <div class="app-page">
        <div class="app-container">

            <div class="notes-card p-3 mb-3">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                    <div>
                        <h1 class="h4 mb-1">Predefined Notes</h1>
                        <div class="text-muted">Browse reusable notes saved from inspection runs.</div>
                    </div>
                    <a href="library.php" class="btn btn-outline-secondary btn-sm">← Back to Library</a>
                </div>
            </div>

            <div class="notes-card p-3 mb-3">
                <form method="get">
                    <div class="filter-grid">
                        <div>
                            <label class="form-label">Search</label>
                            <input type="text" name="q" class="form-control" value="<?= h($q) ?>" placeholder="Search note text, ICR, step, sub-step...">
                        </div>

                        <div>
                            <label class="form-label">ICR</label>
                            <select name="icr_id" class="form-select">
                                <option value="0">All ICRs</option>
                                <?php foreach ($icrOptions as $opt): ?>
                                    <option value="<?= (int)$opt['icr_id'] ?>" <?= $icr_id === (int)$opt['icr_id'] ? 'selected' : '' ?>>
                                        <?= h($opt['icr_number']) ?> - <?= h($opt['title']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="form-label">Note Type</label>
                            <select name="note_type" class="form-select">
                                <option value="">All Types</option>
                                <?php foreach ($valid_note_types as $type): ?>
                                    <option value="<?= h($type) ?>" <?= $note_type === $type ? 'selected' : '' ?>>
                                        <?= h(ucfirst($type)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="form-label">Scope</label>
                            <select name="link_scope" class="form-select">
                                <option value="">All Scopes</option>
                                <?php foreach ($valid_link_scopes as $scope): ?>
                                    <option value="<?= h($scope) ?>" <?= $link_scope === $scope ? 'selected' : '' ?>>
                                        <?= h(ucfirst($scope)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Filter</button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="notes-card p-3 mb-3">
                <strong><?= count($notes) ?></strong> active note<?= count($notes) === 1 ? '' : 's' ?> found.
            </div>

            <div class="vstack gap-3">
                <?php if (empty($notes)): ?>
                    <div class="notes-card p-3 text-muted">No predefined notes found.</div>
                <?php else: ?>
                    <?php foreach ($notes as $note): ?>
                        <div class="note-item">
                            <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap mb-2">
                                <div>
                                    <div class="fw-semibold">
                                        <?= h($note['icr_number'] ?? 'No ICR') ?>
                                        <?php if (!empty($note['step_number'])): ?>
                                            · Step <?= (int)$note['step_number'] ?>
                                        <?php endif; ?>
                                        <?php if (!empty($note['substep_code'])): ?>
                                            <?= h($note['substep_code']) ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="note-meta">
                                        Type: <?= h(ucfirst($note['note_type'])) ?>
                                        · Scope: <?= h(ucfirst($note['link_scope'] ?? 'step')) ?>
                                        · Uses: <?= (int)$note['usage_count'] ?>
                                        · Note ID: <?= (int)$note['note_id'] ?>
                                    </div>
                                </div>
                                <a href="manage_predefined_notes.php?note_id=<?= (int)$note['note_id'] ?>" class="btn btn-outline-primary btn-sm">
                                    Manage
                                </a>
                            </div>

                            <?php if (!empty($note['icr_title'])): ?>
                                <div class="small text-muted mb-1"><?= h($note['icr_title']) ?></div>
                            <?php endif; ?>

                            <?php if (!empty($note['step_description'])): ?>
                                <div class="small mb-1"><strong>Step:</strong> <?= h($note['step_description']) ?></div>
                            <?php endif; ?>

                            <?php if (!empty($note['substep_description'])): ?>
                                <div class="small mb-2"><strong>Sub-step:</strong> <?= h($note['substep_description']) ?></div>
                            <?php endif; ?>

                            <div class="note-text"><?= h($note['note_text']) ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>
</body>
</html>