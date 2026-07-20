@extends('emails.layout')
@section('content')
    <h2 style="margin-top:0;color:#1a7f5a;">{{ $subject }}</h2>
    <div style="font-size:15px;line-height:1.6;color:#1f2d29;">{!! $body !!}</div>
    <hr style="border:none;border-top:1px solid #e3ebe7;margin:24px 0;">
    <p style="color:#6b7d77;font-size:13px;">Cette alerte vous est envoyée car elle correspond à votre profil AlerteMarché.</p>
@endsection
