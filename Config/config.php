<?php

return [
    'name'        => 'Company Tags by Leuchtfeuer',
    'description' => 'Provide a 2nd type of Tags which can be applied to Companies',
    'version'     => '1.1.1',
    'author'      => 'Leuchtfeuer Digital Marketing GmbH',
    'services'    => [
        'integrations' => [
            'mautic.integration.leuchtfeuercompanytags' => [
                'class' => MauticPlugin\LeuchtfeuerCompanyTagsBundle\Integration\LeuchtfeuerCompanyTagsIntegration::class,
                'tags'  => [
                    'mautic.integration',
                    'mautic.basic_integration',
                ],
            ],
            'mautic.integration.leuchtfeuercompanytags.configuration' => [
                'class' => MauticPlugin\LeuchtfeuerCompanyTagsBundle\Integration\Support\ConfigSupport::class,
                'tags'  => [
                    'mautic.config_integration',
                ],
            ],
            'mautic.integration.leuchtfeuercompanytags.config' => [
                'class'     => MauticPlugin\LeuchtfeuerCompanyTagsBundle\Integration\Config::class,
                'arguments' => [
                    'mautic.integrations.helper',
                ],
                'tags'  => [
                    'mautic.integrations.helper',
                ],
            ],
        ],
    ],
    'menu'        => [
        'main' => [
            'mautic.companytag.menu' => [
                'parent'    => 'mautic.companies.menu.index',
                'route'     => 'mautic_companytag_index',
                'access'    => 'companytag:companytags:view',
                'priority'  => 20,
                'checks'    => [
                    'integration' => [
                        'LeuchtfeuerCompanyTags' => [
                            'enabled' => true,
                        ],
                    ],
                ],
            ],
            'mautic.companies.menu.sub.index' => [
                'id'        => 'mautic.companies.menu.index',
                'parent'    => 'mautic.companies.menu.index',
                'route'     => 'mautic_company_index',
                'access'    => ['lead:leads:viewother'],
                'priority'  => 100,
            ],
        ],
    ],
    'routes'      => [
        'main' => [
            'mautic_companytag_index' => [
                'path'       => '/companytag',
                'controller' => 'MauticPlugin\LeuchtfeuerCompanyTagsBundle\Controller\CompanyTagController:indexAction',
            ],
            'mautic_companytag_action' => [
                'path'       => '/companytag/{objectAction}/{objectId}',
                'controller' => 'MauticPlugin\LeuchtfeuerCompanyTagsBundle\Controller\CompanyTagController:executeAction',
            ],
            'mautic_company_index' => [
                'path'       => '/companies/{page}',
                'controller' => 'MauticPlugin\LeuchtfeuerCompanyTagsBundle\Controller\CompanyController::indexAction',
            ],
            'mautic_company_action' => [
                'path'       => '/companies/{objectAction}/{objectId}',
                'controller' => 'MauticPlugin\LeuchtfeuerCompanyTagsBundle\Controller\CompanyController::executeAction',
            ],
        ],
    ],
];
