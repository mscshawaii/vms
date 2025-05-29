<?php
require 'session_check.php';
require 'db_connect.php';

function clean($value) {
    return isset($value) && $value !== '' ? htmlspecialchars(trim($value)) : null;
}

$logged_in_company = $_SESSION['company_id'];
$submitted_company = intval($_POST['company_id'] ?? 0);

// 🔒 Restrict non-MSCS users from submitting vessels for other companies
if ($logged_in_company != 1 && $submitted_company !== $logged_in_company) {
    die("❌ You are not authorized to add a vessel for this company.");
}

// ✅ Gather and sanitize form data
$data = [
    'company_id'       => $submitted_company,
    'vesselName'       => clean($_POST['vesselName']),
    'vesselON'         => clean($_POST['vesselON']),
    'hailingPort'      => clean($_POST['hailingPort']),
    'callSign'         => clean($_POST['callSign']),
    'mmsi'             => $_POST['mmsi'] ?: null,
    'epirbHexId'       => clean($_POST['epirbHexId']),
    'hin'              => clean($_POST['hin']),
    'vesselClass'      => clean($_POST['vesselClass']),
    'classType'        => clean($_POST['classType']),
    'vesselService'    => clean($_POST['vesselService']),
    'grossTons'        => $_POST['grossTons'] ?: null,
    'netTons'          => $_POST['netTons'] ?: null,
    'lightshipTons'    => $_POST['lightshipTons'] ?: null,
    'length'           => $_POST['length'] ?: null,
    'lbp'              => $_POST['lbp'] ?: null,
    'propulsionType'   => clean($_POST['propulsionType']),
    'auxSail'          => isset($_POST['auxSail']) ? (int)$_POST['auxSail'] : 0,
    'horsepower'       => $_POST['horsepower'] ?: null,
    'inspSubChapter'   => clean($_POST['inspSubChapter']),
    'sip'              => isset($_POST['sip']) ? (int)$_POST['sip'] : 0,
    'keelLaidDate'     => $_POST['keelLaidDate'] ?: null,
    'deliveryDate'     => $_POST['deliveryDate'] ?: null,
    'master'           => $_POST['master'] ?: null,
    'deckhands'        => $_POST['deckhands'] ?: null,
    'othersInCrew'     => $_POST['othersInCrew'] ?: null,
    'personInAddition' => $_POST['personInAddition'] ?: null,
    'passengers'       => $_POST['passengers'] ?: null,
    'pob'              => $_POST['pob'] ?: null,
    'route'            => clean($_POST['route']),
    'waters'           => clean($_POST['waters']),
    'hullMaterial'     => clean($_POST['hullMaterial']),
    'lastInspection'   => $_POST['lastInspection'] ?: null,
    'nextScheduledInspection' => $_POST['nextScheduledInspection'] ?: null,
    'lastDrydock'      => $_POST['lastDrydock'] ?: null,
    'nextDrydock'      => $_POST['nextDrydock'] ?: null,
    'nextUnstep'       => $_POST['nextUnstep'] ?: null
];

// 🧠 Build the SQL
$sql = "INSERT INTO vessels (
    company_id, vesselName, vesselON, hailingPort, callSign, mmsi, epirbHexId, hin, vesselClass,
    classType, vesselService, grossTons, netTons, lightshipTons, length, lbp,
    propulsionType, auxSail, horsepower, inspSubChapter, sip, keelLaidDate, deliveryDate,
    master, deckhands, othersInCrew, personInAddition, passengers, pob, route, waters,
    hullMaterial, lastInspection, nextScheduledInspection, lastDrydock, nextDrydock, nextUnstep
) VALUES (
    :company_id, :vesselName, :vesselON, :hailingPort, :callSign, :mmsi, :epirbHexId, :hin, :vesselClass,
    :classType, :vesselService, :grossTons, :netTons, :lightshipTons, :length, :lbp,
    :propulsionType, :auxSail, :horsepower, :inspSubChapter, :sip, :keelLaidDate, :deliveryDate,
    :master, :deckhands, :othersInCrew, :personInAddition, :passengers, :pob, :route, :waters,
    :hullMaterial, :lastInspection, :nextScheduledInspection, :lastDrydock, :nextDrydock, :nextUnstep
)";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($data);

    // 🚀 Redirect on success
    header("Location: dashboard.php?success=vessel_added");
    exit;

} catch (PDOException $e) {
    die("❌ Error: " . $e->getMessage());
}
?>
---

### ✅ Key Fixes Included

- ✅ Moved the `header()` call *before* any output
- ✅ Wrapped all values in `clean()` or null fallback
- ✅ Removed duplicate `$nextUnstep` assignment
- ✅ Provided defaults for optional fields (like empty strings, unchecked checkboxes)

Let me know if you’d like to add a success message or toast alert when a vessel is added.
