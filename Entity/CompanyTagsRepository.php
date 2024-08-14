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

        /** @var CompanyTags|null $existingTag */
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

    /**
     * Get a count of leads that belong to the tag.
     *
     * @return array<int, int>|int
     */
    public function countByLeads(array|int $tagIds): int|array
    {
        $q = $this->getEntityManager()->getConnection()->createQueryBuilder();

        $q->select('count(ctx.company_id) as thecount, ctx.tag_id')
            ->from(MAUTIC_TABLE_PREFIX.'companies_tags_xref', 'ctx');

        $returnArray = is_array($tagIds);

        if (!$returnArray) {
            $tagIds = [$tagIds];
        }

        $q->where(
            $q->expr()->in('ctx.tag_id', $tagIds)
        )
            ->groupBy('ctx.tag_id');

        $result = $q->executeQuery()->fetchAllAssociative();

        $return = [];
        foreach ($result as $r) {
            $return[$r['tag_id']] = $r['thecount'];
        }

        // Ensure lists without leads have a value
        foreach ($tagIds as $l) {
            if (!isset($return[$l])) {
                $return[$l] = 0;
            }
        }

        return ($returnArray) ? $return : $return[$tagIds[0]];
    }

    /**
     * @return array<CompanyTags>
     */
    public function getAllTagObjects(): array
    {
        $query     = $this->createQueryBuilder('t')
            ->select('t')
            ->getQuery();

        return $query->getResult();
    }

    /**
     * @param array<int>|array{} $ids
     *
     * @return array<CompanyTags>
     */
    public function getTagObjectsByIds($ids): array
    {
        $queryBuilder = $this->createQueryBuilder('t')
            ->select('t')
            ->where('t.id IN (:ids)')
            ->setParameter('ids', $ids);

        $query = $queryBuilder->getQuery();

        return $query->getResult();
    }
}
