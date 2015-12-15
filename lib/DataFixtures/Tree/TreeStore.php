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
 * Class TreeStore.
 */
class TreeStore implements TreeStoreInterface
{
    /**
     * @var array
     */
    protected $tree;

    /**
     * @var string
     */
    protected $root;

    /**
     * @param mixed[]     $tree
     * @param null|string $root
     *
     * @return $this
     */
    public static function create(array $tree = [], $root = null)
    {
        $instance = (new self())
            ->setTree($tree)
            ->setRoot($root);

        return $instance;
    }

    /**
     * @param array|null $tree
     *
     * @return $this
     */
    public function setTree(array $tree = null)
    {
        $this->tree = $tree ?: [];

        return $this;
    }

    /**
     * @param null $root
     *
     * @return $this
     */
    public function setRoot($root = null)
    {
        $this->root = $root;

        return $this;
    }

    /**
     * @param string,... $keySet
     *
     * @return mixed
     */
    public function get(...$keySet)
    {
        $keySet = $this->root === null ? $keySet : array_merge((array) $this->root, $keySet);

        return $this->resolveDeepSearch($this->tree, $keySet);
    }

    /**
     * @param array $tree
     * @param array $keySet
     *
     * @return null|mixed
     */
    protected function resolveDeepSearch(array $tree, array $keySet)
    {
        $key = array_shift($keySet);

        if (true !== array_key_exists($key, $tree)) {
            return;
        }

        if (false === (count($keySet) > 0)) {
            return $tree[$key];
        }

        if (!is_array($tree[$key])) {
            return;
        }

        return $this->resolveDeepSearch($tree[$key], $keySet);
    }
}

/* EOF */
