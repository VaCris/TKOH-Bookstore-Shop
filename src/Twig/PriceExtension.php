<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class PriceExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('both_prices', [$this, 'bothPrices']),
        ];
    }

    public function bothPrices(?float $price, string $currency = 'USD'): array
    {
        if ($price === null) {
            return [
                'usd_formatted' => 'NOT FOR SALE',
                'pen_formatted' => 'NO EN VENTA',
                'usd_value' => null,
                'pen_value' => null,
                'forSale' => false,
            ];
        }

        $exchangeRate = 3.75;

        if ($currency === 'USD') {
            $usdPrice = $price;
            $penPrice = $price * $exchangeRate;
        } else {
            $usdPrice = $price / $exchangeRate;
            $penPrice = $price;
        }

        return [
            'usd_formatted' => '$' . number_format($usdPrice, 2),
            'pen_formatted' => 'S/ ' . number_format($penPrice, 2),
            'usd_value' => $usdPrice,
            'pen_value' => $penPrice,
            'forSale' => true,
        ];
    }
}
