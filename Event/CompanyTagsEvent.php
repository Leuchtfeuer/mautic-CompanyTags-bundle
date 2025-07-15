<?php

namespace MauticPlugin\LeuchtfeuerCompanyTagsBundle\Event;

use Mautic\CoreBundle\Event\CommonEvent;
use Mautic\LeadBundle\Entity\Company;

class CompanyTagsEvent extends CommonEvent
{
    public function __construct(
        private Company $company,
        protected $isNew = false,
        private array $tagsToAdd = [],
        private array $tagsToRemove = []
    )
    {
    }

    public function getCompany(): Company
    {
        return $this->company;
    }

    public function getTagsToAdd(): array
    {
        return $this->tagsToAdd;
    }

    public function getTagsToRemove(): array
    {
        return $this->tagsToRemove;
    }

    public function isNew(): bool
    {
        return $this->isNew;
    }

    public function getTags(): array
    {
        return [
            'added'    => $this->getTagsToAdd(),
            'removed' => $this->getTagsToRemove(),
        ];

    }

}
