<?php

namespace MauticPlugin\LeuchtfeuerCompanyTagsBundle\Controller;

use Doctrine\Persistence\ManagerRegistry;
use Mautic\CoreBundle\Controller\AjaxController as CommonAjaxController;
use Mautic\CoreBundle\Controller\AjaxLookupControllerTrait;
use Mautic\CoreBundle\Factory\MauticFactory;
use Mautic\CoreBundle\Factory\ModelFactory;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Service\FlashBag;
use Mautic\CoreBundle\Translation\Translator;
use Mautic\LeadBundle\Model\LeadModel;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Model\CompanyTagModel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
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
        private CompanyTagModel $companyTagModel
    ) {
        parent::__construct($doctrine, $factory, $modelFactory, $userHelper, $coreParametersHelper, $dispatcher, $translator, $flashBag, $requestStack, $security);
    }

    public function addCompanyTagsAction(Request $request)
    {
        $tags = $request->request->get('tags');
        $tags = json_decode($tags, true);

        if (is_array($tags)) {
//            $leadModel = $this->getModel('lead');
//            \assert($leadModel instanceof LeadModel);
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
}
