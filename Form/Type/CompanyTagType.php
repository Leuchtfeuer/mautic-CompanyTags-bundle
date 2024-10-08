<?php

namespace MauticPlugin\LeuchtfeuerCompanyTagsBundle\Form\Type;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Mautic\LeadBundle\Form\DataTransformer\TagEntityModelTransformer;
use MauticPlugin\LeuchtfeuerCompanyTagsBundle\Entity\CompanyTags;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CompanyTagType extends AbstractType
{
    public function __construct(
        private EntityManager $em
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options['add_transformer']) {
            $transformer = new TagEntityModelTransformer(
                $this->em,
                CompanyTags::class,
                $options['multiple']
            );

            $builder->addModelTransformer($transformer);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(
            [
                'label'           => 'mautic.company.tags',
                'class'           => CompanyTags::class,
                'query_builder'   => fn (EntityRepository $er) => $er->createQueryBuilder('t')->orderBy('t.tag', \Doctrine\Common\Collections\Criteria::ASC),
                'choice_label'    => 'tag',
                'multiple'        => true,
                'required'        => false,
                'disabled'        => false,
                'add_transformer' => false,
            ]
        );
    }

    /**
     * @return string
     */
    public function getBlockPrefix()
    {
        return 'company_tag';
    }

    /**
     * @return string
     */
    public function getParent()
    {
        return EntityType::class;
    }
}
