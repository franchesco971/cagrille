<?php

declare(strict_types=1);

namespace Cagrille\AlibabaBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

#[AsCommand(
    name: 'alibaba:auth:token',
    description: 'Obtient un access token Alibaba via /auth/token/create',
)]
class GetTokenCommand extends Command
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $appKey,
        private readonly string $appSecret,
        private readonly string $baseUrl,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Alibaba — Obtention de l\'access token');

        $url = rtrim($this->baseUrl, '/') . '/auth/token/create';

        try {
            $response = $this->httpClient->request('POST', $url, [
                'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
                'body'    => [
                    'app_key'    => $this->appKey,
                    'app_secret' => $this->appSecret,
                ],
            ]);

            $data = $response->toArray(false);

            if (!isset($data['access_token'])) {
                $io->error('Réponse inattendue : ' . json_encode($data));
                return Command::FAILURE;
            }

            $io->success('Access token obtenu avec succès !');
            $io->table(
                ['Clé', 'Valeur'],
                [
                    ['access_token',  $data['access_token']],
                    ['refresh_token', $data['refresh_token'] ?? '—'],
                    ['expires_in',    isset($data['expires_in']) ? $data['expires_in'] . ' s' : '—'],
                    ['token_type',    $data['token_type'] ?? '—'],
                ]
            );

            $io->note('Ajoutez cette ligne dans votre .env.local :');
            $io->writeln('ALIBABA_ACCESS_TOKEN=' . $data['access_token']);

        } catch (\Throwable $e) {
            $io->error('Erreur : ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
