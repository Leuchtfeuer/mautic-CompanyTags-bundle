<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanyTagsBundle\Tests\Functional\EventLister;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\FormBundle\Entity\Form;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\CompanyLead;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\PluginBundle\Entity\Integration;
use Mautic\PluginBundle\Entity\IntegrationRepository;
use Mautic\PluginBundle\Entity\Plugin;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Entity\CompanyTags;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Model\CompanyTagModel;
use Symfony\Component\HttpFoundation\Request;

class FormSubscriberFunctionalTest extends MauticMysqlTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $this->activePlugin();
        $this->useCleanupRollback = false;
        $this->setUpSymfony($this->configParams);
    }

    public function testModifyCompanyTagsInFormSubmission(): void
    {
        $companyTagModel = self::$container->get('mautic.companytag.model.companytag');
        $this->assertInstanceOf(CompanyTagModel::class, $companyTagModel);

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
         * ADD Company to Lead via CompanyLead.
         */
        $companyTagModel->updateCompanyTags($company, [$companyTags1, $companyTags2]);

        $companyLead = new CompanyLead();
        $companyLead->setCompany($company);
        $companyLead->setLead($lead);
        $companyLead->setDateAdded(new \DateTime());
        $companyLead->setPrimary(true);
        $this->em->persist($companyLead);
        $lead->setCompany($company->getName());
        $this->em->persist($lead);
        $this->em->flush();

        /**
         * CREATE Form with companytag.changetags action.
         */
        $form = $this->createFormViaApi('Test Form', [
            [
                'name'       => 'Modify Company Tags',
                'type'       => 'companytag.changetags',
                'order'      => 1,
                'properties' => [
                    'add_tags'    => [$companyTags3->getId(), $companyTags4->getId()],
                    'remove_tags' => [$companyTags1->getId()],
                ],
            ],
        ]);

        /**
         * SUBMIT Form.
         */
        $crawler     = $this->client->request(Request::METHOD_GET, "/form/{$form->getId()}");
        $formCrawler = $crawler->filter('form');
        $this->assertGreaterThan(0, $formCrawler->count(), 'Form not found on page');

        $formElement = $formCrawler->first()->form();
        $formElement->setValues([
            'mauticform[email]' => 'test@test.com',
        ]);

        $this->client->submit($formElement);
        $response = $this->client->getResponse();
        $this->assertTrue($response->isOk() || $response->isRedirection(), 'Form submission failed: '.$response->getContent().' Status: '.$response->getStatusCode());

        /**
         * VERIFY Tags were modified.
         */
        $this->em->clear();
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

        $tagNames = array_map(fn ($tag) => $tag->getTag(), $tags);
        $this->assertNotContains('CompanyTag1', $tagNames);
    }

    public function testModifyCompanyTagsWithoutTagsFromScratch(): void
    {
        $companyTagModel = self::$container->get('mautic.companytag.model.companytag');
        $this->assertInstanceOf(CompanyTagModel::class, $companyTagModel);

        /**
         * ADD Lead
         * ADD Company
         * ADD CompanyTags.
         */
        $company = new Company();
        $company->setName('Company B');
        $company->setDateAdded(new \DateTime());
        $company->setDateModified(new \DateTime());

        $lead = new Lead();
        $lead->setEmail('test2@test.com');
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
         * ADD Company to Lead via CompanyLead (no tags on company yet).
         */
        $companyLead = new CompanyLead();
        $companyLead->setCompany($company);
        $companyLead->setLead($lead);
        $companyLead->setDateAdded(new \DateTime());
        $companyLead->setPrimary(true);
        $this->em->persist($companyLead);
        $lead->setCompany($company->getName());
        $this->em->persist($lead);
        $this->em->flush();

        /**
         * CREATE Form with companytag.changetags action.
         */
        $form = $this->createFormViaApi('Test Form 2', [
            [
                'name'       => 'Modify Company Tags',
                'type'       => 'companytag.changetags',
                'order'      => 1,
                'properties' => [
                    'add_tags'    => [$companyTags3->getId(), $companyTags4->getId()],
                    'remove_tags' => [$companyTags1->getId()],
                ],
            ],
        ]);

        /**
         * SUBMIT Form.
         */
        $crawler     = $this->client->request(Request::METHOD_GET, "/form/{$form->getId()}");
        $formCrawler = $crawler->filter('form');
        $this->assertGreaterThan(0, $formCrawler->count(), 'Form not found on page');

        $formElement = $formCrawler->first()->form();
        $formElement->setValues([
            'mauticform[email]' => 'test2@test.com',
        ]);

        $this->client->submit($formElement);
        $response = $this->client->getResponse();
        $this->assertTrue($response->isOk() || $response->isRedirection(), 'Form submission failed: '.$response->getContent().' Status: '.$response->getStatusCode());

        /**
         * VERIFY Tags were added (company had no tags before).
         */
        $this->em->clear();
        $tags = $companyTagModel->getRepository()->getTagsByCompany($company);
        $this->assertCount(2, $tags);
        $included = [
            'CompanyTag3',
            'CompanyTag4',
        ];

        foreach ($tags as $tag) {
            $this->assertContains($tag->getTag(), $included);
        }

        $tagNames = array_map(fn ($tag) => $tag->getTag(), $tags);
        $this->assertNotContains('CompanyTag1', $tagNames);
        $this->assertNotContains('CompanyTag2', $tagNames);
    }

    public function testFormActionDoesNotExecuteWhenPluginIsDisabled(): void
    {
        $companyTagModel = self::$container->get('mautic.companytag.model.companytag');
        $this->assertInstanceOf(CompanyTagModel::class, $companyTagModel);

        /**
         * DISABLE Plugin.
         */
        $this->activePlugin(false);

        /**
         * ADD Lead, Company, and CompanyTags.
         */
        $company = new Company();
        $company->setName('Company C');
        $company->setDateAdded(new \DateTime());
        $company->setDateModified(new \DateTime());

        $lead = new Lead();
        $lead->setEmail('test3@test.com');
        $lead->setDateAdded(new \DateTime());
        $lead->setDateModified(new \DateTime());

        $companyTags1 = new CompanyTags();
        $companyTags1->setTag('CompanyTag1');
        $companyTags1->setDescription('Description tag 1');

        $this->em->persist($lead);
        $this->em->persist($company);
        $this->em->persist($companyTags1);
        $this->em->flush();

        $companyLead = new CompanyLead();
        $companyLead->setCompany($company);
        $companyLead->setLead($lead);
        $companyLead->setDateAdded(new \DateTime());
        $companyLead->setPrimary(true);
        $this->em->persist($companyLead);
        $lead->setCompany($company->getName());
        $this->em->persist($lead);
        $this->em->flush();

        /**
         * CREATE Form with companytag.changetags action.
         */
        $form = $this->createFormViaApi('Test Form 3', [
            [
                'name'       => 'Modify Company Tags',
                'type'       => 'companytag.changetags',
                'order'      => 1,
                'properties' => [
                    'add_tags'    => [$companyTags1->getId()],
                    'remove_tags' => [],
                ],
            ],
        ]);

        /**
         * SUBMIT Form.
         */
        $crawler     = $this->client->request(Request::METHOD_GET, "/form/{$form->getId()}");
        $formCrawler = $crawler->filter('form');
        $this->assertGreaterThan(0, $formCrawler->count(), 'Form not found on page');

        $formElement = $formCrawler->first()->form();
        $formElement->setValues([
            'mauticform[email]' => 'test3@test.com',
        ]);

        $this->client->submit($formElement);
        $response = $this->client->getResponse();
        $this->assertTrue($response->isOk() || $response->isRedirection(), 'Form submission failed: '.$response->getContent().' Status: '.$response->getStatusCode());

        /**
         * VERIFY Tags were NOT modified (plugin is disabled).
         */
        $this->em->clear();
        $tags = $companyTagModel->getRepository()->getTagsByCompany($company);
        $this->assertCount(0, $tags);
    }

    public function testFormActionDoesNotExecuteWhenLeadHasNoCompany(): void
    {
        $companyTagModel = self::$container->get('mautic.companytag.model.companytag');
        $this->assertInstanceOf(CompanyTagModel::class, $companyTagModel);

        /**
         * ADD Lead without Company
         * ADD CompanyTags.
         */
        $lead = new Lead();
        $lead->setEmail('test4@test.com');
        $lead->setDateAdded(new \DateTime());
        $lead->setDateModified(new \DateTime());

        $companyTags1 = new CompanyTags();
        $companyTags1->setTag('CompanyTag1');
        $companyTags1->setDescription('Description tag 1');

        $this->em->persist($lead);
        $this->em->persist($companyTags1);
        $this->em->flush();

        /**
         * CREATE Form with companytag.changetags action.
         */
        $form = $this->createFormViaApi('Test Form 4', [
            [
                'name'       => 'Modify Company Tags',
                'type'       => 'companytag.changetags',
                'order'      => 1,
                'properties' => [
                    'add_tags'    => [$companyTags1->getId()],
                    'remove_tags' => [],
                ],
            ],
        ]);

        /**
         * SUBMIT Form.
         */
        $crawler     = $this->client->request(Request::METHOD_GET, "/form/{$form->getId()}");
        $formCrawler = $crawler->filter('form');
        $this->assertGreaterThan(0, $formCrawler->count(), 'Form not found on page');

        $formElement = $formCrawler->first()->form();
        $formElement->setValues([
            'mauticform[email]' => 'test4@test.com',
        ]);

        $this->client->submit($formElement);
        $response = $this->client->getResponse();
        $this->assertTrue($response->isOk() || $response->isRedirection(), 'Form submission failed: '.$response->getContent().' Status: '.$response->getStatusCode());

        /**
         * VERIFY No error occurred (action should silently return when no company).
         */
        $this->assertTrue($this->client->getResponse()->isOk());
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
        $integrationRepository = $this->em->getRepository(Integration::class);
        $this->assertInstanceOf(IntegrationRepository::class, $integrationRepository);
        $integrationRepository->saveEntity($integration);
        $this->em->persist($integration);
        $this->em->flush();
    }

    /**
     * Create form via API (useful when you need fields and actions).
     * Uses default fields if none provided.
     *
     * @param array<int, array<string,mixed>> $actions
     */
    private function createFormViaApi(string $name, array $actions = []): Form
    {
        $defaultFields = [
            [
                'label'        => 'Email',
                'type'         => 'email',
                'alias'        => 'email',
                'leadField'    => 'email',
                'mappedField'  => 'email',
                'mappedObject' => 'contact',
            ],
            [
                'label' => 'Submit',
                'type'  => 'button',
            ],
        ];

        $formPayload = [
            'name'        => $name,
            'alias'       => mb_strtolower(str_replace(' ', '', $name)),
            'formType'    => 'standalone',
            'isPublished' => true,
            'fields'      => $defaultFields,
            'actions'     => $actions,
            'postAction'  => 'return',
        ];

        $this->client->request(Request::METHOD_POST, '/api/forms/new', $formPayload);
        $responseContent = $this->client->getResponse()->getContent();
        if (false === $responseContent) {
            throw new \RuntimeException('Failed to get response content from form creation API');
        }

        $response = json_decode($responseContent, true);
        if (!is_array($response) || !isset($response['form']['id'])) {
            throw new \RuntimeException('Invalid response from form creation API: '.$responseContent);
        }

        $formId = $response['form']['id'];
        $form   = $this->em->getRepository(Form::class)->find($formId);
        if (null === $form) {
            throw new \RuntimeException('Form with ID '.$formId.' was not found after creation');
        }

        return $form;
    }
}
