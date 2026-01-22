<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanyTagsBundle\Helper;

use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTriggerEvent;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Model\CompanyTriggerEventModel;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\DTO\TagDeletionValidationResult;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\DTO\TagUsageInfo;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Model\CompanyTagModel;

/**
 * Helper class to validate if company tags can be deleted.
 * Checks if tags are still in use in company point triggers.
 */
class CompanyTagDeleteValidator
{
    public function __construct(
        private CompanyTriggerEventModel $companyTriggerEventModel,
        private CompanyTagModel $companyTagModel
    ) {
    }

    /**
     * Validate which tags can be deleted and which are still in use.
     * Checks usage in: Company Point Triggers, Campaigns (TODO), Forms (TODO).
     *
     * @param array<int> $tagIds Array of tag IDs to validate
     *
     * @return TagDeletionValidationResult
     */
    public function validateForDeletion(array $tagIds): TagDeletionValidationResult
    {
        $deletableIds = [];
        $blockedTags = [];  // Array<string, TagUsageInfo>

        foreach ($tagIds as $tagId) {
            $entity = $this->companyTagModel->getEntity($tagId);

            if (null === $entity || !$entity->getId()) {
                continue;
            }

            // Check usage in triggers
            $usedInTriggerEvents = $this->checkTagUsageInTriggers($entity->getId());
            $usedInTriggers = array_map(fn($triggerEvent) => $triggerEvent->getTrigger(), $usedInTriggerEvents);

            // TODO: Check usage in campaigns (to be implemented)
            $usedInCampaigns = [];

            // TODO: Check usage in forms (to be implemented)
            $usedInForms = [];

            // Create usage info
            $usageInfo = new TagUsageInfo(
                triggers: $usedInTriggers,
                campaigns: $usedInCampaigns,
                forms: $usedInForms
            );

            if ($usageInfo->isUsed()) {
                $blockedTags[$entity->getTag()] = $usageInfo;
            } else {
                $deletableIds[] = $tagId;
            }
        }

        return new TagDeletionValidationResult($deletableIds, $blockedTags);
    }

    /**
     * Check if a tag is used in any company point triggers.
     *
     * @param int $tagId
     *
     * @return CompanyTriggerEvent[]
     */
    private function checkTagUsageInTriggers(int $tagId): array
    {
        $usedInTriggers = [];
        $allTriggerEvents = $this->companyTriggerEventModel->getRepository()->findAll();

        foreach ($allTriggerEvents as $triggerEvent) {
            $properties = $triggerEvent->getProperties();

            // Check if tag is in add_tags
            if (isset($properties['add_tags']) && is_array($properties['add_tags'])) {
                if (in_array($tagId, $properties['add_tags'])) {
                    $usedInTriggers[] = $triggerEvent;
                    continue;
                }
            }

            // Check if tag is in remove_tags
            if (isset($properties['remove_tags']) && is_array($properties['remove_tags'])) {
                if (in_array($tagId, $properties['remove_tags'])) {
                    $usedInTriggers[] = $triggerEvent;
                }
            }
        }

        return $usedInTriggers;
    }
}
