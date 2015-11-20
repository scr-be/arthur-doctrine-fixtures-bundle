<?php

/*
 * This file is part of the Scribe Arthur Doctrine Fixtures Library.
 *
 * (c) Scribe Inc. <oss@scr.be>
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Scribe\Doctrine\DataFixtures;

use Doctrine\Common\DataFixtures\AbstractFixture as BaseAbstractFixture;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Persistence\ObjectManager;
use Scribe\Doctrine\DataFixtures\Loader\YamlFixtureLoader;
use Scribe\Doctrine\DataFixtures\Loader\FixtureLoaderResolver;
use Scribe\Doctrine\DataFixtures\Locator\FixtureLocator;
use Scribe\Doctrine\DataFixtures\Metadata\FixtureMetadata;
use Scribe\Doctrine\DataFixtures\Paths\FixturePaths;
use Scribe\Doctrine\Exception\ORMException;
use Scribe\Doctrine\ORM\Mapping\Entity;
use Scribe\Wonka\Exception\RuntimeException;
use Scribe\Wonka\Utility\Reflection\ClassReflectionAnalyser;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class AbstractFixture.
 */
abstract class AbstractFixture extends BaseAbstractFixture implements FixtureInterface
{
    /**
     * Symfony service container instance.
     *
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var ObjectManager
     */
    protected $objectManager;

    /**
     * Holds interpreted fixture data.
     *
     * @var FixtureMetadata
     */
    protected $metadata;

    /**
     * Number of items to batch when flushing Doctrine.
     *
     * @var int
     */
    protected $insertFlushBatchSize = 1000;

    /**
     * Regular expression to parse class name to translate to fixture data filename.
     *
     * @var string
     */
    protected $fixtureSearchNameRegex = '';

    /**
     * Using fixture search regex for class name, determine fixture name via template.
     *
     * @var string
     */
    protected $fixtureNameTemplate = '%name%Data.%type%';

    /**
     * Array of arrays containing [arts of a filepath to be combined at runtime (cartesian product).
     *
     * @var array[]
     */
    protected $fixtureSearchPathParts = [
        ['../', '../../', '../../../'],
        ['./', 'app'],
        ['config'],
        ['shared_public/fixtures', 'shared_proprietary/fixtures'],
    ];

    /**
     * {@inherit-doc}
     *
     * @return string
     */
    abstract public function getType();

    /**
     * {@inherit-doc}
     *
     * @param ContainerInterface $container
     */
    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;

