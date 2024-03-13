<?php

namespace MauticPlugin\LeuchtfeuerCompanyTagsBundle\Security\Permissions;

use Mautic\CoreBundle\Security\Permissions\AbstractPermissions;
use Symfony\Component\Form\FormBuilderInterface;

class CompanyTagPermissions extends AbstractPermissions
{
    public function __construct($params)
    {
        parent::__construct($params);
        $this->addStandardPermissions(['companytags']);
    }

    public function getName(): string
    {
        return 'companytag';
    }

    /**
     * @param array<mixed> $options
     * @param array<mixed> $data
     */
    public function buildForm(FormBuilderInterface &$builder, array $options, array $data): void
    {
        $this->addExtendedFormFields('companytag', 'companytags', $builder, $data);
    }
}
