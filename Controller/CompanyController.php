<?php

namespace MauticPlugin\LeuchtfeuerCompanyTagsBundle\Controller;

use Doctrine\Persistence\ManagerRegistry;
use Mautic\CoreBundle\Factory\MauticFactory;
use Mautic\CoreBundle\Factory\ModelFactory;
use Mautic\CoreBundle\Factory\PageHelperFactoryInterface;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\InputHelper;
use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Service\FlashBag;
use Mautic\CoreBundle\Translation\Translator;
use Mautic\FormBundle\Helper\FormFieldHelper;
use Mautic\LeadBundle\Controller\CompanyController as CompanyControllerBase;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Model\CompanyModel;
use Mautic\LeadBundle\Model\FieldModel;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Form\Type\CustomCompanyType;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Integration\Config;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Model\CompanyTagModel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

class CompanyController extends CompanyControllerBase
{
    public function __construct(
        FormFactoryInterface $formFactory,
        FormFieldHelper $fieldHelper,
        ManagerRegistry $managerRegistry,
        MauticFactory $factory,
        ModelFactory $modelFactory,
        UserHelper $userHelper,
        CoreParametersHelper $coreParametersHelper,
        EventDispatcherInterface $dispatcher,
        Translator $translator,
        FlashBag $flashBag,
        RequestStack $requestStack,
        CorePermissions $security,
        private CompanyTagModel $companyTagModel,
        private Config $config,
    ) {
        parent::__construct($formFactory, $fieldHelper, $managerRegistry, $factory, $modelFactory, $userHelper, $coreParametersHelper, $dispatcher, $translator, $flashBag, $requestStack, $security);
    }

    /**
     * @param Request $request
     * @param PageHelperFactoryInterface $pageHelperFactory
     * @param int $page
     *
     * @return JsonResponse|RedirectResponse|Response
     */
    public function indexAction(Request $request, PageHelperFactoryInterface $pageHelperFactory, $page = 1): RedirectResponse|JsonResponse|Response
    {
        if (!$this->config->isPublished()) {
            return parent::indexAction($request, $pageHelperFactory, $page);
        }

        // set some permissions
        $permissions = $this->security->isGranted(
            [
                'lead:leads:viewown',
                'lead:leads:viewother',
                'lead:leads:create',
                'lead:leads:editother',
                'lead:leads:editown',
                'lead:leads:deleteown',
                'lead:leads:deleteother',
            ],
            'RETURN_ARRAY'
        );

        if (!$permissions['lead:leads:viewother'] && !$permissions['lead:leads:viewown']) {
            return $this->accessDenied();
        }

        $this->setListFilters();

        $pageHelper = $pageHelperFactory->make('mautic.company', $page);

        $limit      = $pageHelper->getLimit();
        $start      = $pageHelper->getStart();
        $search     = $request->get('search', $request->getSession()->get('mautic.company.filter', ''));
        $filter     = $this->filterByCompanyTag($search);
        $orderBy    = $request->getSession()->get('mautic.company.orderby', 'comp.companyname');
        $orderByDir = $request->getSession()->get('mautic.company.orderbydir', 'ASC');
        $companies = $this->getModel('lead.company')->getEntities(
            [
                'start'          => $start,
                'limit'          => $limit,
                'filter'         => $filter,
                'orderBy'        => $orderBy,
                'orderByDir'     => $orderByDir,
                'withTotalCount' => true,
            ]
        );

        $request->getSession()->set('mautic.company.filter', $search);

        $count     = $companies['count'];
        $companies = $companies['results'];

        if ($count && $count < ($start + 1)) {
            $lastPage  = $pageHelper->countPage($count);
            $returnUrl = $this->generateUrl('mautic_company_index', ['page' => $lastPage]);
            $pageHelper->rememberPage($lastPage);

            return $this->postActionRedirect(
                [
                    'returnUrl'       => $returnUrl,
                    'viewParameters'  => ['page' => $lastPage],
                    'contentTemplate' => 'Mautic\LeadBundle\Controller\CompanyController::indexAction',
                    'passthroughVars' => [
                        'activeLink'    => '#mautic_company_index',
                        'mauticContent' => 'company',
                    ],
                ]
            );
        }

        $pageHelper->rememberPage($page);

        $tmpl  = $request->isXmlHttpRequest() ? $request->get('tmpl', 'index') : 'index';
        $model = $this->getModel('lead.company');
        \assert($model instanceof CompanyModel);
        $companyIds = array_keys($companies);
        $leadCounts = (!empty($companyIds)) ? $model->getRepository()->getLeadCount($companyIds) : [];

        return $this->delegateView(
            [
                'viewParameters' => [
                    'searchValue' => $search,
                    'leadCounts'  => $leadCounts,
                    'items'       => $companies,
                    'page'        => $page,
                    'limit'       => $limit,
                    'permissions' => $permissions,
                    'tmpl'        => $tmpl,
                    'totalItems'  => $count,
                ],
                'contentTemplate' => '@MauticLead/Company/list.html.twig',
                'passthroughVars' => [
                    'activeLink'    => '#mautic_company_index',
                    'mauticContent' => 'company',
                    'route'         => $this->generateUrl('mautic_company_index', ['page' => $page]),
                ],
            ]
        );
    }

