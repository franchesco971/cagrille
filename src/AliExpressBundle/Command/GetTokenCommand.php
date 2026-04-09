<?php

declare(strict_types=1);

namespace Cagrille\AliExpressBundle\Command;

use Cagrille\AliExpressBundle\Api\TokenRefreshService;
use Cagrille\AliExpressBundle\Contract\TokenStorageInterface;
use Cagrille\AliExpressBundle\Exception\AliExpressApiException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Gère le cycle de vie du token OAuth AliExpress.
 *
 * Comportement sans option :
 *   - Token absent                         → affiche l'URL OAuth (étape 1)
 *   - Token valide                         → affiche le statut
 *   - Token expiré + refresh_token dispo   → rafraîchit automatiquement
 *   - Token expiré + pas de refresh_token  → affiche l'URL OAuth
 *
 * Usage :
 *   bin/console aliexpress:auth:token                  # auto
 *   bin/console aliexpress:auth:token --code=<CODE>    # étape 2 manuelle
 *   bin/console aliexpress:auth:token --status         # affiche le statut
 *   bin/console aliexpress:auth:token --refresh        # force le refresh
 */
#[AsCommand(
    name: 'aliexpress:auth:token',
    description: 'Génère ou renouvelle un access token AliExpress (OAuth2 authorization code flow)',
)]
final class GetTokenCommand extends Command
{
    public function __construct(
        private readonly TokenRefreshService $tokenRefreshService,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly string $redirectUri,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'code',
                null,
                InputOption::VALUE_OPTIONAL,
                'Code d\'autorisation OAuth reçu en callback',
            )
            ->addOption(
                'status',
                null,
                InputOption::VALUE_NONE,
                'Affiche le statut du token actuel',
            )
            ->addOption(
                'refresh',
                null,
                InputOption::VALUE_NONE,
                'Force le renouvellement du token via le refresh_token',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var string|null $code */
        $code = $input->getOption('code');
        $status = (bool) $input->getOption('status');
        $forceRefresh = (bool) $input->getOption('refresh');

        if ($status) {
            return $this->showStatus($io);
        }

        if ($forceRefresh) {
            return $this->doRefresh($io);
        }

        if ($code !== null) {
            return $this->exchangeCode($io, $code);
        }

        // Sans option explicite : comportement automatique selon l'état du token
        return $this->autoManageToken($io);
    }

    /**
     * Comportement par défaut (aucune option) :
     *  - Token absent              → affiche l'URL OAuth
     *  - Token valide              → affiche le statut
     *  - Token expiré + refresh_token disponible → rafraîchit automatiquement
     *  - Token expiré + pas de refresh_token     → affiche l'URL OAuth
     */
    private function autoManageToken(SymfonyStyle $io): int
    {
        $accessToken = $this->tokenStorage->getAccessToken();
        $refreshToken = $this->tokenStorage->getRefreshToken();
        $expiringSoon = $this->tokenStorage->isExpiringSoon(0); // 0 = déjà expiré

        // Aucun token → démarrer le flux OAuth
        if ($accessToken === '') {
            $io->note('Aucun token disponible. Démarrage du flux OAuth…');

            return $this->showAuthUrl($io);
        }

        // Token encore valide → juste afficher le statut
        if (!$expiringSoon) {
            return $this->showStatus($io);
        }

        // Token expiré avec refresh_token → rafraîchir automatiquement
        if ($refreshToken !== null && $refreshToken !== '') {
            $io->note('Token expiré — renouvellement automatique via le refresh_token…');

            return $this->doRefresh($io);
        }

        // Token expiré sans refresh_token → forcer un nouveau flux OAuth
        $io->warning('Token expiré et aucun refresh_token disponible. Un nouveau flux OAuth est nécessaire.');

        return $this->showAuthUrl($io);
    }

    private function showStatus(SymfonyStyle $io): int
    {
        $io->title('AliExpress OAuth — Statut du token');

        $accessToken = $this->tokenStorage->getAccessToken();
        $refreshToken = $this->tokenStorage->getRefreshToken();
        $expiresAt = $this->tokenStorage->getExpiresAt();
        $expiringSoon = $this->tokenStorage->isExpiringSoon(3600);

        $io->table(['Champ', 'Valeur'], [
            ['access_token',  $accessToken !== '' ? substr($accessToken, 0, 20) . '…' : '<fg=red>absent</>'],
            ['refresh_token', $refreshToken !== null ? substr($refreshToken, 0, 20) . '…' : '<fg=yellow>absent</>'],
            ['expires_at',    $expiresAt?->format('d/m/Y H:i:s T') ?? '<fg=yellow>inconnue</>'],
            ['statut',        $expiringSoon ? '<fg=yellow>expire bientôt</>' : '<fg=green>valide</>'],
        ]);

        if ($accessToken === '') {
            $io->warning('Aucun token disponible. Lancez la commande sans option pour démarrer le flux OAuth.');
        }

        return Command::SUCCESS;
    }

    private function showAuthUrl(SymfonyStyle $io): int
    {
        $authUrl = $this->tokenRefreshService->getAuthorizationUrl($this->redirectUri);

        $io->title('AliExpress OAuth — Étape 1 : Autorisation');
        $io->text('Ouvrez cette URL dans votre navigateur et connectez-vous avec votre compte vendeur AliExpress :');
        $io->newLine();
        $io->writeln('<href=' . $authUrl . '>' . $authUrl . '</>');
        $io->newLine();
        $io->text('Après approbation, vous serez redirigé vers :');
        $io->writeln('  ' . $this->redirectUri . '?code=<CODE>');
        $io->newLine();
        $io->text('La page peut afficher une erreur, c\'est normal — copiez uniquement le paramètre "code" dans l\'URL.');
        $io->newLine();
        $io->text('Relancez ensuite la commande avec ce code :');
        $io->writeln('  bin/console aliexpress:auth:token --code=<CODE>');

        return Command::SUCCESS;
    }

    private function exchangeCode(SymfonyStyle $io, string $code): int
    {
        $io->title('AliExpress OAuth — Étape 2 : Échange du code');

        try {
            $this->tokenRefreshService->exchangeCode($code);
        } catch (AliExpressApiException $e) {
            $io->error('Erreur lors de l\'échange du code : ' . $e->getMessage());

            return Command::FAILURE;
        }

        $this->showTokenSaved($io);

        return Command::SUCCESS;
    }

    private function doRefresh(SymfonyStyle $io): int
    {
        $io->title('AliExpress OAuth — Renouvellement du token');

        try {
            $this->tokenRefreshService->refresh();
        } catch (AliExpressApiException $e) {
            $io->error('Erreur lors du renouvellement : ' . $e->getMessage());

            return Command::FAILURE;
        }

        $this->showTokenSaved($io);

        return Command::SUCCESS;
    }

    private function showTokenSaved(SymfonyStyle $io): void
    {
        $accessToken = $this->tokenStorage->getAccessToken();
        $expiresAt = $this->tokenStorage->getExpiresAt();

        $io->success('Token sauvegardé dans var/aliexpress_token.json');
        $io->table(['Champ', 'Valeur'], [
            ['access_token', substr($accessToken, 0, 20) . '…'],
            ['expires_at',   $expiresAt?->format('d/m/Y H:i:s T') ?? 'inconnue'],
        ]);

        $io->note('Le token sera utilisé automatiquement. Relancez pour le renouveler :');
        $io->writeln('  bin/console aliexpress:auth:token --refresh');
    }
}
