<?php

namespace MauticPlugin\LeuchtfeuerCompanyTagsBundle\EventListener;

use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTriggerEventRepository;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Event\CompanyTagEvent;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\LeuchtfeuerCompanyTagsEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

class CompanyTagDeleteSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private CompanyTriggerEventRepository $companyTriggerEventRepository,
        private TranslatorInterface $translator
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LeuchtfeuerCompanyTagsEvents::COMPANY_TAG_PRE_DELETE => ['onCompanyTagPreDelete', 0],
        ];
    }

    public function onCompanyTagPreDelete(CompanyTagEvent $event): void
    {
        $companyTag = $event->getCompanyTag();
        $tagId      = $companyTag->getId();

        if (!$tagId) {
            return;
        }

        // Check if the tag is used in any point triggers
        $isUsed = $this->companyTriggerEventRepository->isCompanyTagUsedInTriggers($tagId);

        if ($isUsed) {
            throw new BadRequestHttpException(
                $this->translator->trans(
                    'leuchtfeuer.companytags.error.tag_used_in_point_triggers',
                    ['%tag%' => $companyTag->getTag()],
                    'validators'
                )
            );
        }
    }
}
