<?php

namespace MauticPlugin\LeuchtfeuerCompanyTagsBundle\Tests\Functional\EventLister;

use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CampaignBundle\Entity\Event;
use Mautic\CampaignBundle\Entity\Lead as CampaignMember;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\PluginBundle\Entity\Integration;
use Mautic\PluginBundle\Entity\Plugin;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Entity\CompanyTags;

class CampaignSubscriberTest extends MauticMysqlTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $this->activePlugin();
        $this->useCleanupRollback = false;
        $this->setUpSymfony($this->configParams);
    }

    public function testModifyCompanyTagsInCampaign(): void
    {
        $companyTagModel = self::$container->get('mautic.companytag.model.companytag');

        /**
         * ADD Lead
         * ADD Company
         * ADD CompanyTags.
         */
        $company = new Company();
        $company->setName('Company A');
        $company->setDateAdded(new \DateTime());
        $company->setDateModified(new \DateTime());

        $lead = new Lead();
        $lead->setEmail('test@test.com');
        $lead->setDateAdded(new \DateTime());
        $lead->setDateModified(new \DateTime());

        $companyTags1 = new CompanyTags();
        $companyTags1->setTag('CompanyTag1');
        $companyTags1->setDescription('Description tag 1');

        $companyTags2 = new CompanyTags();
        $companyTags2->setTag('CompanyTag2');
        $companyTags2->setDescription('Description tag 2');

        $companyTags3 = new CompanyTags();
        $companyTags3->setTag('CompanyTag3');
        $companyTags3->setDescription('Description tag 3');

        $companyTags4 = new CompanyTags();
        $companyTags4->setTag('CompanyTag4');
        $companyTags4->setDescription('Description tag 4');

        $this->em->persist($lead);
        $this->em->persist($company);
        $this->em->persist($companyTags1);
        $this->em->persist($companyTags2);
        $this->em->persist($companyTags3);
        $this->em->persist($companyTags4);
        $this->em->flush();

        /**
         * ADD Tags to Company
         * ADD Company to Lead.
         */
        $companyTagModel->updateCompanyTags($company, [$companyTags1, $companyTags2]);
        $lead->setCompany($company);
        $this->em->persist($lead);
        $this->em->flush();

        /**
         * ADD event to Campaign
         * ADD Modify Company Tags in Campaign.
         */
        $modifyTagsAction = new Event();
        $modifyTagsAction->setOrder(1);
        $modifyTagsAction->setName('Add tag / Remove tag');
        $modifyTagsAction->setType('companytag.changetags');
        $modifyTagsAction->setEventType('action');
        $modifyTagsAction->setProperties([
            'add_tags'    => ['CompanyTag3', 'CompanyTag4'],
            'remove_tags' => ['CompanyTag1'],
        ]);

        $campaign = new Campaign();
        $campaign->addEvents([$modifyTagsAction]);
        $campaign->setName('Campaign A');
        $modifyTagsAction->setCampaign($campaign);

        $campaignMember = new CampaignMember();
        $campaignMember->setLead($lead);
        $campaignMember->setCampaign($campaign);
        $campaignMember->setDateAdded(new \DateTime('-61 seconds'));

        $campaign->addLead(0, $campaignMember);

        $this->em->persist($modifyTagsAction);
        $this->em->persist($campaignMember);
        $this->em->persist($campaign);
        $this->em->flush();

        $campaign->setCanvasSettings(
            [
                'nodes' => [
                    [
                        'id'        => $modifyTagsAction->getId(),
                        'positionX' => '1080',
                        'positionY' => '155',
                    ],
                    [
                        'id'        => 'lists',
                        'positionX' => '1180',
                        'positionY' => '50',
                    ],
                ],
                'connections' => [
                    [
                        'sourceId' => 'lists',
                        'targetId' => $modifyTagsAction->getId(),
                        'anchors'  => [
                            [
                                'endpoint' => 'leadsource',
                                'eventId'  => 'lists',
                            ],
                            [
                                'endpoint' => 'top',
                                'eventId'  => $modifyTagsAction->getId(),
                            ],
                        ],
                    ],
                ],
            ]
        );
        $this->em->persist($campaign);
        $this->em->flush();

        $this->testSymfonyCommand('mautic:campaigns:trigger', ['-i' => $campaign->getId()]);

        $tags = $companyTagModel->getRepository()->getTagsByCompany($company);
        $this->assertCount(3, $tags);
        $included = [
            'CompanyTag2',
            'CompanyTag3',
            'CompanyTag4',
        ];

        foreach ($tags as $tag) {
            $this->assertContains($tag->getTag(), $included);
        }

        $this->assertNotContains('CompanyTag1', $included);
    }

    public function testModifyCompanyTagsWithoutTagsFromScratch(): void
    {
        $companyTagModel = self::$container->get('mautic.companytag.model.companytag');

        /**
         * ADD Lead
         * ADD Company
         * ADD CompanyTags.
         */
        $company = new Company();
        $company->setName('Company A');
        $company->setDateAdded(new \DateTime());
        $company->setDateModified(new \DateTime());

        $lead = new Lead();
        $lead->setEmail('test@test.com');
        $lead->setDateAdded(new \DateTime());
        $lead->setDateModified(new \DateTime());

        $companyTags1 = new CompanyTags();
        $companyTags1->setTag('CompanyTag1');
        $companyTags1->setDescription('Description tag 1');

        $companyTags2 = new CompanyTags();
        $companyTags2->setTag('CompanyTag2');
        $companyTags2->setDescription('Description tag 2');

        $companyTags3 = new CompanyTags();
        $companyTags3->setTag('CompanyTag3');
        $companyTags3->setDescription('Description tag 3');

        $companyTags4 = new CompanyTags();
        $companyTags4->setTag('CompanyTag4');
        $companyTags4->setDescription('Description tag 4');

        $this->em->persist($lead);
        $this->em->persist($company);
        $this->em->persist($companyTags1);
        $this->em->persist($companyTags2);
        $this->em->persist($companyTags3);
        $this->em->persist($companyTags4);
        $this->em->flush();

        /**
         * ADD Tags to Company
         * ADD Company to Lead.
         */
//        $companyTagModel->updateCompanyTags($company, [$companyTags1, $companyTags2]);
        $lead->setCompany($company);
        $this->em->persist($lead);
        $this->em->flush();

        /**
         * ADD event to Campaign
         * ADD Modify Company Tags in Campaign.
         */
        $modifyTagsAction = new Event();
        $modifyTagsAction->setOrder(1);
        $modifyTagsAction->setName('Add tag / Remove tag');
        $modifyTagsAction->setType('companytag.changetags');
        $modifyTagsAction->setEventType('action');
        $modifyTagsAction->setProperties([
            'add_tags'    => ['CompanyTag3', 'CompanyTag4'],
            'remove_tags' => ['CompanyTag1'],
        ]);

        $campaign = new Campaign();
        $campaign->addEvents([$modifyTagsAction]);
        $campaign->setName('Campaign A');
        $modifyTagsAction->setCampaign($campaign);

        $campaignMember = new CampaignMember();
        $campaignMember->setLead($lead);
        $campaignMember->setCampaign($campaign);
        $campaignMember->setDateAdded(new \DateTime('-61 seconds'));

        $campaign->addLead(0, $campaignMember);

        $this->em->persist($modifyTagsAction);
        $this->em->persist($campaignMember);
        $this->em->persist($campaign);
        $this->em->flush();

        $campaign->setCanvasSettings(
            [
                'nodes' => [
                    [
                        'id'        => $modifyTagsAction->getId(),
                        'positionX' => '1080',
                        'positionY' => '155',
                    ],
                    [
                        'id'        => 'lists',
                        'positionX' => '1180',
                        'positionY' => '50',
                    ],
                ],
                'connections' => [
                    [
                        'sourceId' => 'lists',
                        'targetId' => $modifyTagsAction->getId(),
                        'anchors'  => [
                            [
                                'endpoint' => 'leadsource',
                                'eventId'  => 'lists',
                            ],
                            [
                                'endpoint' => 'top',
                                'eventId'  => $modifyTagsAction->getId(),
                            ],
                        ],
                    ],
                ],
            ]
        );
        $this->em->persist($campaign);
        $this->em->flush();

        $this->testSymfonyCommand('mautic:campaigns:trigger', ['-i' => $campaign->getId()]);

        $tags = $companyTagModel->getRepository()->getTagsByCompany($company);
        $this->assertCount(2,$tags);
        $included = [
            'CompanyTag3',
            'CompanyTag4',
        ];

        foreach ($tags as $tag) {
            $this->assertContains($tag->getTag(), $included);
        }

        $this->assertNotContains('CompanyTag1', $included);
        $this->assertNotContains('CompanyTag2', $included);
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
