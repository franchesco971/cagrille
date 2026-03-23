<?php

declare(strict_types=1);

namespace Cagrille\AlibabaBundle\Command;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Commande en deux étapes :
 *  1. Sans --code  → affiche l'URL d'autorisation à ouvrir dans le navigateur
 *  2. Avec --code  → échange le code contre un access_token
 *
 * Flux OAuth Alibaba IOP :
 *   GET  https://auth.alibaba.com/oauth/authorize … → le vendeur approuve → callback avec ?code=…
 *   POST https://api.alibaba.com/auth/token/create  (signé HMAC)          → access_token
 */
#[AsCommand(
    name: 'alibaba:auth:token',
    description: 'Génère un access token Alibaba (OAuth2 authorization code flow)',
)]
class GetTokenCommand extends Command
{
    private const AUTH_URL  = 'https://auth.alibaba.com/oauth/authorize';
    private const TOKEN_URL = 'https://api.alibaba.com/auth/token/create';

    public function __construct(
        private readonly string $appKey,
        private readonly string $appSecret,
        private readonly string $baseUrl,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('code', null, InputOption::VALUE_OPTIONAL, 'Code d\'autorisation OAuth reçu en callback');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io   = new SymfonyStyle($input, $output);
        $code = $input->getOption('code');

        if ($code === null) {
            // Étape 1 : afficher l'URL d'autorisation
            $authUrl = self::AUTH_URL . '?' . http_build_query([
                'client_id'     => $this->appKey,
                'redirect_uri'  => 'https://localhost/callback',
                'response_type' => 'code',
                'view'          => 'web',
                'sp'            => 'alibaba',
            ]);

            $io->title('Alibaba OAuth — Étape 1 : Autorisation');
            $io->text('Ouvrez cette URL dans votre navigateur et connectez-vous avec votre compte vendeur Alibaba :');
            $io->newLine();
            $io->writeln('<href=' . $authUrl . '>' . $authUrl . '</>');
            $io->newLine();
            $io->text('Après approbation, vous serez redirigé vers une URL de type :');
            $io->writeln('  https://localhost/callback?code=<CODE>');
            $io->newLine();
            $io->text('Relancez ensuite la commande avec le code reçu :');
            $io->writeln('  bin/console alibaba:auth:token --code=<CODE>');

            return Command::SUCCESS;
        }

        // Étape 2 : échanger le code contre un token
        $io->title('Alibaba OAuth — Étape 2 : Échange du code');

        // Construction de la requête signée selon le protocole IOP Alibaba
        $params = [
            'app_key'     => $this->appKey,
            'timestamp'   => (string) (time() * 1000),
            'sign_method' => 'sha256',
            'code'        => $code,
        ];

        ksort($params);

        $signStr = $this->appSecret;
        foreach ($params as $key => $value) {
            $signStr .= $key . $value;
        }
        $signStr .= $this->appSecret;

        $params['sign'] = strtoupper(hash('sha256', $signStr));

        try {
            $client   = new Client(['timeout' => 15]);
            $response = $client->post(self::TOKEN_URL, ['form_params' => $params]);
            $data     = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

            if (!isset($data['access_token'])) {
                $io->error('Réponse inattendue : ' . json_encode($data, JSON_PRETTY_PRINT));
                return Command::FAILURE;
            }

            $io->success('Access token obtenu !');
            $io->table(['Clé', 'Valeur'], [
                ['access_token',    $data['access_token']],
                ['refresh_token',   $data['refresh_token']  ?? '—'],
                ['expires_in',      ($data['expires_in']    ?? '—') . ' s'],
                ['refresh_expires_in', ($data['refresh_expires_in'] ?? '—') . ' s'],
                ['account',         $data['account']        ?? '—'],
            ]);

            $io->note('Ajoutez cette ligne dans votre .env.local :');
            $io->writeln('ALIBABA_ACCESS_TOKEN=' . $data['access_token']);

        } catch (GuzzleException $e) {
            $io->error('Erreur HTTP : ' . $e->getMessage());
            return Command::FAILURE;
        } catch (\Throwable $e) {
            $io->error('Erreur : ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
