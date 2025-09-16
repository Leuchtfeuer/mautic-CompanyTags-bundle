<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanyTagsBundle\Controller;

use Doctrine\Persistence\ManagerRegistry;
use Mautic\CoreBundle\Controller\AbstractFormController;
use Mautic\CoreBundle\Factory\MauticFactory;
use Mautic\CoreBundle\Factory\ModelFactory;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Service\FlashBag;
use Mautic\CoreBundle\Translation\Translator;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Entity\CompanyTags;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Form\Type\BatchType;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Model\CompanyTagModel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

class BatchCompanyTagController extends AbstractFormController
{
    public function __construct(
        private CompanyTagModel $companyTagModel,
        ManagerRegistry $doctrine,
        MauticFactory $factory,
        ModelFactory $modelFactory,
        UserHelper $userHelper,
        CoreParametersHelper $coreParametersHelper,
        EventDispatcherInterface $dispatcher,
        Translator $translator,
        FlashBag $flashBag,
        RequestStack $requestStack,
        CorePermissions $security,
    ) {
        parent::__construct($doctrine, $factory, $modelFactory, $userHelper, $coreParametersHelper, $dispatcher, $translator, $flashBag, $requestStack, $security);
    }

    /**
     * API for batch action.
     *
     * @see \Mautic\LeadBundle\Controller\BatchCompanyTagController::setAction
     */
    public function setAction(Request $request): JsonResponse
    {
        $requestParameters = $request->request->all();

        if (!isset($requestParameters['companytag_batch']) || !is_array($requestParameters['companytag_batch'])) {
            $params = [];
        } else {
            $params = $requestParameters['companytag_batch'];
        }

        $companyIds = '' === $params['ids'] ? [] : json_decode($params['ids'], true, 512, JSON_THROW_ON_ERROR);

        if ([] !== $companyIds && is_array($companyIds)) {
            $tagsToAdd    = $params['add'] ?? [];
            $tagsToRemove = $params['remove'] ?? [];

            if (is_array($tagsToAdd) && [] !== $tagsToAdd) {
                $companyTagEntities = $this->companyTagModel->getRepository()->findBy(['id' => $tagsToAdd]);
                if ([] !== $companyTagEntities) {
                    $companies = $this->companyTagModel->getCompanyRepository()->findBy(['id' => $companyIds]);
                    if ([] !== $companies) {
                        $this->companyTagModel->updateCompaniesTags($companies, $companyTagEntities);
                    }
                }
            }

            if (is_array($tagsToRemove) && [] !== $tagsToRemove) {
                $companyTagEntities = $this->companyTagModel->getRepository()->findBy(['id' => $tagsToRemove]);
                if ([] !== $companyTagEntities) {
                    $companies = $this->companyTagModel->getCompanyRepository()->findBy(['id' => $companyIds]);
                    if ([] !== $companies) {
                        $this->companyTagModel->updateCompaniesTags($companies, [], $companyTagEntities);
                    }
                }
            }
            $message = $this->translator->trans('mautic.company_tags.return.message.batchcompanies', [
                '%count%' => count($tagsToAdd) + count($tagsToRemove),
            ]);
            $this->addFlashMessage($message);
        } else {
            $this->addFlashMessage('mautic.core.error.ids.missing');
        }

        return new JsonResponse([
            'closeModal' => true,
            'flashes'    => $this->getFlashContent(),
        ]);
    }

    /**
     * @see \Mautic\LeadBundle\Controller\BatchSegmentController::indexAction
     */
    public function indexAction(): Response
    {
        $route    = $this->generateUrl('mautic_company_batch_companytag_set');
        $tags     = $this->companyTagModel->getEntities();
        $items    = [];

        foreach ($tags as $tag) {
            assert($tag instanceof CompanyTags);
            $items[$tag->getName()] = $tag->getId();
        }

        return $this->delegateView(
            [
                'viewParameters' => [
                    'form' => $this->createForm(
                        BatchType::class,
                        [],
                        [
                            'items'  => $items,
                            'action' => $route,
                        ]
                    )->createView(),
                ],
                'contentTemplate' => '@LeuchtfeuerCompanyTags/Batch/form.html.twig',
                'passthroughVars' => [
                    'activeLink'    => '#mautic_companytag_index',
                    'mauticContent' => 'companyBatch',
                    'route'         => $route,
                ],
            ]
        );
    }
}
