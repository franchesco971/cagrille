<?php

declare(strict_types=1);

namespace Cagrille\AliExpressBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * Extension DI du bundle AliExpress.
 * Charge la configuration et expose les paramètres au container.
 * Principe SRP : gère uniquement l'injection de dépendances.
 */
class AliExpressExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config        = $this->processConfiguration($configuration, $configs);

        $container->setParameter('ali_express.app_key',         $config['app_key']);
        $container->setParameter('ali_express.app_secret',      $config['app_secret']);
        $container->setParameter('ali_express.access_token',    $config['access_token']);
        $container->setParameter('ali_express.base_url',        $config['base_url']);
        $container->setParameter('ali_express.timeout',         $config['timeout']);
        $container->setParameter('ali_express.target_currency', $config['target_currency']);
        $container->setParameter('ali_express.target_language', $config['target_language']);
        $container->setParameter('ali_express.sync.batch_size', $config['sync']['batch_size']);
        $container->setParameter('ali_express.sync.keywords',   $config['sync']['keywords']);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');
    }

    public function getAlias(): string
    {
        return 'ali_express';
    }
}
