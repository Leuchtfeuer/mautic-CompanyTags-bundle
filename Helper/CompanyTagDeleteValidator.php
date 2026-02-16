<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanyTagsBundle\Helper;

use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CampaignBundle\Entity\EventRepository;
use Mautic\FormBundle\Entity\Form;
use Mautic\FormBundle\Model\ActionModel;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTrigger;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Model\CompanyTriggerEventModel;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\DTO\TagDeletionValidationResult;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\DTO\TagUsageInfo;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Entity\CompanyTags;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Model\CompanyTagModel;

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
     * @param array<int> $tagIds Array of tag IDs to validate
     */
    public function validateForDeletion(array $tagIds): TagDeletionValidationResult
    {
        $deletableIds = [];
        /** @var array<string, TagUsageInfo> $blockedTags */
        $blockedTags = [];

        $tags = $this->loadTags($tagIds);

        $triggerUsageMap  = $this->buildTriggerUsageMap();
        $campaignUsageMap = $this->buildCampaignUsageMap();
        $formUsageMap     = $this->buildFormUsageMap();

        foreach ($tags as $tagId => $entity) {
            $entityId = $entity->getId();
            if (null === $entityId) {
                continue;
            }

            $usedInTriggers  = $triggerUsageMap[$entityId] ?? [];
            $usedInCampaigns = $campaignUsageMap[$entityId] ?? [];
            $usedInForms     = $formUsageMap[$entityId] ?? [];

            $usageInfo = new TagUsageInfo(
                triggers: $usedInTriggers,
                campaigns: $usedInCampaigns,
                forms: $usedInForms
            );

            if ($usageInfo->isUsed()) {
                $tagName = $entity->getTag();
                if (null !== $tagName) {
                    $blockedTags[$tagName] = $usageInfo;
                }
            } else {
                $deletableIds[] = $tagId;
            }
        }

        return new TagDeletionValidationResult($deletableIds, $blockedTags);
    }

    /**
     * @param array<int> $tagIds
     *
     * @return array<int, CompanyTags>
     */
    private function loadTags(array $tagIds): array
    {
        if (empty($tagIds)) {
            return [];
        }

        $tags   = $this->companyTagModel->getRepository()->findBy(['id' => $tagIds]);
        $result = [];

        foreach ($tags as $tag) {
            if ($tag instanceof CompanyTags) {
                $id = $tag->getId();
                if (null !== $id) {
                    $result[$id] = $tag;
                }
            }
        }

        return $result;
    }

    /**
     * @return array<int, array<int, CompanyTrigger>>
     */
    private function buildTriggerUsageMap(): array
    {
        /** @var array<int, array<int, CompanyTrigger>> $usageMap */
        $usageMap         = [];
        $allTriggerEvents = $this->companyTriggerEventModel->getRepository()->findBy(['type' => 'companytags.updatetags']);

        foreach ($allTriggerEvents as $triggerEvent) {
            $properties = $triggerEvent->getProperties();
            $trigger    = $triggerEvent->getTrigger();

            if (isset($properties['add_tags']) && is_array($properties['add_tags'])) {
                foreach ($properties['add_tags'] as $tagId) {
                    if (!is_numeric($tagId)) {
                        continue;
                    }
                    $tagId = (int) $tagId;
                    if (!isset($usageMap[$tagId])) {
                        $usageMap[$tagId] = [];
                    }
                    $usageMap[$tagId][] = $trigger;
                }
            }

            if (isset($properties['remove_tags']) && is_array($properties['remove_tags'])) {
                foreach ($properties['remove_tags'] as $tagId) {
                    if (!is_numeric($tagId)) {
                        continue;
                    }
                    $tagId = (int) $tagId;
                    if (!isset($usageMap[$tagId])) {
                        $usageMap[$tagId] = [];
                    }
                    $usageMap[$tagId][] = $trigger;
                }
            }
        }

        return $usageMap;
    }

    /**
     * @return array<int, array<int, Campaign>>
     */
    private function buildCampaignUsageMap(): array
    {
        /** @var array<int, array<int, Campaign>> $usageMap */
        $usageMap          = [];
        $allCampaignEvents = $this->campaignEventRepository->findBy(['type' => 'companytag.changetags']);

        foreach ($allCampaignEvents as $campaignEvent) {
            $properties = $campaignEvent->getProperties();
            $campaign   = $campaignEvent->getCampaign();

            if (isset($properties['add_tags']) && is_array($properties['add_tags'])) {
                foreach ($properties['add_tags'] as $tagId) {
                    if (!is_numeric($tagId)) {
                        continue;
                    }
                    $tagId = (int) $tagId;
                    if (!isset($usageMap[$tagId])) {
                        $usageMap[$tagId] = [];
                    }
                    $usageMap[$tagId][] = $campaign;
                }
            }

            if (isset($properties['remove_tags']) && is_array($properties['remove_tags'])) {
                foreach ($properties['remove_tags'] as $tagId) {
                    if (!is_numeric($tagId)) {
                        continue;
                    }
                    $tagId = (int) $tagId;
                    if (!isset($usageMap[$tagId])) {
                        $usageMap[$tagId] = [];
                    }
                    $usageMap[$tagId][] = $campaign;
                }
            }
        }

        return $usageMap;
    }

    /**
     * @return array<int, array<int, Form|null>>
     */
    private function buildFormUsageMap(): array
    {
        /** @var array<int, array<int, Form|null>> $usageMap */
        $usageMap       = [];
        $allFormActions = $this->formActionModel->getRepository()->findBy(['type' => 'companytag.changetags']);

        foreach ($allFormActions as $formAction) {
            $properties = $formAction->getProperties();
            $form       = $formAction->getForm();

            if (isset($properties['add_tags']) && is_array($properties['add_tags'])) {
                foreach ($properties['add_tags'] as $tagId) {
                    if (!is_numeric($tagId)) {
                        continue;
                    }
                    $tagId = (int) $tagId;
                    if (!isset($usageMap[$tagId])) {
                        $usageMap[$tagId] = [];
                    }
                    $usageMap[$tagId][] = $form;
                }
            }

            if (isset($properties['remove_tags']) && is_array($properties['remove_tags'])) {
                foreach ($properties['remove_tags'] as $tagId) {
                    if (!is_numeric($tagId)) {
                        continue;
                    }
                    $tagId = (int) $tagId;
                    if (!isset($usageMap[$tagId])) {
                        $usageMap[$tagId] = [];
                    }
                    $usageMap[$tagId][] = $form;
                }
            }
        }

        return $usageMap;
    }
}
