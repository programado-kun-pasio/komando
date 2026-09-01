<x-mail::message>
# Eine Exception vom Typ "{{ $report['class'] }}" ist aufgetreten.

{{ $report['message'] }}

- **Ort:** {{ $report['location'] }}
- **Zeitpunkt:** {{ $report['occurred_at'] }}
- **Umgebung:** {{ $report['environment'] }}
- **Fingerprint:** {{ $report['fingerprint'] }}

@if ($report['request'] !== [])
## Request

- **Methode:** {{ $report['request']['method'] }}
- **URL:** {{ $report['request']['url'] }}
- **Route:** {{ $report['request']['route'] ?? '-' }}
- **Benutzer-ID:** {{ $report['request']['user_id'] ?? '-' }}
- **Request-ID:** {{ $report['request']['request_id'] ?: '-' }}
@endif

## Stack trace

<pre>
{{ $report['stack_trace'] }}
</pre>
</x-mail::message>
