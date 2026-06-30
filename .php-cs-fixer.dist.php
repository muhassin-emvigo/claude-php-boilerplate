<?php
declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/app/code')
    ->name('*.php')
    ->notPath('Test/')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        'declare_strict_types' => true,
        'array_syntax' => ['syntax' => 'short'],
        'no_unused_imports' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'single_quote' => true,
        'trailing_comma_in_multiline' => ['elements' => ['arguments', 'arrays', 'parameters']],
        'no_extra_blank_lines' => [
            'tokens' => [
                'curly_brace_block',
                'extra',
                'throw',
                'use',
            ],
        ],
        'no_whitespace_in_blank_line' => true,
        'concat_space' => ['spacing' => 'one'],
        'binary_operator_spaces' => ['default' => 'single_space'],
        'class_attributes_separation' => ['elements' => ['method' => 'one']],
        'no_superfluous_phpdoc_tags' => ['allow_mixed' => true],
        'phpdoc_align' => ['align' => 'left'],
        'phpdoc_order' => true,
        'phpdoc_trim' => true,
        'phpdoc_types_order' => ['null_adjustment' => 'always_last'],
        'void_return' => true,
        'return_type_declaration' => ['space_before' => 'none'],
        'single_line_throw' => false,
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => false,
            'import_functions' => false,
        ],
    ])
    ->setFinder($finder)
    ->setCacheFile(__DIR__ . '/.php-cs-fixer.cache');
