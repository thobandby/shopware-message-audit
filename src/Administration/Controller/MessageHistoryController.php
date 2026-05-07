<?php

declare(strict_types=1);

namespace Thorsten\MessengerHistory\Administration\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Thorsten\MessengerHistory\Messenger\Contract\AlertRuleEngineInterface;
use Thorsten\MessengerHistory\Messenger\Contract\MessageAuditRepositoryInterface;
use Thorsten\MessengerHistory\Messenger\Contract\MetricsExporterInterface;
use Thorsten\MessengerHistory\Messenger\Contract\SimpleMessageSearchCriteria;
use Thorsten\MessengerHistory\Messenger\Operator\OperatorActionService;

#[Route(defaults: ['_routeScope' => ['api']])]
final class MessageHistoryController extends AbstractController
{
    public function __construct(
        private readonly MessageAuditRepositoryInterface $auditRepository,
        private readonly OperatorActionService $operatorActionService,
        private readonly MetricsExporterInterface $metricsExporter,
        private readonly AlertRuleEngineInterface $alertRuleEngine,
    ) {
    }

    #[Route(path: '/api/_action/mh/messages/{messageUuid}', name: 'api.action.mh.detail', methods: ['GET'])]
    public function detail(string $messageUuid): JsonResponse
    {
        return new JsonResponse([
            'message' => $this->auditRepository->findByMessageUuid($messageUuid),
        ]);
    }

    #[Route(path: '/api/_action/mh/messages', name: 'api.action.mh.list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $criteria = new SimpleMessageSearchCriteria(
            status: $this->readNullableString($request, 'status'),
            transportName: $this->readNullableString($request, 'transportName'),
            messageClass: $this->readNullableString($request, 'messageClass'),
            businessReference: $this->readNullableString($request, 'businessReference'),
            limit: max(1, (int) $request->query->get('limit', 25)),
            offset: max(0, (int) $request->query->get('offset', 0)),
        );

        return new JsonResponse([
            'data' => $this->auditRepository->search($criteria),
        ]);
    }

    #[Route(path: '/api/_action/mh/messages/{messageUuid}/retry', name: 'api.action.mh.retry', methods: ['POST'])]
    public function retry(string $messageUuid, Request $request): JsonResponse
    {
        $payload = $this->decodePayload($request);

        $result = $this->operatorActionService->retryNow(
            $messageUuid,
            (string) ($payload['reasonCode'] ?? ''),
            isset($payload['reasonText']) ? (string) $payload['reasonText'] : null,
        );

        return new JsonResponse([
            'actionUuid' => $result->getActionUuid(),
            'result' => $result->getResult(),
            'messageUuid' => $result->getMessageUuid(),
            'newStatus' => $result->getNewStatus(),
        ]);
    }

    #[Route(path: '/api/_action/mh/messages/{messageUuid}/quarantine', name: 'api.action.mh.quarantine', methods: ['POST'])]
    public function quarantine(string $messageUuid, Request $request): JsonResponse
    {
        $payload = $this->decodePayload($request);
        $result = $this->operatorActionService->quarantine(
            $messageUuid,
            (string) ($payload['reasonCode'] ?? ''),
            isset($payload['reasonText']) ? (string) $payload['reasonText'] : null,
        );

        return new JsonResponse([
            'actionUuid' => $result->getActionUuid(),
            'result' => $result->getResult(),
            'messageUuid' => $result->getMessageUuid(),
            'newStatus' => $result->getNewStatus(),
        ]);
    }

    #[Route(path: '/api/_action/mh/messages/{messageUuid}/dismiss', name: 'api.action.mh.dismiss', methods: ['POST'])]
    public function dismiss(string $messageUuid, Request $request): JsonResponse
    {
        $payload = $this->decodePayload($request);
        $result = $this->operatorActionService->dismiss(
            $messageUuid,
            (string) ($payload['reasonCode'] ?? ''),
            isset($payload['reasonText']) ? (string) $payload['reasonText'] : null,
        );

        return new JsonResponse([
            'actionUuid' => $result->getActionUuid(),
            'result' => $result->getResult(),
            'messageUuid' => $result->getMessageUuid(),
            'newStatus' => $result->getNewStatus(),
        ]);
    }

    #[Route(path: '/api/_action/mh/metrics', name: 'api.action.mh.metrics', methods: ['GET'])]
    public function metrics(): JsonResponse
    {
        return new JsonResponse([
            'metrics' => $this->metricsExporter->exportPrometheus(),
        ]);
    }

    #[Route(path: '/api/_action/mh/alerts', name: 'api.action.mh.alerts', methods: ['GET'])]
    public function alerts(): JsonResponse
    {
        return new JsonResponse([
            'data' => $this->alertRuleEngine->evaluate(),
        ]);
    }

    /**
     * @return array<string, scalar|null>
     */
    private function decodePayload(Request $request): array
    {
        $content = (string) $request->getContent();
        if ($content === '') {
            return [];
        }

        return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    }

    private function readNullableString(Request $request, string $name): ?string
    {
        $value = $request->query->get($name);

        return \is_string($value) && $value !== '' ? $value : null;
    }
}
