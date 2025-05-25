<?php
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vessel_id = $_POST['vessel_id'];

    // Helper function for NULL-safe decimal conversion
    function toDecimal($value) {
        return ($value === '' || $value === null) ? null : floatval($value);
    }

    function toInt($value) {
        return ($value === '' || $value === null) ? null : intval($value);
    }

    function toDate($value) {
        return ($value === '' || $value === null) ? null : $value;
    }

    // ENUM-safe hull material
    $allowedMaterials = ['Fiberglass', 'Aluminum', 'Steel', 'Wood - Glued', 'Wood - Plank on Frame'];
    $hullMaterial = in_array($_POST['hullMaterial'], $allowedMaterials) ? $_POST['hullMaterial'] : null;

    // Boolean
    $auxSail = isset($_POST['auxSail']) ? 1 : 0;
    $sip = isset($_POST['sip']) ? 1 : 0;

    // Prepare statement
    $stmt = $mysqli->prepare("
        UPDATE vessels SET
            vesselClass = ?, classType = ?, vesselService = ?, inspSubChapter = ?,
            sip = ?, grossTons = ?, netTons = ?, lightshipTons = ?, 
            length = ?, lbp = ?, hullMaterial = ?, auxSail = ?, horsepower = ?,
            propulsionType = ?, route = ?, waters = ?, keelLaidDate = ?, deliveryDate = ?
        WHERE vessel_id = ?
    ");

    if (!$stmt) {
        die("Prepare failed: " . $mysqli->error);
    }

    $stmt->bind_param(
        "ssssiiddddsisissssi",
        $_POST['vesselClass'],
        $_POST['classType'],
        $_POST['vesselService'],
        $_POST['inspSubChapter'],
        $sip,
        toDecimal($_POST['grossTons']),
        toDecimal($_POST['netTons']),
        toDecimal($_POST['lightshipTons']),
        toDecimal($_POST['length']),
        toDecimal($_POST['lbp']),
        $hullMaterial,
        $auxSail,
        toInt($_POST['horsepower']),
        $_POST['propulsionType'],
        $_POST['route'],
        $_POST['waters'],
        toDate($_POST['keelLaidDate']),
        toDate($_POST['deliveryDate']),
        $vessel_id
    );

    if ($stmt->execute()) {
        header("Location: vessel_dashboard.php?id=$vessel_id&updated=1");
        exit;
    } else {
        die("Execution failed: " . $stmt->error);
    }

    $stmt->close();
}
?>
