<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Servicio de conversión de monedas PEN <-> USD
 * Usa tipos de cambio oficiales de SUNAT (Perú)
 */
class CurrencyConverterService
{
    private HttpClientInterface $client;
    private CacheInterface $cache;
    private LoggerInterface $logger;

    private const DEFAULT_COMPRA = 3.387;
    private const DEFAULT_VENTA = 3.396;
    private const SUNAT_API_URL = 'https://api.apis.net.pe/v1/tipo-cambio-sunat';

    public function __construct(
        HttpClientInterface $httpClient,
        CacheInterface $cache,
        LoggerInterface $logger
    ) {
        $this->client = $httpClient;
        $this->cache = $cache;
        $this->logger = $logger;
    }

    /**
     * Obtener tipo de cambio actual desde SUNAT
     */
    private function getSunatRates(): array
    {
        try {
            return $this->cache->get('sunat_exchange_rates', function (ItemInterface $item) {
                $item->expiresAfter(3600 * 12);

                try {
                    $response = $this->client->request('GET', self::SUNAT_API_URL, [
                        'timeout' => 10
                    ]);

                    $data = $response->toArray();

                    $rates = [
                        'compra' => $data['compra'] ?? self::DEFAULT_COMPRA,
                        'venta' => $data['venta'] ?? self::DEFAULT_VENTA,
                        'fecha' => $data['fecha'] ?? date('Y-m-d'),
                        'source' => 'SUNAT'
                    ];

                    $this->logger->info('Tipo de cambio SUNAT actualizado', $rates);

                    return $rates;
                } catch (\Exception $e) {
                    $this->logger->warning('Error obteniendo tasa de SUNAT, usando default', [
                        'error' => $e->getMessage()
                    ]);

                    return [
                        'compra' => self::DEFAULT_COMPRA,
                        'venta' => self::DEFAULT_VENTA,
                        'fecha' => date('Y-m-d'),
                        'source' => 'DEFAULT'
                    ];
                }
            });
        } catch (\Exception $e) {
            return [
                'compra' => self::DEFAULT_COMPRA,
                'venta' => self::DEFAULT_VENTA,
                'fecha' => date('Y-m-d'),
                'source' => 'DEFAULT'
            ];
        }
    }

    /**
     * Convertir de USD a PEN (Compra)
     * Usamos el tipo de cambio de COMPRA cuando convertimos USD a PEN
     */
    public function convertUsdToPen(float $amount, bool $useVenta = false): float
    {
        $rates = $this->getSunatRates();
        $rate = $useVenta ? $rates['venta'] : $rates['compra'];
        return round($amount * $rate, 2);
    }

    /**
     * Convertir de PEN a USD (Venta)
     * Usamos el tipo de cambio de VENTA cuando convertimos PEN a USD
     */
    public function convertPenToUsd(float $amount): float
    {
        $rates = $this->getSunatRates();
        $rate = 1 / $rates['venta'];
        return round($amount * $rate, 2);
    }

    /**
     * Obtener tasa de compra (USD -> PEN)
     */
    public function getCompraRate(): float
    {
        $rates = $this->getSunatRates();
        return $rates['compra'];
    }

    /**
     * Obtener tasa de venta (PEN -> USD)
     */
    public function getVentaRate(): float
    {
        $rates = $this->getSunatRates();
        return $rates['venta'];
    }

    /**
     * Formatear precio con símbolo de moneda
     */
    public function formatPrice(float $amount, string $currency = 'PEN'): string
    {
        $symbols = [
            'PEN' => 'S/',
            'USD' => '$'
        ];

        $symbol = $symbols[$currency] ?? $currency;
        return $symbol . ' ' . number_format($amount, 2);
    }

    /**
     * Obtener precio en ambas monedas
     */
    public function getBothPrices(float $amount, string $fromCurrency = 'USD'): array
    {
        if ($fromCurrency === 'USD') {
            $penAmount = $this->convertUsdToPen($amount);
            return [
                'usd' => $amount,
                'pen' => $penAmount,
                'usd_formatted' => $this->formatPrice($amount, 'USD'),
                'pen_formatted' => $this->formatPrice($penAmount, 'PEN'),
                'rate_used' => $this->getCompraRate()
            ];
        } else {
            $usdAmount = $this->convertPenToUsd($amount);
            return [
                'pen' => $amount,
                'usd' => $usdAmount,
                'pen_formatted' => $this->formatPrice($amount, 'PEN'),
                'usd_formatted' => $this->formatPrice($usdAmount, 'USD'),
                'rate_used' => $this->getVentaRate()
            ];
        }
    }

    /**
     * Limpiar caché de tasas
     */
    public function clearRatesCache(): void
    {
        $this->cache->delete('sunat_exchange_rates');
        $this->logger->info('Caché de tasas de cambio SUNAT limpiada');
    }

    /**
     * Obtener información de tasas actuales
     */
    public function getCurrentRates(): array
    {
        return $this->getSunatRates();
    }
}
