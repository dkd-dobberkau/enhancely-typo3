<?php

declare(strict_types=1);

namespace Enhancely\Enhancely\Backend\InfoModule;

use Enhancely\Enhancely\Client\JsonLdResponse;

interface JsonLdFetcherInterface
{
    public function fetch(string $url, bool $forceRefresh): JsonLdResponse;
}
