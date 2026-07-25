<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Sport;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<TeamFormData>
 */
final class TeamFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Sport is chosen once at creation and is immutable afterwards (a team
        // plays one sport), so the field appears on create only.
        if (true === $options['with_sport']) {
            $builder->add('sport', EntityType::class, [
                'label' => 'Sport',
                'class' => Sport::class,
                'choice_label' => 'name',
                'placeholder' => 'Vyberte sport…',
                'query_builder' => static fn (EntityRepository $repository): QueryBuilder => $repository->createQueryBuilder('s')->orderBy('s.name', 'ASC'),
            ]);
        }

        $builder->add('name', TextType::class, [
            'label' => 'Název týmu',
            'attr' => ['placeholder' => 'Např. Sparta Praha'],
        ]);

        $builder->add('shortName', TextType::class, [
            'label' => 'Zkratka',
            'required' => false,
            'help' => 'Nepovinné. Krátký kód pro úzké prostory, např. „SPA".',
            'attr' => ['placeholder' => 'Např. SPA'],
        ]);

        $builder->add('country', TextType::class, [
            'label' => 'Země',
            'required' => false,
            'help' => 'Nepovinné. Dvoupísmenný kód (ISO), např. CZ — zobrazí vlajku.',
            'attr' => ['placeholder' => 'CZ', 'maxlength' => 2, 'style' => 'text-transform:uppercase'],
        ]);

        $builder->add('brandColor', TextType::class, [
            'label' => 'Barva týmu',
            'required' => false,
            'help' => 'Nepovinné. HEX barva monogramu, např. #EE1C25. Prázdné = barva se odvodí z názvu.',
            'attr' => ['placeholder' => '#EE1C25'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TeamFormData::class,
            'with_sport' => false,
        ]);
        $resolver->setAllowedTypes('with_sport', 'bool');
    }
}
