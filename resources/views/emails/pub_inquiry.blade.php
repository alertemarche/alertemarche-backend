@extends('emails.layout')

@section('content')
<h2 style="margin:0 0 18px;color:#c2410c;font-size:1.25rem;">📢 Nouvelle demande d'espace publicitaire</h2>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin-bottom:22px;">
  <tr>
    <td style="padding:10px 14px;background:#fff7ed;border-left:4px solid #f97316;border-radius:4px 0 0 4px;font-weight:700;font-size:.9rem;color:#7c3c1a;width:160px;vertical-align:top;">Entreprise / Nom</td>
    <td style="padding:10px 14px;background:#fff7ed;font-size:.9rem;color:#1c1917;vertical-align:top;">{{ $company }}</td>
  </tr>
  <tr>
    <td style="padding:10px 14px;font-weight:700;font-size:.9rem;color:#7c3c1a;vertical-align:top;">Email de contact</td>
    <td style="padding:10px 14px;font-size:.9rem;color:#1c1917;vertical-align:top;"><a href="mailto:{{ $email }}" style="color:#f97316;">{{ $email }}</a></td>
  </tr>
  <tr>
    <td style="padding:10px 14px;background:#fff7ed;font-weight:700;font-size:.9rem;color:#7c3c1a;vertical-align:top;">Pays ciblé</td>
    <td style="padding:10px 14px;background:#fff7ed;font-size:.9rem;color:#1c1917;vertical-align:top;">{{ $countryLabel }}</td>
  </tr>
  @if($message)
  <tr>
    <td style="padding:10px 14px;font-weight:700;font-size:.9rem;color:#7c3c1a;vertical-align:top;">Message</td>
    <td style="padding:10px 14px;font-size:.9rem;color:#1c1917;vertical-align:top;line-height:1.5;">{{ $message }}</td>
  </tr>
  @endif
</table>

<p style="margin:0 0 10px;font-size:.88rem;color:#57534e;">Répondre directement à <a href="mailto:{{ $email }}" style="color:#f97316;font-weight:700;">{{ $email }}</a> pour proposer un devis ou fixer un appel.</p>

<div style="margin-top:20px;padding:14px 18px;background:#fef3c7;border-radius:8px;font-size:.82rem;color:#92400e;">
  ⏱ Cette demande a été soumise le {{ now()->timezone('Africa/Porto-Novo')->format('d/m/Y à H:i') }} (WAT).
</div>
@endsection
