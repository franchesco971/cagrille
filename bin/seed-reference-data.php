<?php

declare(strict_types=1);

/**
 * Seed idempotent des données de référence Cagrille.
 * Chaque entité n'est insérée QUE SI elle n'existe pas encore.
 *
 * Usage : php bin/seed-reference-data.php
 */

require __DIR__ . '/_init_helpers.php';

$pdo = createPdo();
$now = date('Y-m-d H:i:s');

/** @var array<string, array{inserted: int, skipped: int}> */
$stats = [
    'currencies'    => ['inserted' => 0, 'skipped' => 0],
    'locales'       => ['inserted' => 0, 'skipped' => 0],
    'countries'     => ['inserted' => 0, 'skipped' => 0],
    'zones'         => ['inserted' => 0, 'skipped' => 0],
    'tax_category'  => ['inserted' => 0, 'skipped' => 0],
    'tax_rate'      => ['inserted' => 0, 'skipped' => 0],
    'shipping_cat'  => ['inserted' => 0, 'skipped' => 0],
    'shipping'      => ['inserted' => 0, 'skipped' => 0],
    'taxons'        => ['inserted' => 0, 'skipped' => 0],
    'channel'       => ['inserted' => 0, 'skipped' => 0],
    'gateway'       => ['inserted' => 0, 'skipped' => 0],
    'payment'       => ['inserted' => 0, 'skipped' => 0],
];

$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

// ──── CURRENCIES ──────────────────────────────────────────────────────────────
echo "  → Currencies...\n";
foreach (['EUR', 'USD', 'PLN', 'CAD', 'CNY', 'NZD', 'GBP', 'AUD', 'MXN'] as $code) {
    $stmt = $pdo->prepare("INSERT IGNORE INTO sylius_currency (code, created_at, updated_at) VALUES (?, ?, ?)");
    $stmt->execute([$code, $now, $now]);
    $stmt->rowCount() > 0 ? $stats['currencies']['inserted']++ : $stats['currencies']['skipped']++;
}

// ──── LOCALES ─────────────────────────────────────────────────────────────────
echo "  → Locales...\n";
foreach (['en_US', 'de_DE', 'fr_FR', 'pl_PL', 'es_ES', 'es_MX', 'pt_PT', 'zh_CN'] as $code) {
    $stmt = $pdo->prepare("INSERT IGNORE INTO sylius_locale (code, created_at, updated_at) VALUES (?, ?, ?)");
    $stmt->execute([$code, $now, $now]);
    $stmt->rowCount() > 0 ? $stats['locales']['inserted']++ : $stats['locales']['skipped']++;
}

// ──── COUNTRIES ───────────────────────────────────────────────────────────────
echo "  → Countries...\n";
$countries = ['AT', 'AU', 'BE', 'CA', 'CH', 'CN', 'DE', 'DK', 'ES', 'FR',
              'GB', 'IT', 'MX', 'NL', 'NO', 'NZ', 'PL', 'PT', 'SE', 'US'];
foreach ($countries as $code) {
    $stmt = $pdo->prepare("INSERT IGNORE INTO sylius_country (code, enabled) VALUES (?, 1)");
    $stmt->execute([$code]);
    $stmt->rowCount() > 0 ? $stats['countries']['inserted']++ : $stats['countries']['skipped']++;
}

// ──── ZONES ───────────────────────────────────────────────────────────────────
echo "  → Zones...\n";

/** @var array<string, array{name: string, type: string, scope: string, priority: int, members: list<string>}> */
$zones = [
    'US'    => ['name' => 'United States of America', 'type' => 'country', 'scope' => 'shipping', 'priority' => 2, 'members' => ['US']],
    'WORLD' => ['name' => 'Rest of the World',        'type' => 'country', 'scope' => 'shipping', 'priority' => 1, 'members' => ['AU', 'CA', 'CN', 'DE', 'ES', 'FR', 'GB', 'MX', 'NZ', 'PL', 'PT']],
    'EEA'   => ['name' => 'European Economic Area',   'type' => 'country', 'scope' => 'all',      'priority' => 0, 'members' => ['AT', 'BE', 'CH', 'DE', 'DK', 'ES', 'FR', 'IT', 'NL', 'NO', 'PL', 'PT', 'SE']],
];

