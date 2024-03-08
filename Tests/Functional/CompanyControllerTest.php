<?php

namespace MauticPlugin\LeuchtfeuerCompanyTagsBundle\Tests\Functional;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\Company;
use Mautic\PluginBundle\Entity\Integration;
use Mautic\PluginBundle\Entity\Plugin;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Entity\CompanyTags;

class CompanyControllerTest extends MauticMysqlTestCase
{
    public const TAG_ONE = 'CompanyTag1';
    public const TAG_TWO = 'CompanyTag2';

    public const TAG_ONE_DESC = 'Description tag 1';
    public const TAG_TWO_DESC = 'Description tag 2';

    public function setUp(): void
    {
        parent::setUp();
        $this->activePlugin();
        $this->useCleanupRollback = false;
        $this->setUpSymfony($this->configParams);
    }

    public function testIndexAction(): void
    {
        $this->client->request('GET', '/s/companytag');
        $this->assertResponseStatusCodeSame(200);
        $this->assertStringContainsString('Company Tags', $this->client->getResponse()->getContent());
        $this->assertStringContainsString('<span class="hidden-xs hidden-sm">New</span>', $this->client->getResponse()->getContent());
    }

    public function testCheckFieldCompanyTags()
    {
        $this->client->request('GET', '/s/companies/new');
        $this->assertResponseStatusCodeSame(200);
        $this->assertStringContainsString('Company Tags', $this->client->getResponse()->getContent());
    }

    public function testSaveNewAction(): void
    {
        $tags                                = $this->addCompanyTags();
        $crawler                             = $this->client->request('GET', '/s/companies/new');
        $form                                = $crawler->filter('form[name=company]')->form();
        $formValues                          = $form->getValues();
        $formValues['company[companyname]']  = 'Test Company';
        $formValues['company[companyemail]'] = 'test@test.com';
        $formValues['custom_company[tag]']   = [$tags[0]->getId(), $tags[1]->getId()];
        $form->setValues($formValues);
        $this->client->submit($form);
        $this->assertResponseStatusCodeSame(200);
        $this->assertStringContainsString('Edit Company Test Company', $this->client->getResponse()->getContent());
        $this->assertStringContainsString(self::TAG_ONE, $this->client->getResponse()->getContent());
        $this->assertStringContainsString(self::TAG_TWO, $this->client->getResponse()->getContent());
        $companyEntity = $this->em->getRepository(Company::class)->findOneBy(['name' => 'Test Company'], ['id' => 'DESC']);
        $companyTags   = $this->em->getRepository(CompanyTags::class)->getTagsByCompany($companyEntity);
        $this->assertCount(2, $companyTags);
        foreach ($companyTags as $companyTag) {
            $this->assertContains($companyTag->getTag(), [self::TAG_ONE, self::TAG_TWO]);
        }
    }

    public function testEditAction(): void
    {
        $this->testSaveNewAction();
        $companyEntity     = $this->em->getRepository(Company::class)->findOneBy(['name' => 'Test Company'], ['id' => 'DESC']);
        $companyTagsBefore = $this->em->getRepository(CompanyTags::class)->getTagsByCompany($companyEntity);
        $this->assertCount(2, $companyTagsBefore);
        $crawler = $this->client->request('GET', '/s/companies/edit/'.$companyEntity->getId());
        $this->assertResponseStatusCodeSame(200);
        $form                               = $crawler->filter('form[name=company]')->form();
        $formValues                         = $form->getValues();
        $tags                               = $this->em->getRepository(CompanyTags::class)->findAll();
        $formValues['company[companyname]'] = 'Test Company 2';
        $formValues['custom_company[tag]']  = [$tags[0]->getId()];
        $form->setValues($formValues);
        $this->client->submit($form);
        $companyTagsAfter = $this->em->getRepository(CompanyTags::class)->getTagsByCompany($companyEntity);
        $this->assertCount(1, $companyTagsAfter);
    }

    public function testFieldCompanyTagsIsWorking()
    {
        $this->testSaveNewAction();
        $companyEntity     = $this->em->getRepository(Company::class)->findOneBy(['name' => 'Test Company'], ['id' => 'DESC']);
        $companyTagsBefore = $this->em->getRepository(CompanyTags::class)->getTagsByCompany($companyEntity);
        $crawler           = $this->client->request('GET', '/s/companies/edit/'.$companyEntity->getId());
        $this->assertResponseStatusCodeSame(200);
        $form       = $crawler->filter('form[name=company]')->form();
        $formValues = $form->getValues();
        foreach ($companyTagsBefore as $companyTag) {
            $this->assertContains((string) $companyTag->getId(), $formValues['custom_company[tag]']);
        }
        $crawler = $this->client->request('GET', '/s/companies/edit/'.$companyEntity->getId());
        preg_match_all('/'.self::TAG_ONE.'/', $this->client->getResponse()->getContent(), $out);
        $this->assertSame(self::TAG_ONE, $out[0][0]);
        preg_match_all('/'.self::TAG_TWO.'/', $this->client->getResponse()->getContent(), $out2);
        $this->assertSame(self::TAG_TWO, $out2[0][0]);
    }

    private function addCompanyTags()
    {
        $companyTag1 = new CompanyTags();
        $companyTag1->setTag(self::TAG_ONE);
        $companyTag1->setDescription('Description tag 1');
        $this->em->persist($companyTag1);
        $this->em->flush();
        $companyTag2 = new CompanyTags();
        $companyTag2->setTag(self::TAG_TWO);
        $this->em->persist($companyTag2);
        $this->em->flush();

        return [
            $companyTag1,
            $companyTag2,
        ];
    }

    private function activePlugin($isPublished = true)
    {
        $this->client->request('GET', '/s/plugins/reload');
        $integration = $this->em->getRepository(Integration::class)->findOneBy(['name' => 'LeuchtfeuerCompanyTags']);
        if (empty($integration)) {
            $plugin      = $this->em->getRepository(Plugin::class)->findOneBy(['bundle' => 'LeuchtfeuerCompanyTagsBundle']);
            $integration = new Integration();
            $integration->setName('LeuchtfeuerCompanyTags');
            $integration->setPlugin($plugin);
            $integration->setApiKeys([]);
        }
        $integration->setIsPublished($isPublished);
        $this->em->getRepository(Integration::class)->saveEntity($integration);
        $this->em->persist($integration);
        $this->em->flush();
    }
}
