<?php

namespace Meynell\BayesianBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class MeynellBayesianExtension extends Extension
{
    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.xml');

        $container->setParameter(sprintf('%s', $this->getAlias()), $config);
        $container->setParameter(sprintf('%s.use_relevant', $this->getAlias()), $config['use_relevant']);
        $container->setParameter(sprintf('%s.min_dev', $this->getAlias()), $config['min_dev']);
        $container->setParameter(sprintf('%s.rob_s', $this->getAlias()), $config['rob_s']);
        $container->setParameter(sprintf('%s.rob_x', $this->getAlias()), $config['rob_x']);
        $container->setParameter(sprintf('%s', $this->getLexerAlias()), $config['lexer']);
        $container->setParameter(sprintf('%s.min_size', $this->getLexerAlias()), $config['lexer']['min_size']);
        $container->setParameter(sprintf('%s.max_size', $this->getLexerAlias()), $config['lexer']['max_size']);
        $container->setParameter(sprintf('%s.allow_numbers', $this->getLexerAlias()), $config['lexer']['allow_numbers']);
        $container->setParameter(sprintf('%s.get_uris', $this->getLexerAlias()), $config['lexer']['get_uris']);
        $container->setParameter(sprintf('%s.get_html', $this->getLexerAlias()), $config['lexer']['get_html']);
        $container->setParameter(sprintf('%s.get_bbcode', $this->getLexerAlias()), $config['lexer']['get_bbcode']);
        $container->setParameter(sprintf('%s', $this->getDegeneratorAlias()), $config['degenerator']);
        $container->setParameter(sprintf('%s.multibyte', $this->getDegeneratorAlias()), $config['degenerator']['multibyte']);
        $container->setParameter(sprintf('%s.encoding', $this->getDegeneratorAlias()), $config['degenerator']['encoding']);
    }

    public function getAlias()
    {
        return 'meynell_bayesian';
    }

    public function getLexerAlias()
    {
        return $this->getAlias() . '.lexer';
    }

    public function getDegeneratorAlias()
    {
        return $this->getAlias() . '.degenerator';
    }
}
