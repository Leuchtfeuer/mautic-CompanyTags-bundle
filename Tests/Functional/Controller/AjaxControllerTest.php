<?php

namespace MauticPlugin\LeuchtfeuerCompanyTagsBundle\Tests\Functional\Controller;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\Company;
use Mautic\PluginBundle\Entity\Integration;
use Mautic\PluginBundle\Entity\Plugin;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Entity\CompanyTags;

class AjaxControllerTest extends MauticMysqlTestCase
{
    public const TAG_ONE = 'CompanyTag1';
    public const TAG_TWO = 'CompanyTag2';

    public const TAG_ONE_DESC = 'Description tag 1';
    public const TAG_TWO_DESC = 'Description tag 2';

    private $tags;

    private $company;

    public function setUp(): void
    {
        parent::setUp();
        $this->activePlugin();

        $this->useCleanupRollback = false;
        $this->setUpSymfony($this->configParams);
        $this->tags    = $this->addCompanyTags();
        $this->company = $this->addCompany($this->tags);
    }

    public function testRemoveCompanyCompanyTag()
    {
        $this->client->request('GET', '/s/companies/view/'.$this->company->getId());
        $this->assertStringContainsString(self::TAG_ONE, $this->client->getResponse()->getContent());
        $this->assertStringContainsString(self::TAG_TWO, $this->client->getResponse()->getContent());
        $tags = $this->em->getRepository(CompanyTags::class)->getTagsByCompany($this->company);
        $this->assertCount(2, $tags);
        $newCompany = $this->em->getRepository(Company::class)->find($this->company->getId());
        $this->client->request('POST', '/s/ajax?action=plugin:LeuchtfeuerCompanyTags:removeCompanyCompanyTag', ['companyId' => $this->company->getId(), 'tagId' => $tags[0]->getId()]);
        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
        $this->assertStringContainsString('{"success":1}', $this->client->getResponse()->getContent());
        $tags = $this->em->getRepository(CompanyTags::class)->getTagsByCompany($this->company);
        $this->assertCount(1, $tags);
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
    }

    private function addCompanyTags(): array
    {
        $crawler                                       = $this->client->request('GET', '/s/companytag/new');
        $form                                          = $crawler->filter('form[name=company_tag_entity]')->form();
        $formValues                                    = $form->getValues();
        $formValues['company_tag_entity[tag]']         = self::TAG_ONE;
        $formValues['company_tag_entity[description]'] = self::TAG_ONE_DESC;
        $form->setValues($formValues);
        $this->client->submit($form);
        $this->assertResponseStatusCodeSame(200);

        $crawler                                       = $this->client->request('GET', '/s/companytag/new');
        $form                                          = $crawler->filter('form[name=company_tag_entity]')->form();
        $formValues                                    = $form->getValues();
        $formValues['company_tag_entity[tag]']         = self::TAG_TWO;
        $formValues['company_tag_entity[description]'] = self::TAG_TWO_DESC;
        $form->setValues($formValues);
        $this->client->submit($form);
        $this->assertResponseStatusCodeSame(200);

        $companyTag1 = $this->em->getRepository(CompanyTags::class)->findOneBy(['tag' => self::TAG_ONE]);
        $companyTag2 = $this->em->getRepository(CompanyTags::class)->findOneBy(['tag' => self::TAG_TWO]);

        //        $companyTag1 = new CompanyTags();
        //        $companyTag1->setTag(self::TAG_ONE. rand(1, 1000000));
        //        $companyTag1->setDescription('Description tag 1');
        //        $this->em->persist($companyTag1);
        //        $this->em->flush();
        //        $companyTag2 = new CompanyTags();
        //        $companyTag2->setTag(self::TAG_TWO. rand(1, 1000000));
        //        $this->em->persist($companyTag2);
        //        $this->em->flush();

        return [
            $companyTag1,
            $companyTag2,
        ];
    }

    private function addCompany($tags=[]): Company
    {
        //        $tags                                = $this->addCompanyTags();
        $random                              = rand(1, 1000000);
        $name                                = 'Test Company '.$random;
        $crawler                             = $this->client->request('GET', '/s/companies/new');
        $form                                = $crawler->filter('form[name=company]')->form();
        $formValues                          = $form->getValues();
        $formValues['company[companyname]']  = $name;
        $formValues['company[score]']        = 0;
        $formValues['company[companyemail]'] = 'test'.$random.'@test.com';
        if (!empty($tags)) {
            $formTags = [];
            foreach ($tags as $tag) {
                $formTags[] = $tag->getId();
            }
            $formValues['custom_company[tag]']   = $formTags;
        }

        $form->setValues($formValues);
        $this->client->submit($form);
        $this->assertResponseStatusCodeSame(200);
        $this->assertStringContainsString('Edit Company Test Company', $this->client->getResponse()->getContent());
        $this->assertStringContainsString(self::TAG_ONE, $this->client->getResponse()->getContent());
        $this->assertStringContainsString(self::TAG_TWO, $this->client->getResponse()->getContent());

        return $this->em->getRepository(Company::class)->findOneBy(['name' => $name]);
    }
}
