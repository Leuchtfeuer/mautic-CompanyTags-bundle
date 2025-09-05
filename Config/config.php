<?php

return [
    'name'        => 'Company Tags by Leuchtfeuer',
    'description' => 'Provide a 2nd type of Tags which can be applied to Companies',
    'version'     => '1.3.5',
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
                'controller' => 'MauticPlugin\LeuchtfeuerCompanyTagsBundle\Controller\CompanyTagController::indexAction',
            ],
            'mautic_companytag_action' => [
                'path'       => '/companytag/{objectAction}/{objectId}',
                'controller' => 'MauticPlugin\LeuchtfeuerCompanyTagsBundle\Controller\CompanyTagController::executeAction',
            ],
            'mautic_company_index' => [
                'path'       => '/companies/{page}',
                'controller' => 'MauticPlugin\LeuchtfeuerCompanyTagsBundle\Controller\CompanyController::indexAction',
            ],
            'mautic_company_action' => [
                'path'       => '/companies/{objectAction}/{objectId}',
                'controller' => 'MauticPlugin\LeuchtfeuerCompanyTagsBundle\Controller\CompanyController::executeAction',
            ],
            'mautic_company_batch_companytag_set' => [
                'path'       => '/companytag/batch/company/set',
                'controller' => 'MauticPlugin\LeuchtfeuerCompanyTagsBundle\Controller\BatchCompanyTagController::setAction',
            ],
            'mautic_company_batch_companytag_view' => [
                'path'       => '/companytag/batch/company/view',
                'controller' => 'MauticPlugin\LeuchtfeuerCompanyTagsBundle\Controller\BatchCompanyTagController::indexAction',
            ],
        ],
        'api'  => [
            'mautic_api_companytags' => [
                'standard_entity' => true,
                'name'            => 'companytags',
                'path'            => '/companytags',
                'controller'      => MauticPlugin\LeuchtfeuerCompanyTagsBundle\Controller\Api\CompanyTagApiController::class,
            ],
            'mauitc_api_companytags_add_companytag_to_company' => [
                'path'       => '/companytags/{companyId}/add',
                'controller' => 'MauticPlugin\LeuchtfeuerCompanyTagsBundle\Controller\Api\CompanyTagApiController::addCompanyTagToCompanyAction',
                'method'     => 'POST',
            ],
            'mauitc_api_companytags_remove_companytag_from_company' => [
                'path'       => '/companytags/{companyId}/remove',
                'controller' => 'MauticPlugin\LeuchtfeuerCompanyTagsBundle\Controller\Api\CompanyTagApiController::removeCompanyTagFromCompanyAction',
                'method'     => 'POST',
            ],
        ],
    ],
];
