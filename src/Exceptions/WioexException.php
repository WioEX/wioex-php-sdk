<?php

declare(strict_types=1);

namespace Wioex\SDK\Exceptions;

use Exception;

class WioexException extends Exception
{
    protected array $context = [];

    public function setContext(array $context): self
    {
        $this->context = $context;
        return $this;
    }

    public function getContext(): array
    {
        return $this->context;
    }
}
