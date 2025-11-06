<?php

namespace MauticPlugin\LeuchtfeuerCompanyTagsBundle\Event;

use Mautic\CoreBundle\Event\CommonEvent;
use Mautic\LeadBundle\Entity\Company;

class CompanyTagsEvent extends CommonEvent
{
    /**
     * @param Company $company
     * @param bool $isNew
     * @param array<mixed> $tagsToAdd
     * @param array<mixed> $tagsToRemove
     */
    public function __construct(
        private Company $company,
        protected $isNew = false,
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

    public function isNew(): bool
    {
        return $this->isNew;
    }

    /**
     * @return array<array<mixed>>
     */
    public function getTags(): array
    {
        return [
            'added'    => $this->getTagsToAdd(),
            'removed'  => $this->getTagsToRemove(),
        ];
    }
}
