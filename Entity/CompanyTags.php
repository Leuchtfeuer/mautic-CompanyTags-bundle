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
    private ?string $tag;
    private $companies;

    private ?string $description;

    public function __construct(string $tag = null, bool $clean = true)
    {
        $this->tag            = $clean && $tag ? $this->validateTag($tag) : $tag;
        $this->loadCompanies();
    }

    public static function loadMetadata(ClassMetadata $metadata): void
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

    public static function loadValidatorMetadata(ValidatorClassMetadata $metadata): void
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

    public function getTag(): ?string
    {
        return $this->tag;
    }

    public function setTag(string $tag): void
    {
        $this->tag = $this->validateTag($tag);
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    private function validateTag(string $tag): string
    {
        return InputHelper::string(trim((string) $tag));
    }

    public function getName(): ?string
    {
        return $this->tag;
    }

    public function getPermissionUser(): int
    {
        return 0;
    }

    public function addCompany(Company $company): void
    {
        if (!$this->companies->contains($company)) {
            $this->companies->add($company);
        }
    }

    public function removeCompany(Company $company): bool
    {
        $key = $this->checkExistCompany($company);
        if (false === $key) {
            return false;
        }
        $this->companies->remove($key);

        return true;
    }

    private function checkExistCompany(Company $company): bool|int
    {
        foreach ($this->companies as $key => $companyTag) {
            if ($companyTag->getId() === $company->getId()) {
                return $key;
            }
        }

        return false;
    }

    public function getCompanies()
    {
        if (!$this->companies instanceof ArrayCollection) {
            $this->loadCompanies();
        }

        return $this->companies;
    }

    private function loadCompanies(): void
    {
        if (empty($this->companies)) {
            $this->companies = new ArrayCollection();
        } else {
            $arrayCollection = new ArrayCollection();
            foreach ($this->companies as $company) {
                $arrayCollection->add($company);
            }
            $this->companies = $arrayCollection;
        }
    }
}
