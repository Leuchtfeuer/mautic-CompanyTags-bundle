<?php

namespace MauticPlugin\LeuchtfeuerCompanyTagsBundle\Tests\Functional\Permissions;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\PluginBundle\Entity\Integration;
use Mautic\PluginBundle\Entity\Plugin;
use Mautic\UserBundle\Entity\Role;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Entity\CompanyTags;

class CompanyTagPermissionsTest extends MauticMysqlTestCase
{
    public function setUp(): void
    {
        parent::setUp(); // TODO: Change the autogenerated stub
        $this->activePlugin();
        $this->useCleanupRollback = false;
        $this->setUpSymfony($this->configParams);
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

    public function testCheckPermissionIsEnable()
    {
        $this->client->request('GET', '/s/roles/new');
        $this->assertResponseStatusCodeSame(200);
        $this->assertStringContainsString('Company Tags - User has access to', $this->client->getResponse()->getContent());
    }

    public function testCreateNewRoleWithCompanyTagPermission()
    {
        $this->registerNewRole('Test Role Company Tag', 'Test Role Description Company Tag', ['viewown', 'viewother']);
        $this->assertStringContainsString('Roles - Edit Test Role Company Tag', $this->client->getResponse()->getContent());
    }

    public function testCreateNewUserWithRoleCompanyTag()
    {
        $role = $this->registerNewRole('Test Role Company Tag', 'Test Role Description Company Tag', ['viewown', 'viewother']);
        $this->client->request('GET', '/s/users/new');
        $this->assertResponseStatusCodeSame(200);
        $this->client->submitForm('Save', [
            'user[firstName]'               => 'Test',
            'user[lastName]'                => 'User',
            'user[email]'                   => 'test@test.com',
            'user[username]'                => 'testuser',
            'user[isPublished]'             => true,
            'user[plainPassword][password]' => '123User***321',
            'user[plainPassword][confirm]'  => '123User***321',
            'user[role]'                    => $role->getId(),
        ]);
        $this->loginUser('testuser');
        $this->client->setServerParameter('PHP_AUTH_USER', 'testuser');
        $this->client->setServerParameter('PHP_AUTH_PW', 'mautic');
        $this->client->request('GET', '/s/dashboard');
        $this->assertResponseStatusCodeSame(200);
        $this->client->request('GET', '/s/companytag');
        $this->assertResponseStatusCodeSame(200);
    }

    public function testCreateNewUserWithJustRoleCompanyTagAccessingDeniedPages()
    {
        $role = $this->registerNewRole('Test Role Company Tag', 'Test Role Description Company Tag', ['viewown', 'viewother']);
        $this->registerNewUser('Test', 'User1', 'test1@test.com', 'testuser1', '123User***321', $role);
        $this->loginUser('testuser1');
        $this->client->setServerParameter('PHP_AUTH_USER', 'testuser1');
        $this->client->setServerParameter('PHP_AUTH_PW', 'mautic');
        $this->client->request('GET', '/s/companies');
        $this->assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/s/contacts');
        $this->assertResponseStatusCodeSame(403);
    }

    private function registerNewUser($firstName='', $lastName='', $email='', $username='', $password='', $role=null)
    {
        $this->client->request('GET', '/s/users/new');
        $this->assertResponseStatusCodeSame(200);
        $this->client->submitForm('Save', [
            'user[firstName]'               => $firstName,
            'user[lastName]'                => $lastName,
            'user[email]'                   => $email,
            'user[username]'                => $username,
            'user[isPublished]'             => true,
            'user[plainPassword][password]' => $password,
            'user[plainPassword][confirm]'  => $password,
            'user[role]'                    => $role->getId(),
        ]);
    }

    private function registerNewRole($name='', $description='', $permissions=[])
    {
        $this->client->request('GET', '/s/roles/new');
        $this->assertResponseStatusCodeSame(200);
        $this->client->submitForm('Save', [
            'role[name]'                                => $name,
            'role[description]'                         => $description,
            'role[permissions][companytag:companytags]' => $permissions,
        ]);
        $this->assertResponseStatusCodeSame(200);

        return $this->em->getRepository(Role::class)->findOneBy(['name' => $name]);
    }

    public function testNewUserWithRoleAccesingViewCompanyTagsPage()
    {
        $companyTags1 = new CompanyTags();
        $companyTags1->setTag('CompanyTag1');
        $companyTags1->setDescription('Description tag 1');
        $this->em->persist($companyTags1);
        $this->em->flush();
        $role = $this->registerNewRole('Test Role Company Tag2', 'Test Role Description Company Tag', ['viewown', 'viewother']);
        $this->registerNewUser('Test', 'User2', 'test2@test.com', 'testuser2', '123User***321', $role);
        $this->loginUser('testuser2');
        $this->client->setServerParameter('PHP_AUTH_USER', 'testuser2');
        $this->client->setServerParameter('PHP_AUTH_PW', 'mautic');
        $this->client->request('GET', '/s/companytag');
        $this->assertResponseStatusCodeSame(200);
        $this->client->request('GET', '/s/companytag/view/'.$companyTags1->getId());
        $this->assertResponseStatusCodeSame(200);
        $this->assertStringContainsString('CompanyTag1', $this->client->getResponse()->getContent());
    }
}