foreach ($zones as $code => $zone) {
    $created = false;
    $zoneId  = find_or_create(
        $pdo, 'sylius_zone', 'code', $code,
        "INSERT INTO sylius_zone (code, name, type, scope, priority) VALUES (?, ?, ?, ?, ?)",
        [$code, $zone['name'], $zone['type'], $zone['scope'], $zone['priority']],
        $created
    );
    if (!$created) {
        $pdo->prepare("UPDATE sylius_zone SET scope = ?, priority = ? WHERE code = ?")
            ->execute([$zone['scope'], $zone['priority'], $code]);
    }
    $created ? $stats['zones']['inserted']++ : $stats['zones']['skipped']++;

    foreach ($zone['members'] as $memberCode) {
        if (!zone_member_exists($pdo, $memberCode, $zoneId)) {
            $pdo->prepare("INSERT INTO sylius_zone_member (code, belongs_to) VALUES (?, ?)")
                ->execute([$memberCode, $zoneId]);
        }
    }
}

// ──── IDs partagés ────────────────────────────────────────────────────────────
// Fetchés une seule fois après création des zones ; utilisés par tax_rate, channel et shipping.
$eeaZoneId = fetch_id($pdo, 'sylius_zone', 'code', 'EEA');

// ──── TAX CATEGORY ────────────────────────────────────────────────────────────
echo "  → Catégories de taxe...\n";
$created = false;
$taxCategoryId = find_or_create(
    $pdo, 'sylius_tax_category', 'code', 'all',
    "INSERT INTO sylius_tax_category (code, name, description, created_at, updated_at) VALUES (?, ?, ?, ?, ?)",
    ['all', 'Tous produits', 'Tous produits', $now, $now],
    $created
);
$created ? $stats['tax_category']['inserted']++ : $stats['tax_category']['skipped']++;

