<?php

declare(strict_types=1);

namespace App\Form;

use App\Rule\RuleRegistry;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<TournamentRuleConfigurationFormData>
 */
final class TournamentRuleConfigurationFormType extends AbstractType
{
    public function __construct(
        private readonly RuleRegistry $ruleRegistry,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $rulesBuilder = $builder->create('rules', null, [
            'compound' => true,
            'label' => false,
        ]);

        foreach ($this->ruleRegistry->all() as $rule) {
            $rulesBuilder->add($rule->identifier, RuleConfigurationEntryFormType::class, [
                'label' => $rule->label,
            ]);
        }

        $builder->add($rulesBuilder);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TournamentRuleConfigurationFormData::class,
        ]);
    }
}
