# Cagrille — Guide d'installation from scratch

Ce document retrace **tous les problèmes rencontrés et leurs solutions** lors de la migration vers Sylius 2.2.
Il constitue la référence pour toute réinstallation future.

---

## Prérequis

- Docker + Docker Compose v2
- Git
- Ports disponibles : `14080`, `14081`, `14306`, `8025`

---

## Installation

```bash
# 1. Cloner le dépôt
git clone <url-du-repo> cagrille
cd cagrille

# 2. Lancer l'initialisation complète
make init

# 3. Ouvrir la boutique
open http://localhost:14080
# Admin : http://localhost:14080/admin  (sylius / sylius)
# Adminer : http://localhost:14081
# MailHog : http://localhost:8025
```

---

## Problèmes rencontrés et solutions

### 1. Le projet original (Sylius 1.13) n'était pas correctement initialisé

**Contexte** : Le projet avait été créé manuellement avec un `composer.json` incomplet.
Sylius n'avait pas été installé via le recipe Flex officiel, donc `bundles.php` et tous
les fichiers de config Sylius étaient manquants ou incomplets.

**Solution** : Repartir de zéro depuis [Sylius-Standard 2.2](https://github.com/Sylius/Sylius-Standard/tree/2.2).

```bash
git clone --depth=1 --branch 2.2 https://github.com/Sylius/Sylius-Standard.git /tmp/sylius-standard
```

---

### 2. AlibabaBundle : conflit de namespace avec l'autodiscovery `App\`

**Erreur** :
```
Fatal error: Cannot declare interface Cagrille\AlibabaBundle\Contract\AlibabaApiClientInterface,
because the name is already in use
```

**Cause** : `config/services.yaml` auto-découvrait tout `src/` sous le namespace `App\`,
ce qui entrait en conflit avec `Cagrille\AlibabaBundle\`.

**Solution** dans `config/services.yaml` :

```yaml
App\:
    resource: '../src/'
    exclude:
        - '../src/Entity/'
        - '../src/Kernel.php'
        - '../src/AlibabaBundle/'   # ← ajout obligatoire

Cagrille\AlibabaBundle\:
    resource: '../src/AlibabaBundle/'
    exclude:
        - '../src/AlibabaBundle/Resources/'
```

---

### 3. AlibabaBundle : arguments scalaires non autowirables

**Erreur** :
```
Cannot autowire service "Cagrille\AlibabaBundle\Api\AlibabaApiClient":
argument "$appKey" of method "__construct()" is type-hinted "string",
you should configure its value explicitly.
```

**Cause** : Le container Symfony ne peut pas autowirer des `string`, `int`, `array` —
il faut les lier à des paramètres/variables d'environnement.

**Solution** dans `config/services.yaml` :

```yaml
Cagrille\AlibabaBundle\Api\AlibabaApiClient:
    arguments:
        $appKey:      '%env(ALIBABA_APP_KEY)%'
        $appSecret:   '%env(ALIBABA_APP_SECRET)%'
        $accessToken: '%env(ALIBABA_ACCESS_TOKEN)%'
        $baseUrl:     '%env(string:ALIBABA_BASE_URL)%'
        $timeout:     '%env(int:ALIBABA_TIMEOUT)%'

Cagrille\AlibabaBundle\Service\ProductSyncService:
    arguments:
        $categories: '%env(json:ALIBABA_SYNC_CATEGORIES)%'
        $batchSize:  '%env(int:ALIBABA_SYNC_BATCH_SIZE)%'
```

**Variables à ajouter dans `.env`** :

```env
###> cagrille/alibaba-bundle ###
ALIBABA_APP_KEY=changeme
ALIBABA_APP_SECRET=changeme
ALIBABA_ACCESS_TOKEN=changeme
ALIBABA_BASE_URL=https://api.alibaba.com
ALIBABA_TIMEOUT=30
ALIBABA_SYNC_CATEGORIES=[]
ALIBABA_SYNC_BATCH_SIZE=50
###< cagrille/alibaba-bundle ###
```

---

### 4. `guzzlehttp/oauth-subscriber` bloquée pour faille de sécurité

**Erreur** au `composer require` :

```
Required package "guzzlehttp/oauth-subscriber" is not present in the lock file.
[...] affected by security advisories ("PKSA-pg71-gz29-h5sq")
```

**Cause** : Ce package n'était pas utilisé dans le code et possède une faille connue.

**Solution** : Le retirer de `composer.json`. Seuls `guzzlehttp/guzzle` et
`league/oauth2-client` sont nécessaires pour AlibabaBundle.

---

### 5. `themes/CagrilleTheme/composer.json` en YAML au lieu de JSON

**Erreur** :
```
In JsonFileConfigurationLoader.php line 33:
  array_merge(): Argument #2 must be of type array, null given
```

**Cause** : Le fichier `themes/CagrilleTheme/composer.json` contenait du YAML
(avec des commentaires `#`, syntaxe `key: value`) au lieu de JSON valide.
SyliusThemeBundle le parse en tant que JSON → `null` → crash.

**Solution** : remplacer le contenu par du JSON valide :

```json
{
    "name": "cagrille/cagrille-theme",
    "type": "sylius-theme",
    "description": "Thème e-commerce sur l'univers du barbecue et du feu",
    "extra": {
        "sylius-theme": {
            "title": "Cagrille BBQ Theme"
        }
    },
    "authors": [
        {
            "name": "Équipe Cagrille",
            "homepage": "https://cagrille.fr"
        }
    ]
}
```

> **Règle** : tout `composer.json` dans un dossier de thème Sylius **doit** être du JSON
> valide et contenir `"type": "sylius-theme"`.

---

### 6. Adapter `compose.override.yml` (ports + adminer)

Sylius-Standard fournit `compose.override.dist.yml`. On copie et adapte :

```yaml
# compose.override.yml
services:
    nginx:
        ports:
            - "14080:80"   # au lieu de 80:80

    mysql:
        ports:
            - "14306:3306"

    adminer:               # remplace phpMyAdmin
        image: adminer:4
        ports:
            - "14081:8080"
        environment:
            ADMINER_DEFAULT_SERVER: mysql
            ADMINER_DESIGN: dracula
        depends_on:
            - mysql

    mailhog:
        ports:
            - "8025:8025"
```

---

### 7. `nodejs` exited with code 0 — normal

Le service `nodejs` dans `compose.override.yml` est un **one-shot** :
il fait `yarn install && yarn build` puis s'arrête proprement (code 0 = succès).
Ce n'est pas une erreur.

---

## Récapitulatif des fichiers modifiés vs Sylius-Standard

| Fichier | Modification |
|---|---|
| `composer.json` | Nom projet, ajout `guzzlehttp/guzzle` + `league/oauth2-client`, autoload `Cagrille\AlibabaBundle\` |
| `config/services.yaml` | Exclusion `src/AlibabaBundle/` de `App\`, ajout autodiscovery + config scalaires AlibabaBundle |
| `.env` | Ajout des variables `ALIBABA_*` |
| `compose.override.yml` | Ports custom (14080/14081/14306), ajout Adminer, MailHog 8025 |
| `themes/CagrilleTheme/composer.json` | Converti de YAML → JSON valide avec `"type": "sylius-theme"` |
| `src/AlibabaBundle/` | Restauré depuis l'ancien projet (inchangé) |
| `themes/CagrilleTheme/` | Restauré depuis l'ancien projet (sauf composer.json corrigé) |
