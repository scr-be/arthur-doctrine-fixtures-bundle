<?php

/*
 * This file is part of the Scribe Arthur Doctrine Fixtures Library.
 *
 * (c) Scribe Inc. <oss@scr.be>
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Scribe\Arthur\DoctrineFixturesBundle\DataFixtures\Locator;

use Scribe\Arthur\DoctrineFixturesBundle\DataFixtures\Paths\FixturePathsInterface;

/**
 * Interface FixtureLocatorInterface.
 */
interface FixtureLocatorInterface
{
    /**
     * Provide the fixture paths object instance.
     *
     * @param FixturePathsInterface $search
     *
     * @return $this
     */
    public function setPaths(FixturePathsInterface $search);

    /**
     * Locate valid file paths out of provides paths/file names.
     *
     * @param string    $file
     * @param true|bool $single
     *
     * @return array
     */
    public function locate($file, $single = true);

    /**
     * Filter invalid paths out of provides paths.
     *
     * @param null|string           $file
     * @param FixturePathsInterface $search
     *
     * @return FixturePaths
     */
    public function locateValidPaths($file = null, FixturePathsInterface $search = null);
}

/* EOF */
