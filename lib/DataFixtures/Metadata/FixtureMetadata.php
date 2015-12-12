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

use Scribe\Wonka\Exception\LogicException;
use Scribe\Wonka\Exception\RuntimeException;
use Scribe\Wonka\Utility\ClassInfo;
use Scribe\Doctrine\DataFixtures\FixtureInterface;
use Scribe\Doctrine\DataFixtures\Loader\FixtureLoaderResolverInterface;
use Scribe\Doctrine\DataFixtures\Locator\FixtureLocatorInterface;
use Scribe\Doctrine\DataFixtures\Tree\TreeStore;

/**
 * FixtureMetadata.
 */
class FixtureMetadata implements FixtureMetadataInterface
{
    /**
     * @var FixtureInterface
     */
    protected $handler;

    /**
     * @var FixtureLocatorInterface
     */
    protected $locator;

    /**
     * @var FixtureLoaderResolverInterface
     */
    protected $loader;

    /**
     * @var string
     */
    protected $className;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $type;

    /**
     * @var string
     */
    protected $fileName;

    /**
     * @var string
     */
    protected $filePath;

    /**
     * @var string
     */
    protected $nameRegex;

    /**
     * @var string
     */
    protected $nameTemplate;

    /**
     * @var string
     */
    protected $data;

    /**
     * @var TreeStore
     */
    protected $tree;

    /**
     * @return $this
     */
    public function load()
    {
        $this->className = $this->getHandlerClassName($this->handler);
        $this->type = $this->handler->getType();
        $this->nameTemplate = $this->resolveFileNameTemplate();
        $this->nameRegex = $this->resolveNameRegex();
        $this->name = $this->resolveName();
        $this->fileName = $this->resolveFileName();
        $this->filePath = $this->resolveLocation();
        $this->data = $this->resolveContents();
        $this->tree = $this->getTreeStore();

        return $this;
    }

    /**
     * @param FixtureInterface $handler
     *
     * @return $this
     */
    public function setHandler(FixtureInterface $handler)
    {
        $this->handler = $handler;

        return $this;
    }

    /**
     * @param FixtureLocatorInterface $locator
     *
     * @return $this
     */
    public function setLocator(FixtureLocatorInterface $locator)
    {
        $this->locator = $locator;

        return $this;
    }

    /**
     * @param FixtureLoaderResolverInterface $loader
     *
     * @return $this
     */
    public function setLoader(FixtureLoaderResolverInterface $loader)
    {
        $this->loader = $loader;

        return $this;
    }

    /**
     * @param string $template
     *
     * @return $this
     */
    public function setNameTemplate($template)
    {
        $this->nameTemplate = (string) $template;

        return $this;
    }

