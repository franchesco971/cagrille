# AliExpressBundle — Dropshipping AliExpress pour Cagrille

Bundle Symfony/Sylius dédié à l'intégration de **l'API AliExpress Open Platform (DS)**
pour automatiser le dropshipping : import de produits avec génération des variants SKU,
passage de commandes groupées et suivi logistique avec notification email automatique.

---

## Sommaire

1. [Architecture](#architecture)
2. [Installation & configuration](#installation--configuration)
3. [Variables d'environnement](#variables-denvironnement)
4. [OAuth — Obtenir un token](#oauth--obtenir-un-token)
5. [Commandes CLI](#commandes-cli)
6. [Commandes Make](#commandes-make)
7. [Flux d'une commande dropship](#flux-dune-commande-dropship)
8. [Suivi de livraison & notification client](#suivi-de-livraison--notification-client)
9. [Connexion Sylius](#connexion-sylius)
10. [Tarification canal](#tarification-canal)
11. [Étendre le bundle](#étendre-le-bundle)
12. [Principes de conception](#principes-de-conception)

---

## Architecture

```
src/AliExpressBundle/
├── AliExpressBundle.php
├── Api/
│   ├── AliExpressApiClient.php           # Client HTTP HMAC-SHA256
│   ├── TokenRefreshService.php           # Échange de code OAuth / refresh token
│   ├── TokenStorage.php                  # Stockage token JSON (var/aliexpress_token.json)
│   └── Endpoint/
│       ├── ProductEndpoint.php           # Recherche & détail produits
│       ├── OrderEndpoint.php             # Création commande groupée
│       ├── MockOrderEndpoint.php         # Mock dev (sans appel API réel)
│       └── LogisticsEndpoint.php         # Suivi colis
├── Command/
│   ├── SyncProductsCommand.php           # aliexpress:sync:products
│   ├── SyncShipmentsCommand.php          # aliexpress:shipment:sync
│   ├── RetryFailedOrdersCommand.php      # aliexpress:orders:retry
│   ├── TrackOrderCommand.php             # aliexpress:order:track
│   └── GetTokenCommand.php               # aliexpress:auth:token
├── Contract/                             # Interfaces (DIP)
│   ├── AliExpressApiClientInterface.php
│   ├── AliExpressOrderRepositoryInterface.php
│   ├── AliExpressOrderPlacementServiceInterface.php
│   ├── AliExpressShipmentSyncServiceInterface.php
│   ├── ProductEndpointInterface.php
│   ├── OrderEndpointInterface.php
│   ├── LogisticsEndpointInterface.php
│   ├── ProductPersistenceInterface.php
│   ├── ProductSyncServiceInterface.php
│   └── TokenStorageInterface.php
├── Controller/
│   ├── AliExpressOAuthCallbackController.php  # GET /aliexpress/callback
│   └── Admin/
│       ├── AliExpressSyncController.php       # Import produits + OAuth admin
│       └── AliExpressOrderController.php
├── Dto/                                  # Objets de transfert (immutables)
│   ├── ProductDto.php                    # Produit complet (+ liste de SkuDto)
│   ├── SkuDto.php                        # Variant SKU (prix, options)
│   ├── OrderDto.php
│   ├── OrderItemDto.php
│   ├── OrderRequestDto.php
│   ├── TrackingDto.php
│   └── TrackingEventDto.php
├── Entity/
│   ├── AliExpressOrder.php               # Entité Doctrine — lien Sylius ↔ AliExpress
│   └── AliExpressOrderStatus.php         # Enum : pending / placed / shipped / delivered / failed
├── EventListener/
│   └── OrderPaymentCompletedListener.php # Déclenche le placement après paiement
├── Exception/
│   └── AliExpressApiException.php
├── Repository/
│   └── AliExpressOrderRepository.php
├── Resources/config/
│   └── services.yaml
└── Service/
    ├── AliExpressOrderPlacementService.php  # Placement commande groupée
    ├── AliExpressShipmentSyncService.php    # Sync tracking + email client
    ├── ChannelPricingCalculator.php         # Formule de tarification canal
    ├── NullProductPersistence.php           # Null Object
    ├── SyliusProductPersistence.php         # Persistance réelle Sylius
    └── ProductSyncService.php               # Orchestration de la sync
```

### Flux de données (sync produits)

```
CLI / Admin
     │
     ▼
SyncProductsCommand / AliExpressSyncController
     │
     ▼
ProductSyncService          ← ProductSyncServiceInterface
     │  pagine les résultats
     ▼
ProductEndpoint             ← ProductEndpointInterface
     │  aliexpress.ds.product.get
     │  aliexpress.affiliate.product.query
     ▼
AliExpressApiClient         ← AliExpressApiClientInterface
     │  POST /sync (HMAC-SHA256)
     ▼
API AliExpress Open Platform
     │
     ▼
ProductDto (immutable)
     │  └── SkuDto[] — un par ae_item_sku_info_d_t_o
     ▼
SyliusProductPersistence    ← ProductPersistenceInterface
     │  upsert (create/update)
     │  ├── ProductVariant par SKU  → code : aliexpress_<id>_sku_<skuId>
     │  ├── ProductOption / ProductOptionValue (réutilisés entre produits)
     │  └── ChannelPricing calculé via ChannelPricingCalculator
     ▼
Catalogue Sylius
```

---

## Installation & configuration

Le bundle est déjà intégré au projet. Les routes/services sont chargés automatiquement
via `config/services.yaml`.

---

## Variables d'environnement

Copier dans `.env.local` et renseigner les vraies valeurs :

### Connexion API

| Variable | Description | Défaut |
|---|---|---|
| `ALIEXPRESS_APP_KEY` | Clé d'application AliExpress | — |
| `ALIEXPRESS_APP_SECRET` | Secret d'application | — |
| `ALIEXPRESS_ACCESS_TOKEN` | Token OAuth | — |
| `ALIEXPRESS_BASE_URL` | URL de base de l'API | `https://api-sg.aliexpress.com` |
| `ALIEXPRESS_TIMEOUT` | Timeout HTTP (secondes) | `30` |
| `ALIEXPRESS_TARGET_CURRENCY` | Devise du prix affiché | `EUR` |
| `ALIEXPRESS_TARGET_LANGUAGE` | Langue des descriptions | `fr` |
| `ALIEXPRESS_SHIP_TO_COUNTRY` | Pays de livraison | `FR` |
| `ALIEXPRESS_SYNC_KEYWORDS` | Mots-clés de sync (JSON) | `["barbecue","grill","bbq accessories"]` |
| `ALIEXPRESS_SYNC_BATCH_SIZE` | Produits par page | `20` |
| `ALIEXPRESS_REDIRECT_URI` | URL de callback OAuth | `https://cagrille.fr/aliexpress/callback` |

### Tarification canal

| Variable | Description | Défaut |
|---|---|---|
| `ALIEXPRESS_PRICING_ADVERTISING_RATE` | Coût publicitaire (% du PDD) | `0.01` (1 %) |
| `ALIEXPRESS_PRICING_PAYMENT_RATE` | Frais de paiement variables (% du PDD) | `0.012` (1,2 %) |
| `ALIEXPRESS_PRICING_PAYMENT_FIXED` | Frais de paiement fixes (€) | `0.10` |
| `ALIEXPRESS_PRICING_TAX_RATE` | Taux de taxes (% du PDD) | `0.128` (12,8 %) |
| `ALIEXPRESS_PRICING_MARGIN_RATE` | Marge (% du PDD) | `0.10` (10 %) |

**Obtenir les credentials :** https://developers.aliexpress-solution.com

> **Dev :** en environnement `dev`, `OrderEndpointInterface` est automatiquement
> substitué par `MockOrderEndpoint` (aucun appel réel, logs uniquement).

---

## OAuth — Obtenir un token

Le token OAuth est nécessaire pour tous les appels API. Il est stocké dans
`var/aliexpress_token.json` et se rafraîchit automatiquement.

### Flux initial (première fois)

```bash
# Étape 1 : affiche l'URL d'autorisation
docker compose run --rm php bin/console aliexpress:auth:token
```

Ouvrez l'URL dans votre navigateur → connectez-vous avec le compte vendeur AliExpress
→ approuvez l'accès → vous êtes redirigé vers :

```
https://cagrille.fr/aliexpress/callback?code=3_529794_XXXX
```

Si vous êtes connecté à l'admin Sylius, le **callback est automatiquement géré** :
le code est échangé et le token sauvegardé. Sinon, copiez le `code` et relancez :

```bash
# Étape 2 : échange du code
docker compose run --rm php bin/console aliexpress:auth:token --code=3_529794_XXXX
```

### Comportement automatique de la commande

| État du token | Action |
|---|---|
| Absent | Affiche l'URL OAuth (étape 1) |
| Valide | Affiche le statut |
| Expiré + `refresh_token` disponible | **Rafraîchit automatiquement** |
| Expiré + pas de `refresh_token` | Affiche l'URL OAuth |

### Options disponibles

```bash
--code=XXX    # Échange manuellement un code de callback
--status      # Affiche access_token, refresh_token, date d'expiration
--refresh     # Force le renouvellement via le refresh_token
```

---

## Commandes CLI

### Synchroniser les produits

```bash
# Synchronisation complète (tous les mots-clés de ALIEXPRESS_SYNC_KEYWORDS)
docker compose run --rm php bin/console aliexpress:sync:products

# Par mot-clé
docker compose run --rm php bin/console aliexpress:sync:products --keyword=barbecue

# Un seul produit par item_id AliExpress
docker compose run --rm php bin/console aliexpress:sync:products --item=1005010455068176
```

> Pour chaque produit importé, **tous ses variants SKU sont créés ou mis à jour**
> (voir [Connexion Sylius — Variants](#variants)).

### Synchroniser le suivi de livraison

```bash
docker compose run --rm php bin/console aliexpress:shipment:sync
```

### Relancer les commandes échouées

```bash
docker compose run --rm php bin/console aliexpress:orders:retry
```

### Afficher le suivi d'une commande spécifique

```bash
docker compose run --rm php bin/console aliexpress:order:track \
  --order=8141234567890 \
  --tracking=JX123456789CN
```

---

## Commandes Make

```bash
make sync-tracking    # Lance aliexpress:shipment:sync (à planifier via cron)
make retry-orders     # Lance aliexpress:orders:retry
make stan             # Analyse PHPStan niveau 9
```

### Planification automatique (production)

```cron
# Sync tracking toutes les heures
0 * * * *  www-data  cd /var/www/cagrille && docker compose exec -T php bin/console aliexpress:shipment:sync

# Retry des commandes échouées toutes les 4 heures
0 */4 * * *  www-data  cd /var/www/cagrille && docker compose exec -T php bin/console aliexpress:orders:retry

# Maintenance du token OAuth
0 * * * *  www-data  cd /var/www/cagrille && docker compose exec -T php bin/console aliexpress:auth:token
```

---

## Flux d'une commande dropship

```
Client paie sur Sylius
        │
        ▼
workflow.sylius_order_payment.completed.pay
        │
        ▼
OrderPaymentCompletedListener  (#[AsEventListener])
        │
        ▼
AliExpressOrderPlacementService::placeForOrder()
        │  — détecte les variants préfixés aliexpress_
        │  — crée une AliExpressOrder par article AliExpress du panier
        │  — 1 seul appel API groupé
        ▼
OrderEndpoint::create()  →  API AliExpress DS
        │
        ▼
AliExpressOrder::markAsPlaced()  →  status = placed
```

---

## Suivi de livraison & notification client

```
Cron / make sync-tracking
        │
        ▼
AliExpressShipmentSyncService::syncAllPendingTracking()
        │
        ▼
LogisticsEndpoint::getTracking()  →  aliexpress.ds.trade.order.logistics.get
        │
        ├─ tracking apparu pour la 1ère fois ?
        │       └─ email "Votre commande est expédiée" (numéro de suivi inclus)
        │
        ├─ status = FINISH → AliExpressOrder::markAsDelivered()
        └─ sinon → AliExpressOrder::markAsShipped() + Shipment Sylius mis à jour
```

---

## Connexion Sylius

### Produit

Code Sylius : `aliexpress_<item_id>`. Ce préfixe distingue les produits AliExpress
des produits Alibaba (`alibaba_<id>`) et des produits manuels.

### Variants

Pour chaque SKU retourné dans `ae_item_sku_info_dtos.ae_item_sku_info_d_t_o` :

| Élément Sylius | Valeur |
|---|---|
| Code variant | `aliexpress_<item_id>_sku_<skuId>` |
| Suivi de stock | **désactivé** (`setTracked(false)`) |
| Catégorie de taxe | première catégorie trouvée en base |
| Options produit | créées depuis les propriétés SKU (ex. Couleur, Taille) |
| Libellé de valeur | `property_value_definition_name` (traduction FR fournie par l'API) |

Si l'API ne retourne pas de SKU (cas *affiliate search*), un seul variant par défaut
est créé avec le code `aliexpress_<item_id>_default`.

### Options et valeurs produit

Les `ProductOption` sont partagées entre tous les produits (réutilisées si elles
existent déjà en base) :

| Élément | Code Sylius |
|---|---|
| ProductOption | `aliexpress_prop_<propertyId>` |
| ProductOptionValue | `aliexpress_propval_<propertyId>_<valueId>` |

### Désactiver la persistance (tests)

```yaml
# config/services.yaml
Cagrille\AliExpressBundle\Contract\ProductPersistenceInterface:
    alias: Cagrille\AliExpressBundle\Service\NullProductPersistence
```

---

## Tarification canal

Le prix de chaque variant est calculé par `ChannelPricingCalculator` selon la
formule suivante :

```
cp    = pdd × ALIEXPRESS_PRICING_ADVERTISING_RATE
fdp   = pdd × ALIEXPRESS_PRICING_PAYMENT_RATE + ALIEXPRESS_PRICING_PAYMENT_FIXED
taxes = pdd × ALIEXPRESS_PRICING_TAX_RATE
marge = pdd × ALIEXPRESS_PRICING_MARGIN_RATE
prix  = pdd + cp + fdp + taxes + marge
```

Où `pdd` (prix de départ) correspond à `offer_sale_price` du SKU pour le **prix
de vente**, et à `sku_price` pour le **prix barré** (`originalPrice`).

Les cinq pourcentages sont des variables d'environnement et peuvent être ajustés
sans rechargement du code.

#### Exemple (pdd = 20,00 €)

| Composante | Calcul | Montant |
|---|---|---|
| Coût pub (cp) | 20 × 1 % | 0,20 € |
| Frais paiement (fdp) | 20 × 1,2 % + 0,10 | 0,34 € |
| Taxes | 20 × 12,8 % | 2,56 € |
| Marge | 20 × 10 % | 2,00 € |
| **Prix final** | 20 + 0,20 + 0,34 + 2,56 + 2,00 | **25,10 €** |

---

## Étendre le bundle

### Implémenter une persistance personnalisée

```php
use Cagrille\AliExpressBundle\Contract\ProductPersistenceInterface;
use Cagrille\AliExpressBundle\Dto\ProductDto;

class MyCustomPersistence implements ProductPersistenceInterface
{
    public function upsert(ProductDto $dto): void { /* ... */ }
    public function existsByAliExpressId(string $id): bool { return false; }
}
```

```yaml
# config/services.yaml
Cagrille\AliExpressBundle\Contract\ProductPersistenceInterface:
    alias: App\Service\MyCustomPersistence
```

### Ajuster la formule de tarification

Il suffit de modifier les variables d'environnement dans `.env.local` —
aucun changement de code n'est nécessaire.

---

## Principes de conception

| Principe | Application |
|---|---|
| **SRP** | Client HTTP ≠ Endpoints ≠ Services métier ≠ Persistance ≠ Calculateur prix |
| **DIP** | Tous les services dépendent d'interfaces, jamais des classes concrètes |
| **OCP** | Persistance, endpoint, placement et tarification swappables |
| **Null Object** | `NullProductPersistence`, `MockOrderEndpoint` — pas de mock en test |
| **Batch groupé** | Un seul appel API par commande, quel que soit le nombre d'articles |
| **Token auto** | `TokenStorage` + `TokenRefreshService` — refresh transparent |
