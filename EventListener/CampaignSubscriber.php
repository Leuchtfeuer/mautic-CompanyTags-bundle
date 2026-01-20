<?php

namespace MauticPlugin\LeuchtfeuerCompanyTagsBundle\EventListener;

use Mautic\CampaignBundle\CampaignEvents;
use Mautic\CampaignBundle\Event\CampaignBuilderEvent;
use Mautic\CampaignBundle\Event\CampaignExecutionEvent;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Model\CompanyModel;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Form\Type\ModifyCompanyTagsType;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\LeuchtfeuerCompanyTagsEvents;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Model\CompanyTagModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Entity\CompanyTags;

class CampaignSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private CompanyTagModel $companyTagsModel,
        private CompanyModel $companyModel,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CampaignEvents::CAMPAIGN_ON_BUILD                        => 'onCampaignBuild',
            LeuchtfeuerCompanyTagsEvents::ON_CAMPAIGN_TRIGGER_ACTION => ['onCampaignTriggerAction', 0],
        ];
    }

    public function onCampaignBuild(CampaignBuilderEvent $event): void
    {
        $action = [
            'label'       => 'mautic.companytag.companytags.events.changetags',
            'description' => 'mautic.ompanytag.companytags.events.changetags_descr',
            'formType'    => ModifyCompanyTagsType::class,
            'eventName'   => LeuchtfeuerCompanyTagsEvents::ON_CAMPAIGN_TRIGGER_ACTION,
        ];
        $event->addAction('companytag.changetags', $action);
    }

    // @phpstan-ignore-next-line
    public function onCampaignTriggerAction(CampaignExecutionEvent $event): void
    {
        if (!$event->checkContext('companytag.changetags')) {
            return;
        }

        $config      = $event->getConfig();
        $lead        = $event->getLead();
        $company     = $lead->getCompany();
        if (empty($company)) {
            return;
        }

        if (!($company instanceof Company)) {
            return;
        }
        $companyName = $company->getName();

        $company    = $this->companyModel->getRepository()->findOneBy(['name' => $companyName]);
        $addTags    = (!empty($config['add_tags'])) ? $config['add_tags'] : [];
        $removeTags = (!empty($config['remove_tags'])) ? $config['remove_tags'] : [];
        $tagsToAdd  = $this->companyTagsModel->getRepository()->findBy(
            [
                'id'     => $addTags,
            ]
        );
        $tagsToRemove = $this->companyTagsModel->getRepository()->findBy(
            [
                'id'     => $removeTags,
            ]
        );

        if ($company instanceof Company && (!empty($tagsToAdd) || !empty($tagsToRemove))) {
            /** @var array<CompanyTags> $tagsToAdd */
            /** @var array<CompanyTags> $tagsToRemove */
            $this->companyTagsModel->updateCompanyTags($company, $tagsToAdd, $tagsToRemove);
        }
    }
}
