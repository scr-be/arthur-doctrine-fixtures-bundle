<?php

/*
 * This file is part of the Scribe Arthur Doctrine Fixtures Library.
 *
 * (c) Scribe Inc. <oss@scr.be>
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Scribe\Arthur\DoctrineFixturesBundle\DataFixtures;

use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\DataFixtures\FixtureInterface as BaseFixtureInterface;
use Scribe\Arthur\DoctrineFixturesBundle\DataFixtures\Paths\FixturePathsInterface;
use Scribe\Arthur\DoctrineFixturesBundle\DataFixtures\Syntax\ReferenceResolverInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Interface FixtureInterface.
 */
interface FixtureInterface extends ContainerAwareInterface, BaseFixtureInterface
{
    /**
     * @return string
     */
    public function getType();

    /**
     * @param string $regex
     */
    public function setFixtureFileSearchRegex($regex);

    /**
     * @return Loader\FixtureLoaderInterface[]
     */
    public function getFixtureFileLoaders();

    /**
     * Attempt to find a matching fixture file for the calling class and parse the file.
     *
     * @param FixturePathsInterface $paths
     *
     * @return $this
     */
    public function loadFixtureMetadata(FixturePathsInterface $paths);

    /**
     * @param ObjectManager              $objectManager
     * @param ReferenceResolverInterface $referenceResolver
     */
    public function loadFixtureData(ObjectManager $objectManager, ReferenceResolverInterface $referenceResolver);

    /**
     * @internal
     *
     * @param ObjectManager $manager
     */
    public function load(ObjectManager $manager);
}

/* EOF */
