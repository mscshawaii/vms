<?php
declare(strict_types=1);

if (!function_exists('checklist_normalize_run_type')) {
    function checklist_normalize_run_type(?string $runType): ?string
    {
        $runType = trim((string)$runType);
        $allowed = ['pre_underway', 'post_underway'];
        return in_array($runType, $allowed, true) ? $runType : null;
    }
}

if (!function_exists('checklist_get_accessible_vessel')) {
    function checklist_get_accessible_vessel(PDO $pdo, int $vesselId, bool $canAccessAllVessels, int $companyId): ?array
    {
        if ($vesselId <= 0) {
            return null;
        }

        if ($canAccessAllVessels) {
            $stmt = $pdo->prepare("
                SELECT vessel_id, vesselName, vesselON, hailingPort, company_id
                FROM vessels
                WHERE vessel_id = ?
                LIMIT 1
            ");
            $stmt->execute([$vesselId]);
        } else {
            $stmt = $pdo->prepare("
                SELECT vessel_id, vesselName, vesselON, hailingPort, company_id
                FROM vessels
                WHERE vessel_id = ?
                  AND company_id = ?
                LIMIT 1
            ");
            $stmt->execute([$vesselId, $companyId]);
        }

        $vessel = $stmt->fetch(PDO::FETCH_ASSOC);
        return $vessel ?: null;
    }
}

if (!function_exists('checklist_get_template_by_type')) {
    function checklist_get_template_by_type(PDO $pdo, string $runType): ?array
    {
        $stmt = $pdo->prepare("
            SELECT template_id, template_key, template_name, is_active
            FROM checklist_templates
            WHERE template_key = ?
              AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$runType]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);
        return $template ?: null;
    }
}

if (!function_exists('checklist_get_template_items')) {
    function checklist_get_template_items(PDO $pdo, int $templateId): array
    {
        $stmt = $pdo->prepare("
            SELECT template_item_id, template_id, item_label, sort_order, is_active
            FROM checklist_template_items
            WHERE template_id = ?
              AND is_active = 1
            ORDER BY sort_order ASC, template_item_id ASC
        ");
        $stmt->execute([$templateId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('checklist_get_vessel_items')) {
    function checklist_get_vessel_items(PDO $pdo, int $vesselId, int $templateId): array
    {
        if ($vesselId <= 0 || $templateId <= 0) {
            return [];
        }

        $stmt = $pdo->prepare("
            SELECT vessel_checklist_item_id, vessel_id, template_id, item_label, sort_order, is_active
            FROM checklist_vessel_items
            WHERE vessel_id = ?
              AND template_id = ?
              AND is_active = 1
            ORDER BY sort_order ASC, vessel_checklist_item_id ASC
        ");
        $stmt->execute([$vesselId, $templateId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('checklist_get_vessel_item_by_id')) {
    function checklist_get_vessel_item_by_id(PDO $pdo, int $vesselChecklistItemId): ?array
    {
        if ($vesselChecklistItemId <= 0) {
            return null;
        }

        $stmt = $pdo->prepare("
            SELECT vessel_checklist_item_id, vessel_id, template_id, item_label, sort_order, is_active, created_by, created_at, updated_at
            FROM checklist_vessel_items
            WHERE vessel_checklist_item_id = ?
            LIMIT 1
        ");
        $stmt->execute([$vesselChecklistItemId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        return $item ?: null;
    }
}

if (!function_exists('checklist_get_suppressed_core_item_ids')) {
    function checklist_get_suppressed_core_item_ids(PDO $pdo, int $vesselId, int $templateId): array
    {
        if ($vesselId <= 0 || $templateId <= 0) {
            return [];
        }

        $stmt = $pdo->prepare("
            SELECT template_item_id
            FROM checklist_vessel_item_suppressions
            WHERE vessel_id = ?
              AND template_id = ?
              AND is_active = 1
        ");
        $stmt->execute([$vesselId, $templateId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }
}

if (!function_exists('checklist_get_suppressed_core_items')) {
    function checklist_get_suppressed_core_items(PDO $pdo, int $vesselId, int $templateId): array
    {
        if ($vesselId <= 0 || $templateId <= 0) {
            return [];
        }

        $stmt = $pdo->prepare("
            SELECT
                s.vessel_item_suppression_id,
                s.vessel_id,
                s.template_id,
                s.template_item_id,
                s.is_active,
                s.created_at,
                s.updated_at,
                cti.item_label,
                cti.sort_order
            FROM checklist_vessel_item_suppressions s
            INNER JOIN checklist_template_items cti
                ON cti.template_item_id = s.template_item_id
            WHERE s.vessel_id = ?
              AND s.template_id = ?
              AND s.is_active = 1
            ORDER BY cti.sort_order ASC, cti.template_item_id ASC
        ");
        $stmt->execute([$vesselId, $templateId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('checklist_get_core_item_by_id')) {
    function checklist_get_core_item_by_id(PDO $pdo, int $templateItemId): ?array
    {
        if ($templateItemId <= 0) {
            return null;
        }

        $stmt = $pdo->prepare("
            SELECT template_item_id, template_id, item_label, sort_order, is_active
            FROM checklist_template_items
            WHERE template_item_id = ?
            LIMIT 1
        ");
        $stmt->execute([$templateItemId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        return $item ?: null;
    }
}

if (!function_exists('checklist_parse_response_key')) {
    function checklist_parse_response_key(string $responseKey): ?array
    {
        if (!preg_match('/^(core|vessel):(\d+)$/', $responseKey, $matches)) {
            return null;
        }

        return [
            'source' => $matches[1],
            'source_id' => (int)$matches[2],
        ];
    }
}

if (!function_exists('checklist_normalize_return_to')) {
    function checklist_normalize_return_to(?string $returnTo, int $vesselId): string
    {
        $fallback = 'vessel_log_create.php?vessel_id=' . $vesselId;
        $returnTo = trim((string)$returnTo);

        if ($returnTo === '') {
            return $fallback;
        }

        if (preg_match('/[\r\n]/', $returnTo)) {
            return $fallback;
        }

        if (preg_match('/^https?:\/\//i', $returnTo)) {
            return $fallback;
        }

        if (str_starts_with($returnTo, '/')) {
            return $fallback;
        }

        return $returnTo;
    }
}

if (!function_exists('checklist_append_query_params')) {
    function checklist_append_query_params(string $url, array $params): string
    {
        $fragment = '';
        $fragmentPos = strpos($url, '#');
        if ($fragmentPos !== false) {
            $fragment = substr($url, $fragmentPos);
            $url = substr($url, 0, $fragmentPos);
        }

        $separator = str_contains($url, '?') ? '&' : '?';
        return $url . $separator . http_build_query($params) . $fragment;
    }
}

if (!function_exists('checklist_get_run_header')) {
    function checklist_get_run_header(PDO $pdo, int $checklistRunId): ?array
    {
        if ($checklistRunId <= 0) {
            return null;
        }

        $stmt = $pdo->prepare("
            SELECT
                cr.checklist_run_id,
                cr.template_id,
                cr.vessel_id,
                cr.log_id,
                cr.run_type,
                cr.status,
                cr.created_by,
                cr.created_at,
                cr.updated_at,
                ct.template_key,
                ct.template_name
            FROM checklist_runs cr
            INNER JOIN checklist_templates ct
                ON ct.template_id = cr.template_id
            WHERE cr.checklist_run_id = ?
            LIMIT 1
        ");
        $stmt->execute([$checklistRunId]);
        $run = $stmt->fetch(PDO::FETCH_ASSOC);
        return $run ?: null;
    }
}

if (!function_exists('checklist_get_run_response_summary')) {
    function checklist_get_run_response_summary(PDO $pdo, int $checklistRunId): array
    {
        $summary = [
            'complete' => 0,
            'not_complete' => 0,
            'na' => 0,
            'total' => 0,
        ];

        if ($checklistRunId <= 0) {
            return $summary;
        }

        $stmt = $pdo->prepare("
            SELECT response_value, COUNT(*) AS item_count
            FROM checklist_run_items
            WHERE checklist_run_id = ?
            GROUP BY response_value
        ");
        $stmt->execute([$checklistRunId]);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $responseValue = (string)($row['response_value'] ?? '');
            $itemCount = (int)($row['item_count'] ?? 0);

            if (array_key_exists($responseValue, $summary)) {
                $summary[$responseValue] = $itemCount;
                $summary['total'] += $itemCount;
            }
        }

        return $summary;
    }
}

if (!function_exists('checklist_get_run_items')) {
    function checklist_get_run_items(PDO $pdo, int $checklistRunId): array
    {
        if ($checklistRunId <= 0) {
            return [];
        }

        $regulationRefSelect = 'NULL AS regulation_ref';
        try {
            $columnStmt = $pdo->prepare("SHOW COLUMNS FROM `checklist_template_items` LIKE ?");
            $columnStmt->execute(['regulation_ref']);
            if ($columnStmt->fetch(PDO::FETCH_ASSOC)) {
                $regulationRefSelect = 'cti.regulation_ref AS regulation_ref';
            }
        } catch (Throwable $e) {
            $regulationRefSelect = 'NULL AS regulation_ref';
        }

        $stmt = $pdo->prepare("
            SELECT
                cri.checklist_run_item_id,
                cri.checklist_run_id,
                cri.template_item_id,
                cri.vessel_checklist_item_id,
                cri.response_value,
                cri.response_note,
                COALESCE(cti.item_label, cvi.item_label) AS item_label,
                CASE
                    WHEN cri.vessel_checklist_item_id IS NOT NULL THEN 'vessel'
                    ELSE 'core'
                END AS source_type,
                CASE
                    WHEN cri.vessel_checklist_item_id IS NOT NULL THEN cvi.sort_order
                    ELSE cti.sort_order
                END AS sort_order,
                $regulationRefSelect
            FROM checklist_run_items cri
            LEFT JOIN checklist_template_items cti
                ON cti.template_item_id = cri.template_item_id
            LEFT JOIN checklist_vessel_items cvi
                ON cvi.vessel_checklist_item_id = cri.vessel_checklist_item_id
            WHERE cri.checklist_run_id = ?
            ORDER BY
                CASE
                    WHEN cri.vessel_checklist_item_id IS NOT NULL THEN 1
                    ELSE 0
                END ASC,
                sort_order ASC,
                cri.checklist_run_item_id ASC
        ");
        $stmt->execute([$checklistRunId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
