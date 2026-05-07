<?php

declare(strict_types=1);

namespace Thorsten\MessengerHistory\Messenger\Monitoring;

final class PayloadHydrator
{
    /**
     * @param array<string, array<array-key, scalar|null>|scalar|null> $payload
     */
    public function hydrate(string $messageClass, array $payload): object
    {
        if (! class_exists($messageClass)) {
            throw new \RuntimeException('Message class not found: ' . $messageClass);
        }

        $reflectionClass = new \ReflectionClass($messageClass);
        $constructor = $reflectionClass->getConstructor();

        if ($constructor === null) {
            return $reflectionClass->newInstance();
        }

        $arguments = [];
        foreach ($constructor->getParameters() as $parameter) {
            $arguments[] = $this->resolveArgumentValue($parameter, $payload);
        }

        return $reflectionClass->newInstanceArgs($arguments);
    }

    /**
     * @param array<string, array<array-key, scalar|null>|scalar|null> $payload
     *
     * @return array<array-key, scalar|null>|scalar|null
     */
    private function resolveArgumentValue(\ReflectionParameter $parameter, array $payload): array|int|float|string|bool|null
    {
        $name = $parameter->getName();

        if (\array_key_exists($name, $payload)) {
            return $payload[$name];
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        throw new \RuntimeException('Cannot hydrate message. Missing payload field: ' . $name);
    }
}
