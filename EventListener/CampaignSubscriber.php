<?php

namespace MauticPlugin\LeuchtfeuerCompanyTagsBundle\EventListener;

use Mautic\CampaignBundle\CampaignEvents;
use Mautic\CampaignBundle\Event\CampaignBuilderEvent;
use Mautic\CampaignBundle\Event\CampaignExecutionEvent;
use Mautic\LeadBundle\Form\Type\ModifyLeadTagsType;
use Mautic\LeadBundle\LeadEvents;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Model\CompanyTagModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CampaignSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private CompanyTagModel $companyTagsModel,
    ) {
    }

    public static function getSubscribedEvents()
    {
        return [
            CampaignEvents::CAMPAIGN_ON_BUILD      => 'onCampaignBuild',
            LeadEvents::ON_CAMPAIGN_TRIGGER_ACTION => ['onCampaignTriggerAction', 0],
        ];
    }

    public function onCampaignBuild(CampaignBuilderEvent $event)
    {
        $action = [
            'label'       => 'mautic.companytag.companytags.events.changetags',
            'description' => 'mautic.ompanytag.companytags.events.changetags_descr',
            'formType'    => ModifyLeadTagsType::class,
            'eventName'   => LeadEvents::ON_CAMPAIGN_TRIGGER_ACTION,
        ];
        $event->addAction('companytag.changetags', $action);
//        $builder->addDecision(
//            'company_tags',
//            [
//                'label'       => 'mautic.company.tags',
//                'description' => 'mautic.company.tags.desc',
//                'eventName'   => 'company.tags',
//                'formType'    => 'company_tags',
//                'formTheme'   => 'MauticCompanyTagsBundle:FormTheme:company_tags.html.php',
//                'formParams'  => [
//                    'update_select' => true,
//                ],
//            ]
//        );
//        $builder->addAction(
//            'company_tags',
//            [
//                'label'       => 'mautic.company.tags',
//                'description' => 'mautic.company.tags.desc',
//                'eventName'   => 'company.tags',
//                'formType'    => 'company_tags',
//                'formTheme'   => 'MauticCompanyTagsBundle:FormTheme:company_tags.html.php',
//                'formParams'  => [
//                    'update_select' => true,
//                ],
//            ]
//        );
    }

    public function onCampaignTriggerAction(CampaignExecutionEvent $event)
    {
        if (!$event->checkContext('companytag.changetags')) {
            return;
        }

        $config = $event->getConfig();
        $lead   = $event->getLead();

        $addTags    = (!empty($config['add_tags'])) ? $config['add_tags'] : [];
        $removeTags = (!empty($config['remove_tags'])) ? $config['remove_tags'] : [];

        $this->companyTagsModel->updateCompanyTags($lead, $addTags, $removeTags);

        return $event->setResult(true);
    }
}
