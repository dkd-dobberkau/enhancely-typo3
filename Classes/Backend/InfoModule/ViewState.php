<?php

declare(strict_types=1);

/*
 * This file is part of the "enhancely" extension for TYPO3 CMS.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Enhancely\Enhancely\Backend\InfoModule;

use Enhancely\Enhancely\Backend\SanityCheck\CheckResult;

final class ViewState
{
    public const BANNER_NONE = '';
    public const BANNER_NOT_CONFIGURED = 'not_configured';
    public const BANNER_DISABLED = 'disabled';
    public const BANNER_SITE_ERROR = 'site_error';
    public const BANNER_ACCESS_DENIED = 'access_denied';

    /**
     * @param string $bannerDetailKey Full LLL key of the detail message; the
     *        template resolves it. Kept as a key rather than a rendered string
     *        so the controller stays free of LanguageService.
     * @param array<int, string> $bannerDetailArguments sprintf arguments for that label
     * @param CheckResult[] $sanityChecks
     * @param array<string, mixed>|null $rawJsonLd
     */
    public function __construct(
        public readonly string $banner = self::BANNER_NONE,
        public readonly string $bannerDetailKey = '',
        public readonly array $bannerDetailArguments = [],
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

    public function getRawJsonLdPretty(): string
    {
        if ($this->rawJsonLd === null) {
            return '';
        }
        return (string)json_encode($this->rawJsonLd, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
