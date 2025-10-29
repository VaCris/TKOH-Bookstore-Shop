<?php

namespace App\Command;

use App\Service\CurrencyConverterService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:currency:update',
    description: 'Actualizar tasas de cambio desde SUNAT',
)]
class UpdateCurrencyRatesCommand extends Command
{
    private CurrencyConverterService $converter;

    public function __construct(CurrencyConverterService $converter)
    {
        parent::__construct();
        $this->converter = $converter;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Actualizando tasas de cambio SUNAT');

        $this->converter->clearRatesCache();

        $rates = $this->converter->getCurrentRates();

        $io->success('Tasas de cambio actualizadas correctamente');

        $io->table(
            ['Tipo', 'Tasa'],
            [
                ['Compra (USD → PEN)', 'S/ ' . number_format($rates['compra'], 3)],
                ['Venta (PEN → USD)', 'S/ ' . number_format($rates['venta'], 3)],
                ['Fecha', $rates['fecha']],
                ['Fuente', $rates['source']],
            ]
        );
        return Command::SUCCESS;
    }
}
