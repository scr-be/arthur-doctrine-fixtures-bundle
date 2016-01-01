<?php

/*
 * This file is part of the Scribe Arthur Doctrine Fixtures Library.
 *
 * (c) Scribe Inc. <oss@scr.be>
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Scribe\Arthur\DoctrineFixturesBundle\DataFixtures\Paths;

/**
 * Interface FixturePathsInterface.
 */
interface FixturePathsInterface extends \IteratorAggregate, \Countable, \ArrayAccess
{
    /**
     * Add any number of paths to array; duplicate values will be ignored.
     *
     * @param string,... $paths
     *
     * @return $this
     */
    public function addPaths(...$paths);

    /**
     * Returns paths as array of strings.
     *
     * @return string[]
     */
    public function getPaths();

    /**
     * Does the instance have any paths?
     *
     * @return bool
     */
    public function hasPaths();

    /**
     * Return new instance with leading slashes removed from all paths.
     *
     * @return FixturePathsInterface
     */
    public function removeLeadingSlashes();

    /**
     * Return new instance with all combinations of the lower and upper paths. When lower is not
     * provided, the paths of the instance are used.
     *
     * @param FixturePathsInterface      $upperDirs
     * @param FixturePathsInterface|null $rootDirs
     *
     * @return FixturePathsInterface
     */
    public function cartesianProduct(FixturePathsInterface $upperDirs, FixturePathsInterface $rootDirs = null);

    /**
     * Return new instance with all combinations of the multi-dimensional array of strings (paths)
     * beginning with the lowest index as the least-specific path part and building right with each
     * additional array provided.
     *
     * @param array[] $pathParts
     *
     * @return FixturePathsInterface
     */
    public static function cartesianProductFromPaths(array ...$pathParts);
}

/* EOF */
