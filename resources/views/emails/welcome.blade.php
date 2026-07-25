@extends('emails.layout')
@section('content')
    <h2 style="margin-top:0;color:#1a7f5a;">Bienvenue{{ $name ? ', '.$name : '' }} !</h2>
    <p>Votre compte AlerteMarché est créé avec succès.</p>
    <p>Profil sélectionné : <strong>{{ ucfirst(str_replace('_', ' ', $profile)) }}</strong></p>
    <p>Pour recevoir des alertes personnalisées par <strong>e-mail</strong> dès qu'une opportunité correspond à vos critères, activez un abonnement.</p>
    <p style="margin:28px 0;">
        <a href="https://alertemarche.com/tarifs" style="background:#1a7f5a;color:#fff;text-decoration:none;padding:12px 22px;border-radius:8px;font-weight:bold;">Choisir un abonnement</a>
    </p>
    <p style="color:#6b7d77;font-size:14px;">Ou copiez ce lien dans votre navigateur : <a href="https://alertemarche.com/tarifs" style="color:#1a7f5a;">https://alertemarche.com/tarifs</a></p>
    <p style="color:#6b7d77;">Merci de votre confiance,<br>L'équipe AlerteMarché</p>
@endsection
