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

use Scribe\Doctrine\ORM\Mapping\Entity;
use Scribe\Wonka\Exception\LogicException;
use Scribe\Wonka\Exception\RuntimeException;
use Scribe\Wonka\Utility\ClassInfo;
use Scribe\Doctrine\DataFixtures\FixtureInterface;
use Scribe\Doctrine\DataFixtures\Loader\FixtureLoaderResolverInterface;
use Scribe\Doctrine\DataFixtures\Locator\FixtureLocatorInterface;
use Scribe\Doctrine\DataFixtures\Tree\TreeStore;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * FixtureMetadata.
 */
class FixtureMetadata implements FixtureMetadataInterface
{
    /**
     * @var ContainerInterface
     */
    protected $container;

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
     * @param ContainerInterface $container
     *
     * @return $this
     */
    public function load(ContainerInterface $container)
    {
        $this->container = $container;
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
     * @param string $parameter
     *
     * @return mixed
     */
    public function getParameter($parameter)
    {
        if ($this->container->hasParameter($parameter)) {
            return $this->container->getParameter($parameter);
        }

        throw new RuntimeException(
            'Parameter "%s" doesn\'t exist in container for "%s".', null, null,
            $parameter, $this->getName()
        );
    }

    /**
     * @param string $key
     *
     * @return mixed
     */
    public function getService($key)
    {
        if ($this->container->has($key)) {
            return $this->container->get($key);
        }

        throw new RuntimeException(
            'Service "%s" doesn\'t exist in container for "%s".', null, null,
            $key, $this->getName()
        );
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param \Closure|null $validateMeta
     * @param \Closure|null $validateData
     *
     * @return string[]|bool[]
     *
     * @throws RuntimeException
     */
    public function getMetaAndDataVersionsValidated(\Closure $validateMeta = null, \Closure $validateData = null)
    {
        $validateMeta = $validateMeta instanceof \Closure ? $validateMeta : function($version) {
            return call_user_func([$this, 'validateVersion'], $version, $this->getMetaVersionRequired());
        };

        $validateData = $validateData instanceof \Closure ? $validateData : function($version) {
            return call_user_func([$this, 'validateVersion'], $version, $this->getDataVersionRequired());
        };

        return $this->validateVersions(
            $this->getVersions('meta_format'),
            $validateMeta,
            $this->getVersions('data_object'),
            $validateData
        );
    }

    /**
     * @param string[] $for
     *
     * @return string[]|string
     */
    public function getVersions(...$for)
    {
        $versions = (array) array_map(function ($which) {
            if (null !== ($v = $this->tree->get('versions', $which))) {
                return $v;
            }

            throw new RuntimeException('Version "%s" not defined for "%s".', null, null, $which, $this->getName());
        }, $for);

        return (count($versions) === 1 ? current($versions) : $versions);
    }

    /**
     * @param string        $metaRunning
     * @param string        $dataRunning
     * @param \Closure|null $validateMeta
     * @param \Closure|null $validateData
     *
     * @return bool[]
     */
    public function validateVersions($metaRunning, \Closure $validateMeta = null, $dataRunning, \Closure $validateData = null)
    {
        return [
            $metaRunning,
            ($validateMeta instanceof \Closure ? $validateMeta($metaRunning) : null),
            $dataRunning,
            ($validateData instanceof \Closure ? $validateData($dataRunning) : null),
        ];
    }

    /**
     * @param string $running
     * @param string $require
     *
     * @return bool
     */
    protected function validateVersion($running, $require)
    {
        $requireMajor = substr($require, 0, 1).'.0.0';
        $runningMajor = substr($running, 0, 1).'.0.0';

        return (bool) (
            true !== version_compare($running, $require, '>') &&
            true === version_compare($runningMajor, $requireMajor, '=')
        );
    }

    /**
     * @return string|null
     */
    public function getMetaVersion()
    {
        return $this->getVersions('meta_format');
    }

    /**
     * @return string|null
     */
    public function getDataVersion()
    {
        return $this->getVersions('data_object');
    }

    /**
     * @return string
     */
    public function getMetaVersionRequired()
    {
        return self::VERSION;
    }

    /**
     * @return string
     */
    public function getDataVersionRequired()
    {
        $entityFQCN = $this->getEntityFQCN();

        if (defined($entityFQCN.'::VERSION')) {
            return constant($entityFQCN.'::VERSION');
        }

        throw new RuntimeException('No data version set in entity at "%s::%s".', null, null, $entityFQCN, 'VERSION');
    }

    public function isAssociationPurgeAllowed()
    {
        return (bool) ($this->tree->get('strategy', 'associations', 'purge'));
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
        return (array) array_keys($this->tree->get('depends'));
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
     * @param string $name
     *
     * @return string
     */
    public function getDependencyEntityParameter($name)
    {
        $dependency = $this->getDependency($name);

        if (!isset($dependency['entity_class'])) {
            throw new RuntimeException('No entity class container parameter set for %s in %s.', null, null, $this->getName(), $name);
        }

        return $dependency['entity_class'];
    }

    /**
     * @param string $fixtureFQCN
     *
     * @return string[]
     */
    public function getAutoLoadedDependencies($fixtureFQCN)
    {
        $dependencies = array_filter($this->getDependencies(), function ($d) {
            return (bool) $this->isAutoLoadedDependency($d);
        });

        return $this->getDependenciesResolved($fixtureFQCN, $dependencies);
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function isAutoLoadedDependency($name)
    {
        $d = $this->getDependency($name);

        return (bool) (isset($d['auto_depends']) && true == $d['auto_depends']);
    }

    /**
     * @param string   $fixtureFQCN
     * @param string[] $dependencies
     *
     * @return bool|string
     */
    protected function getDependenciesResolved($fixtureFQCN, array $dependencies = null)
    {
        return (array) array_map(function ($d) use ($fixtureFQCN) {
            return $this->resolveDependencyToFixtureClass($d, $fixtureFQCN);
        }, $dependencies ?: $this->getDependencies());
    }

    /**
     * @param string $dependencyName
     * @param string $fixtureFQCN
     *
     * @return bool|string
     */
    protected function resolveDependencyToFixtureClass($dependencyName, $fixtureFQCN)
    {
        $entityFQCN = $this->getParameter(
            $this->getDependencyEntityParameter($dependencyName)
        );

        return sprintf('%sLoad%sData',
            ClassInfo::getNamespace($fixtureFQCN),
            ClassInfo::getClassName($entityFQCN)
        );
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
    public function getEntityParameter()
    {
        return $this->tree->get('service', 'entity_class');
    }

    /**
     * @return string
     */
    public function getEntityFQCN()
    {
        return $this->getParameter($this->getEntityParameter());
    }

    /**
     * @return Entity
     */
    public function getEntityInstance()
    {
        $fQCN = $this->getEntityFQCN();

        return new $fQCN;
    }

    /**
     * @return string
     */
    public function getRepositoryParameter()
    {
        return $this->tree->get('service', 'repo_service');
    }

    /**
     * @return string
     */
    public function getRepositoryFQCN()
    {
        return get_class($this->getRepositoryInstance());
    }

    public function getRepositoryInstance()
    {
        return $this->getService($this->getRepositoryParameter());
    }

    /**
     * @return bool
     */
    public function hasReferenceByIndexEnabled()
    {
        return (bool) ($this->tree->get('references', 'identity_from_me'));
    }

    /**
     * @return bool
     */
    public function hasColumnReferences()
    {
        return (bool) (
            null !== $this->getColumnReferences() ||
            true !== (count($this->getColumnReferences()) === 0)
        );
    }

    /**
     * @return string[]|null
     */
    public function getColumnReferences()
    {
        return $this->tree->get('references', 'create_from_cols');
    }

    /**
     * @param \Closure|null $cannonicalize
     *
     * @return array
     */
    public function getColumnReferenceCollections(\Closure $cannonicalize = null)
    {
        $cannonicalize = $cannonicalize instanceof \Closure ? $cannonicalize : function (&$set) {
            $set = (array) $set;
        };

        if (null !== ($columnCollections = $this->tree->get('references', 'create_from_cols'))) {
            array_walk($columnCollections, $cannonicalize);
        }

        return (array) $columnCollections;
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

    /**
     * @return string
     */
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

        return (string) getFirstArrayElement($location);
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
