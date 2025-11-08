<?php

// src/Form/ServiceOfferingType.php
namespace App\Form;

use App\Entity\ServiceOffering;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ServiceOfferingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $b, array $options): void
    {
        $b
            ->add('title', TextType::class)
            ->add('description', TextareaType::class, ['required' => false])
            ->add('durationMin', IntegerType::class)
            ->add('priceCents', IntegerType::class)
            ->add('pricePerAssetCents', IntegerType::class, ['required' => false])
            ->add('priceTotalCents', IntegerType::class, ['required' => false])
            ->add('deliverables', TextareaType::class, ['required' => false])
            ->add('isActive', CheckboxType::class, ['required' => false])
            // NEW fields
            ->add('kind', ChoiceType::class, [
                'choices' => [
                    'Hourly' => 'HOURLY',
                    'Per asset' => 'PER_ASSET',
                    'Package' => 'PACKAGE',
                ],
                'required' => false,
            ])
            ->add('assetType', ChoiceType::class, [
                'choices' => [
                    'Photo' => 'PHOTO',
                    'Video' => 'VIDEO',
                ],
                'required' => false,
            ])
            ->add('includes', TextType::class, [
                'required' => false,
                // we accept JSON string here and decode in PRE_SUBMIT
            ])
            ->add('currency', TextType::class, ['required' => false])
            ->add('featured', CheckboxType::class, ['required' => false]);

        // Decode includes JSON if the client sent a JSON string for `includes`
        $b->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
            $data = $event->getData();
            if (!is_array($data)) {
                return;
            }

            if (isset($data['includes']) && is_string($data['includes'])) {
                $raw = trim($data['includes']);
                if ($raw === '') {
                    $data['includes'] = null;
                } else {
                    // try decode JSON; if it fails, keep as null
                    $decoded = null;
                    try {
                        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                    } catch (\Throwable $e) {
                        // If invalid JSON, try to parse simple key=value pairs (optional) or set null
                        $decoded = null;
                    }
                    $data['includes'] = $decoded;
                }
                $event->setData($data);
            }

            // If client sent minified numeric prices as floats, ensure integers (optional)
            if (isset($data['pricePerAssetCents']) && $data['pricePerAssetCents'] === '') {
                $data['pricePerAssetCents'] = null;
                $event->setData($data);
            }
            if (isset($data['priceTotalCents']) && $data['priceTotalCents'] === '') {
                $data['priceTotalCents'] = null;
                $event->setData($data);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'      => ServiceOffering::class,
            'csrf_protection' => false,
            'allow_extra_fields' => true,
        ]);
    }
}
