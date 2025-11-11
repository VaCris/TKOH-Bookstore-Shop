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
     *
     */
    public function formatCurrency(float $amount, string $currency = 'PEN'): string
    {
        return $this->converter->formatPrice($amount, $currency);
    }

    /**
     * Convertir a PEN y formatear
     *
     */
    public function toPen(float $amount): string
    {
        $converted = $this->converter->convertUsdToPen($amount);
        return $this->converter->formatPrice($converted, 'PEN');
    }

    /**
     * Convertir a USD y formatear
     *
     */
    public function toUsd(float $amount): string
    {
        $converted = $this->converter->convertPenToUsd($amount);
        return $this->converter->formatPrice($converted, 'USD');
    }

    /**
     * Obtener precio en ambas monedas
     */
    public function getBothPrices(?float $amount, string $fromCurrency = 'USD'): array
    {
        if ($amount === null || $amount === 0) {
            return [
                'usd_formatted' => 'NOT FOR SALE',
                'pen_formatted' => 'NO EN VENTA',
                'usd_value' => null,
                'pen_value' => null,
                'forSale' => false,
            ];
        }

        return $this->converter->getBothPrices($amount, $fromCurrency) + ['forSale' => true];
    }

    /**
     * Obtener tasa SUNAT
     * */
    // public function getSunatRate(string $type = 'compra'): float
    // {
    //     if ($type === 'venta') {
    //         return $this->converter->getVentaRate();
    //     }
    //     return $this->converter->getCompraRate();
    // }*/
}