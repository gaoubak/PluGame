<?php

namespace App\Form;

use App\Entity\CreatorProfile;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class CreatorProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('displayName', TextType::class, ['required' => true])
            ->add('bio', TextareaType::class, ['required' => false])
            ->add('baseCity', TextType::class, ['required' => false])
            ->add('travelRadiusKm', IntegerType::class, [
                'required' => false,
                'constraints' => [new Assert\GreaterThanOrEqual(0)]
            ])
            ->add('hourlyRateCents', IntegerType::class, [
                'required' => false,
                'constraints' => [new Assert\GreaterThanOrEqual(0)]
            ])
            ->add('gear', CollectionType::class, [
                'entry_type' => TextType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'required' => false,
            ])
            ->add('specialties', CollectionType::class, [
                'entry_type' => TextType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'required' => false,
            ])

            ->add('portfolioCoverAssetId', TextType::class, ['required' => false])
            ->add('coverPhoto', TextType::class, ['required' => false])

            // metrics & badges
            ->add('responseTime', IntegerType::class, [
                'required' => false,
                'help' => 'Average response time in minutes',
                'constraints' => [new Assert\GreaterThanOrEqual(0)]
            ])
            ->add('acceptanceRate', NumberType::class, [
                'required' => false,
                'scale' => 2,
                'constraints' => [
                    new Assert\Range(['min' => 0, 'max' => 100])
                ]
            ])
            ->add('completionRate', NumberType::class, [
                'required' => false,
                'scale' => 2,
                'constraints' => [
                    new Assert\Range(['min' => 0, 'max' => 100])
                ]
            ])
            ->add('verified', CheckboxType::class, [
                'required' => false,
            ])
            ->add('featuredWork', CollectionType::class, [
                'entry_type' => TextType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'required' => false,
                'help' => 'Array of media IDs to showcase'
            ])

            ->add('avgRating', TextType::class, ['required' => false])
            ->add('ratingsCount', IntegerType::class, ['required' => false])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => CreatorProfile::class,
        ]);
    }
}
