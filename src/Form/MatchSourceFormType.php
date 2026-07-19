<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Sport;
use App\Enum\CompetitionMonetization;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<MatchSourceFormData>
 */
final class MatchSourceFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Sport is chosen once at creation — it drives the period structure
        // (poločasy / třetiny), so it cannot change on edit.
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
            'label' => 'Název zdroje zápasů',
            'attr' => [
                'placeholder' => 'Např. Liga mistrů 2026/27',
            ],
        ]);

        $builder->add('description', TextareaType::class, [
            'label' => 'Popis',
            'required' => false,
            'attr' => [
                'placeholder' => 'Stručný popis zdroje zápasů…',
                'rows' => 4,
            ],
        ]);

        $builder->add('startAt', DateTimeType::class, [
            'label' => 'Začátek',
            'required' => false,
            'widget' => 'single_text',
            'input' => 'datetime_immutable',
            'html5' => false,
            'with_seconds' => false,
            'format' => 'yyyy-MM-dd HH:mm',
            // Stored in UTC, entered/displayed in Czech local time.
            'model_timezone' => 'UTC',
            'view_timezone' => 'Europe/Prague',
        ]);

        $builder->add('endAt', DateTimeType::class, [
            'label' => 'Konec',
            'required' => false,
            'widget' => 'single_text',
            'input' => 'datetime_immutable',
            'html5' => false,
            'with_seconds' => false,
            'format' => 'yyyy-MM-dd HH:mm',
            // Stored in UTC, entered/displayed in Czech local time.
            'model_timezone' => 'UTC',
            'view_timezone' => 'Europe/Prague',
        ]);

        // „Rovnou vytvořit globální soutěž" — offered on curated create only.
        if (true === $options['with_global_option']) {
            $builder->add('createGlobalCompetition', CheckboxType::class, [
                'label' => 'Rovnou vytvořit globální soutěž nad tímto zdrojem',
                'required' => false,
            ]);

            $builder->add('globalCompetitionName', TextType::class, [
                'label' => 'Název globální soutěže',
                'required' => false,
                'attr' => ['placeholder' => 'Nechte prázdné pro převzetí názvu zdroje'],
            ]);

            $builder->add('globalCompetitionEntryFee', IntegerType::class, [
                'label' => 'Vstupné (kredity)',
                'required' => false,
                'help' => '0 = zdarma. Vstupné se strhne jednou při připojení a je nevratné.',
                'attr' => ['min' => 0],
            ]);

            $builder->add('globalCompetitionMonetization', EnumType::class, [
                'label' => 'Monetizace globální soutěže',
                'class' => CompetitionMonetization::class,
                'choice_label' => static fn (CompetitionMonetization $monetization): string => $monetization->label(),
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MatchSourceFormData::class,
            'with_sport' => false,
            'with_global_option' => false,
        ]);
        $resolver->setAllowedTypes('with_sport', 'bool');
        $resolver->setAllowedTypes('with_global_option', 'bool');
    }
}
