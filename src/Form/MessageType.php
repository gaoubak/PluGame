<?php

namespace App\Form;

use App\Entity\Message;
use App\Entity\Conversation;
use App\Entity\MediaAsset;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class MessageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('conversation', EntityType::class, [
                'class' => Conversation::class,
                'choice_label' => 'id',
                'required' => true,
            ])
            ->add('sender', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'email',
                'required' => true,
            ])
            ->add('content', TextareaType::class, [
                'required' => true,
                'constraints' => [new Assert\NotBlank()],
            ])
            ->add('readAt', DateTimeType::class, [
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('media', EntityType::class, [
                'class' => MediaAsset::class,
                'choice_label' => 'id',
                'required' => false,
            ])
            ->add('replyTo', EntityType::class, [
                'class' => Message::class,
                'choice_label' => 'id',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Message::class,
        ]);
    }
}
