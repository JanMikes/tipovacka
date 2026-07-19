<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\MatchSource;
use App\Enum\CompetitionMatchSelectionMode;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @extends AbstractType<CompetitionFormData>
 */
final class CompetitionFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', TextType::class, [
            'label' => 'Název soutěže',
            'attr' => [
                'placeholder' => 'Např. Kámoši u piva',
            ],
        ]);

        $builder->add('description', TextareaType::class, [
            'label' => 'Popis',
            'required' => false,
            'attr' => [
                'placeholder' => 'Stručný popis soutěže…',
                'rows' => 3,
            ],
        ]);

        $withPinOptions = [
            'label' => 'Vygenerovat PIN pro připojení',
            'required' => false,
        ];

        if (true === ($options['with_pin_disabled'] ?? false)) {
            $withPinOptions['disabled'] = true;
            $withPinOptions['help'] = 'PIN lze spravovat po vytvoření v detailu soutěže.';
        }

        $builder->add('withPin', CheckboxType::class, $withPinOptions);

        $builder->add('hideOthersTipsBeforeDeadline', CheckboxType::class, [
            'label' => 'Schovat tipy ostatních před uzávěrkou',
            'required' => false,
            'help' => 'Když je zapnuto, ostatní členové uvidí tvůj tip až po uzávěrce zápasu. Ty samozřejmě vždy vidíš svoje.',
        ]);

        if (true !== $options['with_source_selection']) {
            return;
        }

        /** @var list<MatchSource> $availableSources */
        $availableSources = $options['available_sources'];

        $sourceChoices = [];

        foreach ($availableSources as $source) {
            $sourceChoices[$source->name] = $source->id->toRfc4122();
        }

        $builder->add('matchSourceId', ChoiceType::class, [
            'label' => 'Zdroj zápasů',
            'choices' => $sourceChoices,
            'placeholder' => 'Vyberte zdroj zápasů…',
            'help' => 'Rozpis zápasů, nad kterým se soutěž tipuje.',
            'constraints' => [
                new NotBlank(message: 'Vyberte prosím zdroj zápasů.'),
            ],
        ]);

        $builder->add('selectionMode', EnumType::class, [
            'label' => 'Zápasy soutěže',
            'class' => CompetitionMatchSelectionMode::class,
            'expanded' => true,
            'choice_label' => static fn (CompetitionMatchSelectionMode $mode): string => match ($mode) {
                CompetitionMatchSelectionMode::All => 'Všechny zápasy',
                CompetitionMatchSelectionMode::Subset => 'Vybrat jen některé zápasy',
            },
        ]);

        $builder->add('includePlayoff', CheckboxType::class, [
            'label' => 'Zahrnout playoff zápasy',
            'required' => false,
            'help' => 'Playoff zápasy přibývají do rozpisu postupně. Když je vypnete, soutěž se tipuje jen na základní část.',
        ]);

        /** @var callable(string): array<string, string> $matchChoicesProvider keyed label => match UUID */
        $matchChoicesProvider = $options['match_choices_provider'];
        /** @var callable(string): array<string, array<string, string>> $matchChoiceAttrProvider match UUID => attr */
        $matchChoiceAttrProvider = $options['match_choice_attr_provider'];

        $addSelectedMatchIds = static function (FormBuilderInterface|\Symfony\Component\Form\FormInterface $form, ?string $sourceId) use ($matchChoicesProvider, $matchChoiceAttrProvider): void {
            $choices = null !== $sourceId ? $matchChoicesProvider($sourceId) : [];
            $attrByValue = null !== $sourceId ? $matchChoiceAttrProvider($sourceId) : [];

            $form->add('selectedMatchIds', ChoiceType::class, [
                'label' => 'Vybrané zápasy',
                'choices' => $choices,
                'choice_attr' => static fn (string $value): array => $attrByValue[$value] ?? [],
                'expanded' => true,
                'multiple' => true,
                'required' => false,
            ]);
        };

        $initialSourceId = $options['initial_source_id'];
        \assert(null === $initialSourceId || \is_string($initialSourceId));
        $addSelectedMatchIds($builder, $initialSourceId);

        // Rebuild the match checkbox choices from the *submitted* source so a
        // source switched without page reload cannot smuggle in foreign matches.
        $builder->addEventListener(FormEvents::PRE_SUBMIT, static function (FormEvent $event) use ($addSelectedMatchIds): void {
            /** @var array<string, mixed> $data */
            $data = $event->getData();
            $sourceId = $data['matchSourceId'] ?? null;

            $addSelectedMatchIds($event->getForm(), \is_string($sourceId) && '' !== $sourceId ? $sourceId : null);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CompetitionFormData::class,
            'with_pin_disabled' => false,
            'with_source_selection' => false,
            'available_sources' => [],
            'initial_source_id' => null,
            'match_choices_provider' => static fn (string $sourceId): array => [],
            'match_choice_attr_provider' => static fn (string $sourceId): array => [],
        ]);
        $resolver->setAllowedTypes('with_pin_disabled', 'bool');
        $resolver->setAllowedTypes('with_source_selection', 'bool');
        $resolver->setAllowedTypes('available_sources', MatchSource::class.'[]');
        $resolver->setAllowedTypes('initial_source_id', ['null', 'string']);
        $resolver->setAllowedTypes('match_choices_provider', 'callable');
        $resolver->setAllowedTypes('match_choice_attr_provider', 'callable');
    }
}
