<?php

namespace App\Services\Payment;

use App\Models\Project;
use App\Services\Payment\DTOs\FeeQuote;
use RuntimeException;

/**
 * Moteur de calcul « Montant Reçu → Montant Envoyé ».
 *
 * Règle métier : l'investisseur supporte la TOTALITÉ des frais.
 * Le porteur de projet doit donc encaisser exactement le « Montant Reçu ».
 *
 * Deux moyens de paiement, au choix de l'utilisateur :
 *   • mobile_money → PawaPay  (frais par pays/opérateur, config/pawapay.php)
 *   • card         → PayDunya (carte bancaire 3,50 %, config/paydunya.php)
 *
 * On raisonne en deux temps sur le cycle réel de l'argent :
 *
 *  1. DÉCAISSEMENT (wallet plateforme → compte du porteur)
 *     Les frais de payout sont facturés à l'entreprise EN PLUS du montant
 *     versé. Pour que le porteur touche `net`, le wallet doit contenir :
 *         wallet = net + (net × payout% + payout_fixe) + commission
 *
 *  2. COLLECTE (investisseur → wallet plateforme)
 *     Le PSP prélève ses frais SUR le montant encaissé. Pour que `wallet`
 *     arrive effectivement, l'investisseur doit envoyer :
 *         gross = (wallet + collect_fixe) / (1 − collect%)
 *
 * On résout donc la majoration (« gross-up ») au lieu d'un simple « + x % »,
 * ce qui garantit au centime près que le porteur reçoit son montant cible.
 *
 * La commission plateforme est assise sur le Montant Reçu ; la tranche du
 * barème dégressif est déterminée après conversion en EUR (devise des pivots).
 */
class FeeCalculator
{
    public const METHOD_MOBILE_MONEY = 'mobile_money';
    public const METHOD_CARD         = 'card';

    public function __construct(
        protected CurrencyService $currency,
    ) {}

    /**
     * Moyens de paiement réellement proposables.
     *
     * Un moyen est écarté s'il est désactivé par configuration OU si son PSP
     * n'a pas d'identifiants : sans ce filtre, un environnement où seules les
     * clés PayDunya sont renseignées afficherait quand même « Mobile Money »
     * et échouerait au moment du paiement.
     */
    public function availableMethods(): array
    {
        $methods = [];

        foreach (config('payments.methods', []) as $key => $conf) {
            if (!($conf['enabled'] ?? true)) {
                continue;
            }
            if (!$this->gatewayConfigured((string) ($conf['gateway'] ?? ''))) {
                continue;
            }
            $methods[$key] = [
                'key'         => $key,
                'label'       => $conf['label'] ?? $key,
                'description' => $conf['description'] ?? null,
                'icon'        => $conf['icon'] ?? null,
                'gateway'     => $conf['gateway'] ?? null,
            ];
        }

        return $methods;
    }

    /** Le PSP dispose-t-il de ses identifiants dans cet environnement ? */
    public function gatewayConfigured(string $gateway): bool
    {
        return match (strtolower($gateway)) {
            'pawapay'  => filled(config('pawapay.api_token')),
            'paydunya' => filled(config('paydunya.master_key')),
            default    => false,
        };
    }

    public function defaultMethod(): string
    {
        $available = $this->availableMethods();
        $default   = (string) config('payments.default_method', self::METHOD_MOBILE_MONEY);

        if (isset($available[$default])) {
            return $default;
        }

        // Aucun PSP configuré : on renvoie quand même un moyen nominal pour ne
        // pas casser les devis d'affichage — la tentative de paiement lèvera
        // alors une erreur explicite « PSP non configuré ».
        return (string) (array_key_first($available) ?? $default);
    }

    /** PSP qui traite un moyen de paiement donné. */
    public function gatewayForMethod(string $method): string
    {
        return (string) (config("payments.methods.{$method}.gateway")
            ?? config('payments.default_gateway', 'pawapay'));
    }

    /** Devise de règlement du marché d'un pays (ISO-2). */
    public function marketCurrency(string $countryIso2): string
    {
        return (string) ($this->market($countryIso2)['currency'] ?? 'XOF');
    }

    /**
     * Devis complet pour un projet donné, à partir du montant que le porteur
     * doit recevoir (exprimé dans la devise du marché du projet).
     */
    public function quoteForProject(
        Project $project,
        float $netAmount,
        ?string $method = null,
        ?string $provider = null,
    ): FeeQuote {
        return $this->quote(
            countryIso2: $this->resolveCountry($project),
            netAmount: $netAmount,
            method: $method,
            provider: $provider ?: ($project->payout_mobile_provider ?: null),
            projectCurrency: strtoupper((string) ($project->currency ?: 'EUR')),
        );
    }

