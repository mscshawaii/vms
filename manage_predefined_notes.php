<?php
require 'db_connect.php';
require 'session_check.php';

$title = 'Manage Predefined Notes';
$back_link = 'library.php';
include 'top_nav.php';

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$note_id = (int)($_GET['note_id'] ?? $_POST['note_id'] ?? 0);
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($note_id <= 0) {
        $error = 'Invalid note ID.';
    } else {
        if ($action === 'update_note') {
            $note_text = trim($_POST['note_text'] ?? '');
            $note_type = trim($_POST['note_type'] ?? 'general');
            $allowed = ['general','observation','deficiency','recommendation','disclosure'];

            if ($note_text === '') {
                $error = 'Note text cannot be blank.';
            } elseif (!in_array($note_type, $allowed, true)) {
                $error = 'Invalid note type.';
            } else {
                $stmt = $pdo->prepare("
                    UPDATE predefined_notes
                    SET note_text = ?, note_type = ?, updated_at = NOW()
                    WHERE note_id = ?
                ");
                $stmt->execute([$note_text, $note_type, $note_id]);
                $message = 'Note updated successfully.';
            }
        }

        if ($action === 'deactivate_note') {
            $stmt = $pdo->prepare("
                UPDATE predefined_notes
                SET is_active = 0, updated_at = NOW()
                WHERE note_id = ?
            ");
            $stmt->execute([$note_id]);
            $message = 'Note deactivated.';
        }

        if ($action === 'reactivate_note') {
            $stmt = $pdo->prepare("
                UPDATE predefined_notes
                SET is_active = 1, updated_at = NOW()
                WHERE note_id = ?
            ");
            $stmt->execute([$note_id]);
            $message = 'Note reactivated.';
        }
    }
}

$selectedNote = null;
$noteLinks = [];

if ($note_id > 0) {
    $stmt = $pdo->prepare("
        SELECT *
        FROM predefined_notes
        WHERE note_id = ?
        LIMIT 1
    ");
    $stmt->execute([$note_id]);
    $selectedNote = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($selectedNote) {
        $stmt = $pdo->prepare("
            SELECT
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
            FROM predefined_note_links pnl
            LEFT JOIN icrs i
                ON i.icr_id = pnl.icr_id
            LEFT JOIN icr_steps s
                ON s.step_id = pnl.master_step_id
            LEFT JOIN icr_substeps ss
                ON ss.substep_id = pnl.master_substep_id
            WHERE pnl.note_id = ?
            ORDER BY pnl.note_link_id ASC
        ");
        $stmt->execute([$note_id]);
        $noteLinks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$recentNotes = $pdo->query("
    SELECT note_id, note_type, is_active, usage_count, LEFT(note_text, 120) AS note_preview
    FROM predefined_notes
    ORDER BY note_id DESC
    LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Predefined Notes - VMS</title>

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
        .recent-note {
            border: 1px solid #e3e8ef;
            border-radius: 12px;
            padding: 10px;
            background: #fff;
        }
        .link-card {
            border: 1px solid #e3e8ef;
            border-radius: 12px;
            padding: 10px;
            background: #f8fbff;
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
                        <h1 class="h4 mb-1">Manage Predefined Notes</h1>
                        <div class="text-muted">Review, edit, deactivate, or reactivate saved notes.</div>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="predefined_notes.php" class="btn btn-outline-primary btn-sm">Browse Notes</a>
                        <a href="library.php" class="btn btn-outline-secondary btn-sm">← Back to Library</a>
                    </div>
                </div>
            </div>

            <?php if ($message !== ''): ?>
                <div class="alert alert-success"><?= h($message) ?></div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?= h($error) ?></div>
            <?php endif; ?>

            <div class="row g-3">
                <div class="col-lg-4">
                    <div class="notes-card p-3">
                        <h5 class="mb-3">Recent Notes</h5>

                        <div class="vstack gap-2">
                            <?php foreach ($recentNotes as $note): ?>
                                <a href="manage_predefined_notes.php?note_id=<?= (int)$note['note_id'] ?>"
                                   class="recent-note text-decoration-none text-dark">
                                    <div class="d-flex justify-content-between align-items-start gap-2">
                                        <div>
                                            <div class="fw-semibold">#<?= (int)$note['note_id'] ?></div>
                                            <div class="small text-muted">
                                                <?= h(ucfirst($note['note_type'])) ?>
                                                · Uses: <?= (int)$note['usage_count'] ?>
                                                · <?= (int)$note['is_active'] === 1 ? 'Active' : 'Inactive' ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="small mt-2"><?= h($note['note_preview']) ?></div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    <div class="notes-card p-3">
                        <?php if (!$selectedNote): ?>
                            <div class="text-muted">Select a note from the left to manage it.</div>
                        <?php else: ?>
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                                <div>
                                    <h5 class="mb-1">Note #<?= (int)$selectedNote['note_id'] ?></h5>
                                    <div class="small text-muted">
                                        Uses: <?= (int)$selectedNote['usage_count'] ?>
                                        · Status: <?= (int)$selectedNote['is_active'] === 1 ? 'Active' : 'Inactive' ?>
                                    </div>
                                </div>
                            </div>

                            <form method="post" class="mb-3">
                                <input type="hidden" name="note_id" value="<?= (int)$selectedNote['note_id'] ?>">
                                <input type="hidden" name="action" value="update_note">

                                <div class="mb-3">
                                    <label class="form-label">Note Type</label>
                                    <select name="note_type" class="form-select">
                                        <?php foreach (['general','observation','deficiency','recommendation','disclosure'] as $type): ?>
                                            <option value="<?= h($type) ?>" <?= $selectedNote['note_type'] === $type ? 'selected' : '' ?>>
                                                <?= h(ucfirst($type)) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Note Text</label>
                                    <textarea name="note_text" class="form-control" rows="6" required><?= h($selectedNote['note_text']) ?></textarea>
                                </div>

                                <div class="d-flex gap-2 flex-wrap">
                                    <button type="submit" class="btn btn-primary">Save Changes</button>
                                </div>
                            </form>

                            <div class="d-flex gap-2 flex-wrap mb-4">
                                <?php if ((int)$selectedNote['is_active'] === 1): ?>
                                    <form method="post">
                                        <input type="hidden" name="note_id" value="<?= (int)$selectedNote['note_id'] ?>">
                                        <input type="hidden" name="action" value="deactivate_note">
                                        <button type="submit" class="btn btn-outline-danger">Deactivate Note</button>
                                    </form>
                                <?php else: ?>
                                    <form method="post">
                                        <input type="hidden" name="note_id" value="<?= (int)$selectedNote['note_id'] ?>">
                                        <input type="hidden" name="action" value="reactivate_note">
                                        <button type="submit" class="btn btn-outline-success">Reactivate Note</button>
                                    </form>
                                <?php endif; ?>
                            </div>

                            <h6 class="mb-3">Linked Context</h6>
                            <div class="vstack gap-2">
                                <?php if (empty($noteLinks)): ?>
                                    <div class="text-muted">No linked context found for this note.</div>
                                <?php else: ?>
                                    <?php foreach ($noteLinks as $link): ?>
                                        <div class="link-card">
                                            <div><strong>Scope:</strong> <?= h(ucfirst($link['link_scope'])) ?></div>
                                            <?php if (!empty($link['icr_number'])): ?>
                                                <div><strong>ICR:</strong> <?= h($link['icr_number']) ?> - <?= h($link['icr_title']) ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($link['step_number'])): ?>
                                                <div><strong>Step <?= (int)$link['step_number'] ?>:</strong> <?= h($link['step_description']) ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($link['substep_code'])): ?>
                                                <div><strong>Sub-step <?= h($link['substep_code']) ?>:</strong> <?= h($link['substep_description']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
</body>
</html>