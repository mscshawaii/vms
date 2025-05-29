<?php
function calculateNextInspection($lastInspectionDate, $coiExpDate) {
    if (!$coiExpDate || $coiExpDate === '0000-00-00') {
        return ['type' => '—', 'window' => '—'];
    }

    $exp = new DateTime($coiExpDate);
    $last = ($lastInspectionDate && $lastInspectionDate !== '0000-00-00') ? new DateTime($lastInspectionDate) : null;

    for ($i = 1; $i <= 4; $i++) {
        $annualDate = (clone $exp)->modify("-" . (5 - $i) . " years");
        $start = (clone $annualDate)->modify("-90 days");
        $end = (clone $annualDate)->modify("+90 days");

        if (!$last || $last < $start) {
            return [
                'type' => "Annual (#$i)",
                'window' => $start->format('M d, Y') . " – " . $end->format('M d, Y')
            ];
        }
    }

    $renewalStart = (clone $exp)->modify('-90 days');
    if (!$last || $last < $renewalStart) {
        return [
            'type' => "Renewal",
            'window' => $renewalStart->format('M d, Y') . " – " . $exp->format('M d, Y')
        ];
    }

    if ($last > $exp) {
        return [
            'type' => "Inspection Complete",
            'window' => "—"
        ];
    }

    return ['type' => '—', 'window' => '—'];
}

function getInspectionWindowClass($type, $window) {
    if (!$type || !$window || $window === '—') return '';
    
    $today = new DateTime();

    // Parse format: "Jan 05, 2026 – Apr 05, 2026"
    $parts = preg_split('/–|-/', $window);
    if (count($parts) !== 2) return '';

    $start = DateTime::createFromFormat('M d, Y', trim($parts[0]));
    $end = DateTime::createFromFormat('M d, Y', trim($parts[1]));

    if (!$start || !$end) return '';

    if ($end < $today) return 'bg-danger text-white'; // Overdue

    if (stripos($type, 'Annual') === 0) {
        // Use unmodified copies for range checks
        $earlyWindow = (clone $start)->modify('+90 days');
        $lateWindowStart = (clone $end)->modify('-90 days');

        if ($today >= $start && $today <= $earlyWindow) return 'bg-success text-white'; // Early green
        if ($today >= $lateWindowStart && $today <= $end) return 'bg-warning text-dark'; // Late yellow
    }

    if (stripos($type, 'Renewal') === 0) {
        if ($today >= $start && $today <= $end) return 'bg-warning text-dark'; // Open yellow
        if ($today > $end) return 'bg-danger text-white'; // Past due red
    }

    return '';
}

function getDrydockClass($date) {
    if (!$date || $date === '0000-00-00') return '';
    
    $today = new DateTime();
    $drydock = DateTime::createFromFormat('Y-m-d', $date);
    if (!$drydock) return '';

    $diff = (int)$today->diff($drydock)->format('%r%a'); // Signed days difference

    if ($diff < 0) return 'bg-danger text-white';      // Past due
    if ($diff <= 60) return 'bg-warning text-dark';    // 0–2 months
    if ($diff <= 120) return 'bg-success text-white';  // 2–4 months

    return '';
}
