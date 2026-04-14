<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class PythonOmrApiClient
{
    public function scanEntrance(string $imagePath): array
    {
        return $this->scan($imagePath, 'entrance');
    }

    public function scanTerm(string $imagePath): array
    {
        return $this->scan($imagePath, 'term');
    }

    private function scan(string $imagePath, string $endpoint): array
    {
        if (!is_file($imagePath)) {
            return $this->failure('Uploaded image file was not found.');
        }

        $baseUrl = rtrim((string) config('omr.api.base_url', ''), '/');
        if ($baseUrl === '') {
            return $this->failure('OMR API base URL is not configured.');
        }

        $path = trim((string) config("omr.api.paths.{$endpoint}", ''));
        if ($path === '') {
            return $this->failure('OMR API endpoint path is not configured.');
        }

        $url = $baseUrl . '/' . ltrim($path, '/');
        $timeout = max(1, (int) config('omr.api.timeout_seconds', 30));
        $token = trim((string) config('omr.api.bearer_token', ''));

        $fileContent = @file_get_contents($imagePath);
        if ($fileContent === false) {
            return $this->failure('Unable to read uploaded image for OMR processing.');
        }

        $request = Http::acceptJson()
            ->timeout($timeout)
            ->retry(2, 250, null, false);

        if ($token !== '') {
            $request = $request->withToken($token);
        }

        try {
            $response = $request
                ->attach('image', $fileContent, basename($imagePath))
                ->post($url);
        } catch (ConnectionException $e) {
            return $this->failure('Unable to connect to OMR API service.');
        } catch (\Throwable $e) {
            return $this->failure('OMR API request failed: ' . $e->getMessage());
        }

        if (!$response->successful()) {
            $payload = $response->json();
            $message = 'OMR API request failed with status ' . $response->status() . '.';

            if (is_array($payload)) {
                if (!empty($payload['error'])) {
                    $message = (string) $payload['error'];
                } elseif (!empty($payload['message'])) {
                    $message = (string) $payload['message'];
                }
            }

            return $this->failure($message);
        }

        $decoded = $response->json();
        if (!is_array($decoded)) {
            return $this->failure('Invalid JSON response from OMR API.');
        }

        if (!empty($decoded['error'])) {
            return $this->failure((string) $decoded['error']);
        }

        return [
            'success' => true,
            'data' => $decoded,
        ];
    }

    private function failure(string $message): array
    {
        return [
            'success' => false,
            'message' => $message,
        ];
    }
}
