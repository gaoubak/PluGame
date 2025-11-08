<?php

namespace App\Form;

use App\Entity\AthleteProfile;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class AthleteProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('displayName', TextType::class, ['required' => true])
            ->add('sport', TextType::class, ['required' => false])
            ->add('level', TextType::class, ['required' => false])
            ->add('bio', TextareaType::class, ['required' => false])
            ->add('homeCity', TextType::class, ['required' => false])

            ->add('coverPhoto', TextType::class, ['required' => false])

            ->add('achievements', CollectionType::class, [
                'entry_type' => TextType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'required' => false,
                'help' => 'List of achievements (strings).'
            ])

            ->add('stats', CollectionType::class, [
                'entry_type' => TextType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'required' => false,
                'help' => 'Sport-specific stats. Use entries like \"goals:10\" or switch to a JSON textarea if you prefer.'
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => AthleteProfile::class,
        ]);
    }
}