    /**
     * @param $search
     * @return array<mixed>
     */
    private function filterByCompanyTag($search): array
    {
        // tag:"bola"
        $defaultFilter = ['string' => $search, 'force' => []];
        if(!str_contains($search, 'tag:')) {
            return $defaultFilter;
        }
        $tag = str_replace('tag:', '', $search);
        $tag = str_replace('"', '', $tag);
        $tag = $this->companyTagModel->getRepository()->findOneBy(['tag' => $tag]);
        if(!$tag) {
            return $defaultFilter;
        }
        $companies = $tag->getCompanies()->toArray();

        $companyIds = array_map(function($company) {
            return $company->getId();
        }, $companies);
        return [
            'force' => [
                [
                    'column' => 'comp.id',
                    'expr'   => 'in',
                    'value'  => $companyIds,
                ],
            ],
        ];
    }

    /**
     * Generates edit form and processes post data.
     *
     * @param int  $objectId
     * @param bool $ignorePost
     *
     * @return JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse|Response
     */
    public function editAction(Request $request, $objectId, $ignorePost = false)
    {
        if (!$this->config->isPublished()) {
            return parent::editAction($request, $objectId, $ignorePost);
        }

        $model = $this->getModel('lead.company');
        \assert($model instanceof CompanyModel);
        $entity = $model->getEntity($objectId);

        // set the page we came from
        $page = $request->getSession()->get('mautic.company.page', 1);

        $viewParameters = ['page' => $page];

        // set the return URL
        $returnUrl = $this->generateUrl('mautic_company_index', ['page' => $page]);

        $postActionVars = [
            'returnUrl'       => $returnUrl,
            'viewParameters'  => $viewParameters,
            'contentTemplate' => 'MauticPlugin\LeuchtfeuerCompanyTagsBundle\Controller\CompanyController::indexAction',
            'passthroughVars' => [
                'activeLink'    => '#mautic_company_index',
                'mauticContent' => 'company',
            ],
        ];

        // form not found
        if (null === $entity) {
            return $this->postActionRedirect(
                array_merge(
                    $postActionVars,
                    [
                        'flashes' => [
                            [
                                'type'    => 'error',
                                'msg'     => 'mautic.company.error.notfound',
                                'msgVars' => ['%id%' => $objectId],
                            ],
                        ],
                    ]
                )
            );
        } elseif (!$this->security->hasEntityAccess(
            'lead:leads:editown',
            'lead:leads:editother',
            $entity->getOwner())) {
            return $this->accessDenied();
        } elseif ($model->isLocked($entity)) {
            // deny access if the entity is locked
            return $this->isLocked($postActionVars, $entity, 'lead.company');
        }

        $action       = $this->generateUrl('mautic_company_action', ['objectAction' => 'edit', 'objectId' => $objectId]);
        $method       = $request->getMethod();
        $company      = $request->request->get('company') ?? [];
        $updateSelect = 'POST' === $method
            ? ($company['updateSelect'] ?? false)
            : $request->get('updateSelect', false);

        $leadFieldModel = $this->getModel('lead.field');
        \assert($leadFieldModel instanceof FieldModel);
        $fields = $leadFieldModel->getPublishedFieldArrays('company');
        $form   = $model->createForm(
            $entity,
            $this->formFactory,
            $action,
            ['fields' => $fields, 'update_select' => $updateSelect]
        );
        $companyTagsStructure = $this->customFormCompanyTags($request, 'edit', $entity);

        // /Check for a submitted form and process it
        if (!$ignorePost && 'POST' === $method) {
            $valid = false;

            if (!$cancelled = $this->isFormCancelled($form)) {
                if ($valid = $this->isFormValid($form)) {
                    $data = $request->request->get('company');
                    // pull the data from the form in order to apply the form's formatting
                    foreach ($form as $f) {
                        $data[$f->getName()] = $f->getData();
                    }

                    $model->setFieldValues($entity, $data, true);

                    // form is valid so process the data
                    $model->saveEntity($entity, $this->getFormButton($form, ['buttons', 'save'])->isClicked());
                    $this->companyTagModel->updateCompanyTags($entity, $companyTagsStructure['entitiesToAdd'], $companyTagsStructure['entitiesToRemove']);
                    $companyTags                  = $this->companyTagModel->getTagsByCompany($entity);
                    $companyTagsStructure['form'] = $this->formFactory->create(CustomCompanyType::class, $companyTags);
                    $this->addFlashMessage(
                        'mautic.core.notice.updated',
                        [
                            '%name%'      => $entity->getName(),
                            '%menu_link%' => 'mautic_company_index',
                            '%url%'       => $this->generateUrl(
                                'mautic_company_action',
                                [
                                    'objectAction' => 'view',
                                    'objectId'     => $entity->getId(),
                                ]
                            ),
                        ]
                    );

                    if ($this->getFormButton($form, ['buttons', 'save'])->isClicked()) {
                        $returnUrl = $this->generateUrl('mautic_company_index', $viewParameters);
                        $template  = 'MauticPlugin\LeuchtfeuerCompanyTagsBundle\Controller\CompanyController::indexAction';
                    }
                }
            } else {
                // unlock the entity
                $model->unlockEntity($entity);

                $returnUrl = $this->generateUrl('mautic_company_index', $viewParameters);
                $template  = 'MauticPlugin\LeuchtfeuerCompanyTagsBundle\Controller\CompanyController::indexAction';
            }

            $passthrough = [
                'activeLink'    => '#mautic_company_index',
                'mauticContent' => 'company',
            ];

            // Check to see if this is a popup
            if (!empty($form['updateSelect'])) {
                $template    = false;
                $passthrough = array_merge(
                    $passthrough,
                    [
                        'updateSelect' => $form['updateSelect']->getData(),
                        'id'           => $entity->getId(),
                        'name'         => $entity->getName(),
                    ]
                );
            }

            if ($cancelled || ($valid && $this->getFormButton($form, ['buttons', 'save'])->isClicked())) {
                return $this->postActionRedirect(
                    [
                        'returnUrl'       => $returnUrl,
                        'viewParameters'  => $viewParameters,
                        'contentTemplate' => $template,
                        'passthroughVars' => $passthrough,
                    ]
                );
            } elseif ($valid) {
                // Refetch and recreate the form in order to populate data manipulated in the entity itself
                $company = $model->getEntity($objectId);
                $form    = $model->createForm($company, $this->formFactory, $action, ['fields' => $fields, 'update_select' => $updateSelect]);
            }
        } else {
            // lock the entity
            $model->lockEntity($entity);
        }

        $fields = $model->organizeFieldsByGroup($fields);
        $groups = array_keys($fields);
        sort($groups);
        $template = '@LeuchtfeuerCompanyTags/Company/form_'.($request->get('modal', false) ? 'embedded' : 'standalone').'.html.twig';

        return $this->delegateView(
            [
                'viewParameters' => [
                    'tmpl'              => $request->isXmlHttpRequest() ? $request->get('tmpl', 'index') : 'index',
                    'entity'            => $entity,
                    'form'              => $form->createView(),
                    'formCompanyTags'   => $companyTagsStructure['form']->createView(),
                    'fields'            => $fields,
                    'groups'            => $groups,
                ],
                'contentTemplate' => $template,
                'passthroughVars' => [
                    'activeLink'    => '#mautic_company_index',
                    'mauticContent' => 'company',
                    'updateSelect'  => InputHelper::clean($request->query->get('updateSelect')),
                    'route'         => $this->generateUrl(
                        'mautic_company_action',
                        [
                            'objectAction' => 'edit',
                            'objectId'     => $entity->getId(),
                        ]
                    ),
                ],
            ]
        );
    }

