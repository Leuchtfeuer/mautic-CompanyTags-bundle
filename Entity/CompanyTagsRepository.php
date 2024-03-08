<?php

namespace MauticPlugin\LeuchtfeuerCompanyTagsBundle\Entity;

use Mautic\CoreBundle\Entity\CommonRepository;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\Tag;

class CompanyTagsRepository extends CommonRepository
{
    public function getTagByNameOrCreateNewOne($name= null): CompanyTags
    {
        if (empty($name)) {
            return new CompanyTags();
        }
        $companyTag = new CompanyTags($name, true);

        /** @var Tag|null $existingTag */
        $existingTag = $this->findOneBy(
            [
                'tag' => $companyTag->getTag(),
            ]
        );

        return $existingTag ?? $companyTag;
    }

    public function getTagsByCompany(Company $company): array
    {
        $companyId = $company->getId();
        $query     = $this->createQueryBuilder('t')
            ->select('t')
            ->join('t.companies', 'c')
            ->where('c.id = :companyId')
            ->setParameter('companyId', $companyId)
            ->getQuery();

        return $query->getResult();
    }
}
