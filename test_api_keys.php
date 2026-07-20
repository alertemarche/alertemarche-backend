<?php
/**
 * Script de test des clés API OpenAI et Brevo
 * Usage: php test_api_keys.php
 */

echo "=== Test des clés API AlerteMarché ===\n\n";

// Charger les variables d'environnement
if (file_exists('.env')) {
    $lines = file('.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($key, $value) = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
} else {
    die("❌ Fichier .env introuvable. Créez-le d'abord.\n");
}

// Test OpenAI
echo "1️⃣  TEST OPENAI API\n";
$openaiKey = $_ENV['OPENAI_API_KEY'] ?? '';
if (empty($openaiKey) || $openaiKey === 'sk-proj-VOTRE_CLE_ICI') {
    echo "   ⚠️  Clé OpenAI non configurée dans .env\n\n";
} else {
    echo "   🔑 Clé détectée: " . substr($openaiKey, 0, 20) . "...\n";
    echo "   🔄 Test de connexion...\n";
    
    $ch = curl_init('https://api.openai.com/v1/models');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $openaiKey
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        $models = array_column($data['data'], 'id');
        $hasGpt4o = in_array('gpt-4o', $models) || in_array('gpt-4o-2024-05-13', $models);
        
        echo "   ✅ Connexion réussie !\n";
        echo "   📊 Modèles accessibles: " . count($models) . "\n";
        echo "   🤖 GPT-4o disponible: " . ($hasGpt4o ? "OUI ✅" : "NON ⚠️") . "\n\n";
    } else {
        echo "   ❌ Erreur de connexion (HTTP $httpCode)\n";
        echo "   💡 Vérifiez que la clé est valide et que le compte a des crédits\n\n";
    }
}

// Test Brevo
echo "2️⃣  TEST BREVO API\n";
$brevoKey = $_ENV['BREVO_API_KEY'] ?? '';
if (empty($brevoKey) || $brevoKey === 'xkeysib-VOTRE_CLE_ICI') {
    echo "   ⚠️  Clé Brevo non configurée dans .env\n\n";
} else {
    echo "   🔑 Clé détectée: " . substr($brevoKey, 0, 20) . "...\n";
    echo "   🔄 Test de connexion...\n";
    
    $ch = curl_init('https://api.brevo.com/v3/account');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'api-key: ' . $brevoKey
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        echo "   ✅ Connexion réussie !\n";
        echo "   📧 Email: " . ($data['email'] ?? 'N/A') . "\n";
        echo "   📦 Plan: " . ($data['plan'][0]['type'] ?? 'Free') . "\n";
        echo "   📊 Crédits email restants: " . ($data['plan'][0]['credits'] ?? 'Illimité') . "\n\n";
    } else {
        echo "   ❌ Erreur de connexion (HTTP $httpCode)\n";
        echo "   💡 Vérifiez que la clé est valide\n\n";
    }
}

// Résumé
echo "=== RÉSUMÉ ===\n";
$openaiOk = !empty($openaiKey) && $openaiKey !== 'sk-proj-VOTRE_CLE_ICI';
$brevoOk = !empty($brevoKey) && $brevoKey !== 'xkeysib-VOTRE_CLE_ICI';

if ($openaiOk && $brevoOk) {
    echo "✅ Toutes les clés API sont configurées et fonctionnelles !\n";
    echo "🚀 Vous pouvez démarrer le développement.\n";
} else {
    echo "⚠️  Configuration incomplète:\n";
    if (!$openaiOk) echo "   - OpenAI: à configurer\n";
    if (!$brevoOk) echo "   - Brevo: à configurer\n";
    echo "\n💡 Suivez le guide GUIDE_CREATION_OPENAI_BREVO.pdf\n";
}

echo "\n";
