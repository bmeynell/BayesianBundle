<?php

namespace Meynell\BayesianBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('meynell_bayesian');

        $rootNode
            ->children()                                                           
                ->scalarNode('use_relevant')->defaultValue(15)->end()
                ->scalarNode('min_dev')->defaultValue(0.2)->end()
                ->scalarNode('rob_s')->defaultValue(0.3)->end()
                ->scalarNode('rob_x')->defaultValue(0.5)->end()
                ->arrayNode('lexer')
                    ->children()
                        ->scalarNode('min_size')->defaultValue(3)->end()
                        ->scalarNode('max_size')->defaultValue(30)->end()
                        ->scalarNode('allow_numbers')->defaultValue(false)->end()
                        ->scalarNode('get_uris')->defaultValue(true)->end()
                        ->scalarNode('get_html')->defaultValue(false)->end()
                        ->scalarNode('get_bbcode')->defaultValue(false)->end()
                    ->end()
                ->end()
                ->arrayNode('degenerator')
                    ->children()
                        ->scalarNode('multibyte')->defaultValue(true)->end()
                        ->scalarNode('encoding')->defaultValue('UTF-8')->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
