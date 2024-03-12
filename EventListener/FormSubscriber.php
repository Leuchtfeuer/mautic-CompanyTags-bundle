<?php

namespace MauticPlugin\LeuchtfeuerCompanyTagsBundle\EventListener;

use Mautic\CoreBundle\Exception\BadConfigurationException;
use Mautic\FormBundle\Event\FormBuilderEvent;
use Mautic\FormBundle\Event\SubmissionEvent;
use Mautic\FormBundle\FormEvents;
use Mautic\LeadBundle\Model\CompanyModel;
use Mautic\LeadBundle\Tracker\ContactTracker;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Form\Type\ModifyCompanyTagsType;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Integration\Config;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Model\CompanyTagModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class FormSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Config $config,
        private ContactTracker $contactTracker,
        private CompanyTagModel $companyTagsModel,
        private CompanyModel $companyModel,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            FormEvents::FORM_ON_BUILD            => ['onFormBuilder', 0],
            FormEvents::ON_EXECUTE_SUBMIT_ACTION => [
                ['onFormSubmitActionAddUtmTags', 3],
            ],
        ];
    }

    /**
     * @throws BadConfigurationException
     */
    public function onFormBuilder(FormBuilderEvent $event): void
    {
        $event->addSubmitAction('companytag.changetags', [
            'group'             => 'mautic.companytag.companytags.submitaction',
            'label'             => 'mautic.companytag.companytags.events.changetags',
            'description'       => 'mautic.companytag.companytags.events.changetags_descr',
            'formType'          => ModifyCompanyTagsType::class,
            'eventName'         => FormEvents::ON_EXECUTE_SUBMIT_ACTION,
            'allowCampaignForm' => true,
        ]);
    }

    public function onFormSubmitActionAddUtmTags(SubmissionEvent $event): void
    {
        if (!$this->config->isPublished()) {
            return;
        }

        if (false === $event->checkContext('companytag.changetags')) {
            return;
        }

        if (!$lead = $this->contactTracker->getContact()) {
            return;
        }

        $properties  = $event->getAction()->getProperties();
        $addTags     = $properties['add_tags'] ?: [];
        $removeTags  = $properties['remove_tags'] ?: [];
        $companyName = $lead->getCompany();
        if (empty($companyName)) {
            return;
        }
        $company = $this->companyModel->getRepository()->findOneBy(['name' => $companyName]);
        if (empty($company)) {
            return;
        }

        $tagsToAdd = $this->companyTagsModel->getRepository()->findBy(
            [
                'tag'     => $addTags,
            ]
        );
        $tagsToRemove = $this->companyTagsModel->getRepository()->findBy(
            [
                'tag'     => $removeTags,
            ]
        );
        $this->companyTagsModel->updateCompanyTags($company, $tagsToAdd, $tagsToRemove);
    }
}
