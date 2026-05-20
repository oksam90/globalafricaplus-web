<x-mail::message>
# Nouveau message de contact (page Tarifs — plan Enterprise)

**De :** {{ $senderName }} &lt;{{ $senderEmail }}&gt;
**Téléphone :** {{ $senderPhone }}
**Objet :** {{ $subjectLine }}

---

{{ $body }}

---

<small>
Soumis le {{ $submittedAt }}
@if ($ip)
&nbsp;·&nbsp; IP : {{ $ip }}
@endif

Répondez directement à ce mail — vous tomberez sur {{ $senderName }} ({{ $senderEmail }}).
</small>

Cordialement,
{{ config('app.name') }}
</x-mail::message>
