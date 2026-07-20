# AlerteMarché — Frontend

![AlerteMarché](https://img.shields.io/badge/AlerteMarch%C3%A9-by%20PRO%20BENIN%20SARL-1a7f5a?style=for-the-badge)
![Node](https://img.shields.io/badge/Node.js-20%2B-339933?logo=nodedotjs&logoColor=white)
![Vite](https://img.shields.io/badge/Vite-5-646CFF?logo=vite&logoColor=white)

Interface web de **AlerteMarché**, la plateforme SaaS de veille intelligente pour les appels d'offres au **Bénin**, **Togo** et **Côte d'Ivoire**.

## À propos

Ce dépôt contient l'application front (site vitrine + espace abonné) : présentation de l'offre, configurateur tarifaire multi-pays, inscription/connexion et tableau de bord utilisateur. Il consomme l'API du dépôt [alertemarche-backend](https://github.com/alertemarche/alertemarche-backend).

## Stack technique

| Composant   | Technologie              |
|-------------|--------------------------|
| Runtime     | Node.js 20+              |
| Build       | Vite                     |
| Langage     | JavaScript / TypeScript  |
| Styles      | CSS moderne / utilitaires |
| API         | REST (alertemarche-backend) |

## Démarrage rapide

```bash
npm install
cp .env.example .env   # renseigner l'URL de l'API
npm run dev
```

## Pages principales

- **Accueil** — présentation, sélection du pays (Bénin par défaut)
- **Appels d'offres publics** — veille sur les marchés publics
- **Artisans & Prestataires** — matching inversé + exemple d'alerte WhatsApp
- **Tarifs** — configurateur dynamique multi-pays
- **Tableau de bord** — espace abonné (prestataire)
- **Inscription / Connexion** — écran split-screen

> Détail complet dans [docs/pages.md](docs/pages.md).

## Dépôts du projet

- [alertemarche-backend](https://github.com/alertemarche/alertemarche-backend) — API & cœur métier
- [alertemarche-frontend](https://github.com/alertemarche/alertemarche-frontend) — Interface web (ce dépôt)
- [alertemarche-scrapers](https://github.com/alertemarche/alertemarche-scrapers) — Robots de collecte
- [alertemarche-infra](https://github.com/alertemarche/alertemarche-infra) — Infrastructure & déploiement

---

© PRO BENIN SARL — AlerteMarché
