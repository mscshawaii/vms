<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'db_connect.php';

$vessel_id = intval($_POST['vessel_id'] ?? 0);

// Sanitize helpers
function clean($val) {
    return isset($val) && $val !== '' ? trim($val) : null;
}
function asInt($val) {
    return is_numeric($val) ? (int)$val : null;
}
function asFloat($val) {
    return is_numeric($val) ? (float)$val : null;
}

// Collect inputs
$fields = [
    'company_id'      => asInt($_POST['company_id'] ?? 0),
    'vesselClass'     => clean($_POST['vesselClass']),
    'classType'       => clean($_POST['classType']),
    'vesselService'   => clean($_POST['vesselService']),
    'inspSubChapter'  => clean($_POST['inspSubChapter']),
    'sip'             => isset($_POST['sip']) ? intval($_POST['sip']) : 0,
    'grossTons'       => asFloat($_POST['grossTons']),
    'netTons'         => asFloat($_POST['netTons']),
    'lightshipTons'   => asFloat($_POST['lightshipTons']),
    'length'          => asFloat($_POST['length']),
    'lbp'             => asFloat($_POST['lbp']),
    'hullMaterial'    => clean($_POST['hullMaterial']),
    'auxSail'         => isset($_POST['auxSail']) ? intval($_POST['auxSail']) : 0,
    'horsepower'      => asInt($_POST['horsepower']),
    'propulsionType'  => clean($_POST['propulsionType']),
    'route'           => clean($_POST['route']),
    'waters'          => clean($_POST['waters']),
    'keelLaidDate'    => clean($_POST['keelLaidDate']),
    'deliveryDate'    => clean($_POST['deliveryDate']),
];

// Prepare SQL
$sql = "
    UPDATE vessels SET
        company_id = :company_id,
        vesselClass = :vesselClass,
        classType = :classType,
        vesselService = :vesselService,
        inspSubChapter = :inspSubChapter,
        sip = :sip,
        grossTons = :grossTons,
        netTons = :netTons,
        lightshipTons = :lightshipTons,
        length = :length,
        lbp = :lbp,
        hullMaterial = :hullMaterial,
        auxSail = :auxSail,
        horsepower = :horsepower,
        propulsionType = :propulsionType,
        route = :route,
        waters = :waters,
        keelLaidDate = :keelLaidDate,
        deliveryDate = :deliveryDate
    WHERE vessel_id = :vessel_id
";

$stmt = $pdo->prepare($sql);
$fields['vessel_id'] = $vessel_id;

if ($stmt->execute($fields)) {
    header("Location: vessel_dashboard.php?vessel_id=$vessel_id");
    exit;
} else {
    echo "? Failed to update vessel: " . implode(' | ', $stmt->errorInfo());
}
?>
