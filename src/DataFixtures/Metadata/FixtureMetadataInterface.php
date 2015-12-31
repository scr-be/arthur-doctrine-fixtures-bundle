<?php

/*
 * This file is part of the Scribe Arthur Doctrine Fixtures Library.
 *
 * (c) Scribe Inc. <oss@scr.be>
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Scribe\Doctrine\DataFixtures\Metadata;

use Scribe\Doctrine\DataFixtures\FixtureInterface;
use Scribe\Doctrine\DataFixtures\Loader\FixtureLoaderResolverInterface;
use Scribe\Doctrine\DataFixtures\Locator\FixtureLocatorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Interface FixtureMetadataInterface.
 */
interface FixtureMetadataInterface
{
    /**
     * @var string
     */
    const VERSION = '1.0.0';

    /**
     * @var string
     */
    const MODE_SKIP = 'skip';

    /**
     * @var string
     */
    const MODE_BLIND = 'blind';

    /**
     * @var string
     */
    const MODE_PURGE = 'purge';

    /**
     * @var string
     */
    const MODE_MERGE = 'merge';

    /**
     * @var string
     */
    const MODE_DEFAULT = self::MODE_MERGE;

    /**
     * @param ContainerInterface $container
     *
     * @return $this
     */
    public function load(ContainerInterface $container);

    /**
     * @param FixtureInterface $handler
     *
     * @return $this
     */
    public function setHandler(FixtureInterface $handler);

    /**
     * @param FixtureLocatorInterface $locator
     *
     * @return $this
     */
    public function setLocator(FixtureLocatorInterface $locator);

    /**
     * @param FixtureLoaderResolverInterface $loader
     *
     * @return $this
     */
    public function setLoader(FixtureLoaderResolverInterface $loader);

    /**
     * @param string $template
     *
     * @return $this
     */
    public function setNameTemplate($template);

    /**
     * @param string $regex
     *
     * @return $this
     */
    public function setNameRegex($regex);

    /**
     * @return string
     */
    public function getName();

    /**
     * @return array
     */
    public function getMode();

    /**
     * @return string[]
     */
    public function getMetaAndDataVersionsValidated();

    /**
     * @param string[] $for
     *
     * @return string[]|string
     */
    public function getVersions(...$for);

    /**
     * @return string|null
     */
    public function getMetaVersion();

    /**
     * @return string|null
     */
    public function getDataVersion();

    /**
     * @return array
     */
    public function getCleanupMode();

    /**
     * @return int
     */
    public function getPriority();

    /**
     * @return array
     */
    public function getDependencies();

    /**
     * @param string $name
     *
     * @return null|mixed
     */
    public function getDependency($name);

    /**
     * @return array
     */
    public function getData();

    /**
     * @return bool
     */
    public function isEmpty();

    /**
     * @return bool
     */
    public function isCannibal();

    /**
     * @return null|string
     */
    public function getEntityParameter();

    /**
     * @return bool
     */
    public function hasReferenceByIndexEnabled();

    /**
     * @return bool
     */
    public function hasColumnReferences();

    /**
     * @return array
     */
    public function getColumnReferenceCollections();
}

/* EOF */
