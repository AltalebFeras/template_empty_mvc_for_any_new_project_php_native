# Template MVC Vide pour Tout Nouveau Projet (PHP Natif)

> **Framework MVC PHP**  
> Créé par [Feras Altaleb](https://github.com/AltalebFeras) — à des fins éducatives uniquement.

---

Un framework MVC léger et extensible, construit en PHP natif, pour vous aider à démarrer rapidement et efficacement vos applications web.

> **Nécessite PHP 8.0+** (utilise les attributs PHP natifs pour le routage).

---

## 🚀 Fonctionnalités

- **Architecture MVC** — Séparation claire des responsabilités grâce au modèle Model-View-Controller.
- **Routage par attributs** — Attributs `#[Route]` à la Symfony déclarés directement sur les méthodes des contrôleurs. Aucun fichier de routes centralisé n'est nécessaire.
- **Abstraction de base de données** — Interactions faciles avec la base de données via PDO et un `AbstractRepository` intégré.
- **Sécurité** — Protection contre le vol de session, usurpation de méthode HTTP, détection HTTPS et résolution d'IP réelle.
- **Extensible** — Ajoutez un nouveau contrôleur avec des attributs `#[Route]` : les routes sont découvertes automatiquement par Reflection.
- **Léger** — Dépendances minimales pour des performances optimales.

---

## 🛠️ Bien démarrer

### 1. Cloner le dépôt

```bash
git clone https://github.com/AltalebFeras/template_empty_mvc_for_any_new_project_php_native.git
```

### 2. Installer les dépendances

```bash
composer install
```

### 3. Configurer votre environnement

- Copiez `config_example.php` et renommez-le en `config.php`.
- Mettez à jour les paramètres selon votre environnement (développement ou production) :
  - Configurez votre **connexion à la base de données** dans `config.php`.
  - Définissez votre **URL de base** (`HOME_URL`) dans `config.php`.
  - Configurez les **paramètres de messagerie** dans `config.php`.
  - Définissez votre **fuseau horaire** dans `src/init.php`.

### 4. Définir vos routes

Les routes sont définies avec l'attribut `#[Route]` directement au-dessus de chaque méthode de contrôleur — aucun fichier de routes centralisé n'est requis.

```php
use src\Services\Route;

class UserController
{
    // Page publique — GET uniquement
    #[Route('/login', methods: ['GET'])]
    public function afficherFormulaireLogin(): void
    {
        // afficher la vue de connexion
    }

    // Soumission du formulaire — POST uniquement
    #[Route('/login', methods: ['POST'])]
    public function traiterLogin(): void
    {
        // traiter les identifiants
    }

    // Page protégée — nécessite une session active
    #[Route('/dashboard', methods: ['GET'], authRequired: true)]
    public function afficherDashboard(): void
    {
        // afficher le tableau de bord
    }
}
```

**Paramètres de `#[Route]` :**

| Paramètre | Type | Défaut | Description |
|---|---|---|---|
| `$path` | `string` | *(requis)* | Chemin URL à correspondre (ex. `'/login'`) |
| `$methods` | `string\|array` | `['GET']` | Méthodes HTTP autorisées |
| `$name` | `string` | `''` | Nom de route optionnel |
| `$authRequired` | `bool` | `false` | Redirige vers `/login` si non authentifié |

> Le routeur découvre automatiquement toutes les classes dans `src/Controllers/` — créez simplement un nouveau contrôleur et ajoutez des attributs `#[Route]`.

### 5. Usurpation de méthode HTTP dans les formulaires

Les formulaires HTML ne supportent que `GET` et `POST`. Pour envoyer `PUT`, `PATCH` ou `DELETE`, ajoutez un champ caché :

```html
<form method="POST" action="/ressource/1">
    <input type="hidden" name="_method" value="DELETE">
    ...
</form>
```

`ConfigRouter::getMethod()` résoudra automatiquement la méthode effective.

### 6. Lancer l'application

Accédez à votre application dans le navigateur :

```
http://localhost/chemin-vers-votre-projet/public
```

---

## 📁 Structure du répertoire

```
public/                   # Racine web — pointez votre serveur / virtual host ici
│   index.php             # Point d'entrée unique
│   .htaccess             # Réécriture d'URL (Apache)
└── assets/               # CSS, JS, images

src/
├── init.php              # Amorçage : session, autoloader, config, routeur
├── Abstracts/
│   ├── AbstractController.php   # Helpers render() et redirect()
│   └── AbstractRepository.php  # Helpers CRUD (getAll, getById, create, …)
├── Controllers/          # Vos contrôleurs — ajoutez les attributs #[Route] ici
├── Entities/             # Classes d'entités PHP simples (hydrées via le trait Hydration)
├── Migrations/           # Fichiers SQL de migration
├── Repositories/         # Classes de dépôts étendant AbstractRepository
├── Services/
│   ├── Route.php         # Définition de l'attribut PHP #[Route]
│   ├── router.php        # Découverte et dispatch des routes via Reflection
│   ├── ConfigRouter.php  # Utilitaires HTTP : getMethod, redirect, isAjax, getClientIp…
│   ├── Database.php      # Wrapper de connexion PDO
│   ├── Encrypt_decrypt.php
│   ├── Hydration.php     # Trait pour l'hydratation automatique des entités
│   ├── Mail.php          # Wrapper PHPMailer
│   └── Validator.php
└── Views/                # Templates de vues PHP
```

---

## 🔒 Utilitaires de sécurité (`ConfigRouter`)

| Méthode | Description |
|---|---|
| `ConfigRouter::getMethod()` | Retourne la méthode HTTP réelle, avec support de l'usurpation `PUT`/`PATCH`/`DELETE` via le champ `_method` |
| `ConfigRouter::checkOriginConnection()` | Valide l'IP et le user-agent de la session pour détecter le vol de session — retourne `false` en cas d'anomalie |
| `ConfigRouter::redirect($url, $code)` | Redirection sécurisée avec code HTTP (302 par défaut) |
| `ConfigRouter::isAjax()` | Détecte les requêtes `XMLHttpRequest` / `fetch` |
| `ConfigRouter::isHttps()` | Retourne `true` si la connexion est en HTTPS |
| `ConfigRouter::getClientIp()` | Résout l'IP réelle du client (compatible proxy, validée) |

---

## 🧰 Bonnes pratiques

- Utilisez `$authRequired: true` sur les routes nécessitant un utilisateur connecté.
- N'exposez jamais le dossier `src/` — seul `public/` doit être la racine web.
- Stockez les mots de passe avec `password_hash()` / `password_verify()`.
- Validez et assainissez toutes les entrées utilisateur au niveau du contrôleur.
- Testez soigneusement votre application avant de la déployer en production.

---

## 🤝 Contribuer

Les contributions sont les bienvenues !  
Si vous avez des suggestions d'amélioration ou de nouvelles fonctionnalités, veuillez [ouvrir une issue](https://github.com/AltalebFeras/template_empty_mvc_for_any_new_project_php_native/issues) ou soumettre une pull request.

---

## 👤 Auteur

**Feras Altaleb**  
[GitHub](https://github.com/AltalebFeras)

---

## ⭐️ Amusez-vous à créer des applications web incroyables avec ce framework MVC simple !