<?php

/*
 * This file is part of the Scribe Arthur Doctrine Fixtures Library.
 *
 * (c) Scribe Inc. <oss@scr.be>
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Scribe\Doctrine\DataFixtures\Tree;

/**
 * Interface TreeStoreInterface.
 */
interface TreeStoreInterface
{
    /**
     * @param mixed[]     $tree
     * @param null|string $root
     *
     * @return $this
     */
    public static function create(array $tree = [], $root = null);

    /**
     * @param array|null $tree
     *
     * @return $this
     */
    public function setTree(array $tree = null);

    /**
     * @param null $root
     *
     * @return $this
     */
    public function setRoot($root = null);

    /**
     * @param string,... $keySet
     *
     * @return mixed
     */
    public function get(...$keySet);
}

/* EOF */
