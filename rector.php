<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Attribute\SortAttributeNamedArgsRector;
use Rector\CodeQuality\Rector\FuncCall\SortCallLikeNamedArgsRector;
use Rector\CodingStyle\Rector\ArrowFunction\StaticArrowFunctionRector;
use Rector\CodingStyle\Rector\Closure\StaticClosureRector;
use Rector\CodingStyle\Rector\FuncCall\CountArrayToEmptyArrayComparisonRector;
use Rector\CodingStyle\Rector\String_\SimplifyQuoteEscapeRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\Assign\RemoveUnusedVariableAssignRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveEmptyClassMethodRector;
use Rector\Php73\Rector\FuncCall\JsonThrowOnErrorRector;
use Rector\Php73\Rector\FuncCall\StringifyStrNeedlesRector;
use Rector\Php81\Rector\FuncCall\NullToStrictStringFuncCallArgRector;
use Rector\Php83\Rector\ClassMethod\AddOverrideAttributeToOverriddenMethodsRector;
use Rector\Php84\Rector\Foreach_\ForeachToArrayAllRector;
use Rector\Php84\Rector\Foreach_\ForeachToArrayAnyRector;
use Rector\Php84\Rector\Foreach_\ForeachToArrayFindKeyRector;
use Rector\Php84\Rector\Foreach_\ForeachToArrayFindRector;
use Rector\PHPUnit\CodeQuality\Rector\MethodCall\FlipAssertRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    ->withCache('.cache/rector')
    ->withAttributesSets(all: true)
    ->withPhpSets()
    ->withRules([
        // PHP
        JsonThrowOnErrorRector::class,
        // Code style
        CountArrayToEmptyArrayComparisonRector::class,
        SortAttributeNamedArgsRector::class,
        SortCallLikeNamedArgsRector::class,
        StaticArrowFunctionRector::class,
        StaticClosureRector::class,
        SimplifyQuoteEscapeRector::class,
        // PHPUnit
        FlipAssertRector::class,
        // Dead code
        RemoveEmptyClassMethodRector::class,
        RemoveUnusedVariableAssignRector::class,
    ])
    ->withImportNames(importShortClasses: false)
    ->withTreatClassesAsFinal()
    ->withSkip([
        // PHP
        AddOverrideAttributeToOverriddenMethodsRector::class, // Unwanted
        ForeachToArrayAllRector::class, // Foreach is ten times faster
        ForeachToArrayAnyRector::class, // Foreach is ten times faster
        ForeachToArrayFindRector::class, // Foreach is ten times faster
        ForeachToArrayFindKeyRector::class, // Foreach is ten times faster
        StringifyStrNeedlesRector::class, // Too many false positive
        NullToStrictStringFuncCallArgRector::class, // Too many false positive
    ]);
