<?php

declare(strict_types=1);

namespace Enhancely\Enhancely\Backend\InfoModule;

interface UrlResolverInterface
{
    /**
     * @throws \RuntimeException If page has no site or URL cannot be built.
     */
    public function resolve(int $pageUid, int $languageId): string;

    public function expectedWebsiteTitle(int $pageUid): string;
}
