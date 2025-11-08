<?php

namespace App\Form;

use App\Entity\AvailabilitySlot;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class AvailabilitySlotType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // creator comes from the authenticated user → don't expose it in the form

            ->add('startTime', DateTimeType::class, [
                'widget' => 'single_text',
                'input'  => 'datetime_immutable',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Type(\DateTimeImmutable::class),
                ],
            ])

            ->add('endTime', DateTimeType::class, [
                'widget' => 'single_text',
                'input'  => 'datetime_immutable',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Type(\DateTimeImmutable::class),
                ],
            ])

            ->add('isBooked', CheckboxType::class, [
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AvailabilitySlot::class,
            'csrf_protection' => false, // enable if rendered in Twig
            'constraints' => [
                new Callback(function (AvailabilitySlot $slot, ExecutionContextInterface $ctx) {
                    $start = $slot->getStartTime();
                    $end   = $slot->getEndTime();

                    if ($start && $end && $end <= $start) {
                        $ctx->buildViolation('End time must be after start time.')
                            ->atPath('endTime')->addViolation();
                    }
                }),
            ],
        ]);
    }
}
