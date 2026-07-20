# 🔑 Intégration des Clés API — Guide Rapide

Ce document explique comment intégrer les clés OpenAI et Brevo dans le projet AlerteMarché.

---

## 📋 Prérequis

Vous devez avoir créé les comptes et obtenu les clés API :
- ✅ Clé OpenAI : `sk-proj-...`
- ✅ Clé Brevo : `xkeysib-...`

➡️ Si pas encore fait, suivez le guide **GUIDE_CREATION_OPENAI_BREVO.pdf**

---

## 🚀 Intégration en 3 étapes

### Étape 1 : Créer le fichier .env
```bash
cd /home/ubuntu/github_repos/alertemarche-backend
cp .env.production.template .env
```

### Étape 2 : Remplir les clés API
Ouvrez le fichier `.env` et remplacez :

```env
# AVANT
OPENAI_API_KEY=sk-proj-VOTRE_CLE_ICI
BREVO_API_KEY=xkeysib-VOTRE_CLE_ICI

# APRÈS (avec vos vraies clés)
OPENAI_API_KEY=sk-proj-abc123def456...
BREVO_API_KEY=xkeysib-xyz789uvw012...
```

**Également mettre à jour :**
```env
BREVO_SENDER_EMAIL=famillesmoutairou@gmail.com
BREVO_SENDER_NAME=AlerteMarché
```

### Étape 3 : Tester les clés
```bash
php test_api_keys.php
```

**Résultat attendu :**
```
=== Test des clés API AlerteMarché ===

1️⃣  TEST OPENAI API
   🔑 Clé détectée: sk-proj-abc123def...
   🔄 Test de connexion...
   ✅ Connexion réussie !
   📊 Modèles accessibles: 67
   🤖 GPT-4o disponible: OUI ✅

2️⃣  TEST BREVO API
   🔑 Clé détectée: xkeysib-xyz789uvw...
   🔄 Test de connexion...
   ✅ Connexion réussie !
   📧 Email: famillesmoutairou@gmail.com
   📦 Plan: Free
   📊 Crédits email restants: 300

=== RÉSUMÉ ===
✅ Toutes les clés API sont configurées et fonctionnelles !
🚀 Vous pouvez démarrer le développement.
```

---

## ⚠️ Dépannage

### OpenAI : "HTTP 401"
- La clé est invalide ou expirée
- Vérifiez sur https://platform.openai.com/api-keys

### OpenAI : "GPT-4o disponible: NON"
- Votre compte est en "Tier 0"
- Solution : ajoutez 5 USD de crédit pour passer en "Tier 1"

### Brevo : "HTTP 401"
- La clé est invalide
- Vérifiez sur https://app.brevo.com/settings/keys/api

### Brevo : "Sender email not verified"
- L'email expéditeur n'est pas vérifié
- Allez dans **Senders & IP** → **Senders** → cliquez le lien de vérification

---

## 🔐 Sécurité

⚠️ **Le fichier `.env` contient des secrets** :
- Il est dans `.gitignore` (ne sera JAMAIS commité)
- Ne le partagez JAMAIS publiquement
- Sur le VPS de production, stockez-le dans `/var/secrets/alertemarche/.env`

---

## ✅ Prochaines étapes

Une fois les clés validées :
1. Démarrer le backend en local : `docker-compose up`
2. Tester l'API d'analyse : `POST /api/opportunities/analyze`
3. Configurer le VPS de production (Semaine 1)

---

📧 **Contact :** famillesmoutairou@gmail.com
