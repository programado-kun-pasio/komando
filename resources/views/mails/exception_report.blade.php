<x-mail::message>
# Eine Exception vom Typ "{{ $report['class'] }}" ist aufgetreten.

{{ $report['message'] }}

- **Ort:** {{ $report['location'] }}
- **Zeitpunkt:** {{ $report['occurred_at'] }}
- **Umgebung:** {{ $report['environment'] }}
- **Fingerprint:** <span style="overflow-wrap: anywhere; word-break: break-all;">{{ $report['fingerprint'] }}</span>

@if ($report['request'] !== [])
## Request

- **Methode:** {{ $report['request']['method'] }}
- **URL:** {{ $report['request']['url'] }}
- **Route:** {{ $report['request']['route'] ?? '-' }}
- **Benutzer-ID:** {{ $report['request']['user_id'] ?? '-' }}
- **Request-ID:** {{ $report['request']['request_id'] ?: '-' }}
@endif

## Stack trace

<pre style="box-sizing: border-box; max-width: 100%; white-space: pre-wrap; overflow-wrap: anywhere; word-wrap: break-word; word-break: break-word;">
{{ $report['stack_trace'] }}
</pre>
</x-mail::message>