    /**
     * Devis générique.
     *
     * @param string      $countryIso2     Pays du PROJET (assiette des frais)
     * @param float       $netAmount       Montant Reçu, dans la devise du marché
     * @param string|null $method          mobile_money | card
     * @param string|null $provider        Opérateur mobile money (PawaPay)
     * @param string|null $projectCurrency Devise d'affichage du projet (EUR…)
     */
    public function quote(
        string $countryIso2,
        float $netAmount,
        ?string $method = null,
        ?string $provider = null,
        ?string $projectCurrency = null,
    ): FeeQuote {
        if ($netAmount <= 0) {
            throw new RuntimeException('Le montant reçu doit être supérieur à zéro.');
        }

        $method = $this->normalizeMethod($method);

        // Un pays hors marchés couverts retombe sur le marché par défaut : le
        // devis doit annoncer le marché RÉELLEMENT appliqué, pas celui demandé.
        $countryIso2 = strtoupper($countryIso2);
        if (!$this->hasMarket($countryIso2)) {
            $countryIso2 = (string) config('pawapay.default_market', 'SN');
        }

        $market   = $this->market($countryIso2);
        $currency = (string) $market['currency'];
        $decimals = (int) ($market['decimals'] ?? 0);

        [$providerCode, $providerLabel, $collect, $payout] =
            $this->resolveFees($method, $countryIso2, $market, $provider);

        $net = $this->roundTo($netAmount, $decimals);

        // --- 1. Commission plateforme (barème dégressif sur le Montant Reçu) ---
        $rate       = $this->commissionRate($net, $currency);
        $commission = $this->roundTo($net * $rate, $decimals);

        // --- 2. Frais de décaissement vers le porteur ---
        $payoutFee = $this->roundTo($net * $payout['percent'] + $payout['fixed'], $decimals);

        // Montant qui doit être disponible dans le wallet plateforme.
        $wallet = $net + $commission + $payoutFee;

        // --- 3. Gross-up de la collecte ---
        $cp    = min(0.5, max(0.0, $collect['percent'])); // garde-fou : < 50 %
        $gross = ($wallet + $collect['fixed']) / (1 - $cp);
        $gross = $this->roundUpTo($gross, $decimals);

        $collectionFee = $this->roundTo($gross - $wallet, $decimals);

        // Frais éventuellement prélevés par l'opérateur au payeur (hors flux).
        $customerFee = $this->roundTo(
            $gross * (float) ($collect['customer_percent'] ?? 0) + (float) ($collect['customer_fixed'] ?? 0),
            $decimals,
        );

        // --- 4. Équivalents dans la devise du projet (affichage) ---
        $netProject = $grossProject = null;
        $projectCurrency = $projectCurrency ? strtoupper($projectCurrency) : null;
        if ($projectCurrency && $projectCurrency !== $currency) {
            $fx           = $this->currency->getRate($currency, $projectCurrency);
            $netProject   = round($net * $fx, 2);
            $grossProject = round($gross * $fx, 2);
        } elseif ($projectCurrency) {
            $netProject   = $net;
            $grossProject = $gross;
        }

        return new FeeQuote(
            netAmount:           $net,
            grossAmount:         $gross,
            commissionAmount:    $commission,
            commissionRate:      $rate,
            collectionFee:       $collectionFee,
            payoutFee:           $payoutFee,
            currency:            $currency,
            country:             $countryIso2,
            countryName:         (string) ($market['name'] ?? $countryIso2),
            provider:            $providerCode,
            providerLabel:       $providerLabel,
            method:              $method,
            gateway:             $this->gatewayForMethod($method),
            customerOperatorFee: $customerFee,
            netAmountProjectCurrency:   $netProject,
            grossAmountProjectCurrency: $grossProject,
            projectCurrency:            $projectCurrency,
        );
    }

    /**
     * Devis pour chaque moyen de paiement activé — permet au popup d'afficher
     * les deux options côte à côte avec leur coût réel.
     *
     * @return array<string, FeeQuote>
     */
    public function quoteAllMethods(Project $project, float $netAmount): array
    {
        $quotes = [];

        foreach (array_keys($this->availableMethods()) as $method) {
            $quotes[$method] = $this->quoteForProject($project, $netAmount, $method);
        }

        return $quotes;
    }

