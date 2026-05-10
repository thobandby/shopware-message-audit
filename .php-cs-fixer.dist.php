<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/plugin/MessengerHistoryDashboard/src')
    ->in(__DIR__ . '/tests');

$config = new PhpCsFixer\Config();

return $config->setRules([
    '@PSR12' => true,
    'strict_param' => true,
    'array_syntax' => ['syntax' => 'short'],
    'declare_strict_types' => true,
    'void_return' => true,
    'native_function_invocation' => ['include' => ['@compiler_optimized']],
])
    ->setFinder($finder)
    ->setRiskyAllowed(true);
