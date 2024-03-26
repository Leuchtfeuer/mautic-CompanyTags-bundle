<?php

namespace MauticPlugin\LeuchtfeuerCompanyTagsBundle\Controller;

use Doctrine\Persistence\ManagerRegistry;
use Mautic\CoreBundle\Controller\AjaxController as CommonAjaxController;
use Mautic\CoreBundle\Controller\AjaxLookupControllerTrait;
use Mautic\CoreBundle\Factory\MauticFactory;
use Mautic\CoreBundle\Factory\ModelFactory;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\InputHelper;
use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Service\FlashBag;
use Mautic\CoreBundle\Translation\Translator;
use Mautic\LeadBundle\Model\CompanyModel;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Model\CompanyTagModel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class AjaxController extends CommonAjaxController
{
    use AjaxLookupControllerTrait;

    public function __construct(
        ManagerRegistry $doctrine,
        MauticFactory $factory,
        ModelFactory $modelFactory,
        UserHelper $userHelper,
        CoreParametersHelper $coreParametersHelper,
        EventDispatcherInterface $dispatcher,
        Translator $translator,
        FlashBag $flashBag,
        ?RequestStack $requestStack,
        ?CorePermissions $security,
        private CompanyTagModel $companyTagModel,
        private CompanyModel $companyModel
    ) {
        parent::__construct($doctrine, $factory, $modelFactory, $userHelper, $coreParametersHelper, $dispatcher, $translator, $flashBag, $requestStack, $security);
    }

    public function addCompanyTagsAction(Request $request): JsonResponse
    {
        $tags = $request->request->get('tags');
        $tags = json_decode($tags, true);

        if (is_array($tags)) {
            $newTags   = [];

            foreach ($tags as $tag) {
                if (!is_numeric($tag)) {
                    $newTags[] = $this->companyTagModel->getRepository()->getTagByNameOrCreateNewOne($tag);
                }
            }

            if (!empty($newTags)) {
                $this->companyTagModel->getRepository()->saveEntities($newTags);
            }

            // Get an updated list of tags
            $allTags    = $this->companyTagModel->getRepository()->getSimpleList(null, [], 'tag');
            $tagOptions = '';

            foreach ($allTags as $tag) {
                $selected = (in_array($tag['value'], $tags) || in_array($tag['label'], $tags)) ? ' selected="selected"' : '';
                $tagOptions .= '<option'.$selected.' value="'.$tag['value'].'">'.$tag['label'].'</option>';
            }

            $data = [
                'success' => 1,
                'tags'    => $tagOptions,
            ];
        } else {
            $data = ['success' => 0];
        }

        return $this->sendJsonResponse($data);
    }

    public function removeCompanyCompanyTagAction(Request $request): JsonResponse
    {
        $tagId        = (int) InputHelper::clean($request->request->get('tagId'));
        $companyTagId = (int) InputHelper::clean($request->request->get('companyId'));
        if (!$tagId || !$companyTagId) {
            return $this->sendJsonResponse(['success' => 0]);
        }
        $company    = $this->companyModel->getRepository()->find($companyTagId);
        $companyTag = $this->companyTagModel->getRepository()->find($tagId);
        if (!$companyTag || !$company) {
            return $this->sendJsonResponse(['success' => 0]);
        }
        $this->companyTagModel->removeCompanyTag($company, $companyTag);
        $tags         = $this->companyTagModel->getRepository()->getTagsByCompany($company);
        $tagsIdsSaved = array_map(function ($tag) {
            return $tag->getId();
        }, $tags);
        $data = ['success' => 1];
        if (in_array($tagId, $tagsIdsSaved)) {
            $data = ['success' => 0];
        }

        return $this->sendJsonResponse($data);
    }
}
