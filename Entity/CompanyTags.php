<?php

namespace MauticPlugin\LeuchtfeuerCompanyTagsBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;
use Mautic\ApiBundle\Serializer\Driver\ApiMetadataDriver;
use Mautic\CoreBundle\Doctrine\Mapping\ClassMetadataBuilder;
use Mautic\CoreBundle\Helper\InputHelper;
use Mautic\LeadBundle\Entity\Company;
use Symfony\Component\Validator\Mapping\ClassMetadata as ValidatorClassMetadata;

// class CompanyTags extends CommonEntity
class CompanyTags
{
    private $id;
    private $tag;
    private $companies;

    private $description;

    public function __construct(string $tag = null, bool $clean = true)
    {
        $this->tag            = $clean && $tag ? $this->validateTag($tag) : $tag;
        $this->companies      = new ArrayCollection();
    }

    public static function loadMetadata(ClassMetadata $metadata)
    {
        $builder = new ClassMetadataBuilder($metadata);
        $builder->setTable('company_tags')
            ->setCustomRepositoryClass(CompanyTagsRepository::class)
            ->addIndex(['tag'], 'company_tags_tag');

        $builder->addId();
        $builder->createField('tag', Types::STRING)->columnName('tag')->build();
        $builder->createField('description', Types::STRING)
            ->columnName('description')
            ->nullable()
            ->build();

        $builder->createManyToMany('companies', Company::class)
            ->setJoinTable('companies_tags_xref')
            ->setIndexBy('id')
            ->addInverseJoinColumn('company_id', 'id', false, false, 'CASCADE')
            ->addJoinColumn('tag_id', 'id', false, false, 'CASCADE')
            ->build();
    }

    public static function loadValidatorMetadata(ValidatorClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint(
            'tag',
            new \Symfony\Component\Validator\Constraints\NotBlank(
                [
                    'message' => 'mautic.core.name.required',
                ]
            )
        );
    }

    public static function loadApiMetadata(ApiMetadataDriver $metadata): void
    {
        $metadata->setGroupPrefix('company_tags')
            ->addListProperties(
                [
                    'id',
                    'tag',
                    'description',
                    'companies',
                ]
            )
            ->addProperties(
                [
                    'id',
                    'tag',
                    'description',
                    'companies',
                ]
            )
            ->build();
    }

    public function getId()
    {
        return $this->id;
    }

    public function getTag()
    {
        return $this->tag;
    }

    public function setTag($tag)
    {
//        $this->isChanged('tag', $tag);
        $this->tag = $this->validateTag($tag);
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function setDescription($description)
    {
//        $this->isChanged('description', $description);
        $this->description = $description;
    }

    private function validateTag(string $tag): string
    {
        return InputHelper::string(trim((string) $tag));
    }

    public function getName()
    {
        return $this->tag;
    }

    public function getPermissionUser()
    {
        return 0;
    }

    public function addCompany(Company $company)
    {
        if (!$this->companies->contains($company)) {
            $this->companies->add($company);
        }
    }

    public function removeCompany(Company $company)
    {
        $this->companies->removeElement($company);
    }

    public function getCompanies()
    {
        return $this->companies;
    }
}
