# Template MVC Vide pour Tout Nouveau Projet (PHP Natif)

> **Framework MVC PHP**  
> Créé par [Feras Altaleb](https://github.com/AltalebFeras) — à des fins éducatives uniquement.

---

Un framework MVC léger et extensible, construit en PHP natif, pour vous aider à démarrer rapidement et efficacement vos applications web.

---

## 🚀 Fonctionnalités

- **Architecture MVC** — Séparation claire des responsabilités grâce au modèle Model-View-Controller.
- **Routage Simple** — Système de routage intuitif et flexible.
- **Abstraction de Base de Données** — Interactions faciles avec la base de données via PDO.
- **Sécurité** — Protection intégrée contre les vulnérabilités courantes.
- **Extensible** — Ajoutez facilement de nouvelles fonctionnalités.
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
  - Définissez votre **URL de base** dans `config.php`.
  - Configurez les **paramètres de messagerie** dans `config.php`.
  - Définissez votre **fuseau horaire** dans `config.php`.

### 4. Commencez à développer

- Créez vos contrôleurs, modèles et vues dans les répertoires appropriés.
- Définissez vos routes dans le fichier `routes.php`.

### 5. Lancer l’application

Accédez à votre application dans le navigateur :

```bash
http://localhost/chemin-vers-votre-projet/public
```

---

## 📁 Structure du répertoire

```
src/      # Code principal de l'application (contrôleurs, dépôts, modèles, vues, services)
public/   # Fichiers accessibles publiquement (images, css, js, index.php, .htaccess)
```

---

## 🧰 Bonnes pratiques

- Utilisez les fonctionnalités de sécurité intégrées pour protéger votre application.
- Testez soigneusement votre application avant de la déployer en production.
- Surveillez les performances et la sécurité de votre application.
- Mettez régulièrement à jour votre application pour la garder sécurisée et à jour.

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