# PlantCompanion Symfony

[![Symfony](https://img.shields.io/badge/Symfony-7.4-000000?logo=symfony)](https://symfony.com)
[![PHP](https://img.shields.io/badge/PHP-8.3-777BB4?logo=php)](https://php.net)
[![Vue.js](https://img.shields.io/badge/Vue.js-3.5-4FC08D?logo=vuedotjs)](https://vuejs.org)

PlantCompanion est une application Symfony 7.4 LTS de gestion de plantes et de jardinage, avec un frontend moderne en Vue 3.

---

## Fonctionnalités

### Gestion des Plantes
- CRUD complet des végétaux avec historique des modifications
- Classification par type, groupe, lieu d'origine
- Gestion des porte-greffes
- Suivi des périodes de floraison et fructification
- Calendrier de récolte personnalisé

### Gestion des Photos
- Upload multiple de photos
- Génération automatique de miniatures (250x250, 900x900)
- Extraction des métadonnées EXIF (dates)
- Définition de photos par défaut
- Galerie d'images par plante

### Gestion des Utilisateurs
- Système d'authentification sécurisé
- Scoping multi-utilisateur
- Sélecteur de groupe courant
- Historique des actions et modifications

### Interventions et Suivi
- Enregistrement des actions (plantations, récoltes, observations)
- Historique automatique des modifications
- Filtres par date, type, plante

---

## Architecture Technique

Backend: Symfony 7.4 LTS + Doctrine ORM 3 + MySQL 8.0
Frontend: Vue 3.5 + Pinia 2.2 + Vite 5.4
Infra: Docker (PHP 8.3, Nginx, Node 22, MySQL)

---

## Installation

### Prérequis
- Docker et Docker Compose
- Git

### Cloner le Repository
```bash
git clone https://github.com/loclamor/plantCompanion-symfony.git
cd plantCompanion-symfony
```

### Démarrer l'Environnement
```bash
make build
make up
```

### Installer les Dépendances
```bash
docker compose exec php composer install
make front-install
```

### Démarrer le Frontend
```bash
make front-dev
```

### Accéder à l'Application
- Backend: http://localhost:8001
- Frontend Dev: http://localhost:5173
- API: http://localhost:8001/api

---

## Commandes Utiles

make build - Build des conteneurs Docker
make up - Démarrer tous les services
make down - Arrêter tous les services
make bash - Shell dans le conteneur PHP
make front-dev - Lancer Vite en mode dev
make front-build - Build du frontend
make test - Exécuter les tests
make test-api - Tests API uniquement

---

## Structure du Projet

plantCompanion-symfony/
+-- assets/                    # Frontend (Vue 3 + Vite)
|   +-- src/                  # Code source Vue
+-- config/                   # Configuration Symfony
|   +-- packages/
+-- .docker/                  # Configuration Docker
|   +-- php/Dockerfile
|   +-- nginx/Dockerfile
+-- src/
|   +-- Controller/Api/      # API REST JSON
|   +-- Entity/              # Entités Doctrine
|   +-- Repository/          # Repositories
|   +-- Security/Voter/      # Voters
|   +-- Service/             # Services
+-- templates/
|   +-- spa.html.twig
+-- tests/                   # Tests PHPUnit
+-- .env
+-- composer.json
+-- docker-compose.yml
+-- Makefile