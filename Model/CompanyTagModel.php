<?php

namespace MauticPlugin\LeuchtfeuerCompanyTagsBundle\Model;

use Mautic\CoreBundle\Model\FormModel;
use Mautic\LeadBundle\Entity\Company;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Entity\CompanyTags;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Form\Type\CompanyTagEntityType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

class CompanyTagModel extends FormModel
{
    public function getRepository(): \MauticPlugin\LeuchtfeuerCompanyTagsBundle\Entity\CompanyTagsRepository
    {
        return $this->em->getRepository(CompanyTags::class);
    }

    /**
     * Get a specific entity or generate a new one if id is empty.
     *
     * @param int $id
     */
    public function getEntity($id = null): CompanyTags
    {
        if (is_null($id)) {
            return new CompanyTags();
        }

        return parent::getEntity($id);
    }

    public function getPermissionBase(): string
    {
        return 'companytag:companytags';
    }

    /**
     * {@inheritdoc}
     *
     * @param object                              $entity
     * @param \Symfony\Component\Form\FormFactory $formFactory
     * @param null                                $action
     * @param array<mixed>                        $options
     */
    public function createForm($entity, $formFactory, $action = null, $options = []): FormInterface
    {
        if (!$entity instanceof CompanyTags) {
            throw new MethodNotAllowedHttpException(['CompanyTags']);
        }

        return $formFactory->create(CompanyTagEntityType::class, $entity, $options);
    }

    public function removeCompanyTag(Company $company, CompanyTags $companyTags): void
    {
        $qb = $this->em->getConnection()->createQueryBuilder();
        $qb->delete(MAUTIC_TABLE_PREFIX.'companies_companies_tags_xref')
            ->where('company_id = :company_id')
            ->andWhere('tag_id = :tag_id')
            ->setParameter('company_id', $company->getId())
            ->setParameter('tag_id', $companyTags->getId());
        $qb->executeQuery();
    }

    /**
     * @param array<string> $tagIds
     *
     * @return array<int>
     */
    public function getCompaniesIdByTags(array $tagIds): array
    {
        $qb = $this->em->getConnection()->createQueryBuilder();
        $qb->select('ctx.company_id')
            ->from(MAUTIC_TABLE_PREFIX.'companies_companies_tags_xref', 'ctx')
            ->where(
                $qb->expr()->in('ctx.tag_id', $tagIds)
            );

        $sqlResult = $qb->executeQuery()->fetchAllAssociative();
        if (empty($sqlResult)) {
            return [];
        }

        $ids = [];
        foreach ($sqlResult as $result) {
            $ids[] = $result['company_id'];
        }

        return $ids;
    }

    /**
     * @param array<CompanyTags> $addCompanyTags
     * @param array<CompanyTags> $removeCompanyTags
     */
    public function updateCompanyTags(Company $company, array $addCompanyTags = [], array $removeCompanyTags= []): void
    {
        if (empty($addCompanyTags) && empty($removeCompanyTags)) {
            return;
        }

        foreach ($addCompanyTags as $tag) {
            $tag->addCompany($company);
        }
        foreach ($removeCompanyTags as $tag) {
            $tag->removeCompany($company);
        }

        if (!empty($addCompanyTags)) {
            $this->saveEntities($addCompanyTags);
        }

        if (!empty($removeCompanyTags)) {
            $this->saveEntities($removeCompanyTags);
        }
    }

    /**
     * @return array<CompanyTags>
     *
     * @throws \Doctrine\DBAL\Exception
     */
    public function getTagsByCompany(Company $company): array
    {
        $qb = $this->em->getConnection()->createQueryBuilder();

        $qb->select('ctx.tag_id')
            ->from(MAUTIC_TABLE_PREFIX.'companies_companies_tags_xref', 'ctx')
            ->where('ctx.company_id = :company_id')
            ->setParameter('company_id', $company->getId());

        $sqlResult = $qb->executeQuery()->fetchAllAssociative();
        if (empty($sqlResult)) {
            return [];
        }

        $ids = [];
        foreach ($sqlResult as $result) {
            $ids[] = $result['tag_id'];
        }

        return $this->getRepository()->findBy(['id' => $ids]);
    }
}
