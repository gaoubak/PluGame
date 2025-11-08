<?php

// src/Form/ReviewType.php
namespace App\Form;

use App\Entity\Review;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ReviewType extends AbstractType
{
    public function buildForm(FormBuilderInterface $b, array $options): void
    {
        $b
            // booking, reviewer, creator set in code — not exposed
            ->add('rating', IntegerType::class, [
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Range(min: 1, max: 5, notInRangeMessage: 'Rating must be between 1 and 5'),
                ],
            ])
            ->add('comment', TextareaType::class, [
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $r): void
    {
        $r->setDefaults([
            'data_class'      => Review::class,
            'csrf_protection' => false, // enable if using Twig form
        ]);
    }
}
