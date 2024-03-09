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
    /**
     * @return CompanyTagsRepository
     */
    public function getRepository()
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

    public function getPermissionBase()
    {
        return 'comppanytag:companytags';
    }

    /**
     * {@inheritdoc}
     *
     * @param object                              $entity
     * @param \Symfony\Component\Form\FormFactory $formFactory
     * @param null                                $action
     * @param array                               $options
     *
     * @throws NotFoundHttpException
     */
    public function createForm($entity, $formFactory, $action = null, $options = []): FormInterface
    {
        if (!$entity instanceof CompanyTags) {
            throw new MethodNotAllowedHttpException(['CompanyTags']);
        }

        if (!empty($action)) {
            $options['action'] = $action;
        }

        return $formFactory->create(CompanyTagEntityType::class, $entity, $options);
    }

    public function addCompanyTag(Company $company, CompanyTags $companyTags)
    {
        $companyTags->addCompany($company);
        $this->saveEntity($companyTags);
    }

    public function removeCompanyTag(Company $company, CompanyTags $companyTags)
    {
        $companyTags->removeCompany($company);
        $this->saveEntity($companyTags);
    }

    public function updateCompanyTags(Company $company, array $addCompanyTags, array $removeCompanyTags= [])
    {
        if (empty($addCompanyTags) && empty($removeCompanyTags)) {
            return;
        }

        foreach ($addCompanyTags as $tag) {
            $this->addCompanyTag($company, $tag);
        }

        if (empty($removeCompanyTags)) {
            return;
        }
        foreach ($removeCompanyTags as $tag) {
            $this->removeCompanyTag($company, $tag);
        }
    }

    public function getTagsByCompany(Company $company)
    {
        $qb = $this->em->getConnection()->createQueryBuilder();

        $qb->select('ctx.tag_id')
            ->from(MAUTIC_TABLE_PREFIX.'companies_tags_xref', 'ctx')
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