    /**
     * Taux de commission applicable à un montant, d'après le barème dégressif.
     * Le montant est converti dans la devise des pivots (EUR) avant comparaison.
     *
     *   ≥      5 €  →  3 %
     *   ≥  5 000 €  →  2 %
     *   ≥ 20 000 €  →  1 %
     *
     * Les montants pivots ne sont JAMAIS exposés publiquement.
     */
    public function commissionRate(float $amount, string $currency): float
    {
        $conf  = config('payments.commission');
        $pivot = strtoupper((string) ($conf['pivot_currency'] ?? 'EUR'));

        $inPivot = strtoupper($currency) === $pivot
            ? $amount
            : $amount * $this->currency->getRate($currency, $pivot);

        // Le barème est exprimé en « montant ≥ pivot », donc la borne haute
        // d'une tranche est EXCLUSIVE : 4 999 € → 3 %, 5 000 € → 2 %.
        foreach (($conf['tiers'] ?? []) as $tier) {
            if ($tier['max'] === null || $inPivot < (float) $tier['max']) {
                return (float) $tier['rate'];
            }
        }

        return 0.01;
    }

    /** Investissement minimum, converti dans une devise donnée. */
    public function minimumAmount(string $currency): float
    {
        $min   = (float) config('payments.commission.min_amount', 5);
        $pivot = strtoupper((string) config('payments.commission.pivot_currency', 'EUR'));

        if (strtoupper($currency) === $pivot) {
            return $min;
        }

        return $this->currency->round($min * $this->currency->getRate($pivot, $currency), $currency);
    }

    /**
     * Barème public (sans les montants pivots) pour les pages tarifaires.
     */
    public function publicCommissionScale(): array
    {
        $conf  = config('payments.commission');
        $rates = array_map(fn ($t) => (float) $t['rate'], $conf['tiers'] ?? []);

        return [
            'label' => $conf['public_label'] ?? null,
            'rates' => $rates,
            // Volontairement absent : les montants pivots.
        ];
    }

    /** Pays retenu comme assiette des frais (pays du projet par défaut). */
    public function resolveCountry(Project $project): string
    {
        $iso = $this->normalizeCountry((string) ($project->country ?? ''));

        return $this->hasMarket($iso) ? $iso : (string) config('pawapay.default_market', 'SN');
    }

    public function hasMarket(string $countryIso2): bool
    {
        $iso = strtoupper($countryIso2);

        return isset(config('pawapay.markets', [])[$iso])
            || isset(config('payments.extra_markets', [])[$iso]);
    }

    /** Le pays permet-il un décaissement mobile money PawaPay ? */
    public function hasPawaPayMarket(string $countryIso2): bool
    {
        return isset(config('pawapay.markets', [])[strtoupper($countryIso2)]);
    }

    public function market(string $countryIso2): array
    {
        $iso     = strtoupper($countryIso2);
        $markets = config('pawapay.markets', []);
        $extra   = config('payments.extra_markets', []);

        return $markets[$iso]
            ?? $extra[$iso]
            ?? $markets[config('pawapay.default_market', 'SN')]
            ?? throw new RuntimeException("Marché inconnu : {$iso}");
    }

    /**
     * @return array{0:string,1:array}
     */
    public function resolveProvider(array $market, ?string $provider): array
    {
        $providers = $market['providers'] ?? [];
        if ($providers === []) {
            throw new RuntimeException('Aucun opérateur mobile money configuré pour ce marché.');
        }

        if ($provider && isset($providers[$provider])) {
            return [$provider, $providers[$provider]];
        }

        $code = array_key_first($providers);

        return [$code, $providers[$code]];
    }

    // ─────────────────────────── frais par PSP ───────────────────────────

    /**
     * Résout les frais de collecte et de décaissement selon le moyen de paiement.
     *
     * @return array{0:string,1:string,2:array,3:array} [code, libellé, collecte, payout]
     */
    protected function resolveFees(string $method, string $countryIso2, array $market, ?string $provider): array
    {
        if ($method === self::METHOD_CARD) {
            return [
                'card',
                'Carte bancaire (Visa / Mastercard)',
                $this->normalizeFees(config('paydunya.fees.card', [])),
                $this->normalizeFees(['percent' => $this->payDunyaRate('payout', $countryIso2)]),
            ];
        }

        // Mobile money — PawaPay. Un pays PayDunya-only n'a pas d'opérateur
        // PawaPay : on retombe alors sur la grille PayDunya mobile money.
        if (empty($market['providers'])) {
            return [
                'mobile_money',
                'Mobile Money',
                $this->normalizeFees(['percent' => $this->payDunyaRate('payin', $countryIso2)]),
                $this->normalizeFees(['percent' => $this->payDunyaRate('payout', $countryIso2)]),
            ];
        }

        [$code, $conf] = $this->resolveProvider($market, $provider);

        return [
            $code,
            (string) ($conf['label'] ?? $code),
            $this->providerFees($conf, 'collection'),
            $this->providerFees($conf, 'disbursement'),
        ];
    }

