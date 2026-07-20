<?php
/**
 * Runs PHPCS against only the staged .php files, via --file-list instead of passing
 * paths on the command line.
 *
 * Two Windows-specific problems this avoids:
 *  - cmd.exe has a ~8191 char command-line length limit. captainhook previously built the
 *    phpcs command by interpolating every staged file path directly into one command string;
 *    once enough files were staged, that string exceeded the limit and the process spawn
 *    failed outright (looked like a phpcs failure, but was really an OS-level exec failure).
 *  - PHPCS's directory-wide scan (no explicit file list) re-discovers .phtml/.js/.xml files
 *    via the Magento2 ruleset's custom tokenizers, which crashes with a pre-existing PHPCS
 *    internal regex bug on Windows-style backslash paths. Passing an explicit list of only
 *    the staged .php files avoids that discovery path entirely.
 *
 * Safe to run with zero staged .php files: phpcs is skipped and this exits 0.
 */
declare(strict_types=1);

$root = dirname(__DIR__);

$output = [];
$exitCode = 0;
exec('git diff --cached --name-only --diff-filter=ACMR -- "*.php"', $output, $exitCode);

if ($exitCode !== 0) {
    fwrite(STDERR, "phpcs-staged: failed to list staged files via git diff\n");
    exit(1);
}

$files = array_values(array_filter(array_map('trim', $output)));

if ($files === []) {
    echo "phpcs-staged: no staged .php files, skipping.\n";
    exit(0);
}

$tempFile = tempnam(sys_get_temp_dir(), 'phpcs-staged-');
file_put_contents($tempFile, implode(PHP_EOL, $files) . PHP_EOL);

$phpcsBin = $root . '/vendor/bin/phpcs';
$standard = $root . '/phpcs.xml.dist';

$command = sprintf(
    'php %s --standard=%s --extensions=php,phtml --file-list=%s',
    escapeshellarg($phpcsBin),
    escapeshellarg($standard),
    escapeshellarg($tempFile)
);

passthru($command, $result);

unlink($tempFile);

exit($result);
