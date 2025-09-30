<?php declare(strict_types=1);

namespace Programado\Komando\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TimezoneConversion
{
    public function handle(Request $request, Closure $next): Response
    {
        // Get timezone from header, default to UTC
        $userTimezone = $request->header('X-Timezone', 'Europe/Berlin');

        // Validate timezone
        if (!in_array($userTimezone, timezone_identifiers_list())) {
            $userTimezone = 'UTC';
        }

        // Convert incoming request data from client timezone to UTC
        if ($userTimezone !== 'UTC') {
            $this->convertRequestTimezones($request, $userTimezone);
        }

        $response = $next($request);

        // Only process JSON responses (both JsonResponse and Response with JSON content)
        if (!$this->isJsonResponse($response)) {
            return $response;
        }

        // Convert datetime fields in response from UTC to client timezone
        if ($userTimezone !== 'UTC') {
            $data = $this->getResponseData($response);
            $convertedData = $this->convertResponseTimezones($data, $userTimezone);
            $this->setResponseData($response, $convertedData);
        }

        return $response;
    }

    private function convertRequestTimezones(Request $request, string $timezone): void
    {
        // Convert all request inputs from client timezone to UTC
        $allInputs = $request->all();
        $convertedInputs = $this->convertTimezoneToUtc($allInputs, $timezone);
        $request->merge($convertedInputs);
    }

    private function convertResponseTimezones(array $data, string $timezone): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->convertResponseTimezones($value, $timezone);
            } elseif (is_string($value) && $this->isDateTime($value)) {
                try {
                    $carbonDate = Carbon::parse($value)->utc();
                    $data[$key] = $carbonDate->setTimezone($timezone)->format('Y-m-d H:i:s');
                } catch (\Exception) {
                    // Keep original value if conversion fails
                }
            }
        }

        return $data;
    }

    private function convertTimezoneToUtc(array $data, string $timezone): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->convertTimezoneToUtc($value, $timezone);
            } elseif (is_string($value) && $this->isDateTime($value)) {
                try {
                    $carbonDate = Carbon::parse($value, $timezone);
                    $data[$key] = $carbonDate->utc()->format('Y-m-d H:i:s');
                } catch (\Exception) {
                    // Keep original value if conversion fails
                }
            }
        }

        return $data;
    }

    private function isDateTime(string $value): bool
    {
        // Check for ISO 8601 datetime format
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}[T\s]\d{2}:\d{2}:\d{2}/', $value);
    }

    private function isJsonResponse(Response $response): bool
    {
        $contentType = $response->headers->get('Content-Type', '');
        return str_contains($contentType, 'application/json');
    }

    private function getResponseData(Response $response): array
    {
        if ($response instanceof JsonResponse) {
            return $response->getData(true);
        }

        // For regular Response with JSON content
        return json_decode($response->getContent(), true) ?? [];
    }

    private function setResponseData(Response $response, array $data): void
    {
        if ($response instanceof JsonResponse) {
            $response->setData($data);
        } else {
            // For regular Response with JSON content
            $response->setContent(json_encode($data));
        }
    }
}
