<?php

declare(strict_types=1);

namespace Enhancely\Enhancely\Backend\InfoModule;

use Enhancely\Enhancely\Backend\SanityCheck\CheckResult;

final class ViewState
{
    public const BANNER_NONE = '';
    public const BANNER_NOT_CONFIGURED = 'not_configured';
    public const BANNER_DISABLED = 'disabled';
    public const BANNER_SITE_ERROR = 'site_error';

    /**
     * @param CheckResult[] $sanityChecks
     * @param array<string, mixed>|null $rawJsonLd
     */
    public function __construct(
        public readonly string $banner = self::BANNER_NONE,
        public readonly string $bannerDetail = '',
        public readonly string $statusBadge = 'unknown',
        public readonly string $url = '',
        public readonly ?string $crawledAt = null,
        public readonly ?string $etag = null,
        public readonly ?string $hash = null,
        public readonly string $source = '',
        public readonly array $graphTypes = [],
        public readonly array $sanityChecks = [],
        public readonly ?array $rawJsonLd = null,
        public readonly ?string $errorMessage = null,
    ) {}
}
