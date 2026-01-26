<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanyTagsBundle\DTO;

class TagDeletionValidationResult
{
    /**
     * @param array<int> $deletableIds
     * @param array<string, TagUsageInfo> $blockedTags
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
     * Get formatted list of blocked tags with their usage info.
     * Example: "tag1" (Campaign ID: 12, 134 / Company Point Trigger ID: 5)
     */
    public function getBlockedTagsList(): string
    {
        if (!$this->hasBlockedTags()) {
            return '';
        }

        $errorMessages = [];
        foreach ($this->blockedTags as $tagName => $usageInfo) {
            $usageString = $usageInfo->formatUsageString();
            $errorMessages[] = sprintf('"%s" (%s)', $tagName, $usageString);
        }

        return implode(', ', $errorMessages);
    }
}
