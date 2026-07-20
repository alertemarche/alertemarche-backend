# Architecture — Backend AlerteMarché

## Vue d'ensemble

Le backend est une application **Laravel (PHP 8.2+)** qui expose une API REST et orchestre l'ensemble du cycle de vie d'une alerte : collecte → analyse IA → matching → notification.

```
┌──────────────┐    ┌───────────────┐    ┌──────────────────┐
│  Scrapers    │───▶│  API Backend  │───▶│  Notifications   │
│ (BJ/TG/CI)   │    │   (Laravel)   │    │ WhatsApp / Email │
└──────────────┘    └───────┬───────┘    └──────────────────┘
                            │
              ┌─────────────┼─────────────┐
              ▼             ▼             ▼
        ┌──────────┐  ┌──────────┐  ┌──────────┐
        │PostgreSQL│  │  Redis   │  │ OpenAI   │
        │   15     │  │  7 (queue)│  │ GPT-4o   │
        └──────────┘  └──────────┘  └──────────┘
```

## Composants principaux

### 1. Ingestion des opportunités
Les robots de collecte (`alertemarche-scrapers`) envoient les opportunités brutes au backend via une API interne authentifiée. Chaque opportunité est rattachée à un pays (BJ, TG, CI) et à un type (public / privé).

### 2. Analyse IA (OpenAI GPT-4o)
Chaque opportunité est traitée par un job en file d'attente qui :
- génère un **résumé structuré** (objet, montant, dates clés, lien source) ;
- extrait les **métadonnées de matching** (secteur, métier, localité).

Les documents officiels (DAO) ne sont jamais stockés — seul le lien vers la source gouvernementale est conservé.

### 3. Moteur de matching
- **Matching classique** : mise en correspondance des appels d'offres avec les secteurs suivis par les prestataires et administrations.
- **Matching inversé** : pour les artisans, alertes déclenchées quand un besoin correspond à leur métier et leur localité (ex : maçons recherchés à Parakou).

### 4. Abonnements multi-pays
- L'utilisateur choisit 1, 2 ou 3 pays ; les tarifs **s'additionnent** par pays.
- 4 profils : Artisan (5 000), Prestataire (25 000), Administration/ONG (75 000) FCFA/mois/pays.
- Remise de lancement -50%.
- Freemium : 5 alertes offertes avant blocage et invitation à l'abonnement.

### 5. Notifications
- **WhatsApp Business Platform (Meta)** : canal principal, résumés structurés.
- **Brevo** : e-mails transactionnels et récapitulatifs.
- Envoi asynchrone via files d'attente Redis pour absorber les pics.

### 6. Paiement
- **KKPays** : Mobile Money (MTN, Moov, Wave) et cartes bancaires.
- Webhooks de confirmation → activation/renouvellement d'abonnement.

## Files d'attente (Redis 7)

| Queue          | Rôle                                  |
|----------------|---------------------------------------|
| `ai`           | Résumés & extraction IA               |
| `matching`     | Calcul des correspondances            |
| `notifications`| Envoi WhatsApp / Email                |
| `payments`     | Traitement des webhooks KKPays        |

## Sécurité

- Cloudflare en frontal (WAF, protection DDoS, SSL).
- Secrets gérés via variables d'environnement (voir `.env.example`).
- Authentification API par tokens (Laravel Sanctum).
