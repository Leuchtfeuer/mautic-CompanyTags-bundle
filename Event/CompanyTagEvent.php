<?php

namespace MauticPlugin\LeuchtfeuerCompanyTagsBundle\Event;

use Mautic\CoreBundle\Event\CommonEvent;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Entity\CompanyTags;

class CompanyTagEvent extends CommonEvent
{
    public function __construct(
        private CompanyTags $companyTag,
        protected $isNew = false
    ) {
    }

    public function getCompanyTag(): CompanyTags
    {
        return $this->companyTag;
    }

    public function setCompanyTag(CompanyTags $companyTag): void
    {
        $this->companyTag = $companyTag;
    }
}
