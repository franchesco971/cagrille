<?php

declare(strict_types=1);

namespace Cagrille\AlibabaBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Définit la structure de configuration du bundle Alibaba.
 * Principe SRP : cette classe gère uniquement la définition de la config.
 */
class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('alibaba');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('app_key')
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->info('Clé d\'application Alibaba API')
                ->end()
                ->scalarNode('app_secret')
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->info('Secret d\'application Alibaba API')
                ->end()
                ->scalarNode('access_token')
                    ->defaultValue('')
                    ->info('Token d\'accès OAuth (optionnel si auto-refresh activé)')
                ->end()
                ->scalarNode('base_url')
                    ->defaultValue('https://api.alibaba.com')
                    ->info('URL de base de l\'API Alibaba')
                ->end()
                ->scalarNode('oauth_url')
                    ->defaultValue('https://oauth.alibaba.com/token')
                    ->info('URL d\'authentification OAuth 2.0')
                ->end()
                ->integerNode('timeout')
                    ->defaultValue(30)
                    ->info('Timeout en secondes pour les requêtes HTTP')
                ->end()
                ->arrayNode('sync')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('batch_size')
                            ->defaultValue(50)
                            ->info('Nombre de produits traités par lot lors de la synchronisation')
                        ->end()
                        ->arrayNode('categories')
                            ->scalarPrototype()->end()
                            ->defaultValue(['barbecue', 'grill', 'charcoal', 'bbq accessories'])
                            ->info('Catégories Alibaba à synchroniser')
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
