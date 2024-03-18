<?php

namespace MauticPlugin\LeuchtfeuerCompanyTagsBundle\Tests\Functional\Controller;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\PluginBundle\Entity\Integration;
use Mautic\PluginBundle\Entity\Plugin;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Entity\CompanyTags;

class CompanyTagsTest extends MauticMysqlTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $this->activePlugin();
        $this->useCleanupRollback = false;
        $this->setUpSymfony($this->configParams);
    }

    public function testNewViewAction()
    {
        $this->client->request('GET', '/s/companytag/new');
        $this->assertResponseStatusCodeSame(200);
    }

    public function testNewAction()
    {
        $crawler                                       = $this->client->request('GET', '/s/companytag/new');
        $form                                          = $crawler->filter('form[name=company_tag_entity]')->form();
        $formValues                                    = $form->getValues();
        $formValues['company_tag_entity[tag]']         = 'Test Tag';
        $formValues['company_tag_entity[description]'] = 'Test description';
        $form->setValues($formValues);
        $this->client->submit($form);
        $this->assertResponseStatusCodeSame(200);
        $this->assertStringContainsString('Test Tag', $this->client->getResponse()->getContent());
    }

    public function testNewAndDelete()
    {
        $crawler                                       = $this->client->request('GET', '/s/companytag/new');
        $form                                          = $crawler->filter('form[name=company_tag_entity]')->form();
        $formValues                                    = $form->getValues();
        $formValues['company_tag_entity[tag]']         = 'Test Tag 222';
        $formValues['company_tag_entity[description]'] = 'Test description';
        $form->setValues($formValues);
        $this->client->submit($form);
        $this->assertResponseStatusCodeSame(200);
        $this->assertStringContainsString('Test Tag', $this->client->getResponse()->getContent());
        $companyTag = $this->em->getRepository(CompanyTags::class)->findOneBy(['tag' => 'Test Tag 222'], ['id' => 'DESC']);
        $this->client->request('POST', '/s/companytag/delete/' . $companyTag->getId());
        $this->assertResponseStatusCodeSame(200);
        $companyTag = $this->em->getRepository(CompanyTags::class)->findOneBy(['tag' => 'Test Tag 222'], ['id' => 'DESC']);
        $this->assertEmpty($companyTag);
    }

    public function testNewEditAction()
    {
        $crawler                                       = $this->client->request('GET', '/s/companytag/new');
        $form                                          = $crawler->filter('form[name=company_tag_entity]')->form();
        $formValues                                    = $form->getValues();
        $formValues['company_tag_entity[tag]']         = 'Test Tag 33';
        $formValues['company_tag_entity[description]'] = 'Test description';
        $form->setValues($formValues);
        $this->client->submit($form);
        $this->assertResponseStatusCodeSame(200);
        $this->assertStringContainsString('Test Tag 33', $this->client->getResponse()->getContent());
        $companyTag = $this->em->getRepository(CompanyTags::class)->findOneBy(['tag' => 'Test Tag 33'], ['id' => 'DESC']);
        $crawler    = $this->client->request('GET', '/s/companytag/edit/' . $companyTag->getId());
        $this->assertResponseStatusCodeSame(200);
        $this->assertStringContainsString('Test Tag 33', $this->client->getResponse()->getContent());
        $this->assertStringContainsString('Test description', $this->client->getResponse()->getContent());
        $form                               = $crawler->filter('form[name=company_tag_entity]')->form();
        $formValues                         = $form->getValues();
        $formValues['company_tag_entity[description]'] = 'Test Tag description 1';
        $form->setValues($formValues);
        $this->client->submit($form);
        $this->assertResponseStatusCodeSame(200);
        $this->client->request('GET', '/s/companytag/edit/' . $companyTag->getId());
        $this->assertStringContainsString('Test Tag 33', $this->client->getResponse()->getContent());
        $this->assertStringContainsString('Test Tag description 1', $this->client->getResponse()->getContent());


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
}
