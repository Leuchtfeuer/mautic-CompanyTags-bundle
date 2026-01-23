<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanyTagsBundle\Tests\Functional\Controller;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\PluginBundle\Entity\Integration;
use Mautic\PluginBundle\Entity\Plugin;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Entity\CompanyTags;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Tests\Fixtures\FixtureHelper;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\Request;

class CompanyTagsControllerTest extends MauticMysqlTestCase
{
    protected $useCleanupRollback = false;

    private FixtureHelper $fixtureHelper;

    public function setUp(): void
    {
        parent::setUp();
        $this->activePlugin();
        $this->setUpSymfony($this->configParams);

        $this->fixtureHelper = new FixtureHelper($this->em);
        $this->fixtureHelper->enableCompanyPointsPlugin();
    }

    public function testNewViewAction(): void
    {
        $this->client->request('GET', '/s/companytag/new');
        $this->assertResponseStatusCodeSame(200);
    }

    public function testNewAction(): void
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

    public function testNewAndDelete(): void
    {
        $crawler                                       = $this->client->request('GET', '/s/companytag/new');
        $form                                          = $crawler->filter('form[name=company_tag_entity]')->form();
        $formValues                                    = $form->getValues();
        $formValues['company_tag_entity[tag]']         = 'Test Tag 2223';
        $formValues['company_tag_entity[description]'] = 'Test description';
        $form->setValues($formValues);
        $this->client->submit($form);
        $this->assertResponseStatusCodeSame(200);
        $this->assertStringContainsString('Test Tag 2223', $this->client->getResponse()->getContent());
        $companyTag = $this->em->getRepository(CompanyTags::class)->findOneBy(['tag' => 'Test Tag 2223'], ['id' => 'DESC']);
        $this->client->request('POST', '/s/companytag/delete/'.$companyTag->getId());
        $companyTag = $this->em->getRepository(CompanyTags::class)->findOneBy(['tag' => 'Test Tag 2223'], ['id' => 'DESC']);
        $this->assertEmpty($companyTag);
    }

