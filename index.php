<?php
declare(strict_types=1);

$pubIndex = __DIR__ . '/pub/index.php';

if (is_file($pubIndex)) {
    require $pubIndex;
    return;
}

header('Content-Type: text/html; charset=utf-8');
http_response_code(503);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Magento not installed</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 640px; margin: 3rem auto; padding: 0 1rem; line-height: 1.5; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 4px; }
        h1 { color: #333; }
    </style>
</head>
<body>
    <h1>Magento is not installed yet</h1>
    <p>Apache is working, but this project still needs a full Magento install.</p>
    <p>From PowerShell in the project folder, run:</p>
    <p><code>.\install-magento-xampp.ps1</code></p>
    <p>Requirements: XAMPP Apache + MySQL running, PHP extensions enabled, Composer installed.</p>
</body>
</html>
