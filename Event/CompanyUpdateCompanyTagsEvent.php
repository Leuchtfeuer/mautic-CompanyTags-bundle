<?php

namespace MauticPlugin\LeuchtfeuerCompanyTagsBundle\Event;

use Mautic\CoreBundle\Event\CommonEvent;
use Mautic\LeadBundle\Entity\Company;

class CompanyUpdateCompanyTagsEvent extends CommonEvent
{
    /**
     * @param Company $company
     * @param array<mixed> $tagsToAdd
     * @param array<mixed> $tagsToRemove
     */
    public function __construct(
        private Company $company,
        private array $tagsToAdd = [],
        private array $tagsToRemove = [],
    ) {
    }

    public function getCompany(): Company
    {
        return $this->company;
    }

    /**
     * @return array<mixed>
     */
    public function getTagsToAdd(): array
    {
        return $this->tagsToAdd;
    }

    /**
     * @return array<mixed>
     */
    public function getTagsToRemove(): array
    {
        return $this->tagsToRemove;
    }
}
