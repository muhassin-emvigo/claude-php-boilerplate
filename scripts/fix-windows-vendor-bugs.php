<?php
/**
 * Patches a handful of genuine Magento 2 core bugs that only surface on native
 * Windows installs (they mishandle drive letters, backslashes, and the '|'
 * character in generated filenames). None of this is project-specific logic —
 * it's the same fix that would eventually land upstream in Magento core.
 *
 * Runs automatically after `composer install`/`composer update` (see the
 * "post-install-cmd"/"post-update-cmd" scripts in composer.json), so a fresh
 * checkout on Windows installs cleanly without re-discovering these bugs.
 *
 * Safe to run multiple times: each fix is skipped if already applied, and
 * reported (not fatal) if the target code no longer matches (e.g. after a
 * Magento version bump) so it can be revisited instead of failing silently.
 */
declare(strict_types=1);

$root = dirname(__DIR__);

$fixes = [
    [
        'file' => 'vendor/magento/framework/Image/Adapter/Gd2.php',
        'reason' => "parse_url() misreads a Windows drive letter (e.g. \"C:\") as a URL scheme, "
            . 'so a valid local file path gets rejected as "Wrong file".',
        'from' => <<<'PHP'
        $allowed_schemes = ['ftp', 'ftps', 'http', 'https'];
        $url = parse_url($filename);
        if ($url && isset($url['scheme']) && !in_array($url['scheme'], $allowed_schemes)) {
            return false;
        }
PHP,
        'to' => <<<'PHP'
        $allowed_schemes = ['ftp', 'ftps', 'http', 'https'];
        $url = parse_url($filename);
        // A single-character "scheme" is actually a Windows drive letter (e.g. "C:\..."),
        // not a URL scheme, so it must not be rejected here.
        if ($url && isset($url['scheme']) && strlen($url['scheme']) > 1 && !in_array($url['scheme'], $allowed_schemes)) {
            return false;
        }
PHP,
    ],
    [
        'file' => 'vendor/magento/framework/Image/Adapter/ImageMagick.php',
        'reason' => 'Same drive-letter-as-scheme bug as Gd2.php, duplicated in this adapter.',
        'from' => <<<'PHP'
        $allowed_schemes = ['ftp', 'ftps', 'http', 'https'];
        $url = parse_url($filename);
        if ($url && isset($url['scheme']) && !in_array($url['scheme'], $allowed_schemes)) {
            return false;
        }
PHP,
        'to' => <<<'PHP'
        $allowed_schemes = ['ftp', 'ftps', 'http', 'https'];
        $url = parse_url($filename);
        // A single-character "scheme" is actually a Windows drive letter (e.g. "C:\..."),
        // not a URL scheme, so it must not be rejected here.
        if ($url && isset($url['scheme']) && strlen($url['scheme']) > 1 && !in_array($url['scheme'], $allowed_schemes)) {
            return false;
        }
PHP,
    ],
    [
        'file' => 'vendor/magento/framework/Interception/PluginListGenerator.php',
        'reason' => "'|' is not a valid Windows filename character, but it's used as a separator "
            . 'in generated plugin-list cache filenames, so writing them fails.',
        'from' => "                \$cacheId = implode('|', \$this->scopePriorityScheme) . \"|\" . \$this->cacheId;",
        'to' => <<<'PHP'
                // '|' is not a valid Windows filename character; use '__' so generated cache
                // filenames work on Windows too (this cacheId becomes part of a file path).
                $cacheId = implode('__', $this->scopePriorityScheme) . "__" . $this->cacheId;
PHP,
    ],
    [
        'file' => 'vendor/magento/framework/Interception/PluginList/PluginList.php',
        'reason' => "Same '|'-in-filename bug as PluginListGenerator.php, on the read side.",
        'from' => "            \$cacheId = implode('|', \$this->_scopePriorityScheme) . \"|\" . \$this->_cacheId;",
        'to' => <<<'PHP'
            // '|' is not a valid Windows filename character; use '__' so generated cache
            // filenames work on Windows too (this cacheId becomes part of a file path).
            $cacheId = implode('__', $this->_scopePriorityScheme) . "__" . $this->_cacheId;
PHP,
    ],
    [
        'file' => 'vendor/magento/framework/View/Element/Template/File/Validator.php',
        'reason' => 'getRealPath() returns backslash-separated paths on Windows, but registered '
            . 'component directories use forward slashes, so the prefix match always fails '
            . '(breaks every .phtml template).',
        'from' => <<<'PHP'
        $realPath = $this->fileDriver->getRealPath($path);
        foreach ($directories as $directory) {
            if ($directory !== null && 0 === strpos($realPath, $directory)) {
                return true;
            }
        }
PHP,
        'to' => <<<'PHP'
        // getRealPath() returns OS-native separators (backslashes on Windows), while the
        // registered component directories always use forward slashes; normalize both
        // before comparing so the prefix match works cross-platform.
        $realPath = str_replace('\\', '/', $this->fileDriver->getRealPath($path));
        foreach ($directories as $directory) {
            if ($directory !== null && 0 === strpos($realPath, str_replace('\\', '/', $directory))) {
                return true;
            }
        }
PHP,
    ],
    [
        'file' => 'vendor/magento/framework/App/StaticResource.php',
        'reason' => 'DIRECTORY_SEPARATOR (\'\\\' on Windows) is used to join a theme key that is '
            . "always registered with '/', so every static asset (CSS/JS) request 404s.",
        'from' => <<<'PHP'
        if (!($this->isThemeAllowed($params['area'] . DIRECTORY_SEPARATOR . $params['theme'])
            && $this->localeValidator->isValid($params['locale']))
        ) {
PHP,
        'to' => <<<'PHP'
        // Theme keys are always registered with '/' (see ComponentRegistrar), regardless of OS,
        // so DIRECTORY_SEPARATOR (which is '\' on Windows) must not be used here.
        if (!($this->isThemeAllowed($params['area'] . '/' . $params['theme'])
            && $this->localeValidator->isValid($params['locale']))
        ) {
PHP,
    ],
];

$applied = 0;
$alreadyDone = 0;
$missing = 0;

foreach ($fixes as $fix) {
    $path = $root . '/' . $fix['file'];
    if (!is_file($path)) {
        echo "  [skip]    {$fix['file']} (file not found - not installed yet?)\n";
        continue;
    }

    $content = file_get_contents($path);

    if (strpos($content, $fix['to']) !== false) {
        echo "  [ok]      {$fix['file']} (already patched)\n";
        $alreadyDone++;
        continue;
    }

    if (strpos($content, $fix['from']) === false) {
        echo "  [MISSING] {$fix['file']} - expected code not found. {$fix['reason']}\n";
        echo "            This file may have changed in a Magento update; please re-check manually.\n";
        $missing++;
        continue;
    }

    $newContent = str_replace($fix['from'], $fix['to'], $content, $count);
    file_put_contents($path, $newContent);
    echo "  [patched] {$fix['file']}\n";
    $applied++;
}

echo "\nWindows compatibility fixes: {$applied} applied, {$alreadyDone} already up to date, {$missing} need manual review.\n";

exit($missing > 0 ? 1 : 0);
