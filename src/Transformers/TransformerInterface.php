<?php

declare(strict_types=1);

namespace Wioex\SDK\Transformers;

interface TransformerInterface
{
    public function transform(array $data, array $context = []): array;

    public function supports(array $data, array $context = []): bool;

    public function getName(): string;

    public function getDescription(): string;

    public function getOptions(): array;

    public function setOptions(array $options): self;

    public function validate(array $data, array $context = []): bool;
}