// ──── TAX RATE ────────────────────────────────────────────────────────────────
echo "  → Taux de taxe...\n";
$created = false;
find_or_create(
    $pdo, 'sylius_tax_rate', 'code', 'tax20',
    "INSERT INTO sylius_tax_rate
         (code, name, zone_id, category_id, amount, included_in_price, calculator, created_at, updated_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
    ['tax20', 'taxe à 20', $eeaZoneId, $taxCategoryId, 0.20, 0, 'default', $now, $now],
    $created
);
$created ? $stats['tax_rate']['inserted']++ : $stats['tax_rate']['skipped']++;

// ──── TAXONS — Arbre nested set (idempotent par code) ─────────────────────────
//
//  MENU_CATEGORY  [1..22, lvl=0]
//  ├── Barbecues  [2..11, lvl=1]
//  │   ├── mini        [3..4]
//  │   ├── small       [5..6]
//  │   ├── average-bbq [7..8]
//  │   └── big-bbq     [9..10]
//  └── accessories [12..21, lvl=1]
//      ├── tong        [13..14]
//      ├── chairs      [15..16]
//      ├── lighters    [17..18]
//      └── coolers     [19..20]

echo "  → Taxons (arbre catégories)...\n";

$taxonSql = "INSERT INTO sylius_taxon
    (tree_root, parent_id, code, tree_left, tree_right, tree_level, position, enabled, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

// Racine
$rootCreated = false;
$rootId = find_or_create(
    $pdo, 'sylius_taxon', 'code', 'MENU_CATEGORY',
    $taxonSql, [null, null, 'MENU_CATEGORY', 1, 22, 0, 0, 1, $now, $now],
    $rootCreated
);
if ($rootCreated) {
    $pdo->prepare("UPDATE sylius_taxon SET tree_root = ? WHERE id = ?")->execute([$rootId, $rootId]);
    $stats['taxons']['inserted']++;
} else {
    $stats['taxons']['skipped']++;
}

// Enfants — [code, parent (null = résolu dynamiquement), left, right, level, position]
// L'id résolu sera ajouté en position [6] par la boucle.
/** @var list<array{0: string, 1: int|null, 2: int, 3: int, 4: int, 5: int, 6?: int}> */
$taxonsData = [
    ['Barbecues',   $rootId,  2, 11, 1, 4],
    ['mini',        null,     3,  4, 2, 0],
    ['small',       null,     5,  6, 2, 1],
    ['average-bbq', null,     7,  8, 2, 2],
    ['big-bbq',     null,     9, 10, 2, 3],
    ['accessories', $rootId, 12, 21, 1, 5],
    ['tong',        null,    13, 14, 2, 0],
    ['chairs',      null,    15, 16, 2, 1],
    ['lighters',    null,    17, 18, 2, 2],
    ['coolers',     null,    19, 20, 2, 3],
];

$bbqId = null;
$accId = null;
foreach ($taxonsData as &$tData) {
    [$tCode, $tParent, $tLeft, $tRight, $tLevel, $tPos] = $tData;
    if ($tParent === null) {
        $tParent = ($tLevel === 2 && $tLeft < 12) ? $bbqId : $accId;
    }
    $created = false;
    $id = find_or_create(
        $pdo, 'sylius_taxon', 'code', $tCode,
        $taxonSql, [$rootId, $tParent, $tCode, $tLeft, $tRight, $tLevel, $tPos, 1, $now, $now],
        $created
    );
    $created ? $stats['taxons']['inserted']++ : $stats['taxons']['skipped']++;
    $tData[6] = $id;
    if ($tCode === 'Barbecues')   { $bbqId = $id; }
    if ($tCode === 'accessories') { $accId = $id; }
}
unset($tData);

// Reconstruction de la map code → id pour les traductions
/** @var array<string, int> */
$taxonIds = ['MENU_CATEGORY' => $rootId];
foreach ($taxonsData as $tData) {
    $taxonIds[$tData[0]] = $tData[6];
}

// ──── TAXON TRANSLATIONS ──────────────────────────────────────────────────────
echo "  → Traductions des taxons (fr_FR + en_US)...\n";

/** @var array<string, array<string, array{0: string, 1: string}>> */
$translationsMap = [
    'MENU_CATEGORY' => ['fr_FR' => ['Catégorie',      'categorie'],   'en_US' => ['Category',       'category']],
    'Barbecues'     => ['fr_FR' => ['Barbecues',      'bbq'],         'en_US' => ['Barbecues',       'bbq-us']],
    'mini'          => ['fr_FR' => ['Mini barbecue',  'mini-bbq'],    'en_US' => ['Mini barbecue',   'mini-bbq']],
    'small'         => ['fr_FR' => ['Petit barbecue', 'small-bbq'],   'en_US' => ['Small barbecues', 'small-bbq']],
    'average-bbq'   => ['fr_FR' => ['Moyen bbq',      'average-bbq'], 'en_US' => ['Average bbq',     'average-bbq']],
    'big-bbq'       => ['fr_FR' => ['Grand bbq',      'big-bbq'],     'en_US' => ['Big bbq',         'big-bbq']],
    'accessories'   => ['fr_FR' => ['Accessoires',    'accessories'], 'en_US' => ['Accessories',     'accessories']],
    'tong'          => ['fr_FR' => ['Pinces',          'tong'],       'en_US' => ['Tongs',            'tongs']],
    'chairs'        => ['fr_FR' => ['Chaises',         'chairs'],     'en_US' => ['Chairs',           'chairs']],
    'lighters'      => ['fr_FR' => ['Briquets',        'lighters'],   'en_US' => ['Lighters',         'lighters']],
    'coolers'       => ['fr_FR' => ['Glacière',        'coolers'],    'en_US' => ['Coolers',          'coolers']],
];

/** @var array<string, array<string, string>> */
$descriptionsMap = [
    'Barbecues' => ['fr_FR' => 'Barbecues', 'en_US' => 'Barbecues'],
];

foreach ($translationsMap as $tCode => $localeMap) {
    $taxonId = $taxonIds[$tCode];
    foreach ($localeMap as $locale => [$name, $slug]) {
        if (!taxon_translation_exists($pdo, $taxonId, $locale)) {
            $desc = $descriptionsMap[$tCode][$locale] ?? null;
            $pdo->prepare(
                "INSERT INTO sylius_taxon_translation (translatable_id, locale, name, slug, description)
                 VALUES (?, ?, ?, ?, ?)"
            )->execute([$taxonId, $locale, $name, $slug, $desc]);
        }
    }
}

// ──── CHANNEL CG_EURO_STORE ───────────────────────────────────────────────────
echo "  → Channel CG_EURO_STORE...\n";

$channelCode   = 'CG_EURO_STORE';
$frLocaleId    = fetch_id($pdo, 'sylius_locale',   'code', 'fr_FR');
$enLocaleId    = fetch_id($pdo, 'sylius_locale',   'code', 'en_US');
$eurCurrencyId = fetch_id($pdo, 'sylius_currency', 'code', 'EUR');
$frCountryId   = fetch_id($pdo, 'sylius_country',  'code', 'FR');

$stmtCh = $pdo->prepare(
    "SELECT id, shop_billing_data_id, channel_price_history_config_id FROM sylius_channel WHERE code = ? LIMIT 1"
);
$stmtCh->execute([$channelCode]);
/** @var array{id: string, shop_billing_data_id: string, channel_price_history_config_id: string}|false */
$existingChannel = $stmtCh->fetch();

if ($existingChannel !== false) {
    $channelId = (int) $existingChannel['id'];
    $stats['channel']['skipped']++;
} else {
    $pdo->prepare(
        "INSERT INTO sylius_shop_billing_data (company, tax_id, country_code, street, city, postcode) VALUES (?, ?, ?, ?, ?, ?)"
    )->execute(['copolink', '123456789', 'FR', '14 rue basset', 'paris', '75015']);
    $billingDataId = (int) $pdo->lastInsertId();

    $pdo->prepare(
        "INSERT INTO sylius_channel_price_history_config
             (lowest_price_for_discounted_products_checking_period, lowest_price_for_discounted_products_visible)
         VALUES (30, 1)"
    )->execute([]);
    $priceHistoryConfigId = (int) $pdo->lastInsertId();

    $pdo->prepare(
        "INSERT INTO sylius_channel (
            shop_billing_data_id, channel_price_history_config_id,
            default_locale_id, base_currency_id, default_tax_zone_id, menu_taxon_id,
            code, name, color, description, enabled, hostname, theme_name,
            tax_calculation_strategy, contact_email, contact_phone_number,
            skipping_shipping_step_allowed, skipping_payment_step_allowed,
            account_verification_required, shipping_address_in_checkout_required,
            created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    )->execute([
        $billingDataId, $priceHistoryConfigId,
        $frLocaleId, $eurCurrencyId, $eeaZoneId, $rootId,
        $channelCode, 'Cagrille euro store',
        '#f08c00', 'cagrille store', 1, 'localhost',
        'cagrille/cagrille-theme', 'order_items_based',
        'contact.cagrille@gmail.com', null,
        0, 0, 1, 0,
        $now, $now,
    ]);
    $channelId = (int) $pdo->lastInsertId();
    $stats['channel']['inserted']++;
}

// Relations pivot (INSERT IGNORE = idempotent sur clé composite)
insert_pivot($pdo, 'sylius_channel_locales',    'channel_id', $channelId, 'locale_id',   $frLocaleId);
insert_pivot($pdo, 'sylius_channel_locales',    'channel_id', $channelId, 'locale_id',   $enLocaleId);
insert_pivot($pdo, 'sylius_channel_currencies', 'channel_id', $channelId, 'currency_id', $eurCurrencyId);
insert_pivot($pdo, 'sylius_channel_countries',  'channel_id', $channelId, 'country_id',  $frCountryId);

// ──── SHIPPING CATEGORY ─────────────────────────────────────────────────────
echo "  → Catégorie de livraison...\n";
$created = false;
$shippingCategoryId = find_or_create(
    $pdo, 'sylius_shipping_category', 'code', 'default',
    "INSERT INTO sylius_shipping_category (code, name, description, created_at, updated_at) VALUES (?, ?, ?, ?, ?)",
    ['default', 'Default', null, $now, $now],
    $created
);
$created ? $stats['shipping_cat']['inserted']++ : $stats['shipping_cat']['skipped']++;

// ──── SHIPPING METHOD ─────────────────────────────────────────────────────────
echo "  → Méthode de livraison...\n";

$shippingConfig = (string) json_encode([$channelCode => ['amount' => 0]]);

$shippingCreated  = false;
$shippingMethodId = find_or_create(
    $pdo, 'sylius_shipping_method', 'code', 'default',
    "INSERT INTO sylius_shipping_method
         (code, zone_id, category_id, tax_category_id, calculator, configuration,
          category_requirement, is_enabled, position, created_at, updated_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, 1, 0, ?, ?)",
    ['default', $eeaZoneId, $shippingCategoryId, $taxCategoryId, 'per_unit_rate', $shippingConfig, 2, $now, $now],
    $shippingCreated
);
$shippingCreated ? $stats['shipping']['inserted']++ : $stats['shipping']['skipped']++;

// Traductions fr_FR + en_US
foreach (['fr_FR' => 'defaut', 'en_US' => 'default'] as $locale => $name) {
    $exists = $pdo->prepare(
        "SELECT 1 FROM sylius_shipping_method_translation WHERE translatable_id = ? AND locale = ? LIMIT 1"
    );
    $exists->execute([$shippingMethodId, $locale]);
    if (!$exists->fetch()) {
        $pdo->prepare("INSERT INTO sylius_shipping_method_translation (translatable_id, locale, name) VALUES (?, ?, ?)")
            ->execute([$shippingMethodId, $locale, $name]);
    }
}

insert_pivot($pdo, 'sylius_shipping_method_channels', 'shipping_method_id', $shippingMethodId, 'channel_id', $channelId);

// ──── GATEWAY CONFIG (PayPal) ────────────────────────────────────────────────
echo "  → Gateway config (PayPal)...\n";

// Les credentials sont lus depuis les variables d'environnement.
// Définissez-les dans .env.local avant de lancer le script.
$ppClientId     = (string) (getenv('PAYPAL_CLIENT_ID')     ?: '');
$ppClientSecret = (string) (getenv('PAYPAL_CLIENT_SECRET') ?: '');
$ppMerchantId   = (string) (getenv('PAYPAL_MERCHANT_ID')   ?: '');
$ppSyliusMerchantId = (string) (getenv('PAYPAL_SYLIUS_MERCHANT_ID') ?: '');

$gatewayConfig = json_encode([
    'client_id'            => $ppClientId,
    'client_secret'        => $ppClientSecret,
    'merchant_id'          => $ppMerchantId,
    'use_authorize'        => '1',
    'sylius_merchant_id'   => $ppSyliusMerchantId,
    'reports_sftp_password' => null,
    'reports_sftp_username' => null,
    'partner_attribution_id' => 'Sylius_MP_PPCP',
]);

$gatewayCreated = false;
$gatewayConfigId = find_or_create(
    $pdo, 'sylius_gateway_config', 'gateway_name', 'paypal_express_checkout',
    "INSERT INTO sylius_gateway_config (gateway_name, factory_name, config, use_payum) VALUES (?, ?, ?, 1)",
    ['paypal_express_checkout', 'sylius_paypal', (string) $gatewayConfig],
    $gatewayCreated
);
$gatewayCreated ? $stats['gateway']['inserted']++ : $stats['gateway']['skipped']++;

// ──── PAYMENT METHOD (PayPal) ──────────────────────────────────────────────
echo "  → Méthode de paiement (PayPal)...\n";

$appEnvEnv = $_ENV['APP_ENV'] ?? null;
$appEnvForPayment = is_string($appEnvEnv) ? $appEnvEnv : (getenv('APP_ENV') ?: 'prod');
$paymentCreated = false;
$paymentMethodId = find_or_create(
    $pdo, 'sylius_payment_method', 'code', 'paypal',
    "INSERT INTO sylius_payment_method
         (gateway_config_id, code, environment, is_enabled, position, created_at, updated_at)
     VALUES (?, 'paypal', ?, 1, 1, ?, ?)",
    [$gatewayConfigId, $appEnvForPayment, $now, $now],
    $paymentCreated
);
$paymentCreated ? $stats['payment']['inserted']++ : $stats['payment']['skipped']++;

// Traductions fr_FR + en_US
foreach (['fr_FR' => 'Paypal', 'en_US' => 'Paypal'] as $locale => $name) {
    $exists = $pdo->prepare(
        "SELECT 1 FROM sylius_payment_method_translation WHERE translatable_id = ? AND locale = ? LIMIT 1"
    );
    $exists->execute([$paymentMethodId, $locale]);
    if (!$exists->fetch()) {
        $pdo->prepare(
            "INSERT INTO sylius_payment_method_translation (translatable_id, name, description, instructions, locale)
             VALUES (?, ?, NULL, NULL, ?)"
        )->execute([$paymentMethodId, $name, $locale]);
    }
}

insert_pivot($pdo, 'sylius_payment_method_channels', 'payment_method_id', $paymentMethodId, 'channel_id', $channelId);

$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

// ── Résumé ────────────────────────────────────────────────────────────────────
echo "\n  ✔ Données de référence traitées.\n";
echo "    ┌────────────────┬──────────┬─────────┐\n";
echo "    │ Type           │ Insérés  │ Ignorés │\n";
echo "    ├────────────────┼──────────┼─────────┤\n";
foreach ($stats as $type => $s) {
    printf("    │ %-14s │ %8d │ %7d │\n", $type, $s['inserted'], $s['skipped']);
}
echo "    └────────────────┴──────────┴─────────┘\n";
