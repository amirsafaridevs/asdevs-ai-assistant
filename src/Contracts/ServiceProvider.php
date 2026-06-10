<?php

declare(strict_types=1);

namespace ASDevs\AIAssistant\Contracts;

use ASDevs\AIAssistant\Container;

abstract class ServiceProvider
{
    protected Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    abstract public function register(): void;
}
