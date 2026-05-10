<?php

declare(strict_types=1);

namespace MessengerHistoryDashboard\Storefront\Controller;

use MessengerHistoryDashboard\Core\Repository\FailureRepository;
use MessengerHistoryDashboard\Core\Repository\MessageRepository;
use MessengerHistoryDashboard\Core\Repository\OperatorActionRepository;
use MessengerHistoryDashboard\Core\Repository\TransitionRepository;
use MessengerHistoryDashboard\Core\Service\ReplayService;
use Shopware\Core\Framework\Context;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['api'], '_acl' => ['system.plugin_maintain']])]
final class AdminApiController extends AbstractController
{
    public function __construct(
        private readonly MessageRepository $messageRepository,
        private readonly TransitionRepository $transitionRepository,
        private readonly FailureRepository $failureRepository,
        private readonly OperatorActionRepository $operatorActionRepository,
        private readonly ReplayService $replayService
    ) {
    }

    #[Route(path: '/api/_action/mh/messages', name: 'api.action.mh.messages', methods: ['GET'])]
    public function list(Request $request, Context $context): JsonResponse
    {
        $status = $request->query->get('status');
        $status = \is_string($status) && $status !== '' ? $status : null;
        $query = $request->query->get('query');
        $query = \is_string($query) && trim($query) !== '' ? trim($query) : null;
        $topicGroup = $request->query->get('topicGroup');
        $topicGroup = \is_string($topicGroup) && trim($topicGroup) !== '' ? trim($topicGroup) : null;
        $page = max(1, (int) ($request->query->get('page') ?? 1));
        $limit = max(1, (int) ($request->query->get('limit') ?? 25));
        $createdFrom = $this->parseDateTime($request->query->get('createdFrom'));
        $createdTo = $this->parseDateTime($request->query->get('createdTo'), true);

        return new JsonResponse(
            $this->messageRepository->list($status, $query, $page, $limit, $topicGroup, $createdFrom, $createdTo)
        );
    }

    #[Route(path: '/api/_action/mh/messages/retention/cleanup', name: 'api.action.mh.messages.retention.cleanup', methods: ['POST'])]
    public function cleanup(Request $request, Context $context): JsonResponse
    {
        $days = max(1, (int) ($request->request->get('days') ?? 30));
        $cutoff = new \DateTimeImmutable(\sprintf('-%d days', $days));

        return new JsonResponse([
            'status' => 'ok',
            'cutoff' => $cutoff->format(DATE_ATOM),
            'deleted' => $this->messageRepository->cleanupOlderThan($cutoff),
        ]);
    }

    #[Route(
        path: '/api/_action/mh/messages/{id}',
        name: 'api.action.mh.message.detail',
        requirements: ['id' => '[0-9a-fA-F-]{36,64}'],
        methods: ['GET']
    )]
    public function detail(string $id, Context $context): JsonResponse
    {
        return new JsonResponse([
            'message' => $this->messageRepository->find($id),
            'transitions' => $this->transitionRepository->findByMessageId($id),
            'failures' => $this->failureRepository->findByMessageId($id),
            'actions' => $this->operatorActionRepository->findByMessageId($id),
        ]);
    }

    #[Route(
        path: '/api/_action/mh/messages/{id}/retry',
        name: 'api.action.mh.message.retry',
        requirements: ['id' => '[0-9a-fA-F-]{36,64}'],
        methods: ['POST']
    )]
    public function retry(string $id, Request $request, Context $context): JsonResponse
    {
        $reason = (string) ($request->request->get('reason') ?? 'manual retry');

        return new JsonResponse([
            'status' => 'ok',
            'replayedMessageId' => $this->replayService->retry($id, $reason),
        ]);
    }

    #[Route(
        path: '/api/_action/mh/messages/{id}/quarantine',
        name: 'api.action.mh.message.quarantine',
        requirements: ['id' => '[0-9a-fA-F-]{36,64}'],
        methods: ['POST']
    )]
    public function quarantine(string $id, Request $request, Context $context): JsonResponse
    {
        $reason = (string) ($request->request->get('reason') ?? 'manual quarantine');
        $this->operatorActionRepository->insert($id, 'quarantine', $reason);

        return new JsonResponse(['status' => 'ok']);
    }

    #[Route(
        path: '/api/_action/mh/messages/{id}/dismiss',
        name: 'api.action.mh.message.dismiss',
        requirements: ['id' => '[0-9a-fA-F-]{36,64}'],
        methods: ['POST']
    )]
    public function dismiss(string $id, Request $request, Context $context): JsonResponse
    {
        $reason = (string) ($request->request->get('reason') ?? 'manual dismiss');
        $this->operatorActionRepository->insert($id, 'dismiss', $reason);

        return new JsonResponse(['status' => 'ok']);
    }

    #[Route(path: '/api/_action/mh/metrics', name: 'api.action.mh.metrics', methods: ['GET'])]
    public function metrics(Context $context): JsonResponse
    {
        return new JsonResponse($this->messageRepository->metrics());
    }

    private function parseDateTime(mixed $value, bool $endOfDay = false): ?\DateTimeImmutable
    {
        if (!\is_string($value) || trim($value) === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', trim($value));

        if ($date === false) {
            return null;
        }

        return $endOfDay ? $date->setTime(23, 59, 59) : $date->setTime(0, 0, 0);
    }
}
