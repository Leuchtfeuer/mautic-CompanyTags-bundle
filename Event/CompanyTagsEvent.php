<?php

namespace MauticPlugin\LeuchtfeuerCompanyTagsBundle\Event;

use Mautic\CoreBundle\Event\CommonEvent;
use Mautic\LeadBundle\Entity\Company;

class CompanyTagsEvent extends CommonEvent
{
    public function __construct(private Company $company)
    {
    }

    public function getCompany(): Company
    {
        return $this->company;
    }
}
