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
 * Class DataFixturesLocator.
 */
class FixtureLocator implements FixtureLocatorInterface
{
    /**
     * @var FixturePathsInterface
     */
    protected $search;

    /**
     * {@inherit-doc}.
     *
     * @param FixturePathsInterface $search
     *
     * @return $this
     */
    public function setPaths(FixturePathsInterface $search)
    {
        $this->search = $search;

        return $this;
    }

    /**
     * {@inherit-doc}.
     *
     * @param string    $file
     * @param true|bool $single
     *
     * @return array
     */
    public function locate($file, $single = true)
    {
        if (true !== ($this->search instanceof FixturePathsInterface)) {
            return [];
        }

        $paths = $this
            ->locateValidPaths($file)
            ->getPaths();

        return (array) ($single ? getFirstArrayElement($paths) : $paths);
    }

    /**
     * {@inherit-doc}.
     *
     * @param null|string           $file
     * @param FixturePathsInterface $search
     *
     * @return FixturePathsInterface
     */
    public function locateValidPaths($file = null, FixturePathsInterface $search = null)
    {
        $search = $search ?: $this->search;

        $filtered = $search->filter(function ($p) use ($file) {
            return (bool) (realpath($p.DIRECTORY_SEPARATOR.($file ?: '')));
        })->getPaths();

        for ($i = 0; $i < count($filtered); ++$i) {
            $filtered[$i] = realpath($filtered[$i].DIRECTORY_SEPARATOR.$file);
        }

        return $search::create(...$filtered);
    }
}

/* EOF */