    /**
     * @param string $regex
     *
     * @return $this
     */
    public function setNameRegex($regex)
    {
        $this->nameRegex = (string) $regex;

        return $this;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string[]
     *
     * @throws LogicException
     */
    public function getVersions()
    {
        $v = $this->tree->get('versions');

        if (!$v) {
            throw new LogicException('No structure version set within your fixture!');
        }

        if (!isset($v['self']) || strlen($v['self']) !== 5) {
            throw new LogicException('Not set or malformed version for self: follow semantic versioning (x.x.x)');
        }

        if (!isset($v['data']) || strlen($v['data']) !== 5) {
            throw new LogicException('Not set or malformed version for data: follow semantic versioning (x.x.x)');
        }

        return [$v['self'], $v['data']];
    }

    /**
     * @return string[]
     */
    public function getMode()
    {
        $mode = $this->tree->get('strategy', 'persist');

        return $this->normalizeMode($mode);
    }

    /**
     * @param mixed $mode
     *
     * @return array
     */
    protected function normalizeMode($mode)
    {
        $normalized = [];

        if (isset($mode['prefer']) && $mode['prefer'] != '~') {
            $normalized['prefer'] = $mode['prefer'];
        } else {
            $normalized['prefer'] = 'merge';
        }

        if (isset($mode['fallback']) && $mode['fallback'] != '~') {
            $normalized['fallback'] = $mode['fallback'];
        } else {
            $normalized['fallback'] = 'purge';
        }

        if (isset($mode['failure']) && $mode['failure'] != '~') {
            $normalized['failure'] = $mode['failure'];
        } else {
            $normalized['failure'] = 'warn';
        }

        return $normalized;
    }

    /**
     * @return string[]
     */
    public function getCleanupMode()
    {
        $mode = $this->tree->get('strategy', 'cleanup');

        return $this->normalizeCleanupMode($mode);
    }

    /**
     * @param mixed $mode
     *
     * @return array
     */
    protected function normalizeCleanupMode($mode)
    {
        $normalized = [];

        if (isset($mode['prefer']) && $mode['prefer'] != '~') {
            $normalized['prefer'] = $mode['prefer'];
        } else {
            $normalized['prefer'] = 'purge';
        }

        if (isset($mode['fallback']) && $mode['fallback'] != '~') {
            $normalized['fallback'] = $mode['fallback'];
        } else {
            $normalized['fallback'] = 'none';
        }

        if (isset($mode['failure']) && $mode['failure'] != '~') {
            $normalized['failure'] = $mode['failure'];
        } else {
            $normalized['failure'] = 'warn';
        }

        return $normalized;
    }

    /**
     * @return int
     */
    public function getPriority()
    {
        return (int) ($this->tree->get('strategy', 'priority') ?: 0);
    }

    /**
     * @return array
     */
    public function getDependencies()
    {
        return (array) $this->tree->get('depends');
    }

    /**
     * @param string $name
     *
     * @return null|mixed
     */
    public function getDependency($name)
    {
        return $this->tree->get('depends', $name);
    }

    /**
     * @return array
     */
    public function getData()
    {
        return (array) $this->tree->get('fixture');
    }

    /**
     * @return bool
     */
    public function isEmpty()
    {
        return (bool) empty($this->getData());
    }

    /**
     * @return bool
     */
    public function isCannibal()
    {
        return (bool) $this->tree->get('strategy', 'cannibal');
    }

    /**
     * @return null|string
     */
    public function getServiceKey()
    {
        return $this->tree->get('service', 'entityPath');
    }

    /**
     * @return bool
     */
    public function hasReferenceByIndexEnabled()
    {
        return (bool) ($this->tree->get('references', 'identFromFixture'));
    }

    /**
     * @return bool
     */
    public function hasReferenceByColumnsEnabled()
    {
        return (bool) ($this->tree->get('references', 'buildFromColumns'));
    }

    /**
     * @return array
     */
    public function getReferenceByColumnsSets()
    {
        $prepareColumnSets = function (&$set) { $set = (array) $set; };

        if (null !== ($columnSets = $this->tree->get('references', 'buildFromColumns'))) {
            array_walk($columnSets, $prepareColumnSets);
        }

        return (array) $columnSets;
    }

    /**
     * @internal
     *
     * @return string
     */
    public function getTreeStore()
    {
        return TreeStore::create((array) $this->data, (string) $this->name);
    }

    /**
     * @internal
     *
     * @param FixtureInterface $handler
     *
     * @return string
     */
    public function getHandlerClassName(FixtureInterface $handler)
    {
        return ClassInfo::getClassNameByInstance($handler);
    }

    /**
     * @return string
     */
    protected function resolveNameRegex()
    {
        return (string) ($this->nameRegex ?: '\bLoad([a-zA-Z]{1,})Data');
    }

    /**
     * @return string
     */
    protected function resolveName()
    {
        if (1 !== preg_match('{'.$this->nameRegex.'}', $this->className, $matches)) {
            throw new RuntimeException('Unable to resolve fixture name for %s.', null, null, $this->className);
        }

        return $matches[1];
    }

    protected function resolveFileNameTemplate()
    {
        return (string) ($this->nameTemplate ?: '%name%Data.%type%');
    }

    /**
     * @return string
     */
    protected function resolveFileName()
    {
        $resolvedName = $this->nameTemplate;

        foreach (['name' => $this->name, 'type' => $this->type] as $search => $replace) {
            $resolvedName = str_replace('%'.$search.'%', $replace, $resolvedName);
        }

        return $resolvedName;
    }

    /**
     * @return string
     */
    protected function resolveLocation()
    {
        $location = $this->locator->locate($this->fileName);

        return (string) array_first($location);
    }

    /**
     * @return false|\Symfony\Component\Config\Loader\LoaderInterface
     */
    protected function resolveContents()
    {
        if (false === ($loader = $this->loader->resolve($this->filePath, $this->type))) {
            throw new RuntimeException('Unable to resolve appropriate loader for %s:%s.', null, null, $this->filePath, $this->type);
        }

        return $loader->load($this->filePath, $this->type);
    }
}

/* EOF */
