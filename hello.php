<?php
header('Content-Type: text/plain');
echo "HELLO from DO\n";
echo "SCRIPT_FILENAME=" . ($_SERVER['SCRIPT_FILENAME'] ?? '') . "\n";
