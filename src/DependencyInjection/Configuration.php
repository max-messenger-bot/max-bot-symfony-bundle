<?php

declare(strict_types=1);

namespace MaxMessenger\Bot\Bundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('max_bot');
        $children = $treeBuilder->getRootNode()->children();

        $children->scalarNode('access_token')
            ->isRequired()
            ->cannotBeEmpty()
            ->info('Max Bot API access token.');

        $children->scalarNode('webhook_secret')
            ->defaultNull()
            ->info('Secret key for webhook request validation (X-Max-Bot-Api-Secret header).');

        $children->scalarNode('base_url')
            ->defaultValue('https://platform-api.max.ru')
            ->cannotBeEmpty()
            ->info('Max API base URL.');

        $children->integerNode('connect_timeout')
            ->defaultValue(5000)
            ->min(0)
            ->info('Connection timeout in milliseconds. Use 0 to wait indefinitely.');

        $children->integerNode('timeout')
            ->defaultValue(10000)
            ->min(0)
            ->info('Request timeout in milliseconds. Use 0 to wait indefinitely.');

        return $treeBuilder;
    }
}
