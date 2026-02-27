<?php

declare(strict_types=1);

namespace App\Form;

use App\Dto\Input\TomeInput;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formulaire pour le DTO TomeInput.
 *
 * @extends AbstractType<TomeInput>
 */
class TomeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('bought', CheckboxType::class, [
                'label' => 'Acheté',
                'required' => false,
            ])
            ->add('downloaded', CheckboxType::class, [
                'label' => 'Téléchargé',
                'required' => false,
            ])
            ->add('isbn', TextType::class, [
                'attr' => ['placeholder' => 'Ex: 978-2-505-00123-4'],
                'label' => 'ISBN',
                'required' => false,
            ])
            ->add('number', IntegerType::class, [
                'attr' => ['min' => 0],
                'label' => 'N°',
                'required' => true,
            ])
            ->add('onNas', CheckboxType::class, [
                'label' => 'Sur NAS',
                'required' => false,
            ])
            ->add('read', CheckboxType::class, [
                'label' => 'Lu',
                'required' => false,
            ])
            ->add('title', TextType::class, [
                'attr' => ['placeholder' => 'Titre du tome (optionnel)'],
                'label' => 'Titre',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TomeInput::class,
        ]);
    }
}
