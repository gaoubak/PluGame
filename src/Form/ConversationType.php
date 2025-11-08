<?php

namespace App\Form;

use App\Entity\Conversation;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ConversationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('booking', null, [
                'required' => false,
            ])
            ->add('athlete', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'email',
                'required' => true,
            ])
            ->add('creator', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'email',
                'required' => true,
            ])

            ->add('lastMessageAt', DateTimeType::class, [
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('lastMessagePreview', TextType::class, [
                'required' => false,
            ])
            ->add('unreadCount', IntegerType::class, [
                'required' => false,
            ])
            ->add('archivedAt', DateTimeType::class, [
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('mutedUntil', DateTimeType::class, [
                'widget' => 'single_text',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Conversation::class,
        ]);
    }
}
