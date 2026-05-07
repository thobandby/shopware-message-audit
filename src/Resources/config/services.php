<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Thorsten\MessengerHistory\Administration\Controller\MessageHistoryController;
use Thorsten\MessengerHistory\Messenger\Monitoring\NullMetricsExporter;
use Thorsten\MessengerHistory\Messenger\Monitoring\NullNotificationDispatcher;
use Thorsten\MessengerHistory\Messenger\Monitoring\SymfonyMessengerOperatorActionExecutor;
use Thorsten\MessengerHistory\Messenger\Observer\MessageAuditMiddleware;
use Thorsten\MessengerHistory\Messenger\Observer\MessageLifecycleSubscriber;
use Thorsten\MessengerHistory\Messenger\Operator\OperatorActionService;
use Thorsten\MessengerHistory\Messenger\Policy\AllowAllOperatorPolicy;
use Thorsten\MessengerHistory\Messenger\Monitoring\NoopAlertRuleEngine;
use Thorsten\MessengerHistory\Messenger\Monitoring\NoopOperatorActionExecutor;
use Thorsten\MessengerHistory\Messenger\Monitoring\ReflectionPayloadExtractor;
use Thorsten\MessengerHistory\Messenger\Monitoring\StaticCurrentOperatorProvider;
use Thorsten\MessengerHistory\Messenger\Monitoring\UuidCorrelationResolver;
use Thorsten\MessengerHistory\Messenger\Repository\DbMessageAuditRepository;
use Thorsten\MessengerHistory\Messenger\Repository\DbOperatorActionRepository;
use Thorsten\MessengerHistory\Messenger\Repository\InMemoryAnnotationRepository;
use Thorsten\MessengerHistory\Messenger\Repository\InMemoryMessageAuditRepository;
use Thorsten\MessengerHistory\Messenger\Repository\InMemoryOperatorActionRepository;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()->defaults()->autowire()->autoconfigure()->private();

    $services->load('Thorsten\\MessengerHistory\\', __DIR__ . '/../../')
        ->exclude([__DIR__ . '/../../Resources']);

    $services->set(DbMessageAuditRepository::class);
    $services->set(DbOperatorActionRepository::class);
    $services->set(InMemoryMessageAuditRepository::class);
    $services->set(InMemoryOperatorActionRepository::class);
    $services->set(InMemoryAnnotationRepository::class);
    $services->set(NullMetricsExporter::class);
    $services->set(NullNotificationDispatcher::class);
    $services->set(AllowAllOperatorPolicy::class);
    $services->set(NoopAlertRuleEngine::class);
    $services->set(SymfonyMessengerOperatorActionExecutor::class);
    $services->set(ReflectionPayloadExtractor::class);
    $services->set(StaticCurrentOperatorProvider::class);
    $services->set(UuidCorrelationResolver::class);

    $services->alias('Thorsten\MessengerHistory\Messenger\Contract\MessageAuditRepositoryInterface', DbMessageAuditRepository::class);
    $services->alias('Thorsten\MessengerHistory\Messenger\Contract\OperatorActionRepositoryInterface', DbOperatorActionRepository::class);
    $services->alias('Thorsten\MessengerHistory\Messenger\Contract\AnnotationRepositoryInterface', InMemoryAnnotationRepository::class);
    $services->alias('Thorsten\MessengerHistory\Messenger\Contract\MetricsExporterInterface', NullMetricsExporter::class);
    $services->alias('Thorsten\MessengerHistory\Messenger\Contract\NotificationDispatcherInterface', NullNotificationDispatcher::class);
    $services->alias('Thorsten\MessengerHistory\Messenger\Contract\OperatorPolicyInterface', AllowAllOperatorPolicy::class);
    $services->alias('Thorsten\MessengerHistory\Messenger\Contract\AlertRuleEngineInterface', NoopAlertRuleEngine::class);
    $services->alias('Thorsten\MessengerHistory\Messenger\Contract\OperatorActionExecutorInterface', SymfonyMessengerOperatorActionExecutor::class);
    $services->alias('Thorsten\MessengerHistory\Messenger\Contract\MessagePayloadExtractorInterface', ReflectionPayloadExtractor::class);
    $services->alias('Thorsten\MessengerHistory\Messenger\Contract\CurrentOperatorProviderInterface', StaticCurrentOperatorProvider::class);
    $services->alias('Thorsten\MessengerHistory\Messenger\Contract\MessageCorrelationResolverInterface', UuidCorrelationResolver::class);

    $services->set(MessageLifecycleSubscriber::class)->tag('kernel.event_subscriber');
    $services->set(MessageAuditMiddleware::class)->tag('messenger.middleware');
    $services->set(OperatorActionService::class)->public();
    $services->set(MessageHistoryController::class)->public();
};
