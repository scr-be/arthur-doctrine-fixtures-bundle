<?php

/*
 * This file is part of the Scribe Arthur Doctrine Fixtures Library.
 *
 * (c) Scribe Inc. <oss@scr.be>
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Scribe\Arthur\DoctrineFixturesBundle\DataFixtures\Registry;

use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\FixtureInterface as BaseFixtureInterface;
use Scribe\Arthur\DoctrineFixturesBundle\DataFixtures\FixtureInterface;
use Scribe\Arthur\DoctrineFixturesBundle\DataFixtures\Locator\FixtureLocator;
use Scribe\Arthur\DoctrineFixturesBundle\DataFixtures\Paths\FixturePaths;
use Scribe\Wonka\Exception\RuntimeException;
use Scribe\WonkaBundle\Component\DependencyInjection\Container\ContainerAwareInterface;
use Scribe\WonkaBundle\Component\DependencyInjection\Container\ContainerAwareTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class FixtureRegistry.
 */
class FixtureRegistry extends Loader implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    /**
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->setContainer($container);
    }

    /**
     * @param BaseFixtureInterface $fixture
     */
    public function addFixture(BaseFixtureInterface $fixture)
    {
        if (!($fixture instanceof FixtureInterface) || $this->hasFixture($fixture)) {
            return;
        }

        $fixture->setContainer($this->getContainer());

        $searchPaths = FixturePaths::create()->cartesianProductFromPaths(
            [$this->container->getParameter('kernel.root_dir')],
            ...$this->container->getParameter('s.arthur_doctrine_fixtures.fixture_search_parts')
        );

        $fixture->loadFixtureMetadata($searchPaths);

        parent::addFixture($fixture);
    }

    /**
     * @param string $path
     */
    public function loadFromPath($path)
    {
        if (is_dir($path)) {
            $this->loadFromDirectory($path);
        } elseif (is_file($path)) {
            $this->loadFromFile($path);
        }
    }
}

/* EOF */
