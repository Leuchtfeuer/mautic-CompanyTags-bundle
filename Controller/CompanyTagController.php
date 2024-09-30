<?php

namespace MauticPlugin\LeuchtfeuerCompanyTagsBundle\Controller;

use Doctrine\Persistence\ManagerRegistry;
use Mautic\CoreBundle\Controller\AbstractStandardFormController;
use Mautic\CoreBundle\Factory\MauticFactory;
use Mautic\CoreBundle\Factory\ModelFactory;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Service\FlashBag;
use Mautic\CoreBundle\Translation\Translator;
use Mautic\FormBundle\Helper\FormFieldHelper;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Integration\Config;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Model\CompanyTagModel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

class CompanyTagController extends AbstractStandardFormController
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
        private Config $config
    ) {
        parent::__construct($formFactory, $fieldHelper, $managerRegistry, $factory, $modelFactory, $userHelper, $coreParametersHelper, $dispatcher, $translator, $flashBag, $requestStack, $security);
        if (!$this->config->isPublished()) {
            throw new \RuntimeException('The plugin is not published');
        }
    }

    /**
     * @return RedirectResponse|JsonResponse|array<mixed>|Response
     *
     * @throws \Exception
     */
    public function indexAction(Request $request, int $page = 1): RedirectResponse|JsonResponse|array|Response
    {
        // set some permissions
        $permissions = $this->security->isGranted(
            [
                $this->getPermissionBase().':view',
                $this->getPermissionBase().':create',
                $this->getPermissionBase().':edit',
                $this->getPermissionBase().':delete',
                $this->getPermissionBase().':publish',
            ],
            'RETURN_ARRAY',
            null,
            true
        );
        if (!$permissions[$this->getPermissionBase().':view']) {
            return $this->accessDenied();
        }

        if (!$this->checkActionPermission('index')) {
            return $this->accessDenied();
        }

        $this->setListFilters();

        $session = $request->getSession();
        if (empty($page)) {
            $page = $session->get('mautic.'.$this->getSessionBase().'.page', 1);
        }

        // set limits
        $limit = $session->get('mautic.'.$this->getSessionBase().'.limit', $this->coreParametersHelper->get('default_pagelimit'));
        $start = (1 === $page) ? 0 : (($page - 1) * $limit);
        if ($start < 0) {
            $start = 0;
        }

        $search = $request->get('search', $session->get('mautic.'.$this->getSessionBase().'.filter', ''));
        $session->set('mautic.'.$this->getSessionBase().'.filter', $search);
        $model  = $this->getModel($this->getModelName());
        $repo   = $model->getRepository();
        $filter = ['string' => $search, 'force' => []];

        if (!empty($search)) {
            $filter = [
                'where' => [
                    [
                        'expr' => 'like',
                        'col'  => $repo->getTableAlias().'.tag',
                        'val'  => '%'.$search.'%',
                    ],
                ],
            ];
        }

        $orderBy    = $session->get('mautic.'.$this->getSessionBase().'.orderby', $repo->getTableAlias().'.'.$this->getDefaultOrderColumn());
        $orderByDir = $session->get('mautic.'.$this->getSessionBase().'.orderbydir', $this->getDefaultOrderDirection());

        [$count, $items] = $this->getIndexItems($start, $limit, $filter, $orderBy, $orderByDir);

        if ($count && $count < ($start + 1)) {
            // the number of entities are now less then the current page so redirect to the last page
            $lastPage = (1 === $count) ? 1 : (((ceil($count / $limit)) ?: 1) ?: 1);

            $session->set('mautic.'.$this->getSessionBase().'.page', $lastPage);
            $returnUrl = $this->generateUrl($this->getIndexRoute(), ['page' => $lastPage]);

            return $this->postActionRedirect(
                $this->getPostActionRedirectArguments(
                    [
                        'returnUrl'       => $returnUrl,
                        'viewParameters'  => ['page' => $lastPage],
                        'contentTemplate' => $this->getControllerBase().'::'.$this->getPostActionControllerAction('index').'Action',
                        'passthroughVars' => [
                            'mauticContent' => $this->getJsLoadMethodPrefix(),
                        ],
                    ],
                    'index'
                )
            );
        }

        // set what page currently on so that we can return here after form submission/cancellation
        $session->set('mautic.'.$this->getSessionBase().'.page', $page);
        $tagIds    = array_keys(iterator_to_array($items->getIterator(), true));

        $tagsCount      = (!empty($tagIds)) ? $this->companyTagModel->getRepository()->countByLeads($tagIds) : [];
        $viewParameters = [
            'permissionBase'  => $this->getPermissionBase(),
            'mauticContent'   => $this->getJsLoadMethodPrefix(),
            'sessionVar'      => $this->getSessionBase(),
            'actionRoute'     => $this->getActionRoute(),
            'indexRoute'      => $this->getIndexRoute(),
            'tablePrefix'     => $model->getRepository()->getTableAlias(),
            'modelName'       => $this->getModelName(),
            'translationBase' => $this->getTranslationBase(),
            'searchValue'     => $search,
            'items'           => $items,
            'totalItems'      => $count,
            'page'            => $page,
            'limit'           => $limit,
            'permissions'     => $permissions,
            'tmpl'            => $request->get('tmpl', 'index'),
            'tagsCount'       => $tagsCount,
        ];

        return $this->delegateView(
            $this->getViewArguments(
                [
                    'viewParameters'  => $viewParameters,
                    'contentTemplate' => $this->getTemplateName('list.html.twig'),
                    'passthroughVars' => [
                        'mauticContent' => $this->getJsLoadMethodPrefix(),
                        'route'         => $this->generateUrl($this->getIndexRoute(), ['page' => $page]),
                    ],
                ],
                'index'
            )
        );
    }

    /**
     * @throws \Exception
     */
    public function newAction(Request $request): RedirectResponse|JsonResponse|Response
    {
        return $this->newStandard($request);
    }

    /**
     * @throws \Exception
     */
    public function editAction(Request $request, int $objectId, bool $ignorePost = false): RedirectResponse|JsonResponse|Response
    {
        return $this->editStandard($request, $objectId, $ignorePost);
    }

    public function deleteAction(Request $request, int $objectId): RedirectResponse|JsonResponse
    {
        return $this->deleteStandard($request, $objectId);
    }

    /**
     * Deletes a group of entities.
     *
     * @return JsonResponse|RedirectResponse
     */
    public function batchDeleteAction(Request $request)
    {
        return $this->batchDeleteStandard($request);
    }

    protected function getModelName(): string
    {
        return 'companytag';
    }

    /**
     * Provide the name of the column which is used for default ordering.
     */
    protected function getDefaultOrderColumn(): string
    {
        return 'tag';
    }

    /**
     * Get template base different than @MauticCore/Standard.
     */
    protected function getTemplateBase(): string
    {
        return '@LeuchtfeuerCompanyTags/CompanyTag';
    }

    /**
     * @return RedirectResponse|JsonResponse|array<mixed>|Response
     */
    public function viewAction(Request $request, ?int $objectId): RedirectResponse|JsonResponse|array|Response
    {
        if (!$this->security->isGranted('companytag:companytags:view')) {
            return $this->accessDenied();
        }

        $security = $this->security;
        $tag      = $this->companyTagModel->getEntity($objectId);

        return $this->delegateView([
            'returnUrl'      => $this->generateUrl('mautic_companytag_action', ['objectAction' => 'view', 'objectId' => $tag->getId()]),
            'viewParameters' => [
                'tag'      => $tag,
                'security' => $security,
            ],
            'contentTemplate' => '@LeuchtfeuerCompanyTags/CompanyTag/details.html.twig',
            'passthroughVars' => [
                'activeLink'    => '#mautic_companytag_index',
                'mauticContent' => 'companytag',
            ],
        ]);
    }
}
