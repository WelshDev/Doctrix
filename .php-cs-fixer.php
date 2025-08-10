<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

$finder = Finder::create()
    ->in(__DIR__ . '/src')
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new Config())
    ->setParallelConfig(ParallelConfigFactory::detect())
    ->setFinder($finder)
    ->setRules([
        // PSR-12 basic
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'array_indentation' => true,
        'binary_operator_spaces' => [
            'default' => 'single_space',
            'operators' => [
                '=>' => 'single_space',
                '=' => 'single_space',
            ],
        ],
        'blank_line_after_namespace' => true,
        'blank_line_after_opening_tag' => true,
        'blank_line_before_statement' => [
            'statements' => ['return', 'throw', 'try'],
        ],
        'braces_position' => [
            'control_structures_opening_brace' => 'next_line_unless_newline_at_signature_end',
            'functions_opening_brace' => 'next_line_unless_newline_at_signature_end',
            'anonymous_functions_opening_brace' => 'next_line_unless_newline_at_signature_end',
            'classes_opening_brace' => 'next_line_unless_newline_at_signature_end',
            'anonymous_classes_opening_brace' => 'next_line_unless_newline_at_signature_end',
            'allow_single_line_empty_anonymous_classes' => false,
            'allow_single_line_anonymous_functions' => false,
        ],
        'control_structure_braces' => true,
        'no_unneeded_control_parentheses' => [
            'statements' => ['break', 'clone', 'continue', 'echo_print', 'return', 'switch_case', 'yield'],
        ],
        'no_unneeded_curly_braces' => ['namespaces' => true],
        'control_structure_continuation_position' => [
            'position' => 'next_line',
        ],
        'cast_spaces' => true,
        'class_attributes_separation' => [
            'elements' => ['method' => 'one'],
        ],
        'concat_space' => ['spacing' => 'one'],
        'declare_equal_normalize' => true,
        'function_typehint_space' => true,
        'full_opening_tag' => true,
        'indentation_type' => true,
        'line_ending' => true,
        'lowercase_cast' => true,
        'no_unused_imports' => true,
        'no_whitespace_in_blank_line' => true,
        'no_trailing_whitespace' => true,
        'no_trailing_whitespace_in_comment' => true,
        'no_extra_blank_lines' => [
            'tokens' => [
                'break',
                'continue',
                'return',
                'throw',
                'use',
                'curly_brace_block',
                'extra',
                'parenthesis_brace_block',
            ],
        ],
        'no_empty_comment' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'phpdoc_align' => ['align' => 'left'],
        'phpdoc_annotation_without_dot' => true,
        'phpdoc_indent' => true,
        'phpdoc_line_span' => [
            'const' => 'multi',   // keep class-constant docblocks on multiple lines
            'property' => 'multi',   // keep property docblocks on multiple lines
            'method' => 'multi',   // keep method docblocks on multiple lines
        ],
        'phpdoc_order' => true,
        'phpdoc_return_self_reference' => true,
        'phpdoc_scalar' => true,
        'phpdoc_separation' => false,
        'phpdoc_single_line_var_spacing' => true,
        'phpdoc_summary' => false,
        'phpdoc_to_comment' => false,
        'phpdoc_trim' => true,
        'phpdoc_types' => ['groups' => ['simple', 'alias', 'meta']],
        'return_type_declaration' => ['space_before' => 'none'],
        'single_quote' => true,
        'switch_case_semicolon_to_colon' => true,
        'switch_case_space' => true,
        'ternary_operator_spaces' => true,
        'unary_operator_spaces' => true,
        'whitespace_after_comma_in_array' => true,

        // Type safety
        'declare_strict_types' => false,

        // Namespace and import organization
        'no_leading_import_slash' => true,
        'fully_qualified_strict_types' => true,
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => true,
            'import_functions' => true,
        ],
        'single_import_per_statement' => true,
        'single_line_after_imports' => true,

        // Visibility and modifier ordering
        'visibility_required' => [
            'elements' => ['property', 'method', 'const'],
        ],
        'ordered_class_elements' => [
            'order' => [
                'use_trait',
                'constant_public',
                'constant_protected',
                'constant_private',
                'property_public',
                'property_protected',
                'property_private',
                'construct',
                'destruct',
                'magic',
                'phpunit',
                'method_public',
                'method_protected',
                'method_private',
            ],
        ],

        // Spacing and formatting consistency
        'blank_line_between_import_groups' => true,
        'compact_nullable_type_declaration' => true,
        'single_space_around_construct' => true,
        'spaces_inside_parentheses' => false,
        'no_spaces_around_offset' => true,
        'no_whitespace_before_comma_in_array' => true,
        'normalize_index_brace' => true,
        'object_operator_without_whitespace' => true,
        'standardize_not_equals' => true,
        'standardize_increment' => true,

        // Code quality and cleanup
        'no_empty_statement' => true,
        'no_empty_phpdoc' => true,
        'no_superfluous_elseif' => true,
        'no_useless_else' => true,
        'no_useless_return' => true,
        'single_line_comment_style' => ['comment_types' => ['hash']],
        'multiline_comment_opening_closing' => true,
        'single_line_empty_body' => false,

        // PHP 8+ features
        'trailing_comma_in_multiline' => [
            'elements' => ['arrays', 'arguments', 'parameters'],
        ],
        'nullable_type_declaration' => true,
        'nullable_type_declaration_for_default_null_value' => true,

        // Custom project conventions from CLAUDE.md
        // enforce PascalCase for class names
        'class_definition' => ['multi_line_extends_each_single_line' => true],
        // enforce camelCase for methods and functions
        'method_argument_space' => ['on_multiline' => 'ensure_fully_multiline'],

        // CLAUDE.md specific requirements
        'function_declaration' => [
            'closure_fn_spacing' => 'none',
        ],
        'lambda_not_used_import' => true,
        'return_assignment' => true,
        'simplified_if_return' => true,
        'simplified_null_return' => true,


        // Ensure consistent array syntax
        'list_syntax' => ['syntax' => 'short'],

        // Enforce consistent string concatenation with spaces (as per concat_space rule)
        'string_implicit_backslashes' => true,

        // Additional consistency rules based on CLAUDE.md patterns
        'no_null_property_initialization' => true,
        'simple_to_complex_string_variable' => true,
        'single_trait_insert_per_statement' => true,
    ])
    ->setIndent('    ')
    ->setLineEnding("\n");
