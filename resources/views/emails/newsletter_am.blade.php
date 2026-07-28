@extends('emails.layout')
@section('content')
    @if(($type ?? 'newsletter') === 'pub')
        <div style="display:inline-block;background:#fff3cd;color:#b45309;font-size:12px;font-weight:bold;padding:4px 10px;border-radius:12px;margin-bottom:14px;">ANNONCE PUBLICITAIRE</div>
    @endif
    <h2 style="margin-top:0;color:#1a7f5a;">{{ $subject }}</h2>
    <div style="font-size:15px;line-height:1.6;color:#1f2d29;">{!! $body !!}</div>
    <hr style="border:none;border-top:1px solid #e3ebe7;margin:24px 0;">
    <p style="color:#6b7d77;font-size:13px;">
        Vous recevez cet e-mail en tant qu'abonné d'AlerteMarché. Pour vous désabonner ou modifier vos préférences, connectez-vous à votre espace.
    </p>
@endsection
