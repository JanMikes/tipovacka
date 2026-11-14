<?php

declare(strict_types=1);

namespace App\Service\Email;

use App\Enum\NotificationType;
use App\Value\EmailDigest;
use App\Value\EmailDigestSection;
use App\Value\EmailItem;
use App\Value\UnsubscribeScope;
use Twig\Environment;

/**
 * Every mail the app sends, rendered with made-up data — what the admin
 * „E-maily" page shows, so a change to a template can be LOOKED at without
 * registering an account or waiting for a cron sweep.
 *
 * The samples are fiction (names, soutěže, links) and render the very same
 * templates the handlers do. When a handler gains a context variable, add it
 * here too — a preview that throws is the reminder.
 */
final readonly class EmailPreviews
{
    private const string SAMPLE_LINK = 'https://wtips.cz/odkaz-z-ukazky';

    public function __construct(
        private Environment $twig,
    ) {
    }

    /**
     * @return array<string, array{label: string, group: string, subject: string}> keyed by the preview's slug
     */
    public function list(): array
    {
        return array_map(
            static fn (array $preview): array => [
                'label' => $preview['label'],
                'group' => $preview['group'],
                'subject' => $preview['subject'],
            ],
            $this->previews(),
        );
    }

    /** The rendered HTML of one preview, or null for an unknown slug. */
    public function render(string $slug): ?string
    {
        $preview = $this->previews()[$slug] ?? null;

        if (null === $preview) {
            return null;
        }

        return $this->twig->render($preview['template'], $preview['context']);
    }

    /**
     * @return array<string, array{label: string, group: string, subject: string, template: string, context: array<string, mixed>}>
     */
    private function previews(): array
    {
        $links = ['unsubscribeUrl' => self::SAMPLE_LINK, 'settingsUrl' => self::SAMPLE_LINK];
        $soon = new \DateTimeImmutable('+1 hour');
        $nextWeek = new \DateTimeImmutable('+7 days');

        return [
            'overeni-emailu' => [
                'label' => 'Ověření e-mailu',
                'group' => 'Účet',
                'subject' => 'Potvrďte svůj e-mail a můžete tipovat',
                'template' => 'emails/verify_email.html.twig',
                'context' => ['nickname' => 'Kuba', 'verificationUrl' => self::SAMPLE_LINK.'?expires=1794000000&signature=Zk3VbQ2n8sT1xYpL0aRmC4dE5fG6hJ7k&token=q9W8e7R6t5Y4u3I2o1P0', 'expiresAt' => $soon],
            ],
            'uvitani' => [
                'label' => 'Uvítání po ověření',
                'group' => 'Účet',
                'subject' => 'Vítejte ve Wtips!',
                'template' => 'emails/welcome.html.twig',
                'context' => ['nickname' => 'Kuba', 'loginUrl' => self::SAMPLE_LINK],
            ],
            'obnova-hesla' => [
                'label' => 'Obnovení hesla',
                'group' => 'Účet',
                'subject' => 'Nastavte si nové heslo k Wtips',
                'template' => 'emails/password_reset.html.twig',
                'context' => ['userEmail' => 'kuba@example.com', 'resetUrl' => self::SAMPLE_LINK],
            ],
            'ucet-nenalezen' => [
                'label' => 'Obnova hesla — účet neexistuje',
                'group' => 'Účet',
                'subject' => 'Účet s touto adresou u nás není',
                'template' => 'emails/registration_invite.html.twig',
                'context' => ['userEmail' => 'kuba@example.com', 'registerUrl' => self::SAMPLE_LINK],
            ],
            'nakup-kreditu' => [
                'label' => 'Potvrzení nákupu kreditů',
                'group' => 'Účet',
                'subject' => 'Kredity připsány: +200',
                'template' => 'emails/credit_purchase_receipt.html.twig',
                'context' => ['nickname' => 'Kuba', 'credits' => 200, 'balance' => 245, 'creditsUrl' => self::SAMPLE_LINK, 'invoiceUrl' => self::SAMPLE_LINK],
            ],
            'pozvanka' => [
                'label' => 'Pozvánka do soutěže',
                'group' => 'Pozvánky',
                'subject' => 'Pavel vás zve do soutěže Hospoda U Lípy',
                'template' => 'emails/competition_invitation.html.twig',
                'context' => ['inviterNickname' => 'Pavel', 'competitionName' => 'Hospoda U Lípy', 'matchSourceName' => 'Chance Liga 2026/27', 'invitationUrl' => self::SAMPLE_LINK, 'expiresAt' => $nextWeek],
            ],
            'pozvanka-globalni' => [
                'label' => 'Pozvánka do globální soutěže',
                'group' => 'Pozvánky',
                'subject' => 'Pavel vás zve do soutěže Velká tipovačka Chance Ligy',
                'template' => 'emails/global_competition_invitation.html.twig',
                'context' => ['inviterNickname' => 'Pavel', 'competitionName' => 'Velká tipovačka Chance Ligy', 'matchSourceName' => 'Chance Liga 2026/27', 'entryFeeCredits' => 50, 'invitationUrl' => self::SAMPLE_LINK],
            ],
            'pripominka-tipu' => [
                'label' => 'Připomínka tipů (průběžně)',
                'group' => 'Oznámení',
                'subject' => 'Chybí vám 3 tipy',
                'template' => 'emails/notification.html.twig',
                'context' => $links + [
                    'title' => 'Chybí vám 3 tipy',
                    'body' => 'Blíží se uzávěrky a ve vašich soutěžích vám chybí tipy:',
                    'url' => '/zapasy',
                    'type' => NotificationType::GuessReminder,
                    'items' => [
                        new EmailItem('Divize F 2026/27', '1 tip · uzávěrka ne 15. 11. 10:15', '/zapasy'),
                        new EmailItem('Okresní přebor mužů 2026/27', '2 tipy · uzávěrka ne 15. 11. 13:30', '/zapasy'),
                    ],
                ],
            ],
            'nizky-zustatek' => [
                'label' => 'Jedno oznámení (nízký zůstatek)',
                'group' => 'Oznámení',
                'subject' => 'Nízký zůstatek kreditů',
                'template' => 'emails/notification.html.twig',
                'context' => $links + [
                    'title' => 'Nízký zůstatek kreditů',
                    'body' => 'Zůstatek vaší peněženky klesl na 15 kr. Doplňte si kredity, aby prémiové platby v soutěži Hospoda U Lípy prošly.',
                    'url' => '/kredity',
                    'type' => NotificationType::PremiumBalanceLow,
                    'items' => [],
                ],
            ],
            'denni-souhrn' => [
                'label' => 'Denní souhrn',
                'group' => 'Oznámení',
                'subject' => 'Chybí vám 3 tipy · vyhodnoceny 3 zápasy · 1 novinka',
                'template' => 'emails/digest.html.twig',
                'context' => $links + [
                    'digestHour' => 8,
                    'muteUrls' => [self::SAMPLE_LINK, self::SAMPLE_LINK, self::SAMPLE_LINK],
                    'digest' => new EmailDigest(
                        headline: 'Chybí vám 3 tipy',
                        summary: 'Vyhodnoceny 3 zápasy · 1 novinka',
                        sections: [
                            new EmailDigestSection(null, 'Divize F 2026/27', '/zapasy', new EmailItem('Chybí 1 tip', 'nejbližší uzávěrka ne 15. 11. 10:15', '/zapasy'), [
                                new EmailItem('Lhota 2:1 Újezd: získáváte 3 b., jste 2.'),
                            ]),
                            new EmailDigestSection(null, 'Okresní přebor mužů 2026/27', '/zapasy', new EmailItem('Chybí 2 tipy', 'nejbližší uzávěrka ne 15. 11. 13:30', '/zapasy'), []),
                            new EmailDigestSection(null, 'Hospoda U Lípy', '/zapasy', null, [
                                new EmailItem('Sparta 3:1 Plzeň: získáváte 5 b., jste 1.'),
                                new EmailItem('Slavia 0:0 Baník: získáváte 0 b., jste 1.'),
                                new EmailItem('Nový hráč v soutěži Hospoda U Lípy', 'Hráč Míra se připojil do soutěže Hospoda U Lípy.'),
                            ]),
                        ],
                        actionLabel: 'Natipovat',
                        actionUrl: '/zapasy',
                        daily: true,
                        unsubscribeScope: UnsubscribeScope::all(),
                    ),
                ],
            ],
        ];
    }
}
