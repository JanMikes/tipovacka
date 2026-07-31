<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Sport;
use App\Value\Country;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<TeamFormData>
 */
final class TeamFormType extends AbstractType
{
    public function __construct(
        private readonly Packages $packages,
    ) {
    }

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
            'empty_data' => '',
            'attr' => ['placeholder' => 'Např. Sparta Praha'],
        ]);

        $builder->add('shortName', TextType::class, [
            'label' => 'Zkratka',
            'required' => false,
            'help' => 'Nepovinné. Krátký kód pro úzké prostory, např. „SPA".',
            'attr' => ['placeholder' => 'Např. SPA'],
        ]);

        // A plain <select> over the whole ISO directory, upgraded by tom-select into
        // a searchable list with flags. Without JavaScript it is still a working
        // select — the flag lives in `data-flag`, which the picker reads.
        $builder->add('country', ChoiceType::class, [
            'label' => 'Země',
            'required' => false,
            'placeholder' => 'Bez země',
            'choices' => Country::choices(),
            'choice_attr' => fn (?string $alpha2): array => null !== $alpha2 && null !== ($country = Country::tryFrom($alpha2))
                ? ['data-flag' => $this->packages->getUrl($country->flagAssetPath)]
                : [],
            'help' => 'Nepovinné. Zobrazí vlajku země u znaku týmu.',
            'attr' => [
                'data-controller' => 'tom-select',
                'data-tom-select-placeholder-value' => 'Vyhledejte zemi…',
                'data-tom-select-no-results-text-value' => 'Žádná země nenalezena',
            ],
        ]);

        $builder->add('brandColor', TextType::class, [
            'label' => 'Barva týmu',
            'required' => false,
            'help' => 'Nepovinné. HEX barva monogramu, např. #EE1C25. Prázdné = barva se odvodí z názvu.',
            'attr' => ['placeholder' => '#EE1C25'],
        ]);

        $builder->add('logoFile', FileType::class, [
            'label' => 'Logo',
            'required' => false,
            'help' => 'Nepovinné. PNG, JPG, WebP nebo GIF do 2 MB — uloží se jako WebP do 256 × 256 px. Bez loga se vykreslí monogram.',
            'attr' => ['accept' => 'image/png,image/jpeg,image/webp,image/gif'],
        ]);

        // Offered only where there is something to remove (edit of a team with a logo).
        if (true === $options['with_logo_removal']) {
            $builder->add('removeLogo', CheckboxType::class, [
                'label' => 'Odebrat stávající logo',
                'required' => false,
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TeamFormData::class,
            'with_sport' => false,
            'with_logo_removal' => false,
        ]);
        $resolver->setAllowedTypes('with_sport', 'bool');
        $resolver->setAllowedTypes('with_logo_removal', 'bool');
    }
}
