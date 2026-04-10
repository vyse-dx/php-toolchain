<?php

declare(strict_types=1);

namespace Vyse\Toolchain\PhpCsFixer;

use PhpCsFixer\Config as PhpCsFixerConfig;

class Config extends PhpCsFixerConfig
{
    public function __construct()
    {
        parent::__construct('App Toolchain');

        $this->setRiskyAllowed(true);

        $this->registerCustomFixers([
            new Fixer\EmptyBodyArgumentStyleFixer,
            new Fixer\ForceMethodArgumentsOnNewLinesFixer,
            new Fixer\MockObjectIntersectionOrderFixer,
            new Fixer\SemicolonPlacementFixer,
        ]);
    }

    public function getRules(): array
    {
        return [
            '@PER-CS2x0' => true,
            'array_syntax' => ['syntax' => 'short'],
            'binary_operator_spaces' => [
                'default' => 'single_space',
            ],
            'blank_line_after_opening_tag' => true,
            'blank_line_before_statement' => [
                'statements' => ['break', 'continue', 'declare', 'return', 'throw', 'try'],
            ],
            'class_attributes_separation' => [
                'elements' => [
                    'method' => 'one',
                ],
            ],
            'concat_space' => ['spacing' => 'one'],
            'declare_strict_types' => true,
            'global_namespace_import' => [
                'import_classes' => true,
                'import_constants' => true,
                'import_functions' => true,
            ],
            'linebreak_after_opening_tag' => true,
            'method_argument_space' => [
                'on_multiline' => 'ensure_fully_multiline',
                'keep_multiple_spaces_after_comma' => false,
            ],
            'modernize_types_casting' => true,
            'new_with_parentheses' => false,
            'no_empty_phpdoc' => true,
            'no_superfluous_phpdoc_tags' => [
                'allow_mixed' => true,
                'remove_inheritdoc' => true,
            ],
            'no_unused_imports' => true,
            'no_useless_else' => true,
            'no_useless_return' => true,
            'ordered_class_elements' => true,
            'ordered_imports' => ['sort_algorithm' => 'alpha'],
            'php_unit_test_case_static_method_calls' => [
                'call_type' => 'self',
            ],
            'phpdoc_scalar' => true,
            'phpdoc_to_param_type' => true,
            'phpdoc_to_return_type' => true,
            'single_line_empty_body' => false,
            'single_trait_insert_per_statement' => true,
            'strict_comparison' => true,
            'strict_param' => true,
            'trailing_comma_in_multiline' => [
                'elements' => ['arguments', 'parameters', 'match'],
            ],
            'types_spaces' => [
                'space' => 'single',
            ],
            'unary_operator_spaces' => true,
            'use_arrow_functions' => true,
            'void_return' => true,

            // Custom Fixers
            'App/empty_body_argument_style' => true,
            'App/force_method_arguments_multiline' => true,
            'App/mock_object_intersection_order' => true,
            'App/semicolon_placement' => true,
        ];
    }
}
