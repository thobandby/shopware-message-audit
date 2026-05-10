<?php

declare(strict_types=1);

namespace MessengerHistoryDashboard\Core\Service;

final class PayloadHydrator
{
    /**
     * @param array<string, mixed> $payload
     */
    public function hydrate(string $className, array $payload): object
    {
        if (!class_exists($className)) {
            throw new \RuntimeException('Message class not found: ' . $className);
        }

        $reflectionClass = new \ReflectionClass($className);
        $constructor = $reflectionClass->getConstructor();

        if ($constructor === null) {
            return $reflectionClass->newInstance();
        }

        $args = [];
        foreach ($constructor->getParameters() as $parameter) {
            $name = $parameter->getName();
            if (\array_key_exists($name, $payload)) {
                $args[] = $payload[$name];
            } elseif ($parameter->isDefaultValueAvailable()) {
                $args[] = $parameter->getDefaultValue();
            } else {
                throw new \RuntimeException('Cannot hydrate message. Missing payload field: ' . $name);
            }
        }

        return $reflectionClass->newInstanceArgs($args);
    }
}