        $this->loadFixtureMetadata();
    }

    /**
     * {@inherit-doc}
     *
     * @param string $regex
     */
    public function setFixtureFileSearchRegex($regex)
    {
        $this->fixtureSearchNameRegex = $regex;
    }

    /**
     * {@inherit-doc}
     *
     * @return Paths\FixturePaths
     */
    public function getFixtureFileSearchPaths()
    {
        return FixturePaths::create()->cartesianProductFromPaths(
            [$this->container->getParameter('kernel.root_dir')],
            ...$this->fixtureSearchPathParts
        );
    }

    /**
     * {@inherit-doc}
     *
     * @param array[] ...$paths
     */
    public function setFixtureFileSearchPaths(array ...$paths)
    {
        $this->fixtureSearchPathParts = $paths ?: [];
    }

    /**
     * {@inherit-doc}
     *
     * @return Loader\FixtureLoaderInterface[]
     */
    public function getFixtureFileLoaders()
    {
        return [ new YamlFixtureLoader() ];
    }

    /**
     * {@inherit-doc}
     *
     * @throws RuntimeException
     *
     * @return $this
     */
    public function loadFixtureMetadata()
    {
        try {
            $locator = new FixtureLocator();
            $locator->setPaths($this->getFixtureFileSearchPaths());

            $loader = new FixtureLoaderResolver();
            $loader->assignLoaders($this->getFixtureFileLoaders());

            $metadata = new FixtureMetadata();
            $metadata
                ->setNameRegex($this->fixtureSearchNameRegex)
                ->setNameTemplate($this->fixtureNameTemplate)
                ->setHandler($this)
                ->setLocator($locator)
                ->setLoader($loader)
                ->load();

            $this->metadata = $metadata;

        } catch (\Exception $exception) {
            throw new RuntimeException('Unable to generate metadata for fixture (ORM Loader: %s)', null, $exception, get_class($this));
        }

        return $this;
    }

    /**
     * @param ObjectManager $manager
     */
    public function load(ObjectManager $manager)
    {
        $this->objectManager = $manager;

        if ($this->metadata->isEmpty()) {
            return;
        }

        $this->entityManagerFlushAndClean();

        foreach ($this->metadata->getData() as $index => $data) {
            $this->entityLoadAndPersist($entity, $index, $data);
            $this->entityHandleCannibal($entity);
            $this->entityResolveAllRefs($entity, $index, $data);

            if (($index % $this->insertFlushBatchSize) === 0) {
                $this->entityManagerFlushAndClean();
            }
        }

        $this->entityManagerFlushAndClean();
    }

    /**
     * @return $this
     */
    protected function entityManagerFlushAndClean()
    {
        $this->objectManager->flush();
        $this->objectManager->clear();

        return $this;
    }

    /**
     * @param \Scribe\Doctrine\ORM\Mapping\Entity|mixed $entity
     * @param int|mixed                                 $index
     * @param array[]                                   $data
     *
     * @return $this
     */
    protected function entityLoadAndPersist(&$entity, $index, $data)
    {
        $entity = $this->getNewPopulatedEntity($index, $data);

        $this->objectManager->persist($entity);

        return $this;
    }

    /**
     * @param \Scribe\Doctrine\ORM\Mapping\Entity|mixed $entity
     *
     * @return $this
     */
    protected function entityHandleCannibal(&$entity)
    {
        if ($this->metadata->isCannibal()) {
            $this->objectManager->flush();
        }

        return $this;
    }

    /**
     * @param \Scribe\Doctrine\ORM\Mapping\Entity|mixed $entity
     * @param int|mixed                                 $index
     * @param array[]                                   $data
     *
     * @return $this
     */
    protected function entityResolveAllRefs(&$entity, $index, $data)
    {
        if ($this->metadata->hasReferenceByIndexEnabled()) {
            $this->addReference($this->metadata->getName().':'.$index, $entity);
        }

        if ($this->metadata->hasReferenceByColumnsEnabled()) {

            $referenceByColumnsSetConcat = function($columns) use ($data) {
                array_walk($columns, function (&$c) use ($data) { $c = $data[$c]; });
                return implode(':', (array) $columns);
            };
            $referenceByColumnsSetRegister = function($columns) use ($entity, $referenceByColumnsSetConcat) {
                $this->addReference($this->metadata->getName().':'.$referenceByColumnsSetConcat($columns), $entity);
            };
            array_map($referenceByColumnsSetRegister, $this->metadata->getReferenceByColumnsSets());
        }

        return $this;
    }

    /**
     * @param int     $index
     * @param array[] $values
     *
     * @return \Scribe\Doctrine\ORM\Mapping\Entity|mixed
     */
    protected function getNewPopulatedEntity($index, $values)
    {
        try {
            $entityClassName = $this->container->getParameter($this->metadata->getServiceKey());
            $entity = new $entityClassName();
        } catch (\Exception $exception) {
            throw new RuntimeException('Unable to locate service id %s.', null, $exception, $this->metadata->getServiceKey());
        }

        try {
            return $this->hydrateEntity($entity, $index, $values);
        } catch (\Exception $exception) {
            throw new RuntimeException('Could not hydrate entity: fixture %s, index %s.', null, $exception, $this->metadata->getName(), (string) $index);
        }
    }

    /**
     * @param \Scribe\Doctrine\ORM\Mapping\Entity|mixed $entity
     * @param int|mixed                                 $index
     * @param array[]                                   $values
     *
     * @return \Scribe\Doctrine\ORM\Mapping\Entity|mixed
     */
    protected function hydrateEntity(Entity $entity, $index, $values)
    {
        foreach ($values as $property => $value) {
            $methodCall = $this->getHydrateEntityMethodCall($property);
            $methodData = $this->getHydrateEntityMethodData($property, $values);

            try {
                $entity = $this->hydrateEntityData($entity, $property, $methodCall, $methodData);
            } catch(\Exception $exception) {
                $entity = $this->hydrateEntityData($entity, $property, $methodCall, new ArrayCollection((array) $methodData));
            }
        }

        return $entity;
    }

    /**
     * @param \Scribe\Doctrine\ORM\Mapping\Entity|mixed $entity
     * @param string                                    $property
     * @param string                                    $methodCall
     * @param mixed                                     $methodData
     *
     * @return \Scribe\Doctrine\ORM\Mapping\Entity|mixed
     */
    protected function hydrateEntityData(Entity $entity, $property, $methodCall, $methodData)
    {
        try {

            $reflectProp = (new ClassReflectionAnalyser(new \ReflectionClass($entity)))
                ->setPropertyPublic($property);
            $reflectProp->setValue($entity, $methodData);

            return $entity;

        } catch (\Exception $exception) {
            throw new RuntimeException('Could not assign property "%s" via property, setter or reflection in fixture %s.', null, $exception, $property, $this->metadata->getName());
        }
    }

    /**
     * @param string $property
     *
     * @return string
     */
    protected function getHydrateEntityMethodCall($property)
    {
        return (string) sprintf('set%s', ucfirst($property));
    }

    /**
     * @param string     $property
     * @param array|null $values
     *
     * @return array|mixed
     */
    protected function getHydrateEntityMethodData($property, array $values = null)
    {
        if (!array_key_exists($property, $values)) {
            throw new RuntimeException('Could not find index %s in fixture %s.', null, null, $property, $this->metadata->getName());
        }

        return is_array($values[$property]) ? $this->getHydrationValueSet($values[$property]) : $this->getHydrationValue($values[$property]);
    }

    /**
     * @param array $valueSet
     *
     * @return array
     */
    protected function getHydrationValueSet(array $valueSet = [])
    {
        return (array) array_map([$this, 'getHydrationValue'], $valueSet);
    }

    /**
     * @param mixed $value
     *
     * @return mixed
     */
    protected function getHydrationValue($value)
    {
        if (substr($value, 0, 2) === '++') {
            $value = $this->getHydrationValueUsingInternalRefLookup(substr($value, 2)) ?: $value;
        } elseif (substr($value, 0, 1) === '+' && 1 === preg_match('{^\+([a-z]+:[0-9]+)$}i', $value, $matches)) {
            $value = $this->getHydrationValueUsingInternalRefLookup($matches[1]) ?: $value;
        } elseif (substr($value, 0, 1) === '@' && 1 === preg_match('{^@([a-z]+)\?([^=]+)=([^&]+)$}i', $value, $matches)) {
            $value = $this->getHydrationValueUsingSearchQuery($matches[1], $matches[2], $matches[3]) ?: $value;
        }

        return $value;
    }

    /**
     * @param string $reference
     *
     * @return mixed|null
     */
    protected function getHydrationValueUsingInternalRefLookup($reference)
    {
        return $this->getReference($reference);
    }

    /**
     * @param string $dependencyLookup
     * @param string $column
     * @param string $criteria
     *
     * @throws ORMException
     *
     * @return mixed|null
     */
    protected function getHydrationValueUsingSearchQuery($dependencyLookup, $column, $criteria)
    {
        if (!($dependency = $this->metadata->getDependency($dependencyLookup)) || !(isset($dependency['repository']))) {
            throw new RuntimeException('Missing dependency repo config for %s as called in fixture %s.', null, null, $dependencyLookup, $this->metadata->getName());
        }

        if (!$this->container->has($dependency['repository'])) {
            throw new RuntimeException('Dependency %s for fixture %s cannot be found in container.', null, null, $dependencyLookup, $this->metadata->getName());
        }

        $repo = $this->container->get($dependency['repository']);
        $call = isset($dependency['findMethod']) ? $dependency['findMethod'] : 'findBy'.ucwords($column);

        try {
            $result = call_user_func([$repo, $call], $criteria);
        } catch (\Exception $exception) {
            throw new ORMException('Error searching with call %s(%s) in fixture %s.', null, $exception, $call, $criteria, $this->metadata->getName());
        }

        if (count($result) > 1) {
            throw new ORMException('Search with call %s(%s) in fixture %s has >1 result.', null, null, $call, $criteria, $this->metadata->getName());
        }

        if ($result instanceof ArrayCollection) {
            return $result->first();
        }

        return is_array($result) ? array_values($result)[0] : $result;
    }
}

/* EOF */
