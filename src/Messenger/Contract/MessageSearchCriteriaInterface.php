<?php

declare(strict_types=1);

namespace Thorsten\MessengerHistory\Messenger\Contract;

interface MessageSearchCriteriaInterface
{
    public function getStatus(): ?string;

    public function getTransportName(): ?string;

    public function getMessageClass(): ?string;

    public function getBusinessReference(): ?string;

    public function getLimit(): int;

    public function getOffset(): int;
}
