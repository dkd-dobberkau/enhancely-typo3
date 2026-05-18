<?php

declare(strict_types=1);

namespace Enhancely\Enhancely\Backend\SanityCheck;

final class CheckResult
{
    public function __construct(
        public readonly string $id,
        public readonly string $level,
        public readonly string $message,
    ) {}

    public static function pass(string $id, string $message): self
    {
        return new self($id, 'pass', $message);
    }

    public static function warn(string $id, string $message): self
    {
        return new self($id, 'warn', $message);
    }
}
