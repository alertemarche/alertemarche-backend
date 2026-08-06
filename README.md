# AlerteMarché — Backend (API & cœur métier)

![AlerteMarché](https://img.shields.io/badge/AlerteMarch%C3%A9-by%20PRO%20BENIN%20SARL-1a7f5a?style=for-the-badge)
![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-11-FF2D20?logo=laravel&logoColor=white)

API REST **Laravel 11** de la plateforme SaaS **AlerteMarché** — veille intelligente des appels d'offres au **Bénin**, **Togo**, **Côte d'Ivoire**, **Sénégal** et **Burkina Faso**.

## Fonctionnalités

- **4 profils** : Prestataires, Artisans, Administration publique, ONG.
- **Freemium** : 5 alertes gratuites (e-mail), puis suspension + invitation à s'abonner.
- **Abonnements multi-pays** : tarif de base × nombre de pays, remise de lancement -50 %.
- **Pipeline IA** : résumé structuré GPT-4o + classification sectorielle (files Redis).
- **Matching tricouche** : direct prestataires, inverse artisans, inverse sourcing admin/ONG.
- **Notifications** : e-mail (Brevo) + WhatsApp Business (Meta, abonnés payants).
- **Ingestion scrapers** : API interne authentifiée + déduplication par hash.
- **Back-office** : statistiques, monitoring des robots, validation des besoins.
- **Paiement** : webhook KKPays (Mobile Money + carte).

## Stack

| Composant     | Technologie                 |
|---------------|-----------------------------|
| Framework     | Laravel 11 (PHP 8.2+)       |
| Base de données | PostgreSQL 15             |
| Cache & files | Redis 7                     |
| Auth API      | Laravel Sanctum             |
| IA            | OpenAI GPT-4o               |
| E-mail        | Brevo                       |
| Conteneurs    | Docker + Docker Compose     |

## Démarrage rapide (dev)

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app php artisan migrate --seed
```

L'API est exposée sur `http://localhost:8080`. Vérification : `GET /api/health`.

## Principaux endpoints

| Méthode | Route | Description |
|--------|-------|-------------|
| GET  | `/api/health` | État du service |
| GET  | `/api/geo/detect` | Détection pays via IP |
| POST | `/api/pricing/quote` | Devis multi-pays |
| GET  | `/api/pricing/grid` | Grille des 4 profils |
| GET  | `/api/tenders` | Appels d'offres (filtres) |
| GET  | `/api/needs` | Besoins artisans approuvés |
| POST | `/api/auth/register` | Inscription + OTP |
| POST | `/api/auth/login` | Connexion |
| POST | `/api/auth/otp/verify` | Vérification OTP |
| GET  | `/api/auth/me` | Profil courant (auth) |
| GET  | `/api/alerts` | Historique alertes (auth) |
| POST | `/api/subscriptions` | Souscription (auth) |
| POST | `/api/needs` | Publication d'un besoin (auth) |
| POST | `/api/ingest/tenders` | Ingestion scrapers (jeton) |
| GET  | `/api/admin/stats` | Statistiques (admin) |
| POST | `/api/payments/kkpays/webhook` | Webhook paiement |

## Dépôts du projet

- [alertemarche-backend](https://github.com/alertemarche/alertemarche-backend) — API & cœur métier (ce dépôt)
- [alertemarche-frontend](https://github.com/alertemarche/alertemarche-frontend) — Interface web
- [alertemarche-scrapers](https://github.com/alertemarche/alertemarche-scrapers) — Robots de collecte
- [alertemarche-infra](https://github.com/alertemarche/alertemarche-infra) — Infrastructure & déploiement

---

© PRO BENIN SARL — AlerteMarché
