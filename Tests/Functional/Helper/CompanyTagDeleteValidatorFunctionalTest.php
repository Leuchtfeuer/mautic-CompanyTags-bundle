<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanyTagsBundle\Tests\Functional\Helper;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Helper\CompanyTagDeleteValidator;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Tests\Fixtures\FixtureHelper;
use PHPUnit\Framework\Assert;

class CompanyTagDeleteValidatorFunctionalTest extends MauticMysqlTestCase
{
    protected $useCleanupRollback = false;

    private FixtureHelper $fixtureHelper;
    private CompanyTagDeleteValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtureHelper = new FixtureHelper($this->em);
        $this->fixtureHelper->createAndEnablePlugin();
        $this->fixtureHelper->enableCompanyPointsPlugin();

        $validator = static::getContainer()->get(CompanyTagDeleteValidator::class);
        Assert::assertInstanceOf(CompanyTagDeleteValidator::class, $validator);
        $this->validator = $validator;
    }

    public function testTagCanBeDeletedWhenNotInUse(): void
    {
        // Arrange: Create a tag that is not used anywhere
        $tag = $this->fixtureHelper->createCompanyTag('Unused Tag');
        $tagId = $tag->getId();
        Assert::assertNotNull($tagId);

        // Act: Validate for deletion
        $result = $this->validator->validateForDeletion([$tagId]);

        // Assert: Tag should be deletable
        Assert::assertTrue($result->hasDeletableTags());
        Assert::assertFalse($result->hasBlockedTags());
        Assert::assertContains($tagId, $result->getDeletableIds());
        Assert::assertEmpty($result->getBlockedTags());
    }

    public function testTagCannotBeDeletedWhenUsedInCompanyPointTrigger(): void
    {
        // Arrange: Create tag used in a trigger
        $tag = $this->fixtureHelper->createCompanyTag('Trigger Tag');
        $tagId = $tag->getId();
        Assert::assertNotNull($tagId);
        $tagName = $tag->getTag();
        Assert::assertNotNull($tagName);

        $trigger = $this->fixtureHelper->createCompanyTrigger('Test Trigger');
        $this->fixtureHelper->createCompanyTriggerEventWithTags(
            $trigger,
            addTagIds: [$tagId]
        );

        // Act: Validate for deletion
        $result = $this->validator->validateForDeletion([$tagId]);

        // Assert: Tag should be blocked
        Assert::assertFalse($result->hasDeletableTags());
        Assert::assertTrue($result->hasBlockedTags());
        Assert::assertEmpty($result->getDeletableIds());

        $blockedTags = $result->getBlockedTags();
        Assert::assertArrayHasKey($tagName, $blockedTags);

        $usageInfo = $blockedTags[$tagName];
        Assert::assertCount(1, $usageInfo->getTriggers());
        Assert::assertEquals($trigger->getId(), $usageInfo->getTriggers()[0]->getId());

        // Check error message format
        $errorMessage = $result->getBlockedTagsList();
        Assert::assertStringContainsString('Trigger Tag', $errorMessage);
        Assert::assertStringContainsString('Company Point Trigger ID:', $errorMessage);
        Assert::assertStringContainsString((string) $trigger->getId(), $errorMessage);
    }

    public function testTagCannotBeDeletedWhenUsedInCampaign(): void
    {
        // Arrange: Create tag used in a campaign
        $tag = $this->fixtureHelper->createCompanyTag('Campaign Tag');
        $tagId = $tag->getId();
        Assert::assertNotNull($tagId);
        $tagName = $tag->getTag();
        Assert::assertNotNull($tagName);

        $campaign = $this->fixtureHelper->createCampaign('Test Campaign');
        $this->fixtureHelper->createCampaignEventWithTags(
            $campaign,
            addTagIds: [$tagId]
        );

        // Act: Validate for deletion
        $result = $this->validator->validateForDeletion([$tagId]);

        // Assert: Tag should be blocked
        Assert::assertFalse($result->hasDeletableTags());
        Assert::assertTrue($result->hasBlockedTags());

        $blockedTags = $result->getBlockedTags();
        Assert::assertArrayHasKey($tagName, $blockedTags);

        $usageInfo = $blockedTags[$tagName];
        Assert::assertCount(1, $usageInfo->getCampaigns());
        Assert::assertEquals($campaign->getId(), $usageInfo->getCampaigns()[0]->getId());

        // Check error message format
        $errorMessage = $result->getBlockedTagsList();
        Assert::assertStringContainsString('Campaign ID:', $errorMessage);
        Assert::assertStringContainsString((string) $campaign->getId(), $errorMessage);
    }

    public function testTagCannotBeDeletedWhenUsedInForm(): void
    {
        // Arrange: Create tag used in a form action
        $tag = $this->fixtureHelper->createCompanyTag('Form Tag');
        $tagId = $tag->getId();
        Assert::assertNotNull($tagId);
        $tagName = $tag->getTag();
        Assert::assertNotNull($tagName);

        $form = $this->fixtureHelper->createForm('Test Form');
        $this->fixtureHelper->createFormActionWithTags(
            $form,
            removeTagIds: [$tagId]
        );

        // Act: Validate for deletion
        $result = $this->validator->validateForDeletion([$tagId]);

        // Assert: Tag should be blocked
        Assert::assertFalse($result->hasDeletableTags());
        Assert::assertTrue($result->hasBlockedTags());

        $blockedTags = $result->getBlockedTags();
        Assert::assertArrayHasKey($tagName, $blockedTags);

        $usageInfo = $blockedTags[$tagName];
        Assert::assertCount(1, $usageInfo->getForms());
        $formFromUsage = $usageInfo->getForms()[0];
        Assert::assertNotNull($formFromUsage);
        Assert::assertEquals($form->getId(), $formFromUsage->getId());

        // Check error message format
        $errorMessage = $result->getBlockedTagsList();
        Assert::assertStringContainsString('Form ID:', $errorMessage);
        Assert::assertStringContainsString((string) $form->getId(), $errorMessage);
    }

    public function testTagCannotBeDeletedWhenUsedInMultipleLocations(): void
    {
        // Arrange: Create tag used in trigger, campaign, and form
        $tag = $this->fixtureHelper->createCompanyTag('Multi-Use Tag');
        $tagId = $tag->getId();
        Assert::assertNotNull($tagId);
        $tagName = $tag->getTag();
        Assert::assertNotNull($tagName);

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

        // Act: Validate for deletion
        $result = $this->validator->validateForDeletion([$tagId]);

        // Assert: Tag should be blocked with usage in all locations
        Assert::assertFalse($result->hasDeletableTags());
        Assert::assertTrue($result->hasBlockedTags());

        $blockedTags = $result->getBlockedTags();
        $usageInfo = $blockedTags[$tagName];

        Assert::assertCount(1, $usageInfo->getTriggers());
        Assert::assertCount(1, $usageInfo->getCampaigns());
        Assert::assertCount(1, $usageInfo->getForms());

        // Check error message contains all locations
        $errorMessage = $result->getBlockedTagsList();
        Assert::assertStringContainsString('Company Point Trigger ID:', $errorMessage);
        Assert::assertStringContainsString('Campaign ID:', $errorMessage);
        Assert::assertStringContainsString('Form ID:', $errorMessage);
    }

    public function testBatchValidationWithMixedTags(): void
    {
        // Arrange: Create multiple tags with different usage scenarios
        $deletableTag1 = $this->fixtureHelper->createCompanyTag('Deletable 1');
        $deletableTag1Id = $deletableTag1->getId();
        Assert::assertNotNull($deletableTag1Id);

        $deletableTag2 = $this->fixtureHelper->createCompanyTag('Deletable 2');
        $deletableTag2Id = $deletableTag2->getId();
        Assert::assertNotNull($deletableTag2Id);

        $blockedTag1 = $this->fixtureHelper->createCompanyTag('Blocked 1');
        $blockedTag1Id = $blockedTag1->getId();
        Assert::assertNotNull($blockedTag1Id);

        $trigger = $this->fixtureHelper->createCompanyTrigger('Test Trigger');
        $this->fixtureHelper->createCompanyTriggerEventWithTags(
            $trigger,
            addTagIds: [$blockedTag1Id]
        );

        $blockedTag2 = $this->fixtureHelper->createCompanyTag('Blocked 2');
        $blockedTag2Id = $blockedTag2->getId();
        Assert::assertNotNull($blockedTag2Id);

        $campaign = $this->fixtureHelper->createCampaign('Test Campaign');
        $this->fixtureHelper->createCampaignEventWithTags(
            $campaign,
            addTagIds: [$blockedTag2Id]
        );

        $tagIds = [
            $deletableTag1Id,
            $deletableTag2Id,
            $blockedTag1Id,
            $blockedTag2Id,
        ];

        // Act: Validate all tags
        $result = $this->validator->validateForDeletion($tagIds);

        // Assert: Should have both deletable and blocked tags
        Assert::assertTrue($result->hasDeletableTags());
        Assert::assertTrue($result->hasBlockedTags());

        Assert::assertCount(2, $result->getDeletableIds());
        Assert::assertContains($deletableTag1Id, $result->getDeletableIds());
        Assert::assertContains($deletableTag2Id, $result->getDeletableIds());

        Assert::assertCount(2, $result->getBlockedTags());
        Assert::assertArrayHasKey('Blocked 1', $result->getBlockedTags());
        Assert::assertArrayHasKey('Blocked 2', $result->getBlockedTags());
    }

    public function testValidationWithNonExistentTag(): void
    {
        // Arrange: Use a non-existent tag ID
        $nonExistentId = 99999;

        // Act: Validate for deletion
        $result = $this->validator->validateForDeletion([$nonExistentId]);

        // Assert: Should return empty results (tag doesn't exist)
        Assert::assertFalse($result->hasDeletableTags());
        Assert::assertFalse($result->hasBlockedTags());
        Assert::assertEmpty($result->getDeletableIds());
        Assert::assertEmpty($result->getBlockedTags());
    }

    public function testTagUsedInBothAddAndRemoveTagsArrays(): void
    {
        // Arrange: Create tag used in both add_tags and remove_tags
        $tag = $this->fixtureHelper->createCompanyTag('Dual Usage Tag');
        $tagId = $tag->getId();
        Assert::assertNotNull($tagId);

        $trigger = $this->fixtureHelper->createCompanyTrigger('Test Trigger');
        $this->fixtureHelper->createCompanyTriggerEventWithTags(
            $trigger,
            addTagIds: [$tagId],
            removeTagIds: [$tagId]
        );

        // Act: Validate for deletion
        $result = $this->validator->validateForDeletion([$tagId]);

        // Assert: Tag should be blocked (found in add_tags first)
        Assert::assertFalse($result->hasDeletableTags());
        Assert::assertTrue($result->hasBlockedTags());
    }

    public function testMultipleTriggersUsingSameTag(): void
    {
        // Arrange: Create tag used in multiple triggers
        $tag = $this->fixtureHelper->createCompanyTag('Popular Tag');
        $tagId = $tag->getId();
        Assert::assertNotNull($tagId);
        $tagName = $tag->getTag();
        Assert::assertNotNull($tagName);

        $trigger1 = $this->fixtureHelper->createCompanyTrigger('Trigger 1');
        $this->fixtureHelper->createCompanyTriggerEventWithTags(
            $trigger1,
            addTagIds: [$tagId]
        );

        $trigger2 = $this->fixtureHelper->createCompanyTrigger('Trigger 2');
        $this->fixtureHelper->createCompanyTriggerEventWithTags(
            $trigger2,
            removeTagIds: [$tagId]
        );

        // Act: Validate for deletion
        $result = $this->validator->validateForDeletion([$tagId]);

        // Assert: Tag should be blocked with usage in both triggers
        Assert::assertTrue($result->hasBlockedTags());

        $blockedTags = $result->getBlockedTags();
        $usageInfo = $blockedTags[$tagName];

        Assert::assertCount(2, $usageInfo->getTriggers());

        // Check error message contains both trigger IDs
        $errorMessage = $result->getBlockedTagsList();
        Assert::assertStringContainsString((string) $trigger1->getId(), $errorMessage);
        Assert::assertStringContainsString((string) $trigger2->getId(), $errorMessage);
    }

    public function testEmptyTagIdsArray(): void
    {
        // Act: Validate empty array
        $result = $this->validator->validateForDeletion([]);

        // Assert: Should return empty results
        Assert::assertFalse($result->hasDeletableTags());
        Assert::assertFalse($result->hasBlockedTags());
        Assert::assertEmpty($result->getDeletableIds());
        Assert::assertEmpty($result->getBlockedTags());
    }
}
