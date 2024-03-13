<?php

namespace MauticPlugin\LeuchtfeuerCompanyTagsBundle\Form\Type;

use Mautic\LeadBundle\Form\Type\TagType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractType<mixed>
 */
class ModifyCompanyTagsType extends AbstractType
{
    public function __construct(
        private TranslatorInterface $translator
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add(
            'add_tags',
            CompanyTagType::class,
            [
                'label' => 'mautic.companytag.companytags.add',
                'attr'  => [
                    'data-placeholder'     => $this->translator->trans('mautic.companytag.companytags.select_or_create'),
                    'data-no-results-text' => $this->translator->trans('mautic.companytag.companytags.enter_to_create'),
                    'data-allow-add'       => 'true',
                    'onchange'             => 'Mautic.createCompanyTag(this)',
                ],
                'data'            => $options['data']['add_tags'] ?? null,
                'add_transformer' => true,
            ]
        );

        $builder->add(
            'remove_tags',
            CompanyTagType::class,
            [
                'label' => 'mautic.companytag.companytags.remove',
                'attr'  => [
                    'data-placeholder'     => $this->translator->trans('mautic.companytag.companytags.select_or_create'),
                    'data-no-results-text' => $this->translator->trans('mautic.companytag.companytags.enter_to_create'),
                    'data-allow-add'       => 'true',
                    'onchange'             => 'Mautic.createCompanyTag(this)',
                ],
                'data'            => $options['data']['remove_tags'] ?? null,
                'add_transformer' => true,
            ]
        );
    }
}
