<?php

namespace MauticPlugin\LeuchtfeuerCompanyTagsBundle\Form\Type;

use Mautic\LeadBundle\Form\Type\CompanyType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// class CustomCompanyType extends AbstractTypeExtension
class CustomCompanyType extends AbstractType
{
    public function __construct(
        protected TranslatorInterface $translator
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $data= [];
        if (!empty($options['data'])) {
            $data = $options['data'];
        }

        $builder->add(
            'tag',
            CompanyTagType::class,
            [
                'by_reference' => false,
                'attr'         => [
                    'data-placeholder'     => $this->translator->trans('mautic.lead.tags.select_or_create'),
                    'data-no-results-text' => $this->translator->trans('mautic.lead.tags.enter_to_create'),
                    'data-allow-add'       => 'true',
                    'onchange'             => 'Mautic.createCompanyTag(this)',
                ],
                'data'=> $data,
            ]
        );
    }

}