    /**
     * Taux PayDunya pour un pays, selon NOTRE tranche de flux mensuel.
     */
    protected function payDunyaRate(string $leg, string $countryIso2): float
    {
        $tier    = max(1, min(3, (int) config('paydunya.volume_tier', 1))) - 1;
        $table   = config("paydunya.fees.{$leg}", []);
        $default = config("paydunya.fees.default_{$leg}", [0.0225, 0.0220, 0.0215]);
        $rates   = $table[strtoupper($countryIso2)] ?? $default;

        return (float) ($rates[$tier] ?? $rates[0] ?? 0.0225);
    }

    /**
     * Frais d'un opérateur PawaPay avec repli sur `default_fees`.
     */
    protected function providerFees(array $providerConf, string $leg): array
    {
        $defaults = config("pawapay.default_fees.{$leg}", ['percent' => 0.03, 'fixed' => 0]);
        $conf     = $providerConf[$leg] ?? [];

        return $this->normalizeFees($conf + $defaults);
    }

    /**
     * @return array{percent:float,fixed:float,customer_percent:float,customer_fixed:float}
     */
    protected function normalizeFees(array $conf): array
    {
        return [
            'percent'          => (float) ($conf['percent'] ?? 0.0),
            'fixed'            => (float) ($conf['fixed'] ?? 0.0),
            'customer_percent' => (float) ($conf['customer_percent'] ?? 0.0),
            'customer_fixed'   => (float) ($conf['customer_fixed'] ?? 0.0),
        ];
    }

    protected function normalizeMethod(?string $method): string
    {
        $method = strtolower((string) $method);

        return isset($this->availableMethods()[$method]) ? $method : $this->defaultMethod();
    }

    // ─────────────────────────── helpers ───────────────────────────

    protected function roundTo(float $value, int $decimals): float
    {
        return (float) round($value, $decimals);
    }

    /** Arrondi supérieur au pas de la devise (jamais sous-facturer). */
    protected function roundUpTo(float $value, int $decimals): float
    {
        $factor = 10 ** $decimals;

        return (float) (ceil($value * $factor - 1e-9) / $factor);
    }

    /**
     * Nom de pays ou code ISO → ISO-3166 alpha-2.
     * Table centralisée : toute écriture de `transactions.customer_country`
     * (colonne char(2)) doit passer par ici, sinon MySQL tronque un nom
     * accentué en octets invalides (SQLSTATE 22001).
     */
    public function normalizeCountry(?string $raw): string
    {
        $default = (string) config('pawapay.default_market', 'SN');

        if (!$raw) {
            return $default;
        }

        $s = trim($raw);
        if ($s === '') {
            return $default;
        }
        if (mb_strlen($s) === 2 && ctype_alpha($s)) {
            return strtoupper($s);
        }
        if (mb_strlen($s) === 3 && ctype_alpha($s)) {
            foreach (config('pawapay.markets', []) as $iso2 => $m) {
                if (strtoupper($s) === ($m['iso3'] ?? '')) {
                    return $iso2;
                }
            }
        }

        $key = strtolower(str_replace(
            ['é', 'è', 'ê', 'à', 'ï', 'ô', 'û', 'ç', '-', "'", '`'],
            ['e', 'e', 'e', 'a', 'i', 'o', 'u', 'c', ' ', ' ', ' '],
            $s,
        ));
        $key = preg_replace('/\s+/', ' ', $key) ?? $key;

        $map = [
            'senegal' => 'SN', 'cote d ivoire' => 'CI', 'ivory coast' => 'CI',
            'mali' => 'ML', 'burkina faso' => 'BF', 'burkina' => 'BF', 'togo' => 'TG',
            'benin' => 'BJ', 'niger' => 'NE', 'guinee bissau' => 'GW',
            'cameroun' => 'CM', 'cameroon' => 'CM', 'gabon' => 'GA',
            'congo' => 'CG', 'congo brazzaville' => 'CG',
            'rd congo' => 'CD', 'republique democratique du congo' => 'CD', 'drc' => 'CD',
            'tchad' => 'TD', 'chad' => 'TD', 'centrafrique' => 'CF',
            'guinee equatoriale' => 'GQ',
            'nigeria' => 'NG', 'ghana' => 'GH', 'kenya' => 'KE',
            'ouganda' => 'UG', 'uganda' => 'UG', 'tanzanie' => 'TZ', 'tanzania' => 'TZ',
            'rwanda' => 'RW', 'zambie' => 'ZM', 'zambia' => 'ZM',
            'malawi' => 'MW', 'mozambique' => 'MZ', 'lesotho' => 'LS',
            'ethiopie' => 'ET', 'ethiopia' => 'ET', 'sierra leone' => 'SL',
            'france' => 'FR', 'belgique' => 'BE', 'suisse' => 'CH',
            'canada' => 'CA', 'etats unis' => 'US', 'united states' => 'US',
        ];

        return $map[$key] ?? $default;
    }
}
