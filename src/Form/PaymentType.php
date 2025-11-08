<?php

namespace App\Form;

use App\Entity\Payment;
use App\Entity\Booking;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class PaymentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $admin = (bool) ($options['admin'] ?? false);

        $builder
            // Amount in cents
            ->add('amountCents', IntegerType::class, [
                'label' => 'Amount (in cents)',
                'constraints' => [
                    new Assert\NotNull(),
                    new Assert\GreaterThan(0, message: 'Amount must be greater than 0'),
                ],
                'help' => 'Enter amount in cents (e.g., 5000 for 50.00 EUR)',
            ])

            // Currency (ISO 4217)
            ->add('currency', TextType::class, [
                'label' => 'Currency',
                'empty_data'  => 'EUR',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(min: 3, max: 3),
                    new Assert\Regex(
                        pattern: '/^[A-Z]{3}$/',
                        message: 'Use a 3-letter ISO currency code (e.g. EUR, USD, GBP)'
                    ),
                ],
            ])

            // Payment method
            ->add('paymentMethod', ChoiceType::class, [
                'label' => 'Payment Method',
                'choices' => [
                    'Credit/Debit Card' => 'card',
                    'PayPal' => 'paypal',
                    'Bank Transfer' => 'bank_transfer',
                    'Apple Pay' => 'apple_pay',
                    'Google Pay' => 'google_pay',
                ],
                'empty_data' => 'card',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Choice(['card', 'paypal', 'bank_transfer', 'apple_pay', 'google_pay']),
                ],
            ]);

        // Admin-only fields
        if ($admin) {
            $builder
                // Payment status
                ->add('status', ChoiceType::class, [
                    'label' => 'Payment Status',
                    'choices' => [
                        'Pending'     => Payment::STATUS_PENDING,
                        'Processing'  => Payment::STATUS_PROCESSING,
                        'Completed'   => Payment::STATUS_COMPLETED,
                        'Failed'      => Payment::STATUS_FAILED,
                        'Refunded'    => Payment::STATUS_REFUNDED,
                    ],
                    'constraints' => [new Assert\Choice([
                        Payment::STATUS_PENDING,
                        Payment::STATUS_PROCESSING,
                        Payment::STATUS_COMPLETED,
                        Payment::STATUS_FAILED,
                        Payment::STATUS_REFUNDED,
                    ])],
                ])

                // Associated booking
                ->add('booking', EntityType::class, [
                    'label' => 'Associated Booking',
                    'class' => Booking::class,
                    'choice_label' => function (Booking $booking) {
                        return sprintf(
                            'Booking #%s - %s (%s)',
                            substr((string)$booking->getId(), 0, 8),
                            $booking->getFormattedTotalPrice(),
                            $booking->getStatus()
                        );
                    },
                    'required' => false,
                    'placeholder' => 'Select a booking (optional)',
                ])

                // Transaction ID
                ->add('transactionId', TextType::class, [
                    'label' => 'Transaction ID',
                    'required' => false,
                    'help' => 'External transaction identifier',
                ])

                // Stripe Payment Intent ID
                ->add('stripePaymentIntentId', TextType::class, [
                    'label' => 'Stripe Payment Intent ID',
                    'required' => false,
                    'constraints' => [
                        new Assert\Length(max: 255),
                    ],
                ])

                // Stripe Charge ID
                ->add('stripeChargeId', TextType::class, [
                    'label' => 'Stripe Charge ID',
                    'required' => false,
                    'constraints' => [
                        new Assert\Length(max: 255),
                    ],
                ])

                // Payment Gateway
                ->add('paymentGateway', ChoiceType::class, [
                    'label' => 'Payment Gateway',
                    'choices' => [
                        'Stripe' => 'stripe',
                        'PayPal' => 'paypal',
                        'Manual' => 'manual',
                    ],
                    'empty_data' => 'stripe',
                ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'      => Payment::class,
            'csrf_protection' => false, // Enable if using Twig forms
            'admin'           => false, // Toggle admin-only fields
            'allow_extra_fields' => true, // For API flexibility
        ]);
    }
}
