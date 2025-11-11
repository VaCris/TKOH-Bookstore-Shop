<?php

namespace App\Service;

use Stripe\Stripe;
use Stripe\Checkout\Session;
use Psr\Log\LoggerInterface;

class StripeService
{
    private string $secretKey;
    private string $publicKey;
    private LoggerInterface $logger;

    public function __construct(
        string $stripeSecretKey,
        string $stripePublicKey,
        LoggerInterface $logger
    ) {
        $this->secretKey = $stripeSecretKey;
        $this->publicKey = $stripePublicKey;
        $this->logger = $logger;

        Stripe::setApiKey($this->secretKey);
    }

    public function createCheckoutSession(array $items, string $successUrl, string $cancelUrl): string
    {
        $this->logger->info('[Stripe] Creating checkout session', [
            'items_count' => count($items)
        ]);

        try {
            $lineItems = [];

            foreach ($items as $item) {
                $lineItems[] = [
                    'price_data' => [
                        'currency' => 'usd',
                        'product_data' => [
                            'name' => $item['titulo'],
                            'images' => [$item['imagen']],
                        ],
                        'unit_amount' => (int)($item['precio'] * 100), // Centavos
                    ],
                    'quantity' => $item['cantidad'],
                ];
            }

            $session = Session::create([
                'payment_method_types' => ['card'],
                'line_items' => $lineItems,
                'mode' => 'payment',
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
            ]);

            $this->logger->info('[Stripe] Session created', [
                'session_id' => $session->id
            ]);

            return $session->id;

        } catch (\Exception $e) {
            $this->logger->error('[Stripe] Session creation failed', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getPublicKey(): string
    {
        return $this->publicKey;
    }
}