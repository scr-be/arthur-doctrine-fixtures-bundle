<?php

/*
 * This file is part of the Scribe Arthur Doctrine Fixtures Library.
 *
 * (c) Scribe Inc. <oss@scr.be>
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Scribe\Doctrine\DataFixtures\Paths;

use Scribe\Primitive\Collection\AbstractCollection;
use Scribe\Wonka\Exception\LogicException;

/**
 * Class AbstractFixturePaths.
 */
abstract class AbstractFixturePaths extends AbstractCollection implements FixturePathsInterface
{
    /**
     * @param string[] $paths
     */
    public function __construct(array $paths = [])
    {
        $this->addPaths(...$paths);
    }

    /**
     * Factory to create new instance with initial path set.
     *
     * @param string,... $paths
     *
     * @return static
     */
    public static function create(...$paths)
    {
        $instance = new static;

        return $instance->addPaths(...$paths);
    }

    /**
     * {@inherit-doc}
     *
     * @param string,... $paths
     *
     * @return $this
     */
    public abstract function addPaths(...$paths);
}

/* EOF */
