<?php

namespace App\Console\Commands;

use App\Models\Tender;
use App\Services\BrevoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

/**
 * Vérifie l'état du système (queue, traitement IA) et envoie une alerte email
 * si un blocage est détecté.
 */
class CheckSystemHealth extends Command
{
    protected $signature = 'system:check-health';
    protected $description = 'Vérifie l\'état du système et envoie une alerte si un problème est détecté';

    protected BrevoService $brevo;

    public function __construct(BrevoService $brevo)
    {
        parent::__construct();
        $this->brevo = $brevo;
    }

    public function handle(): int
    {
        $issues = [];

        // 1. Vérifier les marchés bloqués (non traités depuis plus de 2h)
        $stuckTenders = Tender::where('ai_processed', false)
            ->where('created_at', '<', now()->subHours(2))
            ->count();

        if ($stuckTenders > 0) {
            $issues[] = "⚠️ <strong>{$stuckTenders} marchés</strong> bloqués en attente de traitement IA depuis plus de 2h";
        }

        // 2. Vérifier la taille de la file d'attente Redis
        try {
            $queueSize = Redis::llen('queues:ai');
            if ($queueSize > 500) {
                $issues[] = "⚠️ File d'attente IA surchargée : <strong>{$queueSize} jobs</strong> en attente";
            }
        } catch (\Throwable $e) {
            $issues[] = "❌ Impossible de vérifier la file Redis : {$e->getMessage()}";
        }

        // 3. Vérifier le nombre de marchés récents (scrapers actifs)
        $recentTenders = Tender::where('created_at', '>', now()->subHours(24))->count();
        if ($recentTenders === 0) {
            $issues[] = "⚠️ Aucun nouveau marché collecté dans les dernières 24h — les scrapers sont peut-être arrêtés";
        }

        // 4. Vérifier les jobs en échec
        try {
            $failedJobsCount = \DB::table('failed_jobs')->count();
            if ($failedJobsCount > 1000) {
                $issues[] = "⚠️ <strong>{$failedJobsCount} jobs</strong> en échec dans la base de données";
            }
        } catch (\Throwable $e) {
            // Ignorer si la table n'existe pas
        }

        // Si des problèmes sont détectés, envoyer l'email d'alerte
        if (count($issues) > 0) {
            $this->sendAlert($issues, $stuckTenders, $queueSize ?? 0, $recentTenders);
            $this->error('❌ Problèmes détectés — Email d\'alerte envoyé');
            return 1;
        }

        $this->info('✅ Système OK — Aucun problème détecté');
        return 0;
    }

    protected function sendAlert(array $issues, int $stuckTenders, int $queueSize, int $recentTenders): void
    {
        $alertEmail = config('services.brevo.alert_email', 'admin@alertemarche.com');
        
        $htmlContent = view('emails.system_alert', [
            'issues' => $issues,
            'stuck_tenders' => $stuckTenders,
            'queue_size' => $queueSize,
            'recent_tenders' => $recentTenders,
            'timestamp' => now()->format('Y-m-d H:i:s'),
        ])->render();

        $this->brevo->send(
            $alertEmail,
            'Admin AlerteMarché',
            '🚨 Problème d\'affichage de nouveaux marchés',
            $htmlContent
        );

        $this->line("📧 Email d'alerte envoyé à {$alertEmail}");
    }
}
