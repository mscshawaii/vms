<?php
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vessel_id = $_POST['vessel_id'];

    // Helper functions
    function toDecimal($value) {
        return ($value === '' || $value === null) ? null : floatval($value);
    }

    function toInt($value) {
        return ($value === '' || $value === null) ? null : intval($value);
    }

    function toDate($value) {
        return ($value === '' || $value === null) ? null : $value;
    }

    // ENUM and validation
    $allowedMaterials = ['Fiberglass', 'Aluminum', 'Steel', 'Wood - Glued', 'Wood - Plank on Frame'];
    $allowedPropulsionTypes = [
        'Diesel - Inboard',
        'Diesel - Outboard',
        'Gasoline - Inboard',
        'Gasoline - Outboard',
        'Electric'
    ];

    $hullMaterial = in_array($_POST['hullMaterial'], $allowedMaterials) ? $_POST['hullMaterial'] : null;

    $submittedPropulsion = trim(str_replace('–', '-', $_POST['propulsionType'] ?? ''));
    $propulsionType = in_array($submittedPropulsion, $allowedPropulsionTypes) ? $submittedPropulsion : null;

    // Booleans
    $auxSail = isset($_POST['auxSail']) ? intval($_POST['auxSail']) : 0;
    $sip     = isset($_POST['sip']) ? intval($_POST['sip']) : 0;

    // Other fields
    $vesselClass     = $_POST['vesselClass'];
    $classType       = $_POST['classType'];
    $vesselService   = $_POST['vesselService'];
    $inspSubChapter  = $_POST['inspSubChapter'];
    $grossTons       = toDecimal($_POST['grossTons']);
    $netTons         = toDecimal($_POST['netTons']);
    $lightshipTons   = toDecimal($_POST['lightshipTons']);
    $length          = toDecimal($_POST['length']);
    $lbp             = toDecimal($_POST['lbp']);
    $horsepower      = toInt($_POST['horsepower']);
    $route           = $_POST['route'];
    $waters          = $_POST['waters'];
    $keelLaidDate    = toDate($_POST['keelLaidDate']);
    $deliveryDate    = toDate($_POST['deliveryDate']);

    // Prepare query
    $stmt = $mysqli->prepare("
        UPDATE vessels SET
            vesselClass = ?, classType = ?, vesselService = ?, inspSubChapter = ?,
            sip = ?, grossTons = ?, netTons = ?, lightshipTons = ?, 
            length = ?, lbp = ?, hullMaterial = ?, auxSail = ?, horsepower = ?,
            propulsionType = ?, route = ?, waters = ?, keelLaidDate = ?, deliveryDate = ?
        WHERE vessel_id = ?
    ");

    if (!$stmt) {
        die("❌ Prepare failed: " . $mysqli->error);
    }

    // Build parameters
    $types = "ssssiiddddsisissssi";
    $params = [
        &$vesselClass, &$classType, &$vesselService, &$inspSubChapter,
        &$sip, &$grossTons, &$netTons, &$lightshipTons,
        &$length, &$lbp, &$hullMaterial, &$auxSail, &$horsepower,
        &$propulsionType, &$route, &$waters,
        &$keelLaidDate, &$deliveryDate, &$vessel_id
    ];

    // Safely bind using call_user_func_array
    $bindNames[] = $types;
    $bindNames = array_merge($bindNames, $params);
    call_user_func_array([$stmt, 'bind_param'], $bindNames);

    // Execute
    if ($stmt->execute()) {
        header("Location: vessel_dashboard.php?id=$vessel_id&updated=1");
        exit;
    } else {
        die("❌ Execution failed: " . $stmt->error);
    }

    $stmt->close();
}
?>
