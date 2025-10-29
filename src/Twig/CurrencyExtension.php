<?php

namespace App\Twig;

use App\Service\CurrencyConverterService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class CurrencyExtension extends AbstractExtension
{
    private CurrencyConverterService $converter;

    public function __construct(CurrencyConverterService $converter)
    {
        $this->converter = $converter;
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('currency', [$this, 'formatCurrency']),
            new TwigFilter('to_pen', [$this, 'toPen']),
            new TwigFilter('to_usd', [$this, 'toUsd']),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('both_prices', [$this, 'getBothPrices']),
            new TwigFunction('sunat_rate', [$this, 'getSunatRate']),
        ];
    }

    /**
     * Formatear precio con moneda
     * Uso: {{ 25.99|currency('USD') }}
     */
    public function formatCurrency(float $amount, string $currency = 'PEN'): string
    {
        return $this->converter->formatPrice($amount, $currency);
    }

    /**
     * Convertir a PEN y formatear
     * Uso: {{ 25.99|to_pen }}
     */
    public function toPen(float $amount): string
    {
        $converted = $this->converter->convertUsdToPen($amount);
        return $this->converter->formatPrice($converted, 'PEN');
    }

    /**
     * Convertir a USD y formatear
     * Uso: {{ 95.00|to_usd }}
     */
    public function toUsd(float $amount): string
    {
        $converted = $this->converter->convertPenToUsd($amount);
        return $this->converter->formatPrice($converted, 'USD');
    }

    /**
     * Obtener precio en ambas monedas
     */
    public function getBothPrices(float $amount, string $fromCurrency = 'USD'): array
    {
        return $this->converter->getBothPrices($amount, $fromCurrency);
    }

    /**
     * Obtener tasa SUNAT
     */
    public function getSunatRate(string $type = 'compra'): float
    {
        if ($type === 'venta') {
            return $this->converter->getVentaRate();
        }
        return $this->converter->getCompraRate();
    }
}