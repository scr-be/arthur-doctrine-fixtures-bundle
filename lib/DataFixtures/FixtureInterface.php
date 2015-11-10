<?php

/*
 * This file is part of the Scribe Arthur Doctrine Fixtures Library.
 *
 * (c) Scribe Inc. <oss@scr.be>
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Scribe\Doctrine\DataFixtures;

use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Interface FixtureInterface.
 */
interface FixtureInterface extends ContainerAwareInterface
{
    /**
     * @return string
     */
    public function getType();

    /**
     * @param ContainerInterface $container
     */
    public function setContainer(ContainerInterface $container = null);

    /**
     * @param string $regex
     */
    public function setFixtureFileSearchRegex($regex);

    /**
     * @return Paths\FixturePaths
     */
    public function getFixtureFileSearchPaths();

    /**
     * @param array[] ...$paths
     */
    public function setFixtureFileSearchPaths(array ...$paths);

    /**
     * @return Loader\FixtureLoaderInterface[]
     */
    public function getFixtureFileLoaders();

    /**
     * Attempt to find a matching fixture file for the calling class and parse the file.
     *
     * @return $this
     */
    public function loadFixtureMetadata();

    /**
     * @param ObjectManager $manager
     */
    public function load(ObjectManager $manager);
}

/* EOF */
