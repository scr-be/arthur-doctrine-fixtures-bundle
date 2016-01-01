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

use Hoa\Math\Combinatorics\Combination\CartesianProduct;

/**
 * Class FixturePaths.
 */
class FixturePaths extends AbstractFixturePaths
{
    /**
     * {@inherit-doc}.
     *
     * @param string,... $paths
     *
     * @return $this
     */
    public function addPaths(...$paths)
    {
        array_map(function ($p) { $this->add($p); }, $paths);

        return $this;
    }

    /**
     * {@inherit-doc}.
     *
     * @return string[]
     */
    public function getPaths()
    {
        return (array) array_values($this->elements);
    }

    /**
     * {@inherit-doc}.
     *
     * @return bool
     */
    public function hasPaths()
    {
        return (bool) !$this->isEmpty();
    }

    /**
     * {@inherit-doc}.
     *
     * @return FixturePathsInterface
     */
    public function removeLeadingSlashes()
    {
        $result = self::create(...$this->getPaths());

        $result->map(function ($p) {
            return (string) (substr($p, 0, 1) === DIRECTORY_SEPARATOR ? substr($p, 1) : $p);
        });

        return $result;
    }

    /**
     * {@inherit-doc}.
     *
     * @param FixturePathsInterface      $upperDirs
     * @param FixturePathsInterface|null $rootDirs
     *
     * @return FixturePathsInterface
     */
    public function cartesianProduct(FixturePathsInterface $upperDirs, FixturePathsInterface $rootDirs = null)
    {
        $rootDirs = $rootDirs ?: $this;
        $product = new CartesianProduct($rootDirs, $upperDirs);
        $filtered = self::create();

        foreach ($product as $path) {
            $filtered->addPaths($this->normalizePath($path));
        }

        return $filtered;
    }

    /**
     * {@inherit-doc}.
     *
     * @param array[] $pathParts
     *
     * @return FixturePathsInterface
     */
    public static function cartesianProductFromPaths(array ...$pathParts)
    {
        $product = self::create();

        while (null !== ($parts = array_shift($pathParts))) {
            $product = $product->cartesianProduct(self::create(...$parts));
        }

        return $product;
    }

    protected function normalizePath($paths)
    {
        $return = '';

        foreach ($paths as $p) {
            $return .= empty($return) ? $p : DIRECTORY_SEPARATOR.$p;
        }

        return preg_replace('{[/]{2,}}', DIRECTORY_SEPARATOR, $return);
    }
}

/* EOF */
