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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

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
            throw new \Exception('The plugin is not published');
        }
    }

    public function indexAction(Request $request, $page = 1)
    {
        return parent::indexStandard($request, $page);
    }

    public function newAction(Request $request)
    {
        return parent::newStandard($request);
    }

    public function editAction(Request $request, $objectId, $ignorePost = false)
    {
        return parent::editStandard($request, $objectId, $ignorePost);
    }

    protected function getModelName(): string
    {
        return 'companytag';
    }

    /**
     * Provide the name of the column which is used for default ordering.
     *
     * @return string
     */
    protected function getDefaultOrderColumn()
    {
        return 'tag';
    }

    /**
     * Get template base different than @MauticCore/Standard.
     *
     * @return string
     */
    protected function getTemplateBase()
    {
        return '@LeuchtfeuerCompanyTags/CompanyTag';
    }

    public function viewAction(Request $request, $objectId)
    {
        $security = $this->security;
        $tag      = $this->companyTagModel->getEntity($objectId);

        // set the page we came from
        $page = $request->getSession()->get('mautic.tagmanager.page', 1);
        if (null === $tag) {
            // set the return URL
            $returnUrl = $this->generateUrl('mautic_tagmanager_index', ['page' => $page]);

            return $this->postActionRedirect([
                'returnUrl'       => $returnUrl,
                'viewParameters'  => ['page' => $page],
                'contentTemplate' => 'MauticPlugin\LeuchtfeuerCompanyTagsBundle\Controller\CompanyTagController::indexAction',
                'passthroughVars' => [
                    'activeLink'    => '#mautic_companytag_index',
                    'mauticContent' => 'companytag',
                ],
                'flashes' => [
                    [
                        'type'    => 'error',
                        'msg'     => 'mautic.companytag.tag.error.notfound',
                        'msgVars' => ['%id%' => $objectId],
                    ],
                ],
            ]);
        } elseif (!$this->security->isGranted('companytag:companytags:view')) {
            return $this->accessDenied();
        }

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
