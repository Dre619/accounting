<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LencoPaymentService
{
    private string $baseUrl;
    private string $secretKey;

    public function __construct()
    {
        $this->baseUrl   = config('lenco.base_url');
        $this->secretKey = config('lenco.secret_key');
    }

    /**
     * Verify a transaction reference with Lenco's API.
     * Returns the transaction data array on success, null on failure.
     */
    public function verify(string $reference): ?array
    {
        try {
            $response = Http::withToken($this->secretKey)
                ->timeout(15)
                ->get("{$this->baseUrl}/collections/status/{$reference}");

            if ($response->successful()) {
                $data = $response->json();

                // Lenco returns { status: true, data: { status: 'successful', amount, ... } }
                if (($data['status'] ?? false) && ($data['data']['status'] ?? '') === 'successful') {
                    return $data['data'];
                }
            }

            Log::warning('Lenco verification failed', [
                'reference' => $reference,
                'status'    => $response->status(),
                'body'      => $response->body(),
            ]);

            return null;
        } catch (ConnectionException $e) {
            Log::error('Lenco connection error', ['reference' => $reference, 'error' => $e->getMessage()]);
            return null;
        }
    }
}
