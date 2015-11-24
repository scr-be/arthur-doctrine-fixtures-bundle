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

/**
 * Interface FixtureMetadataInterface.
 */
interface FixtureMetadataInterface
{
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
     * @return $this
     */
    public function load();

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
     * @return string
     */
    public function getMode();

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
    public function getServiceKey();

    /**
     * @return bool
     */
    public function hasReferenceByIndexEnabled();

    /**
     * @return bool
     */
    public function hasReferenceByColumnsEnabled();

    /**
     * @return array
     */
    public function getReferenceByColumnsSets();
}

/* EOF */