    public function newAction(Request $request, $entity = null)
    {
        if (!$this->config->isPublished()) {
            return parent::newAction($request, $entity);
        }

        $model = $this->getModel('lead.company');
        \assert($model instanceof CompanyModel);

        if (!($entity instanceof Company)) {
            /** @var \Mautic\LeadBundle\Entity\Company $entity */
            $entity = $model->getEntity();
        }

        if (!$this->security->isGranted('lead:leads:create')) {
            return $this->accessDenied();
        }

        // set the page we came from
        $page         = $request->getSession()->get('mautic.company.page', 1);
        $method       = $request->getMethod();
        $action       = $this->generateUrl('mautic_company_action', ['objectAction' => 'new']);
        $company      = $request->request->get('company') ?? [];
        $updateSelect = InputHelper::clean(
            'POST' === $method
                ? ($company['updateSelect'] ?? false)
                : $request->get('updateSelect', false)
        );

        $leadFieldModel = $this->getModel('lead.field');
        \assert($leadFieldModel instanceof FieldModel);
        $fields = $leadFieldModel->getPublishedFieldArrays('company');
        $form   = $model->createForm($entity, $this->formFactory, $action, ['fields' => $fields, 'update_select' => $updateSelect]);

        $viewParameters       = ['page' => $page];
        $returnUrl            = $this->generateUrl('mautic_company_index', $viewParameters);
        $template             = 'MauticPlugin\LeuchtfeuerCompanyTagsBundle\Controller\CompanyController::indexAction';
        $companyTagsStructure = $this->customFormCompanyTags($request, 'new', $entity);

        // /Check for a submitted form and process it
        if ('POST' === $request->getMethod()) {
            $valid = false;
            if (!$cancelled = $this->isFormCancelled($form)) {
                if ($valid = $this->isFormValid($form)) {
                    // form is valid so process the data
                    // get custom field values
                    $data = $request->request->get('company');
                    // pull the data from the form in order to apply the form's formatting
                    foreach ($form as $f) {
                        $data[$f->getName()] = $f->getData();
                    }
                    $model->setFieldValues($entity, $data, true);
                    // form is valid so process the data
                    $model->saveEntity($entity);
                    $this->companyTagModel->updateCompanyTags($entity, $companyTagsStructure['entitiesToAdd'], $companyTagsStructure['entitiesToRemove']);
                    $this->addFlashMessage(
                        'mautic.core.notice.created',
                        [
                            '%name%'      => $entity->getName(),
                            '%menu_link%' => 'mautic_company_index',
                            '%url%'       => $this->generateUrl(
                                'mautic_company_action',
                                [
                                    'objectAction' => 'edit',
                                    'objectId'     => $entity->getId(),
                                ]
                            ),
                        ]
                    );

                    if ($this->getFormButton($form, ['buttons', 'save'])->isClicked()) {
                        $returnUrl = $this->generateUrl('mautic_company_index', $viewParameters);
                        $template  = 'MauticPlugin\LeuchtfeuerCompanyTagsBundle\Controller\CompanyController::indexAction';
                    } else {
                        // return edit view so that all the session stuff is loaded
                        return $this->editAction($request, $entity->getId(), true);
                    }
                }
            }

            $passthrough = [
                'activeLink'    => '#mautic_company_index',
                'mauticContent' => 'company',
            ];

            // Check to see if this is a popup
            if (!empty($form['updateSelect'])) {
                $template    = false;
                $passthrough = array_merge(
                    $passthrough,
                    [
                        'updateSelect' => $form['updateSelect']->getData(),
                        'id'           => $entity->getId(),
                        'name'         => $entity->getName(),
                    ]
                );
            }

            if ($cancelled || ($valid && $this->getFormButton($form, ['buttons', 'save'])->isClicked())) {
                return $this->postActionRedirect(
                    [
                        'returnUrl'       => $returnUrl,
                        'viewParameters'  => $viewParameters,
                        'contentTemplate' => $template,
                        'passthroughVars' => $passthrough,
                    ]
                );
            }
        }

        $fields = $model->organizeFieldsByGroup($fields);
        $groups = array_keys($fields);
        sort($groups);
        $template = '@LeuchtfeuerCompanyTags/Company/form_'.($request->get('modal', false) ? 'embedded' : 'standalone').'.html.twig';

        return $this->delegateView(
            [
                'viewParameters' => [
                    'tmpl'              => $request->isXmlHttpRequest() ? $request->get('tmpl', 'index') : 'index',
                    'entity'            => $entity,
                    'form'              => $form->createView(),
                    'formCompanyTags'   => $companyTagsStructure['form']->createView(),
                    'fields'            => $fields,
                    'groups'            => $groups,
                ],
                'contentTemplate' => $template,
                'passthroughVars' => [
                    'activeLink'    => '#mautic_company_index',
                    'mauticContent' => 'company',
                    'updateSelect'  => ('POST' === $request->getMethod()) ? $updateSelect : null,
                    'route'         => $this->generateUrl(
                        'mautic_company_action',
                        [
                            'objectAction' => (!empty($valid) ? 'edit' : 'new'), // valid means a new form was applied
                            'objectId'     => $entity->getId(),
                        ]
                    ),
                ],
            ]
        );
    }

