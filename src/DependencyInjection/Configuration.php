<?php

/*
 * This file is part of the Arthur Doctrine Fixture Bundle.
 *
 * (c) Scribe Inc.     <scr@src.run>
 * (c) Rob Frawley 2nd <rmf@src.run>
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Scribe\Arthur\DoctrineFixturesBundle\DependencyInjection;

use Scribe\WonkaBundle\Component\DependencyInjection\AbstractConfiguration;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;

/**
 * Class Configuration.
 */
class Configuration extends AbstractConfiguration
{
    /**
     * @return TreeBuilder
     */
    public function getConfigTreeBuilder()
    {
        return $this
            ->getBuilderRoot()
            ->getNodeDefinition()
            ->info('Configuration for scr-be/arthur-doctrine-fixtures-bundle')
            ->canBeEnabled()
            ->children()
                ->append($this->getFixtureSearchPartsNode())
                ->append($this->getFixtureSearchPathPostfixNode())
                ->append($this->getBundleWhitelistNode())
            ->end()
        ->end();
    }

    /**
     * @return NodeDefinition
     */
    protected function getFixtureSearchPartsNode()
    {
        return $this
            ->getBuilder('fixture_search_parts_list')
            ->getNodeDefinition()
            ->info('Collection of paths used to build search for fixtures.')
            ->defaultValue([
                ['../', '../../', '../../../'],
                ['.config/', '.config-internal'],
                ['fixtures/'],
            ])
            ->prototype('array')
                ->prototype('scalar') ->end()
            ->end();
    }

    /**
     * @return NodeDefinition
     */
    protected function getFixtureSearchPathPostfixNode()
    {
        return $this
            ->getNodeDefinition('fixture_search_path_postfix', 'scalar')
            ->info('Path to add to bundle path to look for ORMs.')
            ->treatNullLike('/DataFixtures/ORM')
            ->defaultValue('/DataFixtures/ORM');
    }

    /**
     * @return NodeDefinition
     */
    protected function getBundleWhitelistNode()
    {
        return $this
            ->getBuilder('bundles_enabled_list')
            ->getNodeDefinition()
            ->info('List of bundles to search in. Empty results in all registered bundles.')
            ->defaultValue([])
            ->prototype('scalar')
            ->end();
    }
}

/* EOF */
