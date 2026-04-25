<?php
$files = [
    'crew_members.php',
    'add_crew_member.php',
    'edit_crew_member.php',
    'save_crew_member.php',
    'delete_crew_member.php'
];

echo "<h2>📁 Crew Member File Check</h2>";
foreach ($files as $file) {
    if (file_exists($file)) {
        echo "✅ <strong>$file</strong> found.<br>";
    } else {
        echo "❌ <strong>$file</strong> not found!<br>";
    }
}
?>
