<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanyTagsBundle\DTO;

/**
 * DTO for tag deletion validation results.
 */
class TagDeletionValidationResult
{
    /**
     * @param array<int> $deletableIds IDs of tags that can be safely deleted
     * @param array<string, TagUsageInfo> $blockedTags Tags that cannot be deleted (tag name => TagUsageInfo)
     */
    public function __construct(
        private array $deletableIds,
        private array $blockedTags
    ) {
    }

    /**
     * @return array<int>
     */
    public function getDeletableIds(): array
    {
        return $this->deletableIds;
    }

    /**
     * @return array<string, TagUsageInfo>
     */
    public function getBlockedTags(): array
    {
        return $this->blockedTags;
    }

    public function hasBlockedTags(): bool
    {
        return !empty($this->blockedTags);
    }

    public function hasDeletableTags(): bool
    {
        return !empty($this->deletableIds);
    }

    /**
     * Format blocked tags as error message.
     * Example: Cannot remove Company Tags that are still in use: "tag1" (Campaign ID: 12, 134 / Company Point Trigger ID: 5)
     */
    public function getBlockedTagsErrorMessage(): string
    {
        if (!$this->hasBlockedTags()) {
            return '';
        }

        $errorMessages = [];
        foreach ($this->blockedTags as $tagName => $usageInfo) {
            $usageString = $usageInfo->formatUsageString();
            $errorMessages[] = sprintf('"%s" (%s)', $tagName, $usageString);
        }

        return sprintf(
            'Cannot remove Company Tags that are still in use: %s',
            implode(', ', $errorMessages)
        );
    }
}
