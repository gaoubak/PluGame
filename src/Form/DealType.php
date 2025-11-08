<?php

// src/Form/DealType.php
namespace App\Form;

use App\Entity\Deal;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class DealType extends AbstractType
{
    public function buildForm(FormBuilderInterface $b, array $options): void
    {
        $admin = (bool) ($options['admin'] ?? false);

        if ($admin) {
            $b->add('status', ChoiceType::class, [
                'choices' => [
                    'Draft'      => Deal::STATUS_DRAFT,
                    'Proposed'   => Deal::STATUS_PROPOSED,
                    'Validated'  => Deal::STATUS_VALIDATED,
                    'Rejected'   => Deal::STATUS_REJECTED,
                    'Cancelled'  => Deal::STATUS_CANCELLED,
                    'Paid'       => Deal::STATUS_PAID,
                ],
                'constraints' => [new Assert\Choice([
                    Deal::STATUS_DRAFT,
                    Deal::STATUS_PROPOSED,
                    Deal::STATUS_VALIDATED,
                    Deal::STATUS_REJECTED,
                    Deal::STATUS_CANCELLED,
                    Deal::STATUS_PAID,
                ])],
            ]);
        }

        $b
            ->add('currency', TextType::class, [
                'empty_data'  => 'EUR',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(min: 3, max: 3),
                    new Assert\Regex('/^[A-Z]{3}$/', 'Use a 3-letter ISO currency (e.g. EUR, USD).'),
                ],
            ])
            ->add('amountCents', IntegerType::class, [
                'constraints' => [new Assert\NotNull(), new Assert\GreaterThanOrEqual(0)],
            ])
            ->add('description', TextareaType::class, ['required' => false])
            ->add('stripeProductId', TextType::class, ['required' => false])
            ->add('stripePriceId', TextType::class, ['required' => false]);
    }

    public function configureOptions(OptionsResolver $r): void
    {
        $r->setDefaults([
            'data_class'      => Deal::class,
            'csrf_protection' => false,
            'admin'           => false,
        ]);
    }
}
