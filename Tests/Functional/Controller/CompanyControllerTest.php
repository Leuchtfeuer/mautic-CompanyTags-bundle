<?php

namespace MauticPlugin\LeuchtfeuerCompanyTagsBundle\Tests\Functional\Controller;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\Company;
use Mautic\PluginBundle\Entity\Integration;
use Mautic\PluginBundle\Entity\Plugin;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Entity\CompanyTags;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Model\CompanyTagModel;

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
    }

    public function testIndexAction(): void
    {
        $this->client->request('GET', '/s/companytag');
        $this->assertResponseStatusCodeSame(200);
        $this->assertStringContainsString('Company Tags', $this->client->getResponse()->getContent());
        $this->assertStringContainsString('<span class="hidden-xs hidden-sm">New</span>', $this->client->getResponse()->getContent());
    }

    public function testCheckFieldCompanyTags(): void
    {
        $this->client->request('GET', '/s/companies/new');
        $this->assertResponseStatusCodeSame(200);
        $this->assertStringContainsString('Company Tags', $this->client->getResponse()->getContent());
    }

    public function testSaveNewAction(string $name='Test Company'): void
    {
        $tags                                = $this->addCompanyTags();
        $crawler                             = $this->client->request('GET', '/s/companies/new');
        $form                                = $crawler->filter('form[name=company]')->form();
        $formValues                          = $form->getValues();
        $formValues['company[companyname]']  = $name;
        $formValues['company[companyemail]'] = 'test@test.com';
        $formValues['custom_company[tag]']   = [$tags[0]->getId(), $tags[1]->getId()];
        $form->setValues($formValues);
        $this->client->submit($form);
        $this->assertResponseStatusCodeSame(200);
        $this->assertStringContainsString('Edit Company '.$name, $this->client->getResponse()->getContent());
        $this->assertStringContainsString(self::TAG_ONE, $this->client->getResponse()->getContent());
        $this->assertStringContainsString(self::TAG_TWO, $this->client->getResponse()->getContent());
        $companyEntity = $this->em->getRepository(Company::class)->findOneBy(['name' => $name], ['id' => 'DESC']);
        $companyTags   = $this->em->getRepository(CompanyTags::class)->getTagsByCompany($companyEntity);
        $this->assertCount(2, $companyTags);
        foreach ($companyTags as $companyTag) {
            $this->assertContains($companyTag->getTag(), [self::TAG_ONE, self::TAG_TWO]);
        }
    }

    public function testEditAction(): void
    {
        $this->testSaveNewAction('Test Company 22');
        $newName           = 'Test Company 22aaa';
        $companyEntity     = $this->em->getRepository(Company::class)->findOneBy(['name' => 'Test Company 22'], ['id' => 'DESC']);
        $companyTagsBefore = $this->em->getRepository(CompanyTags::class)->getTagsByCompany($companyEntity);
        $this->assertCount(2, $companyTagsBefore);
        $crawler = $this->client->request('GET', '/s/companies/edit/'.$companyEntity->getId());
        $this->assertResponseStatusCodeSame(200);
        $form                               = $crawler->filter('form[name=company]')->form();
        $formValues                         = $form->getValues();
        $tags                               = $this->em->getRepository(CompanyTags::class)->findAll();
        $formValues['company[companyname]'] = $newName;
        $formValues['custom_company[tag]']  = [$tags[0]->getId()];
        $form->setValues($formValues);
        $this->client->submit($form);
        $this->assertResponseStatusCodeSame(200);
        $this->assertStringContainsString($newName, $this->client->getResponse()->getContent());
        $companyEntity     = $this->em->getRepository(Company::class)->findOneBy(['name' => $newName], ['id' => 'DESC']);
        $companyTagsAfter  = $this->em->getRepository(CompanyTags::class)->getTagsByCompany($companyEntity);
        $this->assertCount(1, $companyTagsAfter);
    }

    public function testFieldCompanyTagsIsWorking(): void
    {
        $name = 'Test Company 5555';
        $this->testSaveNewAction($name);
        $companyEntity     = $this->em->getRepository(Company::class)->findOneBy(['name' => $name], ['id' => 'DESC']);
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

    /**
     * @param array<string> $tags
     *
     * @return array<CompanyTags>
     *
     * @throws \Doctrine\ORM\Exception\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function addCompanyTags(array $tags = []): array
    {
        if (empty($tags)) {
            $tags = [self::TAG_ONE, self::TAG_TWO];
        }

        foreach ($tags as $tag) {
            $companyTag = new CompanyTags();
            $companyTag->setTag($tag);
            $companyTag->setDescription('Description '.$tag);
            $this->em->persist($companyTag);
            $this->em->flush();
            $result[] = $companyTag;
        }

        return $result;
    }

    private function activePlugin(bool $isPublished = true): void
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
        $this->useCleanupRollback = false;
        $this->setUpSymfony($this->configParams);
    }

    public function testResearchBySameTagInTwoCompanies(): void
    {
        $tags                                = $this->addCompanyTags(['tagTest1', 'tagTest2', 'tagTest3', 'tagTest4']);
        // Set one Company with two tags
        $this->registerCompany('Test Company View List', 'test@test.com', [$tags[0]->getId(), $tags[1]->getId()]);
        // Set one Company with one other tag already set
        $this->registerCompany('Fee Lee Company', 'test1@test.com', [$tags[0]->getId()]);

        // Set one Company with no tags
        $this->registerCompany('Dee Gee Company', 'test1@test.com');

        // Search for tree companies listed
        $crawler  = $this->client->request('GET', '/s/companies');
        $this->assertResponseStatusCodeSame(200);
        $numberOfTrInCompanyListTable    = $crawler->filter('table[id=companyTable]')->filter('tr')->count();
        $this->assertSame(4, $numberOfTrInCompanyListTable);

        // Search for one company listed by tag
        $crawler                         = $this->client->request('GET', '/s/companies?search=tag:"tagTest1"');
        $numberOfTrInCompanyListTable    = $crawler->filter('table[id=companyTable]')->filter('tr')->count();
        $this->assertSame(3, $numberOfTrInCompanyListTable);
        $this->assertStringContainsString('Test Company View List', $this->client->getResponse()->getContent());
        $this->assertStringContainsString('Fee Lee Company', $this->client->getResponse()->getContent());
    }

    public function testCheckContatcsNumberIncompanyTagsPage(): void
    {
        $tags                                = $this->addCompanyTags(['tagTest1', 'tagTest2', 'tagTest3', 'tagTest4']);
        // Set one Company with two tags
        $this->registerFunctionalCompany('Test Company View List', 'test@test.com', [$tags[0]->getId(), $tags[1]->getId()]);
        $this->assertStringContainsString('Edit Company Test Company View List', $this->client->getResponse()->getContent());

        // Set one Company with one other tag already set
        $this->registerFunctionalCompany('Fee Lee Company', 'test1@test.com', [$tags[0]->getId()]);
        $this->assertStringContainsString('Edit Company Fee Lee Company', $this->client->getResponse()->getContent());

        $this->client->request('GET', '/s/companytag');
        $this->assertStringContainsString('View 2 Companies', $this->client->getResponse()->getContent());
        $this->assertStringContainsString('View 1 Company', $this->client->getResponse()->getContent());
    }

    public function testResearchBySameTagInOneCompany(): void
    {
        $tags = $this->addCompanyTags(['tagTest1', 'tagTest2', 'tagTest3', 'tagTest4']);

        // Set one Company with one other tags
        $this->registerCompany('Foo Baa Company', 'test1@test.com', [$tags[2]->getId()]);
        // Set one Company with no tags
        $this->registerCompany('Dee Gee Company', 'test12@test.com');
        // Search for tree companies listed
        $crawler                         = $this->client->request('GET', '/s/companies?search=');
        $numberOfTrInCompanyListTable    = $crawler->filter('table[id=companyTable]')->filter('tr')->count();
        $this->assertSame(3, $numberOfTrInCompanyListTable);
        $crawler                         = $this->client->request('GET', '/s/companies?search=tag:"'.$tags[2]->getName().'"');
        $numberOfTrInCompanyListTable    = $crawler->filter('table[id=companyTable]')->filter('tr')->count();
        $this->assertSame(2, $numberOfTrInCompanyListTable);
        $this->assertStringContainsString('Foo Baa Company', $this->client->getResponse()->getContent());
    }

    public function testResearchByNameDontExist(): void
    {
        $tags = $this->addCompanyTags(['tagTest1']);
        // Set one Company with one other tags
        $this->registerCompany('Foo Baa Company', 'test1@test.com', [$tags[0]->getId()]);
        $crawler                         = $this->client->request('GET', '/s/companies?search=tag:"foobaa"');
        $numberOfTrInCompanyListTable    = $crawler->filter('table[id=companyTable]')->filter('tr')->count();
        $this->assertSame(0, $numberOfTrInCompanyListTable);
    }

    /**
     * @param array<int> $tags
     *
     * @throws \Doctrine\ORM\Exception\NotSupported
     * @throws \Doctrine\ORM\Exception\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function registerCompany(string $companyName, string $companyEmail, array $tags=[]): Company
    {
        $company = new Company();
        $company->setName($companyName);
        $company->setEmail($companyEmail);
        $company->setScore(0);
        $this->em->persist($company);
        $this->em->flush();
        if (!empty($tags)) {
            $companyTag = static::getContainer()->get(CompanyTagModel::class);
            $tags       = $this->em->getRepository(CompanyTags::class)->findBy(['id' => $tags]);
            $companyTag->updateCompanyTags($company, $tags);
        }

        return $company;
    }

    /**
     * @param array<int> $tags
     *
     * @throws \Doctrine\ORM\Exception\NotSupported
     */
    protected function registerFunctionalCompany(string $companyName, string $companyEmail, array $tags = []): Company
    {
        $crawler                             = $this->client->request('GET', '/s/companies/new');
        $form                                = $crawler->filter('form[name=company]')->form();
        $formValues                          = $form->getValues();
        $formValues['company[companyname]']  = $companyName;
        $formValues['company[companyemail]'] = $companyEmail;
        if (!empty($tags)) {
            $formValues['custom_company[tag]']   = $tags;
        }
        $form->setValues($formValues);
        $this->client->submit($form);
        $this->assertResponseStatusCodeSame(200);
        $company = $this->em->getRepository(Company::class)->findOneBy(['name' => $companyName], ['id' => 'DESC']);

        return $company;
    }

    public function testIfJsonIsReturned(): void
    {
        $company = $this->registerCompany('Dee Gee Company', 'test2@test.com');
        $this->client->request('GET', '/s/company/view/'.$company->getId());
        $this->assertStringContainsString('LeuchtfeuerCompanyTagsBundle/Assets/js/companyTag.js', $this->client->getResponse()->getContent());
    }

    public function testSearchCompanySegment()
    {
        $company = $this->registerCompany('Test Company Segment', 'test@test.com');
        $this->client->request('GET', '/s/companies?search=company-segment:comp-seg-2-companies');
        $this->assertResponseStatusCodeSame(200);
    }

    public function testSearchCompanySegmentNoColumn()
    {
        $company = $this->registerCompany('Test Company Segment', 'test@test.com');
        $this->client->request('GET', '/s/companies?search=compan:comp-seg-2-companies');
        $this->assertResponseStatusCodeSame(200);
    }
}
