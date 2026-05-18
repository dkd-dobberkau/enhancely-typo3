<?php

declare(strict_types=1);

namespace Enhancely\Enhancely\Backend\SanityCheck;

final class SanityChecker
{
    /**
     * @param array<string, mixed> $jsonLd       The 'jsonld' object from the Enhancely API response.
     * @param array<string, mixed> $apiMeta      The 'meta' block (crawled_at, status, hash, ...).
     * @param string               $expectedWebsiteTitle From Site::getConfiguration()['websiteTitle'].
     * @return CheckResult[]
     */
    public function check(array $jsonLd, array $apiMeta, string $expectedWebsiteTitle): array
    {
        return [
            $this->checkBreadcrumbAbsolute($jsonLd),
            $this->checkTitleMismatch($jsonLd, $expectedWebsiteTitle),
        ];
    }

    private function checkBreadcrumbAbsolute(array $jsonLd): CheckResult
    {
        $relative = [];
        foreach ($this->findNodesByType($jsonLd, 'BreadcrumbList') as $bc) {
            foreach ($bc['itemListElement'] ?? [] as $item) {
                $url = (string)($item['item'] ?? '');
                if ($url === '') {
                    continue;
                }
                if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
                    $relative[] = $url;
                }
            }
        }

        if ($relative === []) {
            return CheckResult::pass(
                'breadcrumb_absolute',
                'BreadcrumbList items are absolute URLs'
            );
        }

        return CheckResult::warn(
            'breadcrumb_absolute',
            sprintf(
                'BreadcrumbList contains %d relative URL(s): %s',
                count($relative),
                implode(', ', array_slice($relative, 0, 3))
            )
        );
    }

    private function checkTitleMismatch(array $jsonLd, string $expected): CheckResult
    {
        if ($expected === '') {
            return CheckResult::pass(
                'title_mismatch',
                'No websiteTitle configured — skipping check'
            );
        }

        $mismatches = [];
        foreach (['WebSite', 'Organization'] as $type) {
            foreach ($this->findNodesByType($jsonLd, $type) as $node) {
                $name = (string)($node['name'] ?? '');
                if ($name !== '' && $name !== $expected) {
                    $mismatches[$type] = $name;
                }
            }
        }

        if ($mismatches === []) {
            return CheckResult::pass(
                'title_mismatch',
                'Site name matches configured websiteTitle'
            );
        }

        $pairs = [];
        foreach ($mismatches as $type => $found) {
            $pairs[] = sprintf('%s.name="%s"', $type, $found);
        }

        return CheckResult::warn(
            'title_mismatch',
            sprintf(
                'Site name mismatch: %s; expected "%s" (Enhancely may hold a pre-rename crawl)',
                implode(', ', $pairs),
                $expected
            )
        );
    }

    /**
     * @return iterable<array<string, mixed>>
     */
    private function findNodesByType(array $jsonLd, string $type): iterable
    {
        $graph = $jsonLd['@graph'] ?? [$jsonLd];
        foreach ($graph as $node) {
            if (is_array($node) && (($node['@type'] ?? null) === $type)) {
                yield $node;
            }
        }
    }
}
