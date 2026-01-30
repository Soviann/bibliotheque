<?php

namespace App\Form;

use App\Entity\ComicSeries;
use App\Enum\ComicStatus;
use App\Enum\ComicType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ComicSeriesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('authors', AuthorAutocompleteType::class, [
                'label' => 'Auteur(s)',
                'required' => false,
            ])
            ->add('coverUrl', UrlType::class, [
                'label' => 'URL de la couverture',
                'required' => false,
            ])
            ->add('currentIssue', IntegerType::class, [
                'attr' => ['min' => 0],
                'label' => 'Numéro actuel possédé',
                'required' => false,
            ])
            ->add('currentIssueComplete', CheckboxType::class, [
                'label' => 'Complet',
                'required' => false,
            ])
            ->add('description', TextareaType::class, [
                'attr' => ['rows' => 4],
                'label' => 'Description',
                'required' => false,
            ])
            ->add('isbn', TextType::class, [
                'attr' => ['placeholder' => 'Ex: 978-2-505-00123-4'],
                'label' => 'ISBN',
                'required' => false,
            ])
            ->add('isWishlist', CheckboxType::class, [
                'label' => 'Dans la liste de souhaits',
                'required' => false,
            ])
            ->add('lastBought', IntegerType::class, [
                'attr' => ['min' => 0],
                'label' => 'Dernier numéro acheté',
                'required' => false,
            ])
            ->add('lastBoughtComplete', CheckboxType::class, [
                'label' => 'Complet',
                'required' => false,
            ])
            ->add('lastDownloaded', IntegerType::class, [
                'attr' => ['min' => 0],
                'label' => 'Dernier téléchargé',
                'required' => false,
            ])
            ->add('lastDownloadedComplete', CheckboxType::class, [
                'label' => 'Complet',
                'required' => false,
            ])
            ->add('missingIssues', TextType::class, [
                'attr' => ['placeholder' => 'Ex: 0, 3, 5'],
                'label' => 'Tomes manquants',
                'required' => false,
            ])
            ->add('onNas', CheckboxType::class, [
                'label' => 'Présent sur le NAS',
                'required' => false,
            ])
            ->add('ownedIssues', TextType::class, [
                'attr' => ['placeholder' => 'Ex: 8, 9, 16-18, 20, 36, 37'],
                'label' => 'Tomes possédés (si non consécutifs)',
                'required' => false,
            ])
            ->add('publishedCount', IntegerType::class, [
                'attr' => ['min' => 0],
                'label' => 'Nombre de parutions',
                'required' => false,
            ])
            ->add('publishedCountComplete', CheckboxType::class, [
                'label' => 'Complet',
                'required' => false,
            ])
            ->add('publishedDate', TextType::class, [
                'label' => 'Date de publication',
                'required' => false,
            ])
            ->add('publisher', TextType::class, [
                'label' => 'Éditeur',
                'required' => false,
            ])
            ->add('status', EnumType::class, [
                'class' => ComicStatus::class,
                'choice_label' => fn (ComicStatus $status) => $status->getLabel(),
                'label' => 'Statut',
            ])
            ->add('title', TextType::class, [
                'label' => 'Titre de la série',
            ])
            ->add('type', EnumType::class, [
                'class' => ComicType::class,
                'choice_label' => fn (ComicType $type) => $type->getLabel(),
                'label' => 'Type',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ComicSeries::class,
        ]);
    }
}
