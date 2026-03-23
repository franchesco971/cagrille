<?php

declare(strict_types=1);

namespace Cagrille\AliExpressBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Définit la structure de configuration du bundle AliExpress.
 * Principe SRP : gère uniquement la validation de la configuration.
 */
class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('ali_express');
        $rootNode    = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('app_key')
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->info('Clé d\'application AliExpress Open Platform')
                ->end()
                ->scalarNode('app_secret')
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->info('Secret d\'application AliExpress Open Platform')
                ->end()
                ->scalarNode('access_token')
                    ->defaultValue('')
                    ->info('Token OAuth (session) d\'accès à l\'API')
                ->end()
                ->scalarNode('base_url')
                    ->defaultValue('https://api-sg.aliexpress.com')
                    ->info('URL de base de l\'API AliExpress (région SG par défaut)')
                ->end()
                ->integerNode('timeout')
                    ->defaultValue(30)
                    ->info('Timeout HTTP en secondes')
                ->end()
                ->scalarNode('target_currency')
                    ->defaultValue('EUR')
                    ->info('Devise cible pour le prix affiché (EUR, USD, …)')
                ->end()
                ->scalarNode('target_language')
                    ->defaultValue('fr')
                    ->info('Langue cible pour les descriptions produits')
                ->end()
                ->scalarNode('ship_to_country')
                    ->defaultValue('FR')
                    ->info('Code pays ISO destinataire (filtre disponibilité & livraison)')                
                ->end()
                ->arrayNode('sync')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('batch_size')
                            ->defaultValue(20)
                            ->info('Nombre de produits par page lors de la recherche')
                        ->end()
                        ->arrayNode('keywords')
                            ->scalarPrototype()->end()
                            ->defaultValue(['barbecue', 'grill', 'bbq accessories'])
                            ->info('Mots-clés AliExpress à synchroniser automatiquement')
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
