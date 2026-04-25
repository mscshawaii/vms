<?php
$title = $title ?? '';
$back_link = $back_link ?? 'dashboard.php';
?>

<div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom bg-white sticky-top" style="z-index: 1050;">
    
    <div class="fw-semibold">
        <?= htmlspecialchars($title) ?>
    </div>

    <div class="ms-auto d-flex gap-2">

        <!-- Back -->
        <a href="<?= htmlspecialchars($back_link) ?>"
           class="btn btn-outline-secondary btn-sm"
           title="Back">
            ←
        </a>

        <?php if ($company_id === 1): ?>
        <!-- Library (NEW) -->
        <a href="library.php"
           class="btn btn-outline-primary btn-sm"
           title="Library & Training">
            📚
        </a>
        <?php endif; ?>

        <!-- Settings -->
        <a href="user_settings.php"
           class="btn btn-outline-secondary btn-sm"
           title="User Settings">
            ⚙
        </a>

        <!-- Logout -->
        <a href="logout.php"
           class="btn btn-outline-danger btn-sm"
           title="Log Out">
            ⎋
        </a>

    </div>
</div>