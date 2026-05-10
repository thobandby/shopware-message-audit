<?php

declare(strict_types=1);

use NunoMaduro\PhpInsights\Domain\Insights\ClassMethodAverageCyclomaticComplexityIsHigh;
use NunoMaduro\PhpInsights\Domain\Insights\CyclomaticComplexityIsHigh;
use NunoMaduro\PhpInsights\Domain\Insights\ForbiddenSecurityIssues;
use NunoMaduro\PhpInsights\Domain\Insights\MethodCyclomaticComplexityIsHigh;
use PHP_CodeSniffer\Standards\Generic\Sniffs\Files\LineLengthSniff;
use SlevomatCodingStandard\Sniffs\Classes\SuperfluousInterfaceNamingSniff;
use SlevomatCodingStandard\Sniffs\Functions\FunctionLengthSniff;

return [
    'preset' => 'symfony',
    'ide' => 'phpstorm',
    'paths' => [
        __DIR__ . '/src',
        __DIR__ . '/plugin/MessengerHistoryDashboard/src',
    ],
    'exclude' => [
        'bin',
        'public',
        'shopware',
        'var',
        'vendor',
    ],
    'add' => [],
    'remove' => [
        ClassMethodAverageCyclomaticComplexityIsHigh::class,
        CyclomaticComplexityIsHigh::class,
        ForbiddenSecurityIssues::class,
        FunctionLengthSniff::class,
        LineLengthSniff::class,
        MethodCyclomaticComplexityIsHigh::class,
        SuperfluousInterfaceNamingSniff::class,
    ],
    'config' => [],
    'requirements' => [
        'min-quality' => 92,
        'min-complexity' => 95,
        'min-architecture' => 82,
        'min-style' => 91,
        'disable-security-check' => true,
    ],
];
