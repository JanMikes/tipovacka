<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\MatchSource;
use App\Enum\CompetitionMonetization;
use App\Enum\MatchSourceKind;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<GlobalCompetitionFormData>
 */
final class GlobalCompetitionFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Source + name are chosen once at creation. On edit only fee +
        // monetization are editable (and only until the first member joins —
        // enforced by UpdateGlobalCompetitionHandler).
        if (true === $options['with_source']) {
            $builder->add('matchSource', EntityType::class, [
                'label' => 'Zdroj zápasů',
                'class' => MatchSource::class,
                'choice_label' => 'name',
                'placeholder' => 'Vyberte zdroj zápasů…',
                'query_builder' => static fn (EntityRepository $repository): QueryBuilder => $repository->createQueryBuilder('t')
                    ->where('t.kind = :kind')
                    ->andWhere('t.completedAt IS NULL')
                    ->andWhere('t.deletedAt IS NULL')
                    ->setParameter('kind', MatchSourceKind::Curated)
                    ->orderBy('t.name', 'ASC'),
            ]);

            $builder->add('name', TextType::class, [
                'label' => 'Název soutěže',
                'attr' => ['placeholder' => 'Např. Tipovačka Ligy mistrů'],
            ]);
        }

        $builder->add('entryFeeCredits', IntegerType::class, [
            'label' => 'Vstupné (kredity)',
            'help' => '0 = zdarma. Vstupné se strhne jednou při připojení a je nevratné.',
            'attr' => ['min' => 0],
        ]);

        $builder->add('monetization', EnumType::class, [
            'label' => 'Monetizace',
            'class' => CompetitionMonetization::class,
            'choice_label' => static fn (CompetitionMonetization $monetization): string => $monetization->label(),
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => GlobalCompetitionFormData::class,
            'with_source' => true,
            'validation_groups' => static fn (\Symfony\Component\Form\FormInterface $form): array => true === $form->getConfig()->getOption('with_source')
                ? ['Default', 'create']
                : ['Default'],
        ]);
        $resolver->setAllowedTypes('with_source', 'bool');
    }
}
