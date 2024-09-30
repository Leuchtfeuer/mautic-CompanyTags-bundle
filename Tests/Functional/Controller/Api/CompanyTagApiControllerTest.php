<?php

namespace MauticPlugin\LeuchtfeuerCompanyTagsBundle\Tests\Functional\Controller\Api;

use Mautic\LeadBundle\Entity\Company;
use Mautic\PluginBundle\Entity\Integration;
use Mautic\PluginBundle\Entity\Plugin;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests\MauticMysqlTestCase;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Entity\CompanyTags;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Model\CompanyTagModel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CompanyTagApiControllerTest extends MauticMysqlTestCase
{
    public const TAG_ONE = 'CompanyTag1';
    public const TAG_TWO = 'CompanyTag2';

    public const TAG_ONE_DESC = 'Description tag 1';
    public const TAG_TWO_DESC = 'Description tag 2';

    /**
     * @var array<CompanyTags>
     */
    private array $companies = [];

    public function setUp(): void
    {
        parent::setUp();
        $this->activePlugin();
        $this->createStructure();
    }

    private function createStructure(): void
    {
        $tag1            = $this->addTag(self::TAG_ONE, self::TAG_ONE_DESC);
        $tag2            = $this->addTag(self::TAG_TWO, self::TAG_TWO_DESC);
        $this->companies = array_merge($this->addCompany([$tag1], 'Test Company 1'), $this->addCompany([$tag1, $tag2], 'Test Company 2'));
    }

    private function activePlugin(bool $isPublished = true): void
    {
        $this->client->request(Request::METHOD_GET, '/s/plugins/reload');
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

    private function addTag(string $name, string $description): CompanyTags
    {
        $tag = new CompanyTags();
        $tag->setTag($name);
        $tag->setDescription($description);
        $this->em->persist($tag);
        $this->em->flush();

        return $this->em->getRepository(CompanyTags::class)->find($tag->getId());
    }

    /**
     * @param array<CompanyTags> $tags
     *
     * @return array<CompanyTags>|bool
     */
    private function addCompany(array $tags=[], string $nameCompany = 'Test Company'): array|bool
    {
        if (empty($tags)) {
            return false;
        }
        $company = new Company();
        $company->setName($nameCompany);
        $this->em->persist($company);
        $this->em->flush();

        $companyTag = static::getContainer()->get(CompanyTagModel::class);
        $companyTag->updateCompanyTags($company, $tags);

        return $companyTag->getTagsByCompany($company);
    }

    public function testGetCompanyTagsApi(): void
    {
        $this->client->request(Request::METHOD_GET, '/api/companytags/'.$this->companies[0]->getId());
        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(2, $response['companytag']['companies']);

        $this->assertCount(1, $response);
        $this->assertSame(self::TAG_ONE, $response['companytag']['tag']);
        $this->assertSame(self::TAG_ONE_DESC, $response['companytag']['description']);
    }

    public function testNewCompanyTag(): void
    {
        $data = [
            'tag'         => 'Test Company Tag',
            'description' => 'Test Company Tag Description',
        ];
        $this->client->request(Request::METHOD_POST, '/api/companytags/new', $data);
        $this->assertSame(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode());
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame($data['tag'], $response['companytag']['tag']);
        $this->assertSame($data['description'], $response['companytag']['description']);
    }

    public function testNewCompaniesBatchSuccess(): void
    {
        $data = [
            [
                'tag'         => 'Test Company Tag 3',
                'description' => 'Test Company Tag Description 3',
            ],
            [
                'tag'         => 'Test Company Tag 4',
                'description' => 'Test Company Tag Description 4',
            ],
        ];
        $this->client->request('POST', '/api/companytags/batch/new', $data);
        $this->assertSame(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode());
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(2, $response);
        $this->assertSame($data[0]['tag'], $response['companytags'][0]['tag']);
        $this->assertSame($data[0]['description'], $response['companytags'][0]['description']);
        $this->assertSame($data[1]['tag'], $response['companytags'][1]['tag']);
        $this->assertSame($data[1]['description'], $response['companytags'][1]['description']);
        $this->assertSame(Response::HTTP_CREATED, $response['statusCodes'][0]);
        $this->assertSame(Response::HTTP_CREATED, $response['statusCodes'][1]);
    }

    public function testNewCompaniesBatchFail(): void
    {
        $data = [
            [
                'tag'         => 'Test Company Tag 5',
                'description' => 'Test Company Tag Description 5',
            ],
            [
                'description' => 'Test Company Tag Description 6',
            ],
        ];
        $this->client->request(Request::METHOD_POST, '/api/companytags/batch/new', $data);
        $this->assertSame(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode());
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertNotEmpty($response['errors']);
        $this->assertCount(3, $response);
        $this->assertSame($data[0]['tag'], $response['companytags'][0]['tag']);
        $this->assertSame($data[0]['description'], $response['companytags'][0]['description']);
        $this->assertArrayNotHasKey(1, $response['companytags']);
        $this->assertSame(Response::HTTP_BAD_REQUEST, $response['errors'][1]['code']);
        $this->assertSame(Response::HTTP_CREATED, $response['statusCodes'][0]);
        $this->assertSame(Response::HTTP_BAD_REQUEST, $response['statusCodes'][1]);
    }

    public function testEditCompanyTag(): void
    {
        $data = [
            'tag'         => 'Test Company Tag UPDATED',
            'description' => 'Test Company Tag Description UPDATED',
        ];
        $oldCompanyTag = $this->companies[0];
        $this->client->request(Request::METHOD_PATCH, '/api/companytags/'.$oldCompanyTag->getId().'/edit', $data);
        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame($data['tag'], $response['companytag']['tag']);
        $this->assertSame($data['description'], $response['companytag']['description']);
    }

    public function testEditCompanyTagBatchPATCHFail(): void
    {
        $lastCompanyTag = $this->em->getRepository(CompanyTags::class)->findBy([], ['id' => 'DESC'], 1)[0];
        $newId          = $lastCompanyTag->getId() + 1;
        $data           = [
            [
                'id'          => $this->companies[0]->getId(),
                'tag'         => 'Test Company Tag UPDATED',
                'description' => 'Test Company Tag Description UPDATED',
            ],
            [
                'id'          => $newId,
                'tag'         => 'Test Company Tag UPDATED',
                'description' => 'Test Company Tag Description UPDATED',
            ],
        ];

        $this->client->request(Request::METHOD_PATCH, '/api/companytags/batch/edit', $data);
        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(3, $response);
        $this->assertArrayHasKey('companytags', $response);
        $this->assertArrayHasKey('errors', $response);
        $this->assertSame($data[0]['tag'], $response['companytags'][0]['tag']);
        $this->assertSame($data[0]['description'], $response['companytags'][0]['description']);
        $this->assertSame(Response::HTTP_OK, $response['statusCodes'][0]);
        $this->assertSame(Response::HTTP_NOT_FOUND, $response['statusCodes'][1]);
        $this->assertArrayHasKey('1', $response['errors']);
    }

    public function testEditCompanyTagBatchPUTFail(): void
    {
        $lastCompanyTag = $this->em->getRepository(CompanyTags::class)->findBy([], ['id' => 'DESC'], 1)[0];
        $newId          = $lastCompanyTag->getId() + 1;
        $data           = [
            [
                'id'          => $this->companies[0]->getId(),
                'tag'         => 'Test Company Tag 0 UPDATED FIRST',
                'description' => 'Test Company Tag 0 Description UPDATED FIRST',
            ],
            [
                'id'          => null,
                'tag'         => 'Test Company Tag 1 UPDATED PUT',
                'description' => 'Test Company Tag 1 Description UPDATED PUT',
            ],
            [
                'id'          => $newId,
                'tag'         => 'Test Company Tag 2 UPDATED PUT',
                'description' => 'Test Company Tag 2 Description UPDATED PUT',
            ],
            [
                'tag'         => 'Test Company Tag 3 UPDATED PUT',
                'description' => 'Test Company Tag 3 Description UPDATED PUT',
            ],
        ];
        $this->client->request(Request::METHOD_PUT, '/api/companytags/batch/edit', $data);
        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(3, $response);
        $this->assertCount(count($response['companytags']), $data);
        $this->assertArrayHasKey('companytags', $response);
        $this->assertArrayHasKey('errors', $response);
        $this->assertSame(Response::HTTP_OK, $response['statusCodes'][0]);
        $this->assertSame(Response::HTTP_CREATED, $response['statusCodes'][1]);
        $this->assertSame(Response::HTTP_CREATED, $response['statusCodes'][2]);
        $this->assertSame(Response::HTTP_BAD_REQUEST, $response['statusCodes'][3]);
        $this->assertSame($data[0]['tag'], $response['companytags'][0]['tag']);
        $this->assertSame($data[0]['id'], $response['companytags'][0]['id']);
        $this->assertSame($data[1]['tag'], $response['companytags'][1]['tag']);
        $this->assertSame(Response::HTTP_CREATED, $response['statusCodes'][1]);
    }

    public function testDeleteBatchOneSuccessOneFail(): void
    {
        $newCompanyTag1 = $this->addTag('Test Company Tag 7', 'Test Company Tag Description 7');
        $newCompanyTag2 = $this->addTag('Test Company Tag 8', 'Test Company Tag Description 8');

        $newCompanyTag1Id = $newCompanyTag1->getId();
        $this->client->request(Request::METHOD_DELETE, "/api/companytags/batch/delete?ids={$newCompanyTag1->getId()},{$newCompanyTag1->getId()}");
        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(2, $response['companytags']);
        $checkTag1 = $this->em->getRepository(CompanyTags::class)->find($newCompanyTag1Id);
        $this->assertNull($checkTag1);
        $this->assertSame($newCompanyTag1->getTag(), $response['companytags'][0]['tag']);
        $this->assertArrayHasKey('errors', $response);
        $this->assertSame(Response::HTTP_NOT_FOUND, $response['errors'][1]['code']);
    }

    public function testDeleteBatchSuccess(): void
    {
        $newCompanyTag1   = $this->addTag('Test Company Tag 9', 'Test Company Tag Description 9');
        $newCompanyTag2   = $this->addTag('Test Company Tag 10', 'Test Company Tag Description 10');
        $newCompanyTag1Id = $newCompanyTag1->getId();
        $newCompanyTag2Id = $newCompanyTag2->getId();
        $this->client->request(Request::METHOD_DELETE, "/api/companytags/batch/delete?ids={$newCompanyTag1->getId()},{$newCompanyTag2->getId()}");
        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(2, $response['companytags']);
        $checkTag1 = $this->em->getRepository(CompanyTags::class)->find($newCompanyTag1Id);
        $checkTag2 = $this->em->getRepository(CompanyTags::class)->find($newCompanyTag2Id);
        $this->assertNull($checkTag1);
        $this->assertNull($checkTag2);
        $this->assertSame($newCompanyTag1->getTag(), $response['companytags'][0]['tag']);
        $this->assertSame($newCompanyTag2->getTag(), $response['companytags'][1]['tag']);
    }

    public function testDeleteCompanyTagSuccess(): void
    {
        $oldCompanyTag = $this->em->getRepository(CompanyTags::class)->find($this->companies[0]->getId());
        $oldId         = $oldCompanyTag->getId();
        $this->client->request(Request::METHOD_DELETE, '/api/companytags/'.$oldId.'/delete');
        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        $response        = json_decode($this->client->getResponse()->getContent(), true);
        $checkCompanyTag = $this->em->getRepository(CompanyTags::class)->find($oldId);
        $this->assertNull($checkCompanyTag);
        $this->assertNull($response['companytag']['id']);
    }
}
