<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanyTagsBundle\Tests\Fixtures;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CampaignBundle\Entity\Event as CampaignEvent;
use Mautic\FormBundle\Entity\Action;
use Mautic\FormBundle\Entity\Form;
use Mautic\LeadBundle\Entity\Company;
use Mautic\PluginBundle\Entity\Integration;
use Mautic\PluginBundle\Entity\Plugin;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTrigger;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTriggerEvent;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Entity\CompanyTags;

final class FixtureHelper
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function createAndEnablePlugin(): void
    {
        $plugin = $this->em->getRepository(Plugin::class)->findOneBy(['bundle' => 'LeuchtfeuerCompanyTagsBundle']);
        if (!$plugin) {
            $plugin = new Plugin();
            $plugin->setName('Company Tags by Leuchtfeuer');
            $plugin->setBundle('LeuchtfeuerCompanyTagsBundle');
            $this->em->persist($plugin);
            $this->em->flush();
        }

        $integration = $this->em->getRepository(Integration::class)->findOneBy(['name' => 'leuchtfeuercompanytags']);
        if (!$integration) {
            $integration = new Integration();
            $integration->setPlugin($plugin);
            $integration->setIsPublished(true);
            $integration->setName('leuchtfeuercompanytags');
            $this->em->persist($integration);
            $this->em->flush();
        } else {
            $integration->setIsPublished(true);
            $this->em->flush();
        }
    }

    public function enableCompanyPointsPlugin(): void
    {
        $plugin = $this->em->getRepository(Plugin::class)->findOneBy(['bundle' => 'LeuchtfeuerCompanyPointsBundle']);
        if (!$plugin) {
            $plugin = new Plugin();
            $plugin->setName('Company Points by Leuchtfeuer');
            $plugin->setBundle('LeuchtfeuerCompanyPointsBundle');
            $this->em->persist($plugin);
            $this->em->flush();
        }

        $integration = $this->em->getRepository(Integration::class)->findOneBy(['name' => 'LeuchtfeuerCompanyPoints']);
        if (!$integration) {
            $integration = new Integration();
            $integration->setPlugin($plugin);
            $integration->setIsPublished(true);
            $integration->setName('LeuchtfeuerCompanyPoints');
            $this->em->persist($integration);
            $this->em->flush();
        } else {
            $integration->setIsPublished(true);
            $this->em->flush();
        }
    }

    public function createCompanyTag(string $name): CompanyTags
    {
        $tag = new CompanyTags();
        $tag->setTag($name);
        $this->em->persist($tag);
        $this->em->flush();

        return $tag;
    }

    public function createCompanyTrigger(string $name, int $points = 10): CompanyTrigger
    {
        $trigger = new CompanyTrigger();
        $trigger->setName($name);
        $trigger->setPoints($points);
        $trigger->setIsPublished(true);
        $this->em->persist($trigger);
        $this->em->flush();

        return $trigger;
    }

    /**
     * @param array<int> $addTagIds
     * @param array<int> $removeTagIds
     */
    public function createCompanyTriggerEventWithTags(
        CompanyTrigger $trigger,
        array $addTagIds = [],
        array $removeTagIds = [],
    ): CompanyTriggerEvent {
        $event = new CompanyTriggerEvent();
        $event->setTrigger($trigger);
        $event->setType('companytags.updatetags');
        $event->setName('Update Company Tags');
        $event->setProperties([
            'add_tags'    => $addTagIds,
            'remove_tags' => $removeTagIds,
        ]);
        $this->em->persist($event);
        $this->em->flush();

        return $event;
    }

    public function createCampaign(string $name): Campaign
    {
        $campaign = new Campaign();
        $campaign->setName($name);
        $campaign->setIsPublished(true);
        $this->em->persist($campaign);
        $this->em->flush();

        return $campaign;
    }

    /**
     * @param array<int> $addTagIds
     * @param array<int> $removeTagIds
     */
    public function createCampaignEventWithTags(
        Campaign $campaign,
        array $addTagIds = [],
        array $removeTagIds = [],
    ): CampaignEvent {
        $event = new CampaignEvent();
        $event->setCampaign($campaign);
        $event->setType('companytag.changetags');
        $event->setName('Change Company Tags');
        $event->setEventType('action');
        $event->setProperties([
            'add_tags'    => $addTagIds,
            'remove_tags' => $removeTagIds,
        ]);
        $this->em->persist($event);
        $this->em->flush();

        return $event;
    }

    public function createForm(string $name): Form
    {
        $form = new Form();
        $form->setName($name);
        $form->setAlias(mb_strtolower(str_replace(' ', '', $name)));
        $form->setFormType('standalone');
        $form->setIsPublished(true);
        $this->em->persist($form);
        $this->em->flush();

        return $form;
    }

    /**
     * @param array<int> $addTagIds
     * @param array<int> $removeTagIds
     */
    public function createFormActionWithTags(
        Form $form,
        array $addTagIds = [],
        array $removeTagIds = [],
    ): Action {
        $action = new Action();
        $action->setForm($form);
        $action->setType('companytag.changetags');
        $action->setName('Change Company Tags');
        $action->setOrder(1);
        $action->setProperties([
            'add_tags'    => $addTagIds,
            'remove_tags' => $removeTagIds,
        ]);
        $this->em->persist($action);
        $this->em->flush();

        return $action;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createCompany(string $name, array $data = []): Company
    {
        $company = new Company();
        $company->setName($name);

        foreach ($data as $field => $value) {
            $method = 'set'.ucfirst($field);
            if (method_exists($company, $method)) {
                $company->$method($value);
            }
        }

        $this->em->persist($company);
        $this->em->flush();

        return $company;
    }
}
