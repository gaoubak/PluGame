<?php

namespace App\Form;

use App\Entity\Booking;
use App\Entity\ServiceOffering;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class BookingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $admin = (bool) ($options['admin'] ?? false);

        $builder
            // Optional: pick a service to attach
            ->add('service', EntityType::class, [
                'class'        => ServiceOffering::class,
                'choice_label' => 'title',      // adapt to your property
                'required'     => false,
                'placeholder'  => 'Select a service',
            ])

            // Start / End times (use single_text for JS datetime pickers)
            ->add('startTime', DateTimeType::class, [
                'widget' => 'single_text',
                'required' => true,
            ])
            ->add('endTime', DateTimeType::class, [
                'widget' => 'single_text',
                'required' => true,
            ])

            ->add('locationText', TextType::class, [
                'required'   => false,
                'empty_data' => '',
                'constraints' => [new Assert\Length(['max' => 255])],
            ])

            // New short/location field
            ->add('location', TextType::class, [
                'required'   => false,
                'empty_data' => '',
                'constraints' => [new Assert\Length(['max' => 255])],
            ])

            // Notes (athlete) + creatorNotes (new)
            ->add('notes', TextareaType::class, [
                'required' => false,
            ])
            ->add('creatorNotes', TextareaType::class, [
                'required' => false,
            ])

            // Cancellation (admin only writable)
            ->add('cancelledBy', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'email',
                'required' => false,
                'placeholder' => '—',
            ])
            ->add('cancelReason', TextType::class, [
                'required' => false,
            ])

            // Completed timestamp (admin can set)
            ->add('completedAt', DateTimeType::class, [
                'widget' => 'single_text',
                'required' => false,
            ])

            // Money (stored as cents)
            ->add('subtotalCents', IntegerType::class, [
                'constraints' => [new Assert\NotNull(), new Assert\GreaterThanOrEqual(0)],
            ])
            ->add('feeCents', IntegerType::class, [
                'constraints' => [new Assert\NotNull(), new Assert\GreaterThanOrEqual(0)],
            ])
            ->add('taxCents', IntegerType::class, [
                'required'    => false,
                'empty_data'  => '0',
                'constraints' => [new Assert\GreaterThanOrEqual(0)],
            ])
            ->add('totalCents', IntegerType::class, [
                'constraints' => [new Assert\NotNull(), new Assert\GreaterThanOrEqual(0)],
            ])

            ->add('currency', TextType::class, [
                'empty_data'  => 'EUR',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(['min' => 3, 'max' => 3]),
                    new Assert\Regex(['pattern' => '/^[A-Z]{3}$/', 'message' => 'Use a 3-letter ISO currency (e.g. EUR, USD).']),
                ],
            ]);

        // Admin-only: allow changing status and cancelledBy from form (keep control)
        if ($admin) {
            $builder->add('status', ChoiceType::class, [
                'choices'  => [
                    'Pending'      => Booking::STATUS_PENDING,
                    'Accepted'     => Booking::STATUS_ACCEPTED,
                    'Declined'     => Booking::STATUS_DECLINED,
                    'Cancelled'    => Booking::STATUS_CANCELLED,
                    'In progress'  => Booking::STATUS_IN_PROGRESS,
                    'Completed'    => Booking::STATUS_COMPLETED,
                    'Refunded'     => Booking::STATUS_REFUNDED,
                ],
                'constraints' => [new Assert\Choice([
                    Booking::STATUS_PENDING,
                    Booking::STATUS_ACCEPTED,
                    Booking::STATUS_DECLINED,
                    Booking::STATUS_CANCELLED,
                    Booking::STATUS_IN_PROGRESS,
                    Booking::STATUS_COMPLETED,
                    Booking::STATUS_REFUNDED,
                ])],
            ]);
        }

        // Optional geo and stripe ids (kept minimal)
        $builder
            ->add('lat', NumberType::class, [
                'required' => false,
                'scale' => 8,
            ])
            ->add('lng', NumberType::class, [
                'required' => false,
                'scale' => 8,
            ])
            ->add('stripePaymentIntentId', TextType::class, ['required' => false])
            ->add('stripeSubscriptionId', TextType::class, ['required' => false])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'       => Booking::class,
            'csrf_protection'  => false, // enable if rendering in Twig
            // Cross-field validations
            'constraints'      => [
                new Callback(function (Booking $b, ExecutionContextInterface $ctx) {
                    // Times sanity
                    if ($b->getEndTime() <= $b->getStartTime()) {
                        $ctx->buildViolation('End time must be after start time.')
                            ->atPath('endTime')->addViolation();
                    }

                    // Money: total = subtotal + fee + tax
                    $expected = $b->getSubtotalCents() + $b->getFeeCents() + $b->getTaxCents();
                    if ($b->getTotalCents() !== $expected) {
                        $ctx->buildViolation('totalCents must equal subtotalCents + feeCents + taxCents.')
                            ->atPath('totalCents')->addViolation();
                    }

                    // If status is CANCELLED ensure cancelledBy or cancelReason present (example rule)
                    if ($b->getStatus() === Booking::STATUS_CANCELLED) {
                        if ($b->getCancelledBy() === null && empty($b->getCancelReason())) {
                            $ctx->buildViolation('Provide a cancel reason or the user who cancelled.')
                                ->atPath('cancelReason')->addViolation();
                        }
                    }

                    // If status is COMPLETED ensure completedAt is set
                    if ($b->getStatus() === Booking::STATUS_COMPLETED && $b->getCompletedAt() === null) {
                        $ctx->buildViolation('completedAt must be set when marking booking as completed.')
                            ->atPath('completedAt')->addViolation();
                    }
                }),
            ],
            // Toggle admin fields
            'admin' => false,
        ]);
    }
}
