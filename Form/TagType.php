<?php

namespace Jcc\Bundle\AlbumBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class TagType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name')
            ->add('hash')
            ->add('slug')
            //->add('cover')
            ->add('class')
            ->add('sort', 'choice', array('choices' => array('ASC' => 'chronological', 'DESC' => 'reverse')))
            ->add('global', 'checkbox', array(
                'required' => false
            ))
            //->add('sort')
            ->add('save', 'submit')
        ;
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'Jcc\Bundle\AlbumBundle\Entity\Tag'
        ));
    }

    public function getName()
    {
        return 'jcc_bundle_albumbundle_tagtype';
    }
}
