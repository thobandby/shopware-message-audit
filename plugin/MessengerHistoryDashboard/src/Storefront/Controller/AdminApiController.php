<?php

declare(strict_types=1);

namespace MessengerHistoryDashboard\Storefront\Controller;

use MessengerHistoryDashboard\Core\Operator\CurrentOperatorResolver;
use MessengerHistoryDashboard\Core\Operator\OperatorActionPolicy;
use MessengerHistoryDashboard\Core\Repository\FailureRepository;
use MessengerHistoryDashboard\Core\Repository\MessageRepository;
use MessengerHistoryDashboard\Core\Repository\OperatorActionRepository;
use MessengerHistoryDashboard\Core\Repository\TransitionRepository;
use MessengerHistoryDashboard\Core\Service\ReplayService;
use Shopware\Core\Framework\Context;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['api'], '_acl' => ['system.plugin_maintain']])]
final class AdminApiController extends AbstractController
{
    private const MESSAGE_ID_REQUIREMENT = '[0-9a-fA-F-]{36,64}';

    public function __construct(
        private readonly MessageRepository $messageRepository,
        private readonly TransitionRepository $transitionRepository,
        private readonly FailureRepository $failureRepository,
        private readonly OperatorActionRepository $operatorActionRepository,
        private readonly ReplayService $replayService,
        private readonly OperatorActionPolicy $operatorActionPolicy,
        private readonly CurrentOperatorResolver $currentOperatorResolver
    ) {
    }

    #[Route(path: '/api/_action/mh/messages', name: 'api.action.mh.messages', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $locale = $this->resolveLocale($request);
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
        $messageClass = $this->readStringFilter($request, 'messageClass');
        $transportName = $this->readStringFilter($request, 'transportName');
        $businessReference = $this->readStringFilter($request, 'businessReference');

        return new JsonResponse(
            $this->messageRepository->list(new \MessengerHistoryDashboard\Core\Repository\MessageListCriteria(
                $status,
                $query,
                $page,
                $limit,
                $topicGroup,
                $createdFrom,
                $createdTo,
                $messageClass,
                $transportName,
                $businessReference
            ), $locale)
        );
    }

    #[Route(path: '/api/_action/mh/messages/retention/cleanup', name: 'api.action.mh.messages.retention.cleanup', methods: ['POST'])]
    public function cleanup(Request $request): JsonResponse
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
        requirements: ['id' => self::MESSAGE_ID_REQUIREMENT],
        methods: ['GET']
    )]
    public function detail(string $id): JsonResponse
    {
        $request = $this->container->get('request_stack')->getCurrentRequest();
        $locale = $request instanceof Request ? $this->resolveLocale($request) : 'de-DE';
        $message = $this->messageRepository->find($id, $locale);

        if ($message === false) {
            throw new NotFoundHttpException('Message not found.');
        }

        $relatedMessages = [];
        $transitions = $this->transitionRepository->findByMessageId($id);

        if (($message['source'] ?? null) === 'state_change' && \is_string($message['business_reference'] ?? null) && $message['business_reference'] !== '') {
            $relatedMessages = $this->messageRepository->findRelatedStateChangesByBusinessReference((string) $message['business_reference'], $locale);
            $transitions = $this->transitionRepository->findByMessageIds(
                array_map(
                    static fn (array $entry): string => (string) $entry['id'],
                    $relatedMessages
                )
            );
        }

        return new JsonResponse([
            'message' => $message,
            'relatedMessages' => $relatedMessages,
            'transitions' => $transitions,
            'failures' => $this->failureRepository->findByMessageId($id),
            'actions' => $this->operatorActionRepository->findByMessageId($id),
        ]);
    }

    #[Route(
        path: '/api/_action/mh/messages/{id}/retry',
        name: 'api.action.mh.message.retry',
        requirements: ['id' => self::MESSAGE_ID_REQUIREMENT],
        methods: ['POST']
    )]
    public function retry(string $id, Request $request, Context $context): JsonResponse
    {
        $message = $this->requireMessageWithAllowedAction($id, 'retry');
        $reason = (string) ($request->request->get('reason') ?? 'manual retry');

        return new JsonResponse([
            'status' => 'ok',
            'replayedMessageId' => $this->replayService->retry(
                (string) $message['id'],
                $reason,
                $this->currentOperatorResolver->resolve($context)
            ),
        ]);
    }

    #[Route(
        path: '/api/_action/mh/messages/{id}/quarantine',
        name: 'api.action.mh.message.quarantine',
        requirements: ['id' => self::MESSAGE_ID_REQUIREMENT],
        methods: ['POST']
    )]
    public function quarantine(string $id, Request $request, Context $context): JsonResponse
    {
        $this->requireMessageWithAllowedAction($id, 'quarantine');
        $reason = (string) ($request->request->get('reason') ?? 'manual quarantine');
        $this->operatorActionRepository->insert(
            $id,
            'quarantine',
            $reason,
            $this->currentOperatorResolver->resolve($context)
        );

        return new JsonResponse(['status' => 'ok']);
    }

    #[Route(
        path: '/api/_action/mh/messages/{id}/dismiss',
        name: 'api.action.mh.message.dismiss',
        requirements: ['id' => self::MESSAGE_ID_REQUIREMENT],
        methods: ['POST']
    )]
    public function dismiss(string $id, Request $request, Context $context): JsonResponse
    {
        $this->requireMessageWithAllowedAction($id, 'dismiss');
        $reason = (string) ($request->request->get('reason') ?? 'manual dismiss');
        $this->operatorActionRepository->insert(
            $id,
            'dismiss',
            $reason,
            $this->currentOperatorResolver->resolve($context)
        );

        return new JsonResponse(['status' => 'ok']);
    }

    #[Route(path: '/api/_action/mh/metrics', name: 'api.action.mh.metrics', methods: ['GET'])]
    public function metrics(): JsonResponse
    {
        return new JsonResponse($this->messageRepository->metrics());
    }

    /**
     * @return array<string, array<array-key, scalar|null>|list<string>|scalar|null>
     */
    private function requireMessageWithAllowedAction(string $id, string $action): array
    {
        $message = $this->messageRepository->find($id);

        if ($message === false) {
            throw new NotFoundHttpException('Message not found.');
        }

        $status = (string) ($message['status'] ?? '');

        if (! $this->operatorActionPolicy->isAllowed($action, $status)) {
            throw new BadRequestHttpException(
                \sprintf('Action "%s" is not allowed for status "%s".', $action, $status)
            );
        }

        return $message;
    }

    private function parseDateTime(string|int|float|bool|array|null $value, bool $endOfDay = false): ?\DateTimeImmutable
    {
        if (! \is_string($value) || trim($value) === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', trim($value));

        if ($date === false) {
            return null;
        }

        return $endOfDay ? $date->setTime(23, 59, 59) : $date->setTime(0, 0, 0);
    }

    private function readStringFilter(Request $request, string $name): ?string
    {
        $value = $request->query->get($name);

        if (! \is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function resolveLocale(Request $request): string
    {
        $locale = $request->headers->get('sw-admin-locale');

        if (\is_string($locale) && $locale !== '') {
            return $locale;
        }

        $acceptLanguage = $request->headers->get('Accept-Language');

        if (\is_string($acceptLanguage) && $acceptLanguage !== '') {
            $candidate = trim(explode(',', $acceptLanguage)[0]);

            if ($candidate !== '') {
                return $candidate;
            }
        }

        return $request->getLocale();
    }
}
