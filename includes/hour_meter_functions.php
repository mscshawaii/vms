<?php
declare(strict_types=1);

if (!function_exists('vms_hour_current_user_id')) {
    function vms_hour_current_user_id(): int
    {
        return (int)($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
    }
}

if (!function_exists('vms_hour_current_role_id')) {
    function vms_hour_current_role_id(): int
    {
        return (int)($_SESSION['role_id'] ?? 99);
    }
}

if (!function_exists('vms_hour_user_can_manage_history')) {
    function vms_hour_user_can_manage_history(): bool
    {
        return in_array(vms_hour_current_role_id(), [1, 2], true);
    }
}

if (!function_exists('vms_hour_supported_tracking_fields')) {
    function vms_hour_supported_tracking_fields(): array
    {
        return ['hour_tracked', 'hour_display_order', 'hour_meter_active', 'hour_baseline_hours'];
    }
}

if (!function_exists('vms_hour_table_exists')) {
    function vms_hour_table_exists(PDO $pdo, string $table): bool
    {
        try {
            $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
            $stmt->execute([$table]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('vms_hour_column_exists')) {
    function vms_hour_column_exists(PDO $pdo, string $table, string $column): bool
    {
        try {
            $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
            $stmt->execute([$column]);
            return (bool)$stmt->fetch();
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('vms_hour_clean_text')) {
    function vms_hour_clean_text($value): string
    {
        return trim((string)$value);
    }
}

if (!function_exists('vms_hour_decimal_or_null')) {
    function vms_hour_decimal_or_null($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        return round((float)$value, 1);
    }
}

if (!function_exists('vms_hour_int_or_default')) {
    function vms_hour_int_or_default($value, int $default = 0): int
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return $default;
        }

        return (int)$value;
    }
}

if (!function_exists('vms_hour_equipment_tracking_class')) {
    function vms_hour_equipment_tracking_class(int $equipmentTypeId, ?int $equipmentSubtypeId = null): ?string
    {
        if ($equipmentTypeId === 20) {
            return 'propulsion_engine';
        }

        if ($equipmentTypeId === 21 && (int)$equipmentSubtypeId === 48) {
            return 'generator';
        }

        return null;
    }
}

if (!function_exists('vms_hour_is_tracking_eligible')) {
    function vms_hour_is_tracking_eligible(int $equipmentTypeId, ?int $equipmentSubtypeId = null): bool
    {
        return vms_hour_equipment_tracking_class($equipmentTypeId, $equipmentSubtypeId) !== null;
    }
}

if (!function_exists('vms_hour_tracking_payload_from_request')) {
    function vms_hour_tracking_payload_from_request(array $request, int $equipmentTypeId, ?int $equipmentSubtypeId = null): array
    {
        $trackedClass = vms_hour_equipment_tracking_class($equipmentTypeId, $equipmentSubtypeId);
        $hourTracked = $trackedClass !== null && (string)($request['hour_tracked'] ?? '0') === '1';
        $displayOrder = max(0, vms_hour_int_or_default($request['hour_display_order'] ?? 0, 0));
        $meterActive = $hourTracked ? ((string)($request['hour_meter_active'] ?? '1') === '1' ? 1 : 0) : 0;
        $baselineHours = $hourTracked ? (vms_hour_decimal_or_null($request['hour_baseline_hours'] ?? 0) ?? 0.0) : 0.0;

        return [
            'eligible' => $trackedClass !== null,
            'hour_tracked' => $hourTracked ? 1 : 0,
            'tracked_class' => $hourTracked ? $trackedClass : null,
            'display_order' => $displayOrder,
            'meter_active' => $meterActive,
            'baseline_hours' => $baselineHours,
        ];
    }
}

if (!function_exists('vms_hour_assert_unique_location')) {
    function vms_hour_assert_unique_location(PDO $pdo, int $vesselId, string $trackedClass, string $location, ?int $excludeEquipmentId = null): void
    {
        $location = trim($location);
        if ($location === '') {
            throw new RuntimeException('Tracked equipment location is required.');
        }

        $sql = "
            SELECT hm.equipment_id
            FROM equipment_hour_meters hm
            INNER JOIN equipment e ON e.eid = hm.equipment_id
            WHERE hm.vessel_id = ?
              AND hm.tracked_class = ?
              AND hm.is_active = 1
              AND e.is_active = 1
              AND LOWER(TRIM(COALESCE(e.equipmentLocation, ''))) = LOWER(TRIM(?))
        ";
        $params = [$vesselId, $trackedClass, $location];

        if ($excludeEquipmentId !== null) {
            $sql .= " AND hm.equipment_id <> ?";
            $params[] = $excludeEquipmentId;
        }

        $sql .= " LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        if ($stmt->fetchColumn()) {
            throw new RuntimeException('Another active tracked item in this vessel/class already uses that location.');
        }
    }
}

if (!function_exists('vms_hour_location_conflict_exists')) {
    function vms_hour_location_conflict_exists(PDO $pdo, int $vesselId, string $trackedClass, string $location, ?int $excludeEquipmentId = null): bool
    {
        $location = trim($location);
        if ($location === '') {
            return false;
        }

        $sql = "
            SELECT hm.equipment_id
            FROM equipment_hour_meters hm
            INNER JOIN equipment e ON e.eid = hm.equipment_id
            WHERE hm.vessel_id = ?
              AND hm.tracked_class = ?
              AND hm.is_active = 1
              AND e.is_active = 1
              AND LOWER(TRIM(COALESCE(e.equipmentLocation, ''))) = LOWER(TRIM(?))
        ";
        $params = [$vesselId, $trackedClass, $location];

        if ($excludeEquipmentId !== null) {
            $sql .= " AND hm.equipment_id <> ?";
            $params[] = $excludeEquipmentId;
        }

        $sql .= " LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (bool)$stmt->fetchColumn();
    }
}

if (!function_exists('vms_hour_sync_equipment_meter')) {
    function vms_hour_sync_equipment_meter(PDO $pdo, int $equipmentId, int $vesselId, string $location, array $tracking): void
    {
        if (!vms_hour_table_exists($pdo, 'equipment_hour_meters')) {
            return;
        }

        if (empty($tracking['hour_tracked']) || empty($tracking['tracked_class'])) {
            $stmt = $pdo->prepare("UPDATE equipment_hour_meters SET is_active = 0, updated_at = NOW() WHERE equipment_id = ?");
            $stmt->execute([$equipmentId]);
            return;
        }

        vms_hour_assert_unique_location($pdo, $vesselId, (string)$tracking['tracked_class'], $location, $equipmentId);

        $existingStmt = $pdo->prepare("SELECT meter_id, baseline_hours, current_hours FROM equipment_hour_meters WHERE equipment_id = ? LIMIT 1");
        $existingStmt->execute([$equipmentId]);
        $existing = $existingStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($existing) {
            $currentHours = max((float)$existing['current_hours'], (float)$tracking['baseline_hours']);
            $stmt = $pdo->prepare("
                UPDATE equipment_hour_meters
                SET vessel_id = ?,
                    tracked_class = ?,
                    display_order = ?,
                    is_active = ?,
                    baseline_hours = ?,
                    current_hours = ?,
                    updated_at = NOW()
                WHERE equipment_id = ?
            ");
            $stmt->execute([
                $vesselId,
                $tracking['tracked_class'],
                $tracking['display_order'],
                $tracking['meter_active'],
                $tracking['baseline_hours'],
                $currentHours,
                $equipmentId,
            ]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO equipment_hour_meters (
                    equipment_id, vessel_id, tracked_class, display_order, is_active,
                    baseline_hours, current_hours, last_source
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'replacement_setup')
            ");
            $stmt->execute([
                $equipmentId,
                $vesselId,
                $tracking['tracked_class'],
                $tracking['display_order'],
                $tracking['meter_active'],
                $tracking['baseline_hours'],
                $tracking['baseline_hours'],
            ]);

            $meterId = (int)$pdo->lastInsertId();
            vms_hour_insert_reading($pdo, [
                'meter_id' => $meterId,
                'equipment_id' => $equipmentId,
                'vessel_id' => $vesselId,
                'vessel_log_id' => null,
                'reading_source' => 'replacement_setup',
                'reading_hours' => (float)$tracking['baseline_hours'],
                'reading_at' => date('Y-m-d H:i:s'),
                'created_by' => vms_hour_current_user_id() ?: null,
                'note' => 'Initial baseline created from equipment setup.',
            ], true);
        }
    }
}

if (!function_exists('vms_hour_get_tracked_meters_for_vessel')) {
    function vms_hour_get_tracked_meters_for_vessel(PDO $pdo, int $vesselId, bool $activeOnly = true): array
    {
        if (!vms_hour_table_exists($pdo, 'equipment_hour_meters')) {
            return [];
        }

        $sql = "
            SELECT
                hm.*,
                e.equipmentName,
                e.equipmentLocation,
                e.manufacturer,
                e.modelNumber,
                e.serialNumber,
                e.is_active AS equipment_is_active
            FROM equipment_hour_meters hm
            INNER JOIN equipment e ON e.eid = hm.equipment_id
            WHERE hm.vessel_id = ?
        ";

        if ($activeOnly) {
            $sql .= " AND hm.is_active = 1 AND COALESCE(e.is_active, 1) = 1";
        }

        $sql .= " ORDER BY FIELD(hm.tracked_class, 'propulsion_engine', 'generator'), hm.display_order ASC, e.equipmentLocation ASC, e.equipmentName ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$vesselId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('vms_hour_get_meter_by_equipment')) {
    function vms_hour_get_meter_by_equipment(PDO $pdo, int $equipmentId): ?array
    {
        if (!vms_hour_table_exists($pdo, 'equipment_hour_meters')) {
            return null;
        }

        $stmt = $pdo->prepare("
            SELECT hm.*, e.equipmentName, e.equipmentLocation
            FROM equipment_hour_meters hm
            INNER JOIN equipment e ON e.eid = hm.equipment_id
            WHERE hm.equipment_id = ?
            LIMIT 1
        ");
        $stmt->execute([$equipmentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

if (!function_exists('vms_hour_insert_reading')) {
    function vms_hour_insert_reading(PDO $pdo, array $reading, bool $allowDecrease = false): int
    {
        $meterStmt = $pdo->prepare("SELECT * FROM equipment_hour_meters WHERE meter_id = ? LIMIT 1");
        $meterStmt->execute([(int)$reading['meter_id']]);
        $meter = $meterStmt->fetch(PDO::FETCH_ASSOC);
        if (!$meter) {
            throw new RuntimeException('Meter not found.');
        }

        $newHours = round((float)$reading['reading_hours'], 1);
        $currentHours = round((float)$meter['current_hours'], 1);
        if (!$allowDecrease && $newHours < $currentHours) {
            throw new RuntimeException('Reading cannot decrease below current hours.');
        }

        $stmt = $pdo->prepare("
            INSERT INTO equipment_hour_readings (
                meter_id, equipment_id, vessel_id, vessel_log_id, reading_source,
                reading_hours, reading_at, created_by, note
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            (int)$reading['meter_id'],
            (int)$reading['equipment_id'],
            (int)$reading['vessel_id'],
            $reading['vessel_log_id'] !== null ? (int)$reading['vessel_log_id'] : null,
            $reading['reading_source'],
            $newHours,
            $reading['reading_at'],
            $reading['created_by'] !== null ? (int)$reading['created_by'] : null,
            $reading['note'] ?? null,
        ]);

        $readingId = (int)$pdo->lastInsertId();

        $update = $pdo->prepare("
            UPDATE equipment_hour_meters
            SET current_hours = ?,
                last_reading_id = ?,
                last_source = ?,
                updated_at = NOW()
            WHERE meter_id = ?
        ");
        $update->execute([$newHours, $readingId, $reading['reading_source'], (int)$reading['meter_id']]);

        if ($reading['reading_source'] === 'meter_verification') {
            $verifyStmt = $pdo->prepare("
                UPDATE equipment_hour_meters
                SET last_verified_hours = ?, last_verified_at = ?, updated_at = NOW()
                WHERE meter_id = ?
            ");
            $verifyStmt->execute([$newHours, $reading['reading_at'], (int)$reading['meter_id']]);
        }

        return $readingId;
    }
}

if (!function_exists('vms_hour_audit_adjustment')) {
    function vms_hour_audit_adjustment(PDO $pdo, array $audit): void
    {
        if (!vms_hour_table_exists($pdo, 'equipment_hour_adjustments_audit')) {
            return;
        }

        $stmt = $pdo->prepare("
            INSERT INTO equipment_hour_adjustments_audit (
                meter_id, equipment_id, vessel_id, action_type, old_hours,
                new_hours, reason, related_reading_id, actor_user_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            (int)$audit['meter_id'],
            (int)$audit['equipment_id'],
            (int)$audit['vessel_id'],
            $audit['action_type'],
            $audit['old_hours'] !== null ? round((float)$audit['old_hours'], 1) : null,
            round((float)$audit['new_hours'], 1),
            $audit['reason'],
            $audit['related_reading_id'] !== null ? (int)$audit['related_reading_id'] : null,
            (int)$audit['actor_user_id'],
        ]);
    }
}

if (!function_exists('vms_hour_get_reading_warning')) {
    function vms_hour_get_reading_warning(?float $currentHours, ?float $newHours, ?string $departDt = null, ?string $returnDt = null): ?string
    {
        if ($currentHours === null || $newHours === null) {
            return null;
        }

        $departTs = $departDt ? strtotime($departDt) : false;
        $returnTs = $returnDt ? strtotime($returnDt) : false;

        if ($departTs === false || $returnTs === false || $returnTs <= $departTs) {
            return null;
        }

        $voyageDurationHours = round(($returnTs - $departTs) / 3600, 2);
        $allowedIncrease = $voyageDurationHours + 5.0;
        $actualIncrease = round($newHours - $currentHours, 1);

        if ($actualIncrease > $allowedIncrease) {
            return 'This reading exceeds the expected increase for the voyage duration. Please confirm it is correct.';
        }

        return null;
    }
}

if (!function_exists('vms_hour_schedule_anchor')) {
    function vms_hour_schedule_anchor(PDO $pdo, array $schedule, array $meter): float
    {
        $stmt = $pdo->prepare("
            SELECT MAX(completion_hours)
            FROM equipment_maintenance_events
            WHERE schedule_id = ?
        ");
        $stmt->execute([(int)$schedule['schedule_id']]);
        $eventAnchor = $stmt->fetchColumn();

        if ($eventAnchor !== null) {
            return round((float)$eventAnchor, 1);
        }

        return round((float)($meter['baseline_hours'] ?? 0.0), 1);
    }
}

if (!function_exists('vms_hour_recalculate_schedule')) {
    function vms_hour_recalculate_schedule(PDO $pdo, int $scheduleId): ?array
    {
        $stmt = $pdo->prepare("
            SELECT s.*, hm.current_hours, hm.baseline_hours, hm.tracked_class, e.equipmentName, e.equipmentLocation
            FROM equipment_maintenance_schedules s
            INNER JOIN equipment_hour_meters hm ON hm.meter_id = s.meter_id
            INNER JOIN equipment e ON e.eid = s.equipment_id
            WHERE s.schedule_id = ?
            LIMIT 1
        ");
        $stmt->execute([$scheduleId]);
        $schedule = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$schedule) {
            return null;
        }

        $anchor = vms_hour_schedule_anchor($pdo, $schedule, $schedule);
        $nextDue = round($anchor + (int)$schedule['interval_hours'], 1);

        $update = $pdo->prepare("
            UPDATE equipment_maintenance_schedules
            SET last_completed_hours = ?,
                next_due_hours = ?,
                updated_at = NOW()
            WHERE schedule_id = ?
        ");
        $update->execute([$anchor, $nextDue, $scheduleId]);

        $schedule['last_completed_hours'] = $anchor;
        $schedule['next_due_hours'] = $nextDue;
        return $schedule;
    }
}

if (!function_exists('vms_hour_due_state')) {
    function vms_hour_due_state(float $currentHours, float $nextDueHours, int $advanceNoticeHours): ?string
    {
        if ($currentHours >= $nextDueHours) {
            return 'due';
        }

        if ($currentHours >= ($nextDueHours - max(0, $advanceNoticeHours))) {
            return 'due_soon';
        }

        return null;
    }
}

if (!function_exists('vms_hour_ensure_schedule_task')) {
    function vms_hour_ensure_schedule_task(PDO $pdo, int $scheduleId): void
    {
        if (!vms_hour_table_exists($pdo, 'equipment_maintenance_schedules')) {
            return;
        }

        $schedule = vms_hour_recalculate_schedule($pdo, $scheduleId);
        if (!$schedule || (int)$schedule['is_active'] !== 1) {
            return;
        }

        $currentHours = round((float)$schedule['current_hours'], 1);
        $nextDueHours = round((float)$schedule['next_due_hours'], 1);
        $dueState = vms_hour_due_state($currentHours, $nextDueHours, (int)$schedule['advance_notice_hours']);

        if ($dueState === null) {
            return;
        }

        $status = $currentHours > $nextDueHours ? 'overdue' : 'open';
        $title = trim((string)$schedule['service_name']) . ' - ' . trim((string)$schedule['equipmentName']);
        $description = implode("\n", [
            'Generated from hour-based maintenance schedule.',
            'Equipment: ' . trim((string)$schedule['equipmentName']),
            'Location: ' . trim((string)$schedule['equipmentLocation']),
            'Current Hours: ' . number_format($currentHours, 1),
            'Due Hours: ' . number_format($nextDueHours, 1),
            'Status: ' . strtoupper($dueState),
        ]);

        $existingStmt = $pdo->prepare("
            SELECT task_id, status
            FROM tasks
            WHERE related_schedule_id = ?
              AND cycle_due_hours = ?
            LIMIT 1
        ");
        $existingStmt->execute([$scheduleId, $nextDueHours]);
        $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $update = $pdo->prepare("
                UPDATE tasks
                SET title = ?, description = ?, status = ?, due_state = ?, updated_at = CURRENT_TIMESTAMP
                WHERE task_id = ?
            ");
            $update->execute([$title, $description, $status, $dueState, (int)$existing['task_id']]);
            return;
        }

        $assignedTo = vms_hour_default_task_assignee($pdo, (int)$schedule['vessel_id']);
        $insert = $pdo->prepare("
            INSERT INTO tasks (
                title, description, vessel_id, created_by, equipment_id, assigned_to, due_date,
                completed_date, is_recurring, recurrence_interval, status, priority, task_type,
                related_meter_id, related_schedule_id, cycle_due_hours, due_state
            ) VALUES (?, ?, ?, ?, ?, ?, CURDATE(), NULL, 0, 'none', ?, 'moderate', 'hour_maintenance', ?, ?, ?, ?)
        ");
        $insert->execute([
            $title,
            $description,
            (int)$schedule['vessel_id'],
            vms_hour_current_user_id() ?: null,
            (int)$schedule['equipment_id'],
            $assignedTo,
            $status,
            (int)$schedule['meter_id'],
            $scheduleId,
            $nextDueHours,
            $dueState,
        ]);
    }
}

if (!function_exists('vms_hour_default_task_assignee')) {
    function vms_hour_default_task_assignee(PDO $pdo, int $vesselId): ?int
    {
        $stmt = $pdo->prepare("
            SELECT u.id
            FROM vessel_crew vc
            INNER JOIN users u ON u.id = vc.crew_id
            WHERE vc.vessel_id = ?
              AND vc.is_active = 1
              AND u.is_active = 1
            ORDER BY FIELD(vc.role, 'Maintenance', 'Master', 'Owner', 'Admin', 'Deckhand'), u.id
            LIMIT 1
        ");
        $stmt->execute([$vesselId]);
        $value = $stmt->fetchColumn();
        return $value ? (int)$value : null;
    }
}

if (!function_exists('vms_hour_sync_meter_tasks')) {
    function vms_hour_sync_meter_tasks(PDO $pdo, int $meterId): void
    {
        $stmt = $pdo->prepare("SELECT schedule_id FROM equipment_maintenance_schedules WHERE meter_id = ? AND is_active = 1");
        $stmt->execute([$meterId]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $scheduleId) {
            vms_hour_ensure_schedule_task($pdo, (int)$scheduleId);
        }
    }
}

if (!function_exists('vms_hour_close_stale_schedule_tasks')) {
    function vms_hour_close_stale_schedule_tasks(PDO $pdo, int $scheduleId, float $activeCycleDueHours, string $reason): void
    {
        $stmt = $pdo->prepare("
            UPDATE tasks
            SET status = 'complete',
                corrected_date = CURDATE(),
                completed_date = CURDATE(),
                corrective_action = CONCAT(COALESCE(corrective_action, ''), CASE WHEN COALESCE(corrective_action, '') = '' THEN '' ELSE '\n\n' END, ?),
                updated_at = CURRENT_TIMESTAMP
            WHERE related_schedule_id = ?
              AND task_type = 'hour_maintenance'
              AND status IN ('open', 'in_progress', 'overdue')
              AND cycle_due_hours <> ?
        ");
        $stmt->execute([$reason, $scheduleId, $activeCycleDueHours]);
    }
}

if (!function_exists('vms_hour_create_maintenance_event')) {
    function vms_hour_create_maintenance_event(PDO $pdo, array $payload): int
    {
        $stmt = $pdo->prepare("
            INSERT INTO equipment_maintenance_events (
                schedule_id, meter_id, equipment_id, vessel_id, task_id, event_type,
                completion_date, completion_hours, performed_by, note, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            (int)$payload['schedule_id'],
            (int)$payload['meter_id'],
            (int)$payload['equipment_id'],
            (int)$payload['vessel_id'],
            $payload['task_id'] !== null ? (int)$payload['task_id'] : null,
            $payload['event_type'],
            $payload['completion_date'],
            round((float)$payload['completion_hours'], 1),
            $payload['performed_by'] !== null ? trim((string)$payload['performed_by']) : null,
            trim((string)$payload['note']),
            (int)$payload['created_by'],
        ]);

        return (int)$pdo->lastInsertId();
    }
}

if (!function_exists('vms_hour_complete_maintenance_task')) {
    function vms_hour_complete_maintenance_task(PDO $pdo, int $taskId, string $completionDate, float $completionHours, string $note, ?string $performedBy = null): void
    {
        $taskStmt = $pdo->prepare("
            SELECT t.*, s.equipment_id, s.meter_id, s.vessel_id
            FROM tasks t
            INNER JOIN equipment_maintenance_schedules s ON s.schedule_id = t.related_schedule_id
            WHERE t.task_id = ?
              AND t.task_type = 'hour_maintenance'
            LIMIT 1
        ");
        $taskStmt->execute([$taskId]);
        $task = $taskStmt->fetch(PDO::FETCH_ASSOC);
        if (!$task) {
            throw new RuntimeException('Maintenance task not found.');
        }

        vms_hour_create_maintenance_event($pdo, [
            'schedule_id' => (int)$task['related_schedule_id'],
            'meter_id' => (int)$task['meter_id'],
            'equipment_id' => (int)$task['equipment_id'],
            'vessel_id' => (int)$task['vessel_id'],
            'task_id' => $taskId,
            'event_type' => 'completion',
            'completion_date' => $completionDate,
            'completion_hours' => $completionHours,
            'performed_by' => $performedBy,
            'note' => $note,
            'created_by' => vms_hour_current_user_id(),
        ]);

        $schedule = vms_hour_recalculate_schedule($pdo, (int)$task['related_schedule_id']);
        if (!$schedule) {
            throw new RuntimeException('Schedule not found after completion.');
        }

        $updateTask = $pdo->prepare("
            UPDATE tasks
            SET status = 'complete',
                corrected_date = ?,
                completed_date = ?,
                corrective_action = CONCAT(COALESCE(corrective_action, ''), CASE WHEN COALESCE(corrective_action, '') = '' THEN '' ELSE '\n\n' END, ?),
                updated_at = CURRENT_TIMESTAMP
            WHERE task_id = ?
        ");
        $updateTask->execute([$completionDate, $completionDate, $note, $taskId]);

        vms_hour_close_stale_schedule_tasks($pdo, (int)$task['related_schedule_id'], (float)$schedule['next_due_hours'], 'Closed after maintenance cycle was re-anchored.');
        vms_hour_ensure_schedule_task($pdo, (int)$task['related_schedule_id']);
    }
}

if (!function_exists('vms_hour_backfill_maintenance')) {
    function vms_hour_backfill_maintenance(PDO $pdo, int $scheduleId, string $completionDate, float $completionHours, string $note, ?string $performedBy = null): void
    {
        if (!vms_hour_user_can_manage_history()) {
            throw new RuntimeException('Not authorized to backfill maintenance history.');
        }

        if (trim($note) === '') {
            throw new RuntimeException('Backfill reason/note is required.');
        }

        $scheduleStmt = $pdo->prepare("SELECT * FROM equipment_maintenance_schedules WHERE schedule_id = ? LIMIT 1");
        $scheduleStmt->execute([$scheduleId]);
        $schedule = $scheduleStmt->fetch(PDO::FETCH_ASSOC);
        if (!$schedule) {
            throw new RuntimeException('Schedule not found.');
        }

        vms_hour_create_maintenance_event($pdo, [
            'schedule_id' => (int)$schedule['schedule_id'],
            'meter_id' => (int)$schedule['meter_id'],
            'equipment_id' => (int)$schedule['equipment_id'],
            'vessel_id' => (int)$schedule['vessel_id'],
            'task_id' => null,
            'event_type' => 'backfill',
            'completion_date' => $completionDate,
            'completion_hours' => $completionHours,
            'performed_by' => $performedBy,
            'note' => $note,
            'created_by' => vms_hour_current_user_id(),
        ]);

        $schedule = vms_hour_recalculate_schedule($pdo, $scheduleId);
        if (!$schedule) {
            throw new RuntimeException('Unable to refresh schedule after backfill.');
        }

        vms_hour_close_stale_schedule_tasks($pdo, $scheduleId, (float)$schedule['next_due_hours'], 'Closed after historical maintenance backfill changed the active cycle.');
        vms_hour_ensure_schedule_task($pdo, $scheduleId);
    }
}

if (!function_exists('vms_hour_month_key')) {
    function vms_hour_month_key(?DateTimeInterface $dt = null): string
    {
        $dt = $dt ?: new DateTimeImmutable('now');
        return $dt->format('Y-m');
    }
}

if (!function_exists('vms_hour_ensure_monthly_verification_task')) {
    function vms_hour_ensure_monthly_verification_task(PDO $pdo, int $vesselId, ?string $monthKey = null): void
    {
        $monthKey = $monthKey ?: vms_hour_month_key();

        $meters = vms_hour_get_tracked_meters_for_vessel($pdo, $vesselId, true);
        if (!$meters) {
            return;
        }

        $stmt = $pdo->prepare("
            SELECT task_id
            FROM tasks
            WHERE vessel_id = ?
              AND task_type = 'meter_verification'
              AND verification_month = ?
            LIMIT 1
        ");
        $stmt->execute([$vesselId, $monthKey]);
        if ($stmt->fetchColumn()) {
            return;
        }

        $title = 'Meter Verification - ' . $monthKey;
        $lines = ['Monthly meter verification for active tracked propulsion engines and generators.'];
        foreach ($meters as $meter) {
            $lines[] = trim((string)$meter['equipmentName']) . ' (' . trim((string)$meter['equipmentLocation']) . ') - current ' . number_format((float)$meter['current_hours'], 1) . ' hrs';
        }

        $insert = $pdo->prepare("
            INSERT INTO tasks (
                title, description, vessel_id, created_by, assigned_to, due_date, completed_date,
                is_recurring, recurrence_interval, status, priority, task_type, verification_month
            ) VALUES (?, ?, ?, ?, ?, CURDATE(), NULL, 1, 'monthly', 'open', 'moderate', 'meter_verification', ?)
        ");
        $insert->execute([
            $title,
            implode("\n", $lines),
            $vesselId,
            vms_hour_current_user_id() ?: null,
            vms_hour_default_task_assignee($pdo, $vesselId),
            $monthKey,
        ]);
    }
}

if (!function_exists('vms_hour_apply_verification_reading')) {
    function vms_hour_apply_verification_reading(PDO $pdo, int $meterId, float $newHours, string $reason): void
    {
        if (!vms_hour_user_can_manage_history()) {
            throw new RuntimeException('Not authorized to reconcile meter verification readings.');
        }

        $meterStmt = $pdo->prepare("SELECT * FROM equipment_hour_meters WHERE meter_id = ? LIMIT 1");
        $meterStmt->execute([$meterId]);
        $meter = $meterStmt->fetch(PDO::FETCH_ASSOC);
        if (!$meter) {
            throw new RuntimeException('Meter not found.');
        }

        $oldHours = round((float)$meter['current_hours'], 1);
        $readingId = vms_hour_insert_reading($pdo, [
            'meter_id' => (int)$meter['meter_id'],
            'equipment_id' => (int)$meter['equipment_id'],
            'vessel_id' => (int)$meter['vessel_id'],
            'vessel_log_id' => null,
            'reading_source' => 'meter_verification',
            'reading_hours' => $newHours,
            'reading_at' => date('Y-m-d H:i:s'),
            'created_by' => vms_hour_current_user_id(),
            'note' => $reason,
        ], true);

        if ($oldHours !== round($newHours, 1)) {
            vms_hour_audit_adjustment($pdo, [
                'meter_id' => (int)$meter['meter_id'],
                'equipment_id' => (int)$meter['equipment_id'],
                'vessel_id' => (int)$meter['vessel_id'],
                'action_type' => 'meter_verification',
                'old_hours' => $oldHours,
                'new_hours' => $newHours,
                'reason' => $reason,
                'related_reading_id' => $readingId,
                'actor_user_id' => vms_hour_current_user_id(),
            ]);
        }

        vms_hour_sync_meter_tasks($pdo, (int)$meter['meter_id']);
    }
}

if (!function_exists('vms_hour_apply_manual_correction')) {
    function vms_hour_apply_manual_correction(PDO $pdo, int $meterId, float $newHours, string $reason): void
    {
        if (!vms_hour_user_can_manage_history()) {
            throw new RuntimeException('Not authorized to correct meter readings.');
        }

        if (trim($reason) === '') {
            throw new RuntimeException('Correction reason is required.');
        }

        $meterStmt = $pdo->prepare("SELECT * FROM equipment_hour_meters WHERE meter_id = ? LIMIT 1");
        $meterStmt->execute([$meterId]);
        $meter = $meterStmt->fetch(PDO::FETCH_ASSOC);
        if (!$meter) {
            throw new RuntimeException('Meter not found.');
        }

        $oldHours = round((float)$meter['current_hours'], 1);
        $readingId = vms_hour_insert_reading($pdo, [
            'meter_id' => (int)$meter['meter_id'],
            'equipment_id' => (int)$meter['equipment_id'],
            'vessel_id' => (int)$meter['vessel_id'],
            'vessel_log_id' => null,
            'reading_source' => 'manual_correction',
            'reading_hours' => $newHours,
            'reading_at' => date('Y-m-d H:i:s'),
            'created_by' => vms_hour_current_user_id(),
            'note' => $reason,
        ], true);

        vms_hour_audit_adjustment($pdo, [
            'meter_id' => (int)$meter['meter_id'],
            'equipment_id' => (int)$meter['equipment_id'],
            'vessel_id' => (int)$meter['vessel_id'],
            'action_type' => 'manual_correction',
            'old_hours' => $oldHours,
            'new_hours' => $newHours,
            'reason' => $reason,
            'related_reading_id' => $readingId,
            'actor_user_id' => vms_hour_current_user_id(),
        ]);

        vms_hour_sync_meter_tasks($pdo, (int)$meter['meter_id']);
    }
}

if (!function_exists('vms_hour_replace_equipment')) {
    function vms_hour_replace_equipment(PDO $pdo, int $oldEquipmentId, int $newEquipmentId, float $installedHours, bool $copySchedules): void
    {
        $oldMeter = vms_hour_get_meter_by_equipment($pdo, $oldEquipmentId);
        $newMeter = vms_hour_get_meter_by_equipment($pdo, $newEquipmentId);
        if (!$oldMeter || !$newMeter) {
            throw new RuntimeException('Replacement meter was not created.');
        }

        $pdo->prepare("UPDATE equipment_hour_meters SET is_active = 0, updated_at = NOW() WHERE meter_id = ?")->execute([(int)$oldMeter['meter_id']]);

        vms_hour_apply_manual_correction($pdo, (int)$newMeter['meter_id'], $installedHours, 'Replacement setup baseline.');

        if ($copySchedules && $oldMeter && vms_hour_table_exists($pdo, 'equipment_maintenance_schedules')) {
            $scheduleStmt = $pdo->prepare("
                SELECT service_name, interval_hours, advance_notice_hours, is_active
                FROM equipment_maintenance_schedules
                WHERE meter_id = ?
            ");
            $scheduleStmt->execute([(int)$oldMeter['meter_id']]);
            $insert = $pdo->prepare("
                INSERT INTO equipment_maintenance_schedules (
                    meter_id, equipment_id, vessel_id, service_name, interval_hours,
                    advance_notice_hours, is_active, last_completed_hours, next_due_hours
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NULL, NULL)
            ");
            foreach ($scheduleStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $insert->execute([
                    (int)$newMeter['meter_id'],
                    $newEquipmentId,
                    (int)$newMeter['vessel_id'],
                    $row['service_name'],
                    (int)$row['interval_hours'],
                    (int)$row['advance_notice_hours'],
                    (int)$row['is_active'],
                ]);
                vms_hour_recalculate_schedule($pdo, (int)$pdo->lastInsertId());
            }
        }
    }
}

if (!function_exists('vms_hour_parse_posted_meter_readings')) {
    function vms_hour_parse_posted_meter_readings(array $request): array
    {
        $rows = [];
        $posted = $request['meter_hours'] ?? [];
        if (!is_array($posted)) {
            return $rows;
        }

        foreach ($posted as $meterId => $value) {
            $meterId = (int)$meterId;
            if ($meterId <= 0) {
                continue;
            }

            $clean = trim((string)$value);
            $rows[$meterId] = $clean;
        }

        return $rows;
    }
}

if (!function_exists('vms_hour_log_form_values')) {
    function vms_hour_log_form_values(array $storedState = []): array
    {
        $raw = $storedState['meter_hours'] ?? [];
        return is_array($raw) ? $raw : [];
    }
}

if (!function_exists('vms_hour_derive_legacy_engine_fields')) {
    function vms_hour_derive_legacy_engine_fields(PDO $pdo, int $vesselId, array $meterReadings): array
    {
        $legacy = [
            'engine_hours_port' => null,
            'engine_hours_stbd' => null,
        ];

        if (!$meterReadings) {
            return $legacy;
        }

        $meters = vms_hour_get_tracked_meters_for_vessel($pdo, $vesselId, true);
        foreach ($meters as $meter) {
            if ((string)$meter['tracked_class'] !== 'propulsion_engine') {
                continue;
            }

            $meterId = (int)$meter['meter_id'];
            if (!array_key_exists($meterId, $meterReadings)) {
                continue;
            }

            $location = strtolower(trim((string)($meter['equipmentLocation'] ?? '')));
            $value = vms_hour_decimal_or_null($meterReadings[$meterId]);
            if ($value === null) {
                continue;
            }

            if ($legacy['engine_hours_port'] === null && strpos($location, 'port') !== false) {
                $legacy['engine_hours_port'] = $value;
            } elseif ($legacy['engine_hours_stbd'] === null && (strpos($location, 'starboard') !== false || strpos($location, 'stbd') !== false)) {
                $legacy['engine_hours_stbd'] = $value;
            } elseif ($legacy['engine_hours_port'] === null) {
                $legacy['engine_hours_port'] = $value;
            } elseif ($legacy['engine_hours_stbd'] === null) {
                $legacy['engine_hours_stbd'] = $value;
            }
        }

        return $legacy;
    }
}

if (!function_exists('vms_hour_apply_voyage_log_readings')) {
    function vms_hour_apply_voyage_log_readings(PDO $pdo, int $vesselId, int $logId, array $postedReadings, ?string $departDt = null, ?string $returnDt = null, array &$warnings = []): void
    {
        if (!$postedReadings) {
            return;
        }

        $meters = vms_hour_get_tracked_meters_for_vessel($pdo, $vesselId, true);
        $meterMap = [];
        foreach ($meters as $meter) {
            $meterMap[(int)$meter['meter_id']] = $meter;
        }

        foreach ($postedReadings as $meterId => $rawValue) {
            if (!isset($meterMap[$meterId])) {
                continue;
            }

            $meter = $meterMap[$meterId];
            $clean = trim((string)$rawValue);
            if ($clean === '' || !is_numeric($clean)) {
                throw new RuntimeException('Tracked meter readings must be numeric.');
            }

            $newHours = round((float)$clean, 1);
            $currentHours = round((float)$meter['current_hours'], 1);

            if (!vms_hour_user_can_manage_history() && $newHours < $currentHours) {
                throw new RuntimeException('Tracked meter readings cannot decrease.');
            }

            $warning = vms_hour_get_reading_warning($currentHours, $newHours, $departDt, $returnDt);
            if ($warning !== null) {
                $warnings[] = trim((string)$meter['equipmentName']) . ': ' . $warning;
            }

            vms_hour_insert_reading($pdo, [
                'meter_id' => (int)$meter['meter_id'],
                'equipment_id' => (int)$meter['equipment_id'],
                'vessel_id' => $vesselId,
                'vessel_log_id' => $logId,
                'reading_source' => 'voyage_log',
                'reading_hours' => $newHours,
                'reading_at' => date('Y-m-d H:i:s'),
                'created_by' => vms_hour_current_user_id(),
                'note' => 'Voyage log reading update.',
            ]);

            vms_hour_sync_meter_tasks($pdo, (int)$meter['meter_id']);
        }
    }
}
