@extends('emails.layout')
@section('content')
    <h2 style="margin-top:0;color:#1a7f5a;">Bienvenue{{ $name ? ', '.$name : '' }} !</h2>
    <p>Votre compte AlerteMarché est activé. Vous bénéficiez de <strong>5 alertes gratuites</strong> pour découvrir nos opportunités.</p>
    <p>Profil sélectionné : <strong>{{ ucfirst(str_replace('_', ' ', $profile)) }}</strong></p>
    <p>Dès qu'une opportunité correspond à vos critères, vous la recevez par e-mail. Pour activer le canal <strong>WhatsApp</strong> et un volume d'alertes illimité, souscrivez à l'un de nos abonnements.</p>
    <p style="margin:28px 0;">
        <a href="https://alertemarche.com/tarifs.html" style="background:#1a7f5a;color:#fff;text-decoration:none;padding:12px 22px;border-radius:8px;font-weight:bold;">Voir les tarifs</a>
    </p>
    <p style="color:#6b7d77;">Merci de votre confiance,<br>L'équipe AlerteMarché</p>
@endsection
