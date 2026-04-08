# AliExpressBundle — Dropshipping AliExpress pour Cagrille

Bundle Symfony/Sylius dédié à l'intégration de **l'API AliExpress Open Platform (DS)**
pour automatiser le dropshipping : import de produits, passage de commandes groupées
et suivi logistique avec notification email automatique.

---

## Sommaire

1. [Architecture](#architecture)
2. [Installation & configuration](#installation--configuration)
3. [Variables d'environnement](#variables-denvironnement)
4. [Commandes CLI](#commandes-cli)
5. [Commandes Make](#commandes-make)
6. [Flux d'une commande dropship](#flux-dune-commande-dropship)
7. [Suivi de livraison & notification client](#suivi-de-livraison--notification-client)
8. [Connexion Sylius](#connexion-sylius)
9. [Étendre le bundle](#étendre-le-bundle)
10. [Principes de conception](#principes-de-conception)

---

## Architecture

```
src/AliExpressBundle/
├── AliExpressBundle.php
├── Api/
│   ├── AliExpressApiClient.php           # Client HTTP HMAC-SHA256
│   └── Endpoint/
│       ├── ProductEndpoint.php           # Recherche & détail produits
│       ├── OrderEndpoint.php             # Création commande groupée
│       ├── MockOrderEndpoint.php         # Mock dev (sans appel API réel)
│       └── LogisticsEndpoint.php         # Suivi colis
├── Command/
│   ├── SyncProductsCommand.php           # aliexpress:sync:products
│   ├── SyncShipmentsCommand.php          # aliexpress:shipment:sync
│   ├── RetryFailedOrdersCommand.php      # aliexpress:orders:retry
│   └── TrackOrderCommand.php             # aliexpress:order:track
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
├── Dto/
│   ├── ProductDto.php
│   ├── OrderDto.php
│   ├── OrderItemDto.php                  # Item individuel dans une commande groupée
│   ├── OrderRequestDto.php               # Requête de commande groupée (items[])
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
    ├── NullProductPersistence.php           # Null Object
    ├── SyliusProductPersistence.php
    └── ProductSyncService.php
```

---

## Installation & configuration

Le bundle est déjà intégré au projet. Les routes/services sont chargés automatiquement
via `config/services.yaml`.

---

## Variables d'environnement

Copier dans `.env.local` et renseigner les vraies valeurs :

| Variable | Description | Défaut |
|---|---|---|
| `ALIEXPRESS_APP_KEY` | Clé d'application AliExpress | — |
| `ALIEXPRESS_APP_SECRET` | Secret d'application | — |
| `ALIEXPRESS_ACCESS_TOKEN` | Token OAuth | — |
| `ALIEXPRESS_BASE_URL` | URL de base de l'API | `https://api-sg.aliexpress.com` |
| `ALIEXPRESS_TIMEOUT` | Timeout HTTP (secondes) | `30` |
| `ALIEXPRESS_TARGET_CURRENCY` | Devise du prix affiché | `EUR` |
| `ALIEXPRESS_TARGET_LANGUAGE` | Langue des descriptions | `fr` |
| `ALIEXPRESS_SYNC_KEYWORDS` | Mots-clés de sync (JSON) | `["barbecue","grill","bbq accessories"]` |
| `ALIEXPRESS_SYNC_BATCH_SIZE` | Produits par page | `20` |

**Obtenir les credentials :** https://developers.aliexpress-solution.com

> **Dev :** en environnement `dev`, `OrderEndpointInterface` est automatiquement
> substitué par `MockOrderEndpoint` (aucun appel réel, logs uniquement).

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

> **Note :** ne pas confondre avec `alibaba:sync:products` (Alibaba.com ICBU).
> Les IDs AliExpress (ex. `1005010455068176`) ne sont pas valides pour Alibaba.

### Synchroniser le suivi de livraison

Récupère le numéro de suivi depuis l'API AliExpress pour toutes les commandes
placées en attente de tracking. **Envoie automatiquement un email au client**
lors de la première apparition d'un numéro de suivi.

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

Ajouter dans le crontab serveur pour maintenir le suivi à jour :

```cron
# Sync tracking toutes les heures
0 * * * *  www-data  cd /var/www/cagrille && docker compose exec -T php bin/console aliexpress:shipment:sync

# Retry des commandes échouées toutes les 4 heures
0 */4 * * *  www-data  cd /var/www/cagrille && docker compose exec -T php bin/console aliexpress:orders:retry
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
OrderPaymentCompletedListener  (#[AsEventListener], priorité 50)
        │
        ▼
AliExpressOrderPlacementService::placeForOrder()
        │  — crée une AliExpressOrder par article AliExpress du panier
        │  — flush batch (1 requête DB)
        │  — 1 seul appel API groupé (tous les articles en une fois)
        ▼
OrderEndpoint::create()  →  API AliExpress DS
        │
        ▼
AliExpressOrder::markAsPlaced()  →  status = placed
```

### Codes produits

Les variantes AliExpress ont le code `aliexpress_<item_id>_default`.
Le service de placement les détecte via le préfixe `aliexpress_`.
Les produits Alibaba (`alibaba_<id>`) et manuels sont ignorés.

### Commandes groupées

Tous les articles AliExpress d'un même panier sont regroupés en **un seul appel API**.
Le montant total est réparti équitablement entre chaque `AliExpressOrder`.

---

## Suivi de livraison & notification client

```
Cron / make sync-tracking
        │
        ▼
aliexpress:shipment:sync
        │
        ▼
AliExpressShipmentSyncService::syncAllPendingTracking()
        │  — commandes "placed" sans tracking
        │  — commandes "shipped" (pour détecter la livraison)
        ▼
LogisticsEndpoint::getTracking()  →  aliexpress.ds.trade.order.logistics.get
        │
        ├─ tracking apparu pour la 1ère fois ?
        │       └─ ShipmentEmailManagerInterface::sendConfirmationEmail()
        │              → email Sylius standard "Votre commande est expédiée"
        │              → numéro de suivi inclus dans l'email
        │
        ├─ status = FINISH → AliExpressOrder::markAsDelivered()
        └─ sinon → AliExpressOrder::markAsShipped() + Shipment Sylius mis à jour
```

L'email est envoyé **une seule fois** : à la première détection d'un numéro de suivi.
Un échec d'envoi email est loggué en `warning` mais ne bloque pas la synchronisation.

---

## Connexion Sylius

Les produits sont créés avec le code `aliexpress_<item_id>` (produit) et
`aliexpress_<item_id>_default` (variante). La persistance est active par défaut
via `SyliusProductPersistence`.

Pour désactiver temporairement (tests) :

```yaml
# config/services.yaml
Cagrille\AliExpressBundle\Contract\ProductPersistenceInterface:
    alias: Cagrille\AliExpressBundle\Service\NullProductPersistence
```

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

Puis dans `config/services.yaml` :

```yaml
Cagrille\AliExpressBundle\Contract\ProductPersistenceInterface:
    alias: App\Service\MyCustomPersistence
```

---

## Principes de conception

| Principe | Application |
|---|---|
| **SRP** | Client HTTP ≠ Endpoints ≠ Services métier ≠ Persistance |
| **DIP** | Tous les services dépendent d'interfaces, jamais des classes concrètes |
| **OCP** | Persistance, endpoint et placement swappables sans modifier les services |
| **Null Object** | `NullProductPersistence`, `MockOrderEndpoint` — pas de mock en test |
| **Batch groupé** | Un seul appel API par commande, quel que soit le nombre d'articles |


Bundle Symfony/Sylius dédié à l'intégration de **l'API AliExpress Open Platform (DS)**
pour automatiser le dropshipping : import de produits, passage de commandes et suivi logistique.

---

## Sommaire

1. [Architecture](#architecture)
2. [Installation & configuration](#installation--configuration)
3. [Variables d'environnement](#variables-denvironnement)
4. [Commandes CLI](#commandes-cli)
5. [Connexion Sylius](#connexion-sylius)
6. [Étendre le bundle](#étendre-le-bundle)
7. [Principes de conception](#principes-de-conception)

---

## Architecture

```
src/AliExpressBundle/
├── AliExpressBundle.php                  # Point d'entrée du bundle
├── Api/
│   ├── AliExpressApiClient.php           # Client HTTP HMAC-SHA256
│   └── Endpoint/
│       ├── ProductEndpoint.php           # Recherche & détail produits
│       ├── OrderEndpoint.php             # Création & consultation commandes
│       └── LogisticsEndpoint.php         # Suivi colis
├── Command/
│   ├── SyncProductsCommand.php           # aliexpress:sync:products
│   └── TrackOrderCommand.php             # aliexpress:order:track
├── Contract/                             # Interfaces (DIP)
│   ├── AliExpressApiClientInterface.php
│   ├── ProductEndpointInterface.php
│   ├── OrderEndpointInterface.php
│   ├── LogisticsEndpointInterface.php
│   ├── ProductPersistenceInterface.php
│   └── ProductSyncServiceInterface.php
├── DependencyInjection/
│   ├── AliExpressExtension.php
│   └── Configuration.php
├── Dto/                                  # Objets de transfert (immutables)
│   ├── ProductDto.php
│   ├── OrderDto.php
│   ├── OrderRequestDto.php
│   ├── TrackingDto.php
│   └── TrackingEventDto.php
├── Exception/
│   └── AliExpressApiException.php
├── Resources/config/
│   └── services.yaml
└── Service/
    ├── NullProductPersistence.php        # Null Object (tests/dev)
    ├── SyliusProductPersistence.php      # Persistance réelle Sylius
    └── ProductSyncService.php            # Orchestration de la sync
```

### Flux de données

```
CLI / Scheduler
     │
     ▼
SyncProductsCommand
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
     │
     ▼
SyliusProductPersistence    ← ProductPersistenceInterface
     │  upsert (create/update)
     ▼
Catalogue Sylius (code préfixe : aliexpress_<item_id>)
```

---

## Installation & configuration

Le bundle est déjà intégré au projet. Il est chargé via `config/services.yaml`
(même pattern que l'AlibabaBundle — pas besoin de `bundles.php`).

---

## Variables d'environnement

Copier les variables dans `.env.local` et renseigner les vraies valeurs :

| Variable | Description | Défaut |
|---|---|---|
| `ALIEXPRESS_APP_KEY` | Clé d'application AliExpress | — |
| `ALIEXPRESS_APP_SECRET` | Secret d'application | — |
| `ALIEXPRESS_ACCESS_TOKEN` | Token OAuth (session) | — |
| `ALIEXPRESS_BASE_URL` | URL de base de l'API | `https://api-sg.aliexpress.com` |
| `ALIEXPRESS_TIMEOUT` | Timeout HTTP (secondes) | `30` |
| `ALIEXPRESS_TARGET_CURRENCY` | Devise du prix affiché | `EUR` |
| `ALIEXPRESS_TARGET_LANGUAGE` | Langue des descriptions | `fr` |
| `ALIEXPRESS_SYNC_KEYWORDS` | Mots-clés de sync (JSON) | `["barbecue","grill","bbq accessories"]` |
| `ALIEXPRESS_SYNC_BATCH_SIZE` | Produits par page | `20` |

**Obtenir les credentials :** https://developers.aliexpress-solution.com

---

## Commandes CLI

### Synchroniser les produits

```bash
# Synchronisation complète (tous les mots-clés de ALIEXPRESS_SYNC_KEYWORDS)
docker compose run --rm php bin/console aliexpress:sync:products

# Par mot-clé
docker compose run --rm php bin/console aliexpress:sync:products --keyword=barbecue

# Un seul produit par item_id
docker compose run --rm php bin/console aliexpress:sync:products --item=1234567890
```

### Suivi d'une commande

```bash
docker compose run --rm php bin/console aliexpress:order:track \
  --order=8141234567890 \
  --tracking=JX123456789CN
```

---

## Connexion Sylius

Les produits sont créés/mis à jour dans Sylius avec le code `aliexpress_<item_id>`.
Cela les distingue des produits Alibaba (`alibaba_<id>`) et des produits manuels.

### Activer la persistance Sylius

La persistance est active par défaut via `SyliusProductPersistence` (configurée dans
`config/services.yaml`). Pour désactiver temporairement (tests) :

```yaml
# config/services.yaml
Cagrille\AliExpressBundle\Contract\ProductPersistenceInterface:
    alias: Cagrille\AliExpressBundle\Service\NullProductPersistence
```

### Passer une commande dropship

Injecter `OrderEndpointInterface` et construire un `OrderRequestDto` :

```php
use Cagrille\AliExpressBundle\Contract\OrderEndpointInterface;
use Cagrille\AliExpressBundle\Dto\OrderRequestDto;

class DropshipOrderHandler
{
    public function __construct(
        private readonly OrderEndpointInterface $orderEndpoint,
    ) {}

    public function handle(Order $syliusOrder): void
    {
        $request = new OrderRequestDto(
            syliusOrderId:    (string) $syliusOrder->getNumber(),
            productId:        '1234567890',
            quantity:         2,
            skuAttr:          '200000182:193',
            shippingAddress:  '42 rue de la Paix',
            recipientName:     'Jean Dupont',
            recipientPhone:   '+33612345678',
            country:          'FR',
            city:             'Paris',
            zipCode:          '75002',
            logisticsService: 'CAINIAO_STANDARD',
        );

        $orderDto = $this->orderEndpoint->create($request);
        // Stocker $orderDto->aliExpressOrderId dans la commande Sylius…
    }
}
```

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

Puis dans `config/services.yaml` :

```yaml
Cagrille\AliExpressBundle\Contract\ProductPersistenceInterface:
    alias: App\Service\MyCustomPersistence
```

---

## Principes de conception

| Principe | Application |
|---|---|
| **DRY** | Même architecture que l'AlibabaBundle (interfaces, DTOs, Null Object) |
| **YAGNI** | Pas d'abstraction commune Alibaba/AliExpress tant qu'elle n'est pas nécessaire |
| **SRP** | Client HTTP ≠ Endpoints ≠ Services métier ≠ Persistance |
| **DIP** | `ProductSyncService` dépend de `ProductPersistenceInterface`, jamais de Sylius |
| **OCP** | Persistance swappable sans modifier `ProductSyncService` |
| **Null Object** | `NullProductPersistence` remplace tout mock en dev/test |
