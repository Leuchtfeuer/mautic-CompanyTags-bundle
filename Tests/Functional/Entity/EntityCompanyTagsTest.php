<?php

namespace MauticPlugin\LeuchtfeuerCompanyTagsBundle\Tests\Functional\Entity;

use Mautic\LeadBundle\Entity\Company;
use Mautic\PluginBundle\Entity\Integration;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests\MauticMysqlTestCase;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Entity\CompanyTags;
use Mautic\PluginBundle\Entity\Plugin;

class EntityCompanyTagsTest extends MauticMysqlTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $this->activePlugin();
    }

    public function testNewCompanyTag(): void
    {
        $companyTag = new CompanyTags();
        $companyTag->setTag('TestTag');
        $companyTag->setDescription('TestDescription');
        $this->em->persist($companyTag);
        $this->em->flush();
        $this->assertNotNull($companyTag->getId());
        $this->assertEquals('TestDescription', $companyTag->getDescription());
    }

    public function testAddCompany(): void
    {
        $company = new Company();
        $company->setName('TestCompany');
        $this->em->persist($company);
        $this->em->flush();
        $this->assertNotNull($company->getId());

        $companyTag = new CompanyTags();
        $companyTag->setTag('TestTag');
        $companyTag->setDescription('TestDescription');
        $this->em->persist($companyTag);
        $this->em->flush();
        $this->assertNotNull($companyTag->getId());
        $this->assertEquals('TestDescription', $companyTag->getDescription());

        $companyTag->addCompany($company);
        $this->em->flush();
        $this->assertContains($company, $companyTag->getCompanies());

        $companyTag = $this->em->getRepository(CompanyTags::class)->findOneBy(['tag' => 'TestTag']);
        $this->assertContains($company, $companyTag->getCompanies());
    }

    public function testRemoveCompany(): void
    {
        $company = new Company();
        $company->setName('TestCompany');
        $this->em->persist($company);
        $this->em->flush();
        $this->assertNotNull($company->getId());

        $companyTag = new CompanyTags();
        $companyTag->setTag('TestTag');
        $companyTag->setDescription('TestDescription');
        $this->em->persist($companyTag);
        $this->em->flush();
        $this->assertNotNull($companyTag->getId());
        $this->assertEquals('TestDescription', $companyTag->getDescription());

        $companyTag->addCompany($company);
        $this->em->flush();
        $this->assertContains($company, $companyTag->getCompanies());

        $companyTag->removeCompany($company);
        $this->em->flush();
        $this->assertNotContains($company, $companyTag->getCompanies());
    }

    //    public function testCh

    private function activePlugin(bool $isPublished = true): void
    {
        $this->client->request('GET', '/s/plugins/reload');
        $integration = $this->em->getRepository(Integration::class)->findOneBy(['name' => 'LeuchtfeuerCompanyTags']);
        if (empty($integration)) {
            $plugin      = $this->em->getRepository(Plugin::class)->findOneBy(['bundle' => 'LeuchtfeuerCompanyTagsBundle']);
            $integration = new Integration();
            $integration->setName('LeuchtfeuerCompanyTags');
            $integration->setPlugin($plugin);
        }
        $integration->setIsPublished($isPublished);
        $this->em->persist($integration);
        $this->em->flush();
        $this->useCleanupRollback = false;
        $this->setUpSymfony($this->configParams);
    }
}