    public function testNewEditAction(): void
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
        $crawler    = $this->client->request('GET', '/s/companytag/edit/'.$companyTag->getId());
        $this->assertResponseStatusCodeSame(200);
        $this->assertStringContainsString('Test Tag 33', $this->client->getResponse()->getContent());
        $this->assertStringContainsString('Test description', $this->client->getResponse()->getContent());
        $form                                          = $crawler->filter('form[name=company_tag_entity]')->form();
        $formValues                                    = $form->getValues();
        $formValues['company_tag_entity[description]'] = 'Test Tag description 1';
        $form->setValues($formValues);
        $this->client->submit($form);
        $this->assertResponseStatusCodeSame(200);
        $this->client->request('GET', '/s/companytag/edit/'.$companyTag->getId());
        $this->assertStringContainsString('Test Tag 33', $this->client->getResponse()->getContent());
        $this->assertStringContainsString('Test Tag description 1', $this->client->getResponse()->getContent());
    }

    public function testDeleteUnusedTagSucceeds(): void
    {
        $tag = $this->fixtureHelper->createCompanyTag('Unused Tag');
        $tagId = $tag->getId();

        $this->client->request(
            Request::METHOD_POST,
            "/s/companytag/delete/{$tagId}"
        );

        $response = $this->client->getResponse();

        Assert::assertTrue($response->isRedirection() || $response->isOk());

        $this->em->clear();
        $deletedTag = $this->em->getRepository(CompanyTags::class)->find($tagId);
        Assert::assertNull($deletedTag, 'Tag should be deleted from database');
    }

    public function testDeleteTagUsedInTriggerFails(): void
    {
        $tag = $this->fixtureHelper->createCompanyTag('Trigger Tag');
        $tagId = $tag->getId();
        Assert::assertNotNull($tagId);

        $trigger = $this->fixtureHelper->createCompanyTrigger('Test Trigger');
        $this->fixtureHelper->createCompanyTriggerEventWithTags(
            $trigger,
            addTagIds: [$tagId]
        );

        $this->client->request(
            Request::METHOD_POST,
            "/s/companytag/delete/{$tagId}"
        );

        $response = $this->client->getResponse();
        Assert::assertTrue($response->isOk(), 'Should stay on page with error message');

        $content = $response->getContent();
        Assert::assertIsString($content);
        Assert::assertStringContainsString('Cannot remove Company Tags that are still in use', $content);
        Assert::assertStringContainsString('Company Point Trigger ID:', $content);

        $this->em->clear();
        $existingTag = $this->em->getRepository(CompanyTags::class)->find($tagId);
        Assert::assertNotNull($existingTag, 'Tag should still exist in database');
    }

    public function testDeleteTagUsedInCampaignFails(): void
    {
        $tag = $this->fixtureHelper->createCompanyTag('Campaign Tag');
        $tagId = $tag->getId();
        Assert::assertNotNull($tagId);

        $campaign = $this->fixtureHelper->createCampaign('Test Campaign');
        $this->fixtureHelper->createCampaignEventWithTags(
            $campaign,
            addTagIds: [$tagId]
        );

        $this->client->request(
            Request::METHOD_POST,
            "/s/companytag/delete/{$tagId}"
        );

        $response = $this->client->getResponse();
        Assert::assertTrue($response->isOk());

        $content = $response->getContent();
        Assert::assertIsString($content);
        Assert::assertStringContainsString('Cannot remove Company Tags that are still in use', $content);
        Assert::assertStringContainsString('Campaign ID:', $content);

        $this->em->clear();
        $existingTag = $this->em->getRepository(CompanyTags::class)->find($tagId);
        Assert::assertNotNull($existingTag);
    }

    public function testDeleteTagUsedInFormFails(): void
    {
        $tag = $this->fixtureHelper->createCompanyTag('Form Tag');
        $tagId = $tag->getId();
        Assert::assertNotNull($tagId);

        $form = $this->fixtureHelper->createForm('Test Form');
        $this->fixtureHelper->createFormActionWithTags(
            $form,
            removeTagIds: [$tagId]
        );

        $this->client->request(
            Request::METHOD_POST,
            "/s/companytag/delete/{$tagId}"
        );

        $response = $this->client->getResponse();
        Assert::assertTrue($response->isOk());

        $content = $response->getContent();
        Assert::assertIsString($content);
        Assert::assertStringContainsString('Cannot remove Company Tags that are still in use', $content);
        Assert::assertStringContainsString('Form ID:', $content);

        $this->em->clear();
        $existingTag = $this->em->getRepository(CompanyTags::class)->find($tagId);
        Assert::assertNotNull($existingTag);
    }

    public function testDeleteTagUsedInMultipleLocationsFails(): void
    {
        $tag = $this->fixtureHelper->createCompanyTag('Multi-Location Tag');
        $tagId = $tag->getId();
        Assert::assertNotNull($tagId);

        $trigger = $this->fixtureHelper->createCompanyTrigger('Test Trigger');
        $this->fixtureHelper->createCompanyTriggerEventWithTags(
            $trigger,
            addTagIds: [$tagId]
        );

        $campaign = $this->fixtureHelper->createCampaign('Test Campaign');
        $this->fixtureHelper->createCampaignEventWithTags(
            $campaign,
            removeTagIds: [$tagId]
        );

        $form = $this->fixtureHelper->createForm('Test Form');
        $this->fixtureHelper->createFormActionWithTags(
            $form,
            addTagIds: [$tagId]
        );

        $this->client->request(
            Request::METHOD_POST,
            "/s/companytag/delete/{$tagId}"
        );

        $response = $this->client->getResponse();
        Assert::assertTrue($response->isOk());

        $content = $response->getContent();
        Assert::assertIsString($content);
        Assert::assertStringContainsString('Cannot remove Company Tags that are still in use', $content);
        Assert::assertStringContainsString('Company Point Trigger ID:', $content);
        Assert::assertStringContainsString('Campaign ID:', $content);
        Assert::assertStringContainsString('Form ID:', $content);

        $this->em->clear();
        $existingTag = $this->em->getRepository(CompanyTags::class)->find($tagId);
        Assert::assertNotNull($existingTag);
    }

    public function testBatchDeleteAllUnusedTagsSucceeds(): void
    {
        $tag1 = $this->fixtureHelper->createCompanyTag('Unused 1');
        $tag2 = $this->fixtureHelper->createCompanyTag('Unused 2');
        $tag3 = $this->fixtureHelper->createCompanyTag('Unused 3');

        $tag1Id = $tag1->getId();
        $tag2Id = $tag2->getId();
        $tag3Id = $tag3->getId();

        $tagIds = [$tag1Id, $tag2Id, $tag3Id];

        $this->client->request(
            Request::METHOD_POST,
            '/s/companytag/batchDelete?ids='.json_encode($tagIds)
        );

        $response = $this->client->getResponse();

        Assert::assertTrue($response->isRedirection() || $response->isOk());

        $this->em->clear();
        Assert::assertNull($this->em->getRepository(CompanyTags::class)->find($tag1Id));
        Assert::assertNull($this->em->getRepository(CompanyTags::class)->find($tag2Id));
        Assert::assertNull($this->em->getRepository(CompanyTags::class)->find($tag3Id));
    }

    public function testBatchDeleteMixedTagsDeletesOnlyUnusedOnes(): void
    {
        $unusedTag1 = $this->fixtureHelper->createCompanyTag('Unused 1');
        $unusedTag2 = $this->fixtureHelper->createCompanyTag('Unused 2');

        $unusedTag1Id = $unusedTag1->getId();
        $unusedTag2Id = $unusedTag2->getId();

        $usedTag1 = $this->fixtureHelper->createCompanyTag('Used in Trigger');
        $usedTag1Id = $usedTag1->getId();
        Assert::assertNotNull($usedTag1Id);

        $trigger = $this->fixtureHelper->createCompanyTrigger('Test Trigger');
        $this->fixtureHelper->createCompanyTriggerEventWithTags(
            $trigger,
            addTagIds: [$usedTag1Id]
        );

        $usedTag2 = $this->fixtureHelper->createCompanyTag('Used in Campaign');
        $usedTag2Id = $usedTag2->getId();
        Assert::assertNotNull($usedTag2Id);

        $campaign = $this->fixtureHelper->createCampaign('Test Campaign');
        $this->fixtureHelper->createCampaignEventWithTags(
            $campaign,
            addTagIds: [$usedTag2Id]
        );

        $tagIds = [
            $unusedTag1Id,
            $unusedTag2Id,
            $usedTag1Id,
            $usedTag2Id,
        ];

        $this->client->request(
            Request::METHOD_POST,
            '/s/companytag/batchDelete?ids='.json_encode($tagIds)
        );

        $response = $this->client->getResponse();
        Assert::assertTrue($response->isOk() || $response->isRedirection());

        $this->em->clear();
        Assert::assertNull($this->em->getRepository(CompanyTags::class)->find($unusedTag1Id));
        Assert::assertNull($this->em->getRepository(CompanyTags::class)->find($unusedTag2Id));

        Assert::assertNotNull($this->em->getRepository(CompanyTags::class)->find($usedTag1Id));
        Assert::assertNotNull($this->em->getRepository(CompanyTags::class)->find($usedTag2Id));
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
