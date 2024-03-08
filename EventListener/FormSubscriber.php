<?php

namespace MauticPlugin\LeuchtfeuerCompanyTagsBundle\EventListener;

use Mautic\FormBundle\Event\FormBuilderEvent;
use Mautic\FormBundle\Event\SubmissionEvent;
use Mautic\FormBundle\FormEvents;
use Mautic\LeadBundle\Entity\UtmTag;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Form\Type\ModifyCompanyTagsType;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class FormSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            FormEvents::FORM_ON_BUILD            => ['onFormBuilder', 0],
            FormEvents::ON_EXECUTE_SUBMIT_ACTION => [
                ['onFormSubmitActionAddUtmTags', 3],
            ],
        ];
    }

    public function onFormBuilder(FormBuilderEvent $event)
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
//        if (false === $event->checkContext('lead.addutmtags')) {
//            return;
//        }
//
//        if (!$contact = $this->contactTracker->getContact()) {
//            return;
//        }
//
//        $queryReferer = $queryArray = [];
//
//        parse_str($event->getRequest()->server->get('QUERY_STRING'), $queryArray);
//        $refererURL       = $event->getRequest()->server->get('HTTP_REFERER');
//        $refererParsedUrl = parse_url($refererURL);
//
//        if (isset($refererParsedUrl['query'])) {
//            parse_str($refererParsedUrl['query'], $queryReferer);
//        }
//
//        $utmValues = new UtmTag();
//        $utmValues->setLead($contact);
//        $utmValues->setQuery($event->getRequest()->query->all());
//        $utmValues->setReferer($refererURL);
//        $utmValues->setUrl($event->getRequest()->server->get('REQUEST_URI'));
//        $utmValues->setDateAdded(new \DateTime());
//        $utmValues->setRemoteHost($refererParsedUrl['host'] ?? null);
//        $utmValues->setUserAgent($event->getRequest()->server->get('HTTP_USER_AGENT') ?? null);
//        $utmValues->setUtmCampaign($queryArray['utm_campaign'] ?? $queryReferer['utm_campaign'] ?? null);
//        $utmValues->setUtmContent($queryArray['utm_content'] ?? $queryReferer['utm_content'] ?? null);
//        $utmValues->setUtmMedium($queryArray['utm_medium'] ?? $queryReferer['utm_medium'] ?? null);
//        $utmValues->setUtmSource($queryArray['utm_source'] ?? $queryReferer['utm_source'] ?? null);
//        $utmValues->setUtmTerm($queryArray['utm_term'] ?? $queryReferer['utm_term'] ?? null);
//
//        if ($utmValues->hasUtmTags()) {
//            $this->leadModel->getUtmTagRepository()->saveEntity($utmValues);
//            $this->leadModel->setUtmTags($utmValues->getLead(), $utmValues);
//        }
    }
}
