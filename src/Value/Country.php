<?php

declare(strict_types=1);

namespace App\Value;

/**
 * A country of the ISO 3166-1 directory: its alpha-2 code (what we store), its
 * alpha-3 code (what the flag asset is named after) and its Czech name (what a
 * human picks in the admin).
 *
 * The registry below is the single source of truth for the „Země" field — the
 * admin picker lists it, validation accepts exactly its codes, and every flag in
 * `assets/flags/` is one 64px round WebP named by the alpha-3 code. It is
 * ordered by Czech name (diacritics folded), so callers never sort it again.
 */
final readonly class Country
{
    /**
     * alpha-2 => [alpha-3, Czech name], ordered by Czech name.
     *
     * @var array<string, array{string, string}>
     */
    private const array REGISTRY = [
        'AL' => ['ALB', 'Albánie'],
        'DZ' => ['DZA', 'Alžírsko'],
        'AS' => ['ASM', 'Americká Samoa'],
        'AD' => ['AND', 'Andorra'],
        'AO' => ['AGO', 'Angola'],
        'AI' => ['AIA', 'Anguilla'],
        'AQ' => ['ATA', 'Antarktida'],
        'AG' => ['ATG', 'Antigua a Barbuda'],
        'AR' => ['ARG', 'Argentina'],
        'AM' => ['ARM', 'Arménie'],
        'AW' => ['ABW', 'Aruba'],
        'AU' => ['AUS', 'Austrálie'],
        'AZ' => ['AZE', 'Ázerbajdžán'],
        'BS' => ['BHS', 'Bahamy'],
        'BH' => ['BHR', 'Bahrajn'],
        'JE' => ['JEY', 'Bailiwick Jersey'],
        'BD' => ['BGD', 'Bangladéš'],
        'BB' => ['BRB', 'Barbados'],
        'BE' => ['BEL', 'Belgie'],
        'BZ' => ['BLZ', 'Belize'],
        'BY' => ['BLR', 'Bělorusko'],
        'BJ' => ['BEN', 'Benin'],
        'BM' => ['BMU', 'Bermudy'],
        'BT' => ['BTN', 'Bhútánské království'],
        'BO' => ['BOL', 'Bolívie'],
        'BQ' => ['BES', 'Bonaire, Svatý Eustach a Saba'],
        'BA' => ['BIH', 'Bosna-Hercegovina'],
        'BW' => ['BWA', 'Botswana'],
        'BV' => ['BVT', 'Bouvetův ostrov'],
        'BR' => ['BRA', 'Brazílie'],
        'IO' => ['IOT', 'Britské indickooceánské terit.'],
        'VG' => ['VGB', 'Britské Panenské ostrovy'],
        'BN' => ['BRN', 'Brunej Darussalam'],
        'BG' => ['BGR', 'Bulharsko'],
        'BF' => ['BFA', 'Burkina Faso'],
        'BI' => ['BDI', 'Burundi'],
        'TD' => ['TCD', 'Čad'],
        'ME' => ['MNE', 'Černá Hora'],
        'CZ' => ['CZE', 'Česká republika'],
        'CL' => ['CHL', 'Chile'],
        'HR' => ['HRV', 'Chorvatsko'],
        'CN' => ['CHN', 'ČLR'],
        'CK' => ['COK', 'Cookovy ostrovy'],
        'CW' => ['CUW', 'Curaçao'],
        'DK' => ['DNK', 'Dánsko'],
        'TL' => ['TLS', 'Demokratická republika Východní Timor'],
        'DM' => ['DMA', 'Dominika'],
        'DO' => ['DOM', 'Dominikánská republika'],
        'DJ' => ['DJI', 'Džibutsko'],
        'EG' => ['EGY', 'Egypt'],
        'EC' => ['ECU', 'Ekvádor'],
        'ER' => ['ERI', 'Eritrea'],
        'EE' => ['EST', 'Estonsko'],
        'ET' => ['ETH', 'Etiopie'],
        'FO' => ['FRO', 'Faerské ostrovy'],
        'FK' => ['FLK', 'Falklandy'],
        'FM' => ['FSM', 'Federativní státy a Mikronésie'],
        'FJ' => ['FJI', 'Fidži'],
        'PH' => ['PHL', 'Filipíny'],
        'FI' => ['FIN', 'Finsko'],
        'FR' => ['FRA', 'Francie'],
        'GF' => ['GUF', 'Francouzská Guayana'],
        'TF' => ['ATF', 'Francouzská jižní území'],
        'PF' => ['PYF', 'Francouzská Polynésie'],
        'GA' => ['GAB', 'Gabon'],
        'GM' => ['GMB', 'Gambie'],
        'GH' => ['GHA', 'Ghana'],
        'GI' => ['GIB', 'Gibraltar'],
        'GD' => ['GRD', 'Grenada'],
        'GL' => ['GRL', 'Grónsko'],
        'GE' => ['GEO', 'Gruzie'],
        'GP' => ['GLP', 'Guadeloupe'],
        'GU' => ['GUM', 'Guam'],
        'GT' => ['GTM', 'Guatemala'],
        'GG' => ['GGY', 'Guernsey'],
        'GN' => ['GIN', 'Guinea'],
        'GW' => ['GNB', 'Guinea-Bissau'],
        'GY' => ['GUY', 'Guyana'],
        'HT' => ['HTI', 'Haiti'],
        'HM' => ['HMD', 'Heardův a MacDonaldův o.'],
        'HN' => ['HND', 'Honduras'],
        'HK' => ['HKG', 'Hongkong'],
        'IN' => ['IND', 'Indie'],
        'ID' => ['IDN', 'Indonésie'],
        'IQ' => ['IRQ', 'Irák'],
        'IR' => ['IRN', 'Irán'],
        'IE' => ['IRL', 'Irsko'],
        'IS' => ['ISL', 'Island'],
        'IT' => ['ITA', 'Itálie'],
        'IL' => ['ISR', 'Izrael'],
        'JM' => ['JAM', 'Jamajka'],
        'JP' => ['JPN', 'Japonsko'],
        'YE' => ['YEM', 'Jemen'],
        'ZA' => ['ZAF', 'Jihoafrická republika'],
        'SS' => ['SSD', 'Jihosúdánská republika'],
        'GS' => ['SGS', 'Jižní Georgie a Jižní Sanwich. o.'],
        'KR' => ['KOR', 'Jižní Korea'],
        'JO' => ['JOR', 'Jordánsko'],
        'KY' => ['CYM', 'Kajmanské ostrovy'],
        'KH' => ['KHM', 'Kambodža'],
        'CM' => ['CMR', 'Kamerun'],
        'CA' => ['CAN', 'Kanada'],
        'CV' => ['CPV', 'Kapverdy'],
        'QA' => ['QAT', 'Katar'],
        'KZ' => ['KAZ', 'Kazachstán'],
        'KE' => ['KEN', 'Keňa'],
        'KI' => ['KIR', 'Kiribati'],
        'KP' => ['PRK', 'KLDR'],
        'CC' => ['CCK', 'Kokosové ostrovy'],
        'CO' => ['COL', 'Kolumbie'],
        'KM' => ['COM', 'Komory'],
        'CD' => ['COD', 'Kongo, demokratická republika'],
        'CG' => ['COG', 'Konžská republika'],
        'XK' => ['XKX', 'Kosovská republika'],
        'CR' => ['CRI', 'Kostarika'],
        'CU' => ['CUB', 'Kuba'],
        'KW' => ['KWT', 'Kuvajt'],
        'CY' => ['CYP', 'Kypr'],
        'KG' => ['KGZ', 'Kyrgyzstán'],
        'LA' => ['LAO', 'Laos'],
        'LS' => ['LSO', 'Lesotho'],
        'LB' => ['LBN', 'Libanon'],
        'LR' => ['LBR', 'Libérie'],
        'LY' => ['LBY', 'Libye'],
        'LI' => ['LIE', 'Lichtenštejnsko'],
        'LT' => ['LTU', 'Litva'],
        'LV' => ['LVA', 'Lotyšsko'],
        'LU' => ['LUX', 'Lucembursko'],
        'MO' => ['MAC', 'Macao'],
        'MG' => ['MDG', 'Madagaskar'],
        'HU' => ['HUN', 'Maďarsko'],
        'MK' => ['MKD', 'Makedonie'],
        'MY' => ['MYS', 'Malajsie'],
        'MW' => ['MWI', 'Malawi'],
        'MV' => ['MDV', 'Maledivy'],
        'ML' => ['MLI', 'Mali'],
        'MT' => ['MLT', 'Malta'],
        'MA' => ['MAR', 'Maroko'],
        'MH' => ['MHL', 'Marshallovy ostrovy'],
        'MQ' => ['MTQ', 'Martinik'],
        'MU' => ['MUS', 'Mauricius'],
        'MR' => ['MRT', 'Mauritánie'],
        'YT' => ['MYT', 'Mayotte'],
        'MX' => ['MEX', 'Mexiko'],
        'MD' => ['MDA', 'Moldavsko'],
        'MC' => ['MCO', 'Monako'],
        'MN' => ['MNG', 'Mongolsko'],
        'MS' => ['MSR', 'Montserrat'],
        'MZ' => ['MOZ', 'Mosambik'],
        'MM' => ['MMR', 'Myanmar (Barma)'],
        'NA' => ['NAM', 'Namíbie'],
        'NR' => ['NRU', 'Nauru'],
        'DE' => ['DEU', 'Německo (od r. 1991)'],
        'NP' => ['NPL', 'Nepál'],
        'NE' => ['NER', 'Niger'],
        'NG' => ['NGA', 'Nigérie'],
        'NI' => ['NIC', 'Nikaragua'],
        'NU' => ['NIU', 'Niue'],
        'NL' => ['NLD', 'Nizozemí'],
        'NF' => ['NFK', 'Norfolk'],
        'NO' => ['NOR', 'Norsko'],
        'NC' => ['NCL', 'Nová Kaledonie'],
        'NZ' => ['NZL', 'Nový Zéland'],
        'OM' => ['OMN', 'Omán'],
        'IM' => ['IMN', 'Ostrov Man'],
        'UM' => ['UMI', 'Ostrovy USA v Tichém o.'],
        'PK' => ['PAK', 'Pákistán'],
        'PW' => ['PLW', 'Palau'],
        'PS' => ['PSE', 'Palestina'],
        'PA' => ['PAN', 'Panama'],
        'VI' => ['VIR', 'Panenské ostrovy (USA)'],
        'PG' => ['PNG', 'Papua-Nová Guinea'],
        'PY' => ['PRY', 'Paraguay'],
        'PE' => ['PER', 'Peru'],
        'PN' => ['PCN', 'Pitcairnovy ostrovy'],
        'CI' => ['CIV', 'Pobřeží slonoviny'],
        'PL' => ['POL', 'Polsko'],
        'PR' => ['PRI', 'Portoriko'],
        'PT' => ['PRT', 'Portugalsko'],
        'AT' => ['AUT', 'Rakousko'],
        'GR' => ['GRC', 'Řecko'],
        'RE' => ['REU', 'Réunion'],
        'GQ' => ['GNQ', 'Rovníková Guinea'],
        'RO' => ['ROU', 'Rumunsko'],
        'RU' => ['RUS', 'Rusko'],
        'RW' => ['RWA', 'Rwanda'],
        'PM' => ['SPM', 'Saint Pierre a Miquelon'],
        'SB' => ['SLB', 'Šalomounovy ostrovy'],
        'SV' => ['SLV', 'Salvador'],
        'WS' => ['WSM', 'Samoa'],
        'SM' => ['SMR', 'San Marino'],
        'SA' => ['SAU', 'Saúdská Arábie'],
        'SN' => ['SEN', 'Senegal'],
        'MP' => ['MNP', 'Severní Marianny'],
        'SC' => ['SYC', 'Seychelly'],
        'SL' => ['SLE', 'Sierra Leone'],
        'SG' => ['SGP', 'Singapur'],
        'SK' => ['SVK', 'Slovensko'],
        'SI' => ['SVN', 'Slovinsko'],
        'SO' => ['SOM', 'Somalsko'],
        'ES' => ['ESP', 'Španělsko'],
        'SJ' => ['SJM', 'Špicberky'],
        'AE' => ['ARE', 'Spojené arabské emiráty'],
        'RS' => ['SRB', 'Srbsko'],
        'LK' => ['LKA', 'Srí Lanka'],
        'CF' => ['CAF', 'Středoafrická republika'],
        'SD' => ['SDN', 'Súdán'],
        'SR' => ['SUR', 'Surinam'],
        'SH' => ['SHN', 'Sv. Helena'],
        'KN' => ['KNA', 'Sv. Kryštof'],
        'LC' => ['LCA', 'Sv. Lucie'],
        'MF' => ['MAF', 'Sv. Martin (FR)'],
        'SX' => ['SXM', 'Sv. Martin (NL)'],
        'ST' => ['STP', 'Sv. Tomáš'],
        'VC' => ['VCT', 'Sv. Vincenc a Grenadiny'],
        'BL' => ['BLM', 'Svatý Bartoloměj'],
        'SZ' => ['SWZ', 'Svazijsko'],
        'SE' => ['SWE', 'Švédsko'],
        'CH' => ['CHE', 'Švýcarsko'],
        'SY' => ['SYR', 'Sýrie'],
        'TJ' => ['TJK', 'Tádžikistán'],
        'TZ' => ['TZA', 'Tanzánie'],
        'TW' => ['TWN', 'Tchaj-wan'],
        'TH' => ['THA', 'Thajsko'],
        'TG' => ['TGO', 'Togo'],
        'TK' => ['TKL', 'Tokelau'],
        'TO' => ['TON', 'Tonga'],
        'TT' => ['TTO', 'Trinidad a Tobago'],
        'TN' => ['TUN', 'Tunisko'],
        'TR' => ['TUR', 'Turecko'],
        'TU' => ['TKM', 'Turkmenistán'],
        'TC' => ['TCA', 'Turks a Caicos'],
        'TV' => ['TUV', 'Tuvalu'],
        'UG' => ['UGA', 'Uganda'],
        'UA' => ['UKR', 'Ukrajina'],
        'UY' => ['URY', 'Uruguay'],
        'US' => ['USA', 'USA'],
        'UZ' => ['UZB', 'Uzbekistán'],
        'CX' => ['CXR', 'Vánoční ostrovy'],
        'VU' => ['VUT', 'Vanuatu'],
        'VA' => ['VAT', 'Vatikán'],
        'GB' => ['GBR', 'Velká Británie'],
        'VE' => ['VEN', 'Venezuela'],
        'VN' => ['VNM', 'Vietnam'],
        'WF' => ['WLF', 'Wallisovy ostrovy'],
        'ZM' => ['ZMB', 'Zambie'],
        'EH' => ['ESH', 'Západní Sahara'],
        'ZW' => ['ZWE', 'Zimbabwe'],
    ];

    /** AssetMapper logical path of the round flag, e.g. „flags/CZE.webp". */
    public string $flagAssetPath;

    private function __construct(
        /** ISO 3166-1 alpha-2, upper-cased — the value stored on a team. */
        public string $alpha2,
        /** ISO 3166-1 alpha-3, upper-cased — names the flag asset. */
        public string $alpha3,
        /** Czech name, e.g. „Česká republika". */
        public string $name,
    ) {
        $this->flagAssetPath = 'flags/'.$this->alpha3.'.webp';
    }

    /** Null for null, blank or unknown input — so templates can call it unconditionally. */
    public static function tryFrom(?string $alpha2): ?self
    {
        if (null === $alpha2) {
            return null;
        }

        $alpha2 = mb_strtoupper(trim($alpha2));
        $entry = self::REGISTRY[$alpha2] ?? null;

        if (null === $entry) {
            return null;
        }

        return new self($alpha2, $entry[0], $entry[1]);
    }

    /**
     * Every country, ordered by Czech name.
     *
     * @return list<self>
     */
    public static function all(): array
    {
        return array_map(
            static fn (string $alpha2): self => new self($alpha2, self::REGISTRY[$alpha2][0], self::REGISTRY[$alpha2][1]),
            array_keys(self::REGISTRY),
        );
    }

    /**
     * Czech name => alpha-2 code, ordered by Czech name — the shape ChoiceType wants.
     *
     * @return array<string, string>
     */
    public static function choices(): array
    {
        return array_combine(
            array_map(static fn (array $entry): string => $entry[1], self::REGISTRY),
            array_keys(self::REGISTRY),
        );
    }

    /**
     * The only accepted alpha-2 codes — what the „Země" field validates against.
     *
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_keys(self::REGISTRY);
    }
}