    /**
     * @param int $objectId
     *
     * @return RedirectResponse|JsonResponse|array<mixed>|Response
     */
    public function viewAction(Request $request, $objectId): RedirectResponse|JsonResponse|array|Response
    {
        if (!$this->config->isPublished()) {
            return parent::viewAction($request, $objectId); // TODO: Change the autogenerated stub
        }

        /** @var CompanyModel $model */
        $model  = $this->getModel('lead.company');

        $company = $model->getEntity($objectId);

        // set the return URL
        $returnUrl = $this->generateUrl('mautic_company_index');

        $postActionVars = [
            'returnUrl'       => $returnUrl,
            'contentTemplate' => 'Mautic\LeadBundle\Controller\CompanyController::indexAction',
            'passthroughVars' => [
                'activeLink'    => '#mautic_company_index',
                'mauticContent' => 'company',
            ],
        ];

        if (null === $company) {
            return $this->postActionRedirect(
                array_merge(
                    $postActionVars,
                    [
                        'flashes' => [
                            [
                                'type'    => 'error',
                                'msg'     => 'mautic.company.error.notfound',
                                'msgVars' => ['%id%' => $objectId],
                            ],
                        ],
                    ]
                )
            );
        }

        /** @var \Mautic\LeadBundle\Entity\Company $company */
        $model->getRepository()->refetchEntity($company);

        // set some permissions
        $permissions = $this->security->isGranted(
            [
                'lead:leads:viewown',
                'lead:leads:viewother',
                'lead:leads:create',
                'lead:leads:editown',
                'lead:leads:editother',
                'lead:leads:deleteown',
                'lead:leads:deleteother',
            ],
            'RETURN_ARRAY'
        );

        if (!$this->security->hasEntityAccess(
            'lead:leads:viewown',
            'lead:leads:viewother',
            $company->getPermissionUser()
        )
        ) {
            return $this->accessDenied();
        }

        $fields         = $company->getFields();
        $contacts       = $model->getCompanyLeadRepository()->getCompanyLeads($objectId);

        $leadIds = array_column($contacts, 'lead_id');

        $contacts = $this->getCompanyContacts($request, $objectId, 0, $leadIds);

        return $this->delegateView(
            [
                'viewParameters' => [
                    'company'           => $company,
                    'tags'              => $this->companyTagModel->getTagsByCompany($company),
                    'fields'            => $fields,
                    'items'             => $contacts['items'],
                    'permissions'       => $permissions,
                    'engagementData'    => $this->getCompanyEngagementsForGraph($contacts),
                    'security'          => $this->security,
                    'page'              => $contacts['page'],
                    'totalItems'        => $contacts['count'],
                    'limit'             => $contacts['limit'],
                ],
                'contentTemplate' => '@LeuchtfeuerCompanyTags/Company/company.html.twig',
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function customFormCompanyTags(Request $request, string $objectAction, Company $company): array
    {
        $requestData = [];
        if ($request->request->has('custom_company')) {
            $requestData = $request->request->get('custom_company');
        }

        $newTagsEntities  = $requestData['tag'] ?? [];
        $entitiesToRemove = $this->companyTagModel->getTagsByCompany($company);
        if (!empty($newTagsEntities)) {
            $newTagsEntities = $this->companyTagModel->getRepository()->findBy(['id' => $newTagsEntities]);
            foreach ($entitiesToRemove as $key => $entity) {
                if (in_array($entity, $newTagsEntities)) {
                    unset($entitiesToRemove[$key]);
                }
            }
        }

        if (!isset($request->request->get('custom_company')['tag']) && 'edit' !== $objectAction) {
            return [
                'form'             => $this->formFactory->create(CustomCompanyType::class),
                'entitiesToAdd'    => $newTagsEntities,
                'entitiesToRemove' => $entitiesToRemove,
            ];
        }

        if ('edit' === $objectAction) {
            $companyTags = $this->companyTagModel->getTagsByCompany($company);
        }

        if (empty($companyTags)) {
            return [
                'form'             => $this->formFactory->create(CustomCompanyType::class),
                'entities'         => $newTagsEntities,
                'entitiesToAdd'    => $newTagsEntities,
                'entitiesToRemove' => $entitiesToRemove,
            ];
        }

        $form = $this->formFactory->create(CustomCompanyType::class, $companyTags);

        return [
            'form'             => $form,
            'entities'         => $newTagsEntities,
            'entitiesToAdd'    => $newTagsEntities,
            'entitiesToRemove' => $entitiesToRemove,
        ];
    }
}
