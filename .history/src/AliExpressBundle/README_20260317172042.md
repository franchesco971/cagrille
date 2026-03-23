# AliExpressBundle — Dropshipping AliExpress pour Cagrille

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
