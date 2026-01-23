<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanyTagsBundle\DTO;

use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\FormBundle\Entity\Form;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTrigger;

class TagUsageInfo
{
    /**
     * @param array<CompanyTrigger> $triggers
     * @param array<Campaign> $campaigns
     * @param array<Form|null> $forms
     */
    public function __construct(
        private array $triggers = [],
        private array $campaigns = [],
        private array $forms = []
    ) {
    }

    /**
     * @return array<CompanyTrigger>
     */
    public function getTriggers(): array
    {
        return $this->triggers;
    }

    /**
     * @return array<Campaign>
     */
    public function getCampaigns(): array
    {
        return $this->campaigns;
    }

    /**
     * @return array<Form|null>
     */
    public function getForms(): array
    {
        return $this->forms;
    }

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
            $formIds = array_map(fn($form) => $form->getId(), array_filter($this->forms));
            $parts[] = sprintf('Form ID: %s', implode(', ', $formIds));
        }

        return implode(' / ', $parts);
    }
}
