<?php
error_reporting(E_ALL);
$file = __DIR__ . "/src/Services/ScrapeProtection.php";
if (\!file_exists($file)) {
    echo "File not found: $file
";
    exit(1);
}
$code = file_get_contents($file);
$tokens = @token_get_all($code);
if ($tokens === false) {
    echo "SYNTAX ERROR in ScrapeProtection.php
";
    exit(1);
} else {
    echo "ScrapeProtection.php syntax is valid (" . count($tokens) . " tokens)
";
    exit(0);
}
