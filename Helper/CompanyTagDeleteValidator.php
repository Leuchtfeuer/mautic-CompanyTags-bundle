<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanyTagsBundle\Helper;

use Mautic\CampaignBundle\Entity\Event;
use Mautic\CampaignBundle\Entity\EventRepository;
use Mautic\FormBundle\Entity\Action;
use Mautic\FormBundle\Model\ActionModel;
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
        private CompanyTagModel $companyTagModel,
        private EventRepository $campaignEventRepository,
        private ActionModel $formActionModel,
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
            $usedInTriggerEvents = $this->checkTagUsageInPointTriggers($entity->getId());
            $usedInTriggers = array_map(fn($triggerEvent) => $triggerEvent->getTrigger(), $usedInTriggerEvents);

            $usedInCampaignEvents = $this->checkTagUsageInCampaigns($entity->getId());
            $usedInCampaigns = array_map(fn($campaignEvent) => $campaignEvent->getCampaign(), $usedInCampaignEvents);

            $usedInFormActions = $this->checkTagUsageInForms($entity->getId());
            $usedInForms = array_map(fn($formAction) => $formAction->getForm(), $usedInFormActions);

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
    private function checkTagUsageInPointTriggers(int $tagId): array
    {
        $usedInTriggers = [];
        $allTriggerEvents = $this->companyTriggerEventModel->getRepository()->findBy(['type' => 'companytags.updatetags']);

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

    /**
     *
     * @return Event[]
     */
    private function checkTagUsageInCampaigns(int $tagId): array
    {

        $usedInCampaignEvents = [];
        $allCamaignEvents = $this->campaignEventRepository->findBy(['type' => 'companytag.changetags']);

        foreach ($allCamaignEvents as $campaignEvent) {
            $properties = $campaignEvent->getProperties();

            // Check if tag is in add_tags
            if (isset($properties['add_tags']) && is_array($properties['add_tags'])) {
                if (in_array($tagId, $properties['add_tags'])) {
                    $usedInCampaignEvents[] = $campaignEvent;
                    continue;
                }
            }

            // Check if tag is in remove_tags
            if (isset($properties['remove_tags']) && is_array($properties['remove_tags'])) {
                if (in_array($tagId, $properties['remove_tags'])) {
                    $usedInCampaignEvents[] = $campaignEvent;
                }
            }
        }

        return $usedInCampaignEvents;
    }

    /**
     *
     * @return Action[]
     */
    private function checkTagUsageInForms(int $tagId): array
    {
        $usedInFormActions = [];
        $allFormActions = $this->formActionModel->getRepository()->findBy(['type' => 'companytag.changetags']);


        foreach ($allFormActions as $formAction) {
            $properties = $formAction->getProperties();

            // Check if tag is in add_tags
            if (isset($properties['add_tags']) && is_array($properties['add_tags'])) {
                if (in_array($tagId, $properties['add_tags'])) {
                    $usedInFormActions[] = $formAction;
                    continue;
                }
            }

            // Check if tag is in remove_tags
            if (isset($properties['remove_tags']) && is_array($properties['remove_tags'])) {
                if (in_array($tagId, $properties['remove_tags'])) {
                    $usedInFormActions[] = $formAction;
                }
            }
        }

        return $usedInFormActions;
    }
}
