<?php

/*
 * This file is part of the Scribe Arthur Doctrine Fixtures Library.
 *
 * (c) Scribe Inc. <oss@scr.be>
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Scribe\Doctrine\DataFixtures\Locator;

use Scribe\Doctrine\DataFixtures\Paths\FixturePaths;

/**
 * Class DataFixturesLocator.
 */
class FixtureLocator implements FixtureLocatorInterface
{
    /**
     * @var FixturePaths
     */
    protected $search;

    /**
     * {@inherit-doc}
     *
     * @param FixturePaths $search
     *
     * @return $this
     */
    public function setPaths(FixturePaths $search)
    {
        $this->search = $search;

        return $this;
    }

    /**
     * {@inherit-doc}
     *
     * @param string    $file
     * @param true|bool $single
     *
     * @return array
     */
    public function locate($file, $single = true)
    {
        if (true !== ($this->search instanceof FixturePaths)) { return []; }

        $paths = $this
            ->locateValidPaths($file)
            ->getPaths();

        return (array) ($single ? array_first($paths) : $paths);
    }

    /**
     * {@inherit-doc}
     *
     * @param FixturePaths $search
     * @param null|string  $file
     *
     * @return FixturePaths
     */
    public function locateValidPaths($file = null, FixturePaths $search = null)
    {
        $search = $search ?: $this->search;
        
        $search->filter(function (&$p) use ($file) {
            if (false !== ($realPath = realpath($p . DIRECTORY_SEPARATOR . ($file ?: '')))) {
                $p = $realPath;
            }

            return (bool) ($realPath ?: false);
        });

        return $search::create(...$search->getPaths());
    }
}

/* EOF */
