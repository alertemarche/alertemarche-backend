@extends('emails.layout')
@section('content')
    <h2 style="margin-top:0;color:#c53030;">🚨 Problème d'affichage de nouveaux marchés</h2>
    
    <p>Un ou plusieurs problèmes ont été détectés sur AlerteMarché :</p>
    
    <div style="background:#fef2f2;border-left:4px solid #c53030;padding:16px;margin:20px 0;">
        @foreach($issues as $issue)
            <p style="margin:8px 0;">{!! $issue !!}</p>
        @endforeach
    </div>

    <h3 style="color:#1a7f5a;margin-top:24px;">📊 État du système</h3>
    <table style="width:100%;border-collapse:collapse;margin:16px 0;">
        <tr style="background:#f7fafc;">
            <td style="padding:12px;border:1px solid #e2e8f0;"><strong>Marchés bloqués (>2h)</strong></td>
            <td style="padding:12px;border:1px solid #e2e8f0;color:{{ $stuck_tenders > 0 ? '#c53030' : '#1a7f5a' }};">
                {{ $stuck_tenders }}
            </td>
        </tr>
        <tr>
            <td style="padding:12px;border:1px solid #e2e8f0;"><strong>File d'attente IA (Redis)</strong></td>
            <td style="padding:12px;border:1px solid #e2e8f0;color:{{ $queue_size > 500 ? '#c53030' : '#1a7f5a' }};">
                {{ $queue_size }} jobs
            </td>
        </tr>
        <tr style="background:#f7fafc;">
            <td style="padding:12px;border:1px solid #e2e8f0;"><strong>Nouveaux marchés (24h)</strong></td>
            <td style="padding:12px;border:1px solid #e2e8f0;color:{{ $recent_tenders === 0 ? '#c53030' : '#1a7f5a' }};">
                {{ $recent_tenders }}
            </td>
        </tr>
        <tr>
            <td style="padding:12px;border:1px solid #e2e8f0;"><strong>Timestamp</strong></td>
            <td style="padding:12px;border:1px solid #e2e8f0;">{{ $timestamp }}</td>
        </tr>
    </table>

    <h3 style="color:#1a7f5a;margin-top:24px;">🔧 Actions à entreprendre</h3>
    <ol style="line-height:1.8;">
        <li><strong>Vérifier les workers de queue</strong><br>
            <code style="background:#f7fafc;padding:4px 8px;border-radius:4px;">docker ps | grep alertemarche_queue</code>
        </li>
        <li><strong>Vérifier les logs</strong><br>
            <code style="background:#f7fafc;padding:4px 8px;border-radius:4px;">docker logs alertemarche_queue --tail=50</code>
        </li>
        <li><strong>Débloquer les marchés (si nécessaire)</strong><br>
            <code style="background:#f7fafc;padding:4px 8px;border-radius:4px;">docker exec alertemarche_app php artisan tinker --execute="DB::statement('UPDATE tenders SET ai_processed = true WHERE ai_processed = false');"</code>
        </li>
        <li><strong>Redémarrer les workers</strong><br>
            <code style="background:#f7fafc;padding:4px 8px;border-radius:4px;">cd /home/ubuntu/alertemarche/infra && docker compose -f docker-compose.prod.yml restart queue scheduler</code>
        </li>
    </ol>

    <p style="margin-top:24px;padding:16px;background:#fef5e7;border-left:4px solid #f59e0b;border-radius:4px;">
        <strong>⚡ Rappel :</strong> Ce monitoring automatique vous permet de détecter et corriger les problèmes avant qu'ils n'impactent vos abonnés.
    </p>
@endsection
