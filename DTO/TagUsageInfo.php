<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanyTagsBundle\DTO;

/**
 * DTO to track where a company tag is being used.
 * Supports multiple entity types: triggers, campaigns, forms.
 */
class TagUsageInfo
{
    /**
     * @param array $triggers Array of CompanyTrigger objects
     * @param array $campaigns Array of Campaign objects (to be implemented)
     * @param array $forms Array of Form objects (to be implemented)
     */
    public function __construct(
        private array $triggers = [],
        private array $campaigns = [],
        private array $forms = []
    ) {
    }

    /**
     * @return array
     */
    public function getTriggers(): array
    {
        return $this->triggers;
    }

    /**
     * @return array
     */
    public function getCampaigns(): array
    {
        return $this->campaigns;
    }

    /**
     * @return array
     */
    public function getForms(): array
    {
        return $this->forms;
    }

    /**
     * Check if tag is used anywhere.
     */
    public function isUsed(): bool
    {
        return !empty($this->triggers) || !empty($this->campaigns) || !empty($this->forms);
    }

    /**
     * Format usage info as string for error messages.
     * Example: "Company Point Trigger ID: 5, 4 / Campaign ID: 12, 134 / Form ID: 1, 2"
     */
    public function formatUsageString(): string
    {
        $parts = [];

        if (!empty($this->triggers)) {
            $triggerIds = array_map(fn($trigger) => $trigger->getId(), $this->triggers);
            $parts[] = sprintf('Company Point Trigger ID: %s', implode(', ', $triggerIds));
        }

        if (!empty($this->campaigns)) {
            $campaignIds = array_map(fn($campaign) => $campaign->getId(), $this->campaigns);
            $parts[] = sprintf('Campaign ID: %s', implode(', ', $campaignIds));
        }

        if (!empty($this->forms)) {
            $formIds = array_map(fn($form) => $form->getId(), $this->forms);
            $parts[] = sprintf('Form ID: %s', implode(', ', $formIds));
        }

        return implode(' / ', $parts);
    }
}
