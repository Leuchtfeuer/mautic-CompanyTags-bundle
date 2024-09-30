<?php

namespace MauticPlugin\LeuchtfeuerCompanyTagsBundle\Controller\Api;

use Doctrine\Persistence\ManagerRegistry;
use Mautic\ApiBundle\Controller\CommonApiController;
use Mautic\ApiBundle\Helper\EntityResultHelper;
use Mautic\CoreBundle\Factory\MauticFactory;
use Mautic\CoreBundle\Factory\ModelFactory;
use Mautic\CoreBundle\Helper\AppVersion;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Translation\Translator;
use Mautic\LeadBundle\Model\CompanyModel;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Entity\CompanyTags;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Model\CompanyTagModel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

class CompanyTagApiController extends CommonApiController
{
    /**
     * Model object for processing the entity.
     *
     * @var CompanyTagModel|null
     */
    protected $model;

    // @phpstan-ignore-next-line
    public function __construct(
        protected CorePermissions $security,
        Translator $translator,
        EntityResultHelper $entityResultHelper,
        RouterInterface $router,
        FormFactoryInterface $formFactory,
        AppVersion $appVersion,
        RequestStack $requestStack,
        ManagerRegistry $doctrine,
        ModelFactory $modelFactory,
        EventDispatcherInterface $dispatcher,
        CoreParametersHelper $coreParametersHelper,
        MauticFactory $factory,
        private CompanyTagModel $companyTagModel,
        private CompanyModel $companyModel
    ) {
        $this->model             = $this->companyTagModel;
        $this->entityClass       = CompanyTags::class;
        $this->entityNameOne     = 'companytag';
        $this->entityNameMulti   = 'companytags';
        $this->permissionBase    = 'companytag:companytags';
        parent::__construct($security, $translator, $entityResultHelper, $router, $formFactory, $appVersion, $requestStack, $doctrine, $modelFactory, $dispatcher, $coreParametersHelper, $factory);
    }

    public function addCompanyTagToCompanyAction(Request $request, int $companyId): Response
    {
        if (!$this->security->isGranted($this->permissionBase.':edit')) {
            return $this->accessDenied();
        }

        $tagsId = $request->request->get('tags');

        if (!$companyId || !$tagsId) {
            return $this->badRequest();
        }

        $company  = $this->companyModel->getEntity($companyId);
        $tags     = $this->model->getRepository()->findBy(['id' => $tagsId]);

        if (!$company || !$tags) {
            return $this->notFound();
        }

        $this->model->updateCompanyTags($company, $tags);

        try {
            $data = [
                'tags'    => $tags,
                'success' => true,
            ];
            $view = $this->view($data, Response::HTTP_OK);
        } catch (\Exception $e) {
            $data = [
                'success' => false,
                'message' => $e->getMessage(),
            ];
            $view = $this->view($data, Response::HTTP_FORBIDDEN);
        }

        return $this->handleView($view);
    }

    public function removeCompanyTagFromCompanyAction(Request $request, int $companyId): Response
    {
        if (!$this->security->isGranted($this->permissionBase.':edit')) {
            return $this->accessDenied();
        }

        $tagsId = $request->request->get('tags');

        if (!$companyId || !$tagsId) {
            return $this->badRequest();
        }

        $company  = $this->companyModel->getEntity($companyId);
        $tags     = $this->model->getRepository()->findBy(['id' => $tagsId]);

        if (!$company || !$tags) {
            return $this->notFound();
        }

        $this->model->updateCompanyTags($company, [], $tags);

        try {
            $data = [
                'tags'    => $tags,
                'success' => true,
            ];
            $view = $this->view($data, Response::HTTP_OK);
        } catch (\Exception $e) {
            $data = [
                'success' => false,
                'message' => $e->getMessage(),
            ];
            $view = $this->view($data, Response::HTTP_FORBIDDEN);
        }

        return $this->handleView($view);
    }
}
