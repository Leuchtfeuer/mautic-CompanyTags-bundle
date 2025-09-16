<?php

namespace MauticPlugin\LeuchtfeuerCompanyTagsBundle\Event;

use Mautic\CoreBundle\Event\CommonEvent;
use Mautic\LeadBundle\Entity\Company;

class CompanyUpdateCompanyTagsEvent extends CommonEvent
{
    public function __construct(
        private Company $company,
        private array $tagsToAdd = [],
        private array $tagsToRemove = []
    ) {
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
}
