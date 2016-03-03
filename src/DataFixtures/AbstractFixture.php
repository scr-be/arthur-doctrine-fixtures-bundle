<?php

/*
 * This file is part of the Scribe Arthur Doctrine Fixtures Library.
 *
 * (c) Scribe Inc. <oss@scr.be>
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Scribe\Arthur\DoctrineFixturesBundle\DataFixtures;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\DataFixtures\AbstractFixture as BaseAbstractFixture;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\EntityManager;
use Scribe\Arthur\DoctrineFixturesBundle\DataFixtures\Exception\StrategyException;
use Scribe\Arthur\DoctrineFixturesBundle\DataFixtures\Loader\FixtureLoaderResolver;
use Scribe\Arthur\DoctrineFixturesBundle\DataFixtures\Locator\FixtureLocator;
use Scribe\Arthur\DoctrineFixturesBundle\DataFixtures\Metadata\FixtureMetadata;
use Scribe\Arthur\DoctrineFixturesBundle\DataFixtures\Paths\FixturePathsInterface;
use Scribe\Arthur\DoctrineFixturesBundle\DataFixtures\Registrar\PurgedEntityRegistrar;
use Scribe\Arthur\DoctrineFixturesBundle\DataFixtures\Syntax\ReferenceResolver;
use Scribe\Arthur\DoctrineFixturesBundle\DataFixtures\Syntax\ReferenceResolverInterface;
use Scribe\Doctrine\Exception\ORMException;
use Scribe\Doctrine\ORM\Mapping\Entity;
use Scribe\Wonka\Component\Hydrator\Manager\HydratorManager;
use Scribe\Wonka\Component\Hydrator\Mapping\HydratorMapping;
use Scribe\Wonka\Console\OutBuffer;
use Scribe\Wonka\Exception\LogicException;
use Scribe\Wonka\Exception\RuntimeException;
use Scribe\Wonka\Utility\Reflection\ClassReflectionAnalyser;
use Scribe\Wonka\Utility\ClassInfo;
use Scribe\WonkaBundle\Component\DependencyInjection\Container\ContainerAwareTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class AbstractFixture.
 */
abstract class AbstractFixture extends BaseAbstractFixture implements FixtureInterface
{
    use ContainerAwareTrait;

    /**
     * @var EntityManager
     */
    protected $manager;

    /**
     * Holds interpreted fixture data.
     *
     * @var FixtureMetadata
     */
    protected $metadata;

    /**
     * Resolves inter-fixture references.
     *
     * @var ReferenceResolverInterface
     */
    protected $referenceResolver;

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
     * Dynamically resolved namespace of fixture entity.
     *
     * @var bool|string
     */
    protected $entityNamespace = false;

    /**
     * @var null|bool
     */
    protected $skip;

    /**
     * @var array
     */
    protected $identLog = [];

    /**
     * @var int
     */
    protected $countPurge = 0;

    /**
     * @var int
     */
    protected $countUpdate = 0;

    /**
     * @var int
     */
    protected $countInsert = 0;

    /**
     * @var int
     */
    protected $countSkip = 0;

    /**
     * {@inherit-doc}.
     *
     * @return string
     */
    abstract public function getType();

    /**
     * {@inherit-doc}.
     *
     * @param string $regex
     */
    public function setFixtureFileSearchRegex($regex)
    {
        $this->fixtureSearchNameRegex = $regex;
    }

    /**
     * {@inherit-doc}.
     *
     * @throws RuntimeException
     *
     * @return $this
     */
    public function loadFixtureMetadata(FixturePathsInterface $paths)
    {
        try {
            $locator = new FixtureLocator();
            $locator->setPaths($paths);

            $loader = new FixtureLoaderResolver();
            $loader->assignLoaders($this->getFixtureFileLoaders());

            $metadata = new FixtureMetadata();
            $metadata
                ->setNameRegex($this->fixtureSearchNameRegex)
                ->setNameTemplate($this->fixtureNameTemplate)
                ->setHandler($this)
                ->setLocator($locator)
                ->setLoader($loader)
                ->load($this->container);

            $this->metadata = $metadata;
        } catch (\Exception $exception) {
            throw new RuntimeException('Unable to generate metadata for fixture (ORM Loader: %s)', null, $exception, get_class($this));
        }

        return $this;
    }

    /**
     * @param ObjectManager              $objectManager
     * @param ReferenceResolverInterface $referenceResolver
     */
    public function loadFixtureData(ObjectManager $objectManager, ReferenceResolverInterface $referenceResolver)
    {
        $this->referenceResolver = $referenceResolver;
        $this->load($objectManager);
    }

    /**
     * @internal
     *
     * @param ObjectManager $manager
     */
    public function load(ObjectManager $manager)
    {
        echo PHP_EOL;
        $this->manager = $manager;

        $this->checkVersions();

        if ($this->skip === true) {
            OutBuffer::stat('+y/i-runmode +y/b-[ended]+w/- previous error set mode to skip');
            echo PHP_EOL;

            return;
        }

        if ($this->metadata->isEmpty()) {
            OutBuffer::stat('+y/i-runmode +y/b-[ended]+w/- empty data set provided by fixture');
            echo PHP_EOL;

            return;
        }

        $shortDepNameList = function ($fullyQualifiedDependencies) {
            $dependencyList = [];

            foreach ($fullyQualifiedDependencies as $d) {
                $dependencyList[] = substr(preg_replace('{.+Load}', '', $d), 0, -4);
            }

            return implode(',', $dependencyList);
        };

        if (method_exists($this, 'getDependencies')) {
            OutBuffer::stat('+g/i-depends+g/b- [rdeps]+w/- ordered by dependencies=[ +w/i-'.($shortDepNameList($this->getDependencies())).' +w/-]');
        } elseif (method_exists($this, 'getOrder')) {
            OutBuffer::stat('+g/i-depends+g/b- [order]+w/- ordered by priority=[ +w/i-'.$this->getOrder().' +w/-]');
        }

        foreach (['prefer', 'fallback', 'failure'] as $attemptType) {
            try {
                list($persistMode, $cleanupMode) = $this->resolveRuntimeMode($attemptType);
                $this->performLoad($attemptType, $persistMode, $cleanupMode);
            } catch (StrategyException $e) {
                $this->performFailure($e->getMessage());
                continue;
            }

            break;
        }

        echo PHP_EOL;
    }

    protected function performFailure($cause)
    {
        OutBuffer::stat('+y/i-runmode +y/b-[warns]+w/- not importing=[ %s ]+w/- cause=[ %s ]', $this->getEntityFQCN(), $cause);

        return false;
    }

    /**
     * @throws \Exception
     */
    protected function performLoad($for, $persistMode, $cleanupMode)
    {
        $dataFixtures = $this->metadata->getData();
        $countFixtures = count($dataFixtures);

        if ($persistMode === FixtureMetadata::MODE_SKIP) {
            throw new StrategyException('intentional skip');
        }

        $this->performModePreLoad($persistMode);

        if ($persistMode === FixtureMetadata::MODE_SKIP) {
            throw new StrategyException('intentional skip');
        }

        OutBuffer::stat('+g/i-persist +g/b-[start]+w/- persisting fixtures to orm=[ +w/i-'.$countFixtures.' found +w/-]');

        $this->entityManagerFlushAndClean();

        foreach ($dataFixtures as $index => $data) {
            $entity = $this->getNewHydratedEntity($index, $data);

            if ($persistMode === FixtureMetadata::MODE_PURGE || $persistMode === FixtureMetadata::MODE_BLIND) {
                $this->loadAndPersistEntity($entity);
            } else {
                $this->loadAndMergeEntity($index, $entity);
            }

            $this->handleCannibalisticEntity($entity);
            $this->resolveEntityReferences($entity, $index, $data);

            if (($index % $this->insertFlushBatchSize) === 0) {
                $this->entityManagerFlushAndClean();
            }
        }

        $this->entityManagerFlushAndClean();

        $this->performCleanup($persistMode, $cleanupMode);

        OutBuffer::stat(
            '+g/i-persist +g/b-[ended]+w/- stats=[ +w/i-'.
            $this->countPurge.' purges +w/-|+w/i- '.$this->countUpdate.' updates +w/-|+w/i- '.
            $this->countInsert.' inserts +w/-|+w/i- '.$this->countSkip.
            ' skips +w/-]+w/- totals=+w/-[+w/b- '.
            ($this->countSkip + $this->countUpdate + $this->countInsert + $this->countPurge).' +w/i-of+w/b- '.$countFixtures.' +w/i-fixtures managed +w/-]'
        );
    }

    /**
     * @param string $persistMode
     * @param string $cleanupMode
     */
    protected function performCleanup($persistMode, $cleanupMode)
    {
        if ($persistMode === FixtureMetadata::MODE_PURGE) {
            OutBuffer::stat('+g/i-removal +g/b-[clean]+w/- skipping cleanup for mode=[+w/i- %s found +w/-]', FixtureMetadata::MODE_PURGE);

            return;
        }

        $identities = array_map(function ($identity) {
            return current($identity);
        }, $this->identLog);

        $entities = $this
            ->manager
            ->getRepository($this->getEntityFQCN())
            ->findAll();

        $toRemove = array_values(array_filter($entities, function (Entity $entity) use ($identities) {
            return (bool) (!in_array($entity->getIdentity(), $identities));
        }));

        OutBuffer::stat('+g/i-removal +g/b-[clean]+w/- remove stale items from orm=[+w/i- %d found +w/-]', count($toRemove));

        if (count($toRemove) === 0) {
            return;
        }

        $exceptions = [];

        foreach ($toRemove as $i => $entity) {
            try {
                $this->manager->remove($entity);

                $this->entityManagerFlushAndClean();
            } catch (\Exception $exception) {
                $exceptions[] = $exception;

                continue;
            }

            ++$this->countPurge;
        }

        $this->validateCleanup($exceptions);
    }

    /**
     * @param array $exceptions
     */
    protected function validateCleanup(array $exceptions)
    {
        if (count($exceptions) === 0) {
            return;
        }

        OutBuffer::stat('+g/i-removal +g/b-[start]+w/- cleaning completed with errors=[+w/i- %d failed +w/-]', count($exceptions));
    }

    /**
     * @return $this
     */
    protected function checkVersions()
    {
        list($yaml, $yamlValid, $data, $dataValid)
            = $this->metadata->getMetaAndDataVersionsValidated();

        try {
            if (!$yamlValid) {
                throw new LogicException('Metadata version is not compatible with fixture-defined version.');
            }

            if (!$dataValid) {
                throw new LogicException('Metadata version is not compatible with fixture-defined version.');
            }
        } catch (LogicException $exception) {
            OutBuffer::stat('+y/i-version+y/b- [check]+w/- metadata version required=[+w/i- %s +w/-] declared=[+w/i- %s +w/-]', $this->metadata->getMetaVersionRequired(), $yaml);
            OutBuffer::stat('+y/i-version+y/b- [check]+w/- object data version required=[+w/i- %s +w/-] declared=[+w/i- %s +w/-]', $this->metadata->getDataVersionRequired(), $data);

            $this->skip = true;
        }

        return $this;
    }

    /**
     * @param string|null $for
     *
     * @return bool
     */
    protected function resolveRuntimeMode($for = null)
    {
        $persistMode = $this->metadata->getMode();
        $cleanupMode = $this->metadata->getCleanupMode();
        $modes = ['persist' => $persistMode, 'cleanup' => $cleanupMode];
        $status = '+g/i-runmode+g/b- [start]+w/- using strategy=[ +w/i-%s +w/-] for=[ +w/i-%s +w/-] ';
        $normalized = [];

        foreach ($modes as $i => $s) {
            $tmp = [];
            foreach ($s as $type => $mode) {
                if ($for !== null && $for !== $type) {
                    continue;
                }

                switch ($mode) {
                    case FixtureMetadata::MODE_BLIND:
                    case FixtureMetadata::MODE_PURGE:
                    case FixtureMetadata::MODE_MERGE:
                    case FixtureMetadata::MODE_SKIP:
                        $tmp[$type] = $this->normalizeStrategy($i, $mode);
                        break;

                    default:
                        $tmp[$type] = $this->normalizeStrategy($i, FixtureMetadata::MODE_DEFAULT);
                        break;
                }
            }

            if ($for !== null) {
                $normalized[] = $tmp[$for];
            } else {
                $normalized[] = $tmp;
            }
        }

        $modesForString = function ($normalized) use ($modes) {
            $r = [];

            for ($i = 0; $i < count($normalized); ++$i) {
                if (is_array($normalized[$i])) {
                    $r[] = implode(':', array_values($normalized[$i]));
                } else {
                    $r[] = $normalized[$i];
                }
            }

            return $r;
        };

        $r = $modesForString($normalized);

        OutBuffer::stat($status,
            implode(',', $r),
            implode(',', (array) array_keys($modes))
        );

        return $normalized;
    }

    /**
     * @param string $for
     * @param string $strategy
     *
     * @return mixed
     */
    protected function normalizeStrategy($for, $strategy)
    {
        return $strategy;
    }

    /**
     * @param string $mode
     *
     * @return bool
     */
    protected function performModePreLoad(&$mode)
    {
        $entityFqcn = $this->getEntityFQCN();

        list($entityMeta, $identityField, $identityNatural) = $this->resolveEntityMetadata();

        if ($mode === FixtureMetadata::MODE_MERGE && !$identityNatural) {
            OutBuffer::stat('+y/i-runmode +y/b-[merge]+w/- import strategy unavailable for non-natural entities');

            throw new StrategyException('invalid import mode for entity type');
        }

        if ($mode !== FixtureMetadata::MODE_PURGE) {
            return true;
        }

        try {
            $this->performEntityAssociationPurge($entityFqcn);
        } catch (RuntimeException $exception) {
            OutBuffer::stat('+y/i-runmode +y/b-[purge]+w/- import strategy unavailable as entity has associations "%s".', implode(',', $entityMeta->getAssociationNames()));
            $mode = FixtureMetadata::MODE_SKIP;

            return false;
        } catch (LogicException $exception) {
            //"strategy -> associations -> purge" set to true i
            OutBuffer::stat('+y/i-runmode +y/b-[purge]+w/- '.$exception->getMessage());
            OutBuffer::stat('+y/i-runmode +y/b-[purge]+w/- list of associations required for purge:', $exception->getMessage());
            foreach ($exception->getAttributes()['a'] as $i => $association) {
                OutBuffer::stat('+y/i-runmode +y/b-[purge]+w/-   - %s', $association);
            }

            $mode = FixtureMetadata::MODE_SKIP;

            return false;
        }

        return true;
    }

    /**
     * @param string $entityFQCN
     */
    protected function performEntityAssociationPurge($entityFQCN)
    {
        $metadata = $this->getClassMetadata($entityFQCN);

        if (count($metadata->getAssociationNames()) === 0) {
            return;
        }

        $associationMap = $this->getEntityAssociationMap($entityFQCN);

        if (count($associationMap) > 1 && !$this->metadata->isAssociationPurgeAllowed()) {
            throw LogicException::create()
                ->setMessage('refusing to purge discovered associations=[+w/i- %d found +w/-] without explicit config', count($associationMap))
                ->setAttributes(['a' => $associationMap]);
        }

        foreach ($associationMap as $a) {
            if ($this->addRegisteredAssociationPurgedToLog($a)) {
                $this->performEntityPurge($a);
            }
        }
    }

    /**
     * @return string
     */
    protected function getRegisteredAssociationsPurgedLogFilePath()
    {
        $logDirectory = $this->container->getParameter('kernel.logs_dir');

        return $logDirectory.DIRECTORY_SEPARATOR.'arthur-doctrine-fixtures-associations-purged.serial';
    }

    /**
     * @param $a
     *
     * @return bool
     */
    protected function addRegisteredAssociationPurgedToLog($a)
    {
        $logFilePath = $this->getRegisteredAssociationsPurgedLogFilePath();
        $registrar = new PurgedEntityRegistrar();

        if (file_exists($logFilePath)) {
            $registrar = unserialize(file_get_contents($logFilePath));
        }

        if ($registrar->contains($a)) {
            return false;
        }

        $registrar->add($a);

        file_put_contents($logFilePath, serialize($registrar));

        return true;
    }

    /**
     * @param string $entityFQCN
     *
     * @return string[]
     */
    protected function getEntityAssociationMap($entityFQCN)
    {
        $metadata = $this
            ->manager
            ->getClassMetadata($entityFQCN);

        $map = [];
        $associations = $metadata->getAssociationNames();

        foreach ($associations as $a) {
            if ($metadata->isAssociationInverseSide($a)) {
                $map = array_merge($map, $this->getEntityAssociationMap($metadata->getAssociationTargetClass($a)));
            }

            $map = array_merge((array) $metadata->getAssociationTargetClass($a), $map);
        }

        $map = array_reverse(array_merge((array) $entityFQCN, $map));

        return array_reverse(array_unique($map));
    }

    /**
     * @param string $entityFQCN
     */
    protected function performEntityPurge($entityFQCN)
    {
        $toDelete = $this
            ->manager
            ->getRepository($entityFQCN)
            ->findAll();

        OutBuffer::stat('+g/b-preload [purge]+w/- truncating previous entities=[+w/i- %d found +w/-] for=[+w/i- %s +w/-] ', count($toDelete), ClassInfo::getClassName($entityFQCN));

        foreach ($toDelete as $d) {
            $this
                ->manager
                ->remove($d);

            ++$this->countPurge;
        }

        $this->entityManagerFlushAndClean();
    }

    /**
     * @param string $entityName
     *
     * @return \Doctrine\Common\Persistence\Mapping\ClassMetadata
     */
    protected function getClassMetadata($entityName)
    {
        return $this
            ->manager
            ->getClassMetadata($entityName);
    }

    /**
     * @return mixed[]
     */
    protected function resolveEntityMetadata()
    {
        $entityMeta = $this->getClassMetadata($this->getEntityFQCN());

        try {
            return [
                $entityMeta,
                $entityMeta->isIdentifierNatural(),
                $entityMeta->getSingleIdentifierFieldName(),
            ];
        } catch (\Exception $e) {
            throw new \RuntimeException('Could not get entity metadata/identity information.');
        }
    }

    /**
     * @return string
     */
    protected function getEntityFQCN()
    {
        $entityFQCN = $this->entityNamespace;

        if (!$this->entityNamespace) {
            $this->entityNamespace = $this->metadata->getEntityFQCN();
        }

        if ($entityFQCN !== $this->entityNamespace) {
            OutBuffer::stat('+g/i-reflect+g/b- [paths]+w/- entity fqcn=[+w/i- '.$this->entityNamespace.' +w/-]');
        }

        return $this->entityNamespace;
    }

    /**
     * @return $this
     */
    protected function entityManagerFlushAndClean()
    {
        $this->manager->flush();
        $this->manager->clear();

        return $this;
    }

    /**
     * @param \Scribe\Doctrine\ORM\Mapping\Entity|mixed $entity
     *
     * @throws \Exception
     *
     * @return $this
     */
    protected function loadAndPersistEntity($entity)
    {
        $this->manager->persist($entity);
        ++$this->countInsert;

        return $this;
    }

    /**
     * @param \Scribe\Doctrine\ORM\Mapping\Entity|mixed $entity
     * @param mixed                                     $index
     *
     * @throws \Exception
     *
     * @return $this
     */
    protected function loadAndMergeEntity($index, $entity)
    {
        try {
            $entityMetadata = $this->getClassMetadata($this->getEntityFQCN());

            $this->identLog[] = $identity = $entityMetadata->getIdentifierValues($entity);

            if (count($identity) > 0) {
                $identity = [key($identity) => current($identity)];
            } elseif (!$entity->hasIdentity()) {
                OutBuffer::stat('+y/b-preload +y/i-[warns]+w/- import could not begin for "%s:%d"',
                    basename($this->metadata->getName()), $index);
                OutBuffer::stat('+y/b-preload +y/i-[warns]+w/- import strategy "merge" unavailable due to failed identifier map resolution');
            }

            $repository = $this
                ->manager
                ->getRepository($this->getEntityFQCN());

            $entitySearched = $repository->findOneBy($identity);

            $this->manager->initializeObject($entitySearched);

            if ($entitySearched && !$entity->isEqualTo($entitySearched)) {
                $mapper = new HydratorManager(new HydratorMapping(true));
                $entity = $mapper->getMappedObject($entity, $entitySearched);
                $this->manager->remove($entitySearched);
                $this->manager->merge($entity);
                $this->manager->persist($entity);
                $this->manager->flush();
                $this->manager->clear();
                ++$this->countUpdate;

                return $this;
            } elseif ($entitySearched && $entity->isEqualTo($entitySearched)) {
                $entity = $entitySearched;
                ++$this->countSkip;

                return $this;
            }

            $this->loadAndPersistEntity($entity);
        } catch (\Exception $e) {
            throw $e;
        }

        return $this;
    }

    /**
     * @param Entity $entity
     *
     * @return $this
     */
    protected function handleCannibalisticEntity(Entity $entity)
    {
        if ($this->metadata->isCannibal()) {
            $this->manager->flush($entity);
            $this->manager->detach($entity);
        }

        return $this;
    }

    /**
     * @param Entity    $entity
     * @param int|mixed $index
     * @param array[]   $data
     *
     * @return $this
     */
    protected function resolveEntityReferences(Entity $entity, $index, $data)
    {
        if ($this->metadata->hasReferenceByIndexEnabled()) {
            $this->addReference($this->metadata->getName().':'.$index, $entity);
        }

        if ($this->metadata->hasColumnReferences()) {
            $referenceByColumnsSetConcat = function ($columns) use ($data) {
                array_walk($columns, function (&$c) use ($data) { $c = $data[$c]; });

                return implode(':', (array) $columns);
            };

            $referenceByColumnsSetRegister = function ($columns) use ($entity, $referenceByColumnsSetConcat) {
                $this->addReference($this->metadata->getName().':'.$referenceByColumnsSetConcat($columns), $entity);
            };

            array_map($referenceByColumnsSetRegister, $this->metadata->getColumnReferenceCollections());
        }

        return $this;
    }

    /**
     * @param int     $index
     * @param mixed[] $values
     *
     * @return Entity
     */
    protected function getNewHydratedEntity($index, $values)
    {
        $entity = $this->metadata->getEntityInstance();

        try {
            $this->hydrateEntity($entity, $values);

            return $entity;
        } catch (RuntimeException $exception) {
            throw new RuntimeException(
                'Could not get hydrated entity "%s" for fixture "%s" item "%s".', null, $exception,
                get_class($entity), $this->metadata->getName(), (string) $index
            );
        }
    }

    /**
     * @param Entity  $entity
     * @param mixed[] $values
     *
     * @return Entity
     */
    protected function hydrateEntity(Entity $entity, $values)
    {
        foreach ($values as $property => $value) {
            $data = $this->getFixtureDataValueResolved($property, $values);
            $this->hydrateEntityData($entity, $property, $data);
        }
    }

    /**
     * @param Entity $entity
     * @param $property
     * @param $data
     */
    protected function hydrateEntityData(Entity $entity, $property, $data)
    {
        $exceptions = [];
        $setterMethod = sprintf('set%s', ucfirst($property));
        $dataCollection = new ArrayCollection((array) $data);

        try {
            $this->hydrateEntityDataUsingSetterMethod($entity, $setterMethod, $data);

            return;
        } catch (\Exception $exception) {
            $exceptions[] = $exception;
        }

        try {
            $this->hydrateEntityDataUsingSetterMethod($entity, $setterMethod, $dataCollection);

            return;
        } catch (\Exception $exception) {
            $exceptions[] = $exception;
        }

        try {
            $this->hydrateEntityDataUsingPropertyReflection($entity, $property, $data);

            return;
        } catch (\Exception $exception) {
            $exceptions[] = $exception;
        }

        try {
            $this->hydrateEntityDataUsingPropertyReflection($entity, $property, $dataCollection);

            return;
        } catch (\Exception $exception) {
            $exceptions[] = $exception;
        }

        $this->validateEntityHydration($entity, $property, [$data, $dataCollection], $exceptions);
    }

    /**
     * @param Entity                    $entity
     * @param string                    $property
     * @param mixed[]|ArrayCollection[] $possibleData
     * @param \Exception[]              $exceptions
     *
     * @throws RuntimeException
     *
     * @return true
     */
    protected function validateEntityHydration(Entity $entity, $property, array $possibleData, array $exceptions)
    {
        if (count($exceptions) !== 0) {
            throw $this->getNewHydrationException($exceptions);
        }

        $data = $this->getEntityReflectionProperty($entity, $property)->getValue($entity);

        foreach ($possibleData as $possible) {
            if ($data === $possible) {
                return true;
            }
        }

        throw new RuntimeException(
            'Data was set without error but could not be verified for %s::%s', null, null,
            get_class($entity), $property
        );
    }

    /**
     * @param \Exception[] $exceptions
     *
     * @return RuntimeException
     */
    protected function getNewHydrationException(array $exceptions)
    {
        $errors = array_map(function (\Exception $exception) {
            return $exception->getMessage();
        }, $exceptions);

        return new RuntimeException('Could not hydrate entities: %s', null, null, (string) implode(' / ', $errors));
    }

    /**
     * @param Entity $entity
     * @param string $method
     * @param mixed  $data
     */
    protected function hydrateEntityDataUsingSetterMethod(Entity $entity, $method, $data)
    {
        if (!method_exists($entity, $method)) {
            throw new RuntimeException('Method "%s::%s" does not exist.', null, null, get_class($entity), $method);
        }

        $parameters = (array) $this->getEntityReflectionMethodParameters($entity, $method);

        if (count($parameters) === 0) {
            throw new RuntimeException('Method "%s::%s" does not accept any parameters for "%s".', null, null, get_class($entity), $method);
        }

        $firstParameter = current($parameters);

        if (!method_exists($firstParameter, 'getType')) {
            throw new RuntimeException('Unsuported parameter reflection version.');
        }

        if (null !== ($parameterType = current($parameters)->getType())) {
            $this->validateParameterType($entity, $method, $parameterType, $data);
        }

        try {
            $entity->{$method}($data);
        } catch (\Exception $e) {
            throw new RuntimeException('Could not set data using method "%s::%s"', null, null, get_class($entity), $method);
        }
    }

    protected function validateParameterType(Entity $entity, $method, \ReflectionType $type, $data)
    {
        $typeString = (string) $type;
        $typeMethod = 'is_'.$typeString;

        if (!function_exists($typeMethod)) {
            $typeClass = $typeString;
            $typeString = 'object';
            $typeMethod = 'is_object';
        }

        if (true === $typeMethod($data)) {
            return;
        }

        throw new RuntimeException(
            'Method "%s::%s" expects "%s" but got "%s" for fixture identity "%s".', null, null,
            get_class($entity), $method,
            (isset($typeClass) ? $typeClass : $typeString),
            (gettype($data).(is_object($data) ? sprintf('" type "%s', get_class($data)) : '')),
            ($entity->hasIdentity() ? $entity->getIdentity() : spl_object_hash($entity))
        );
    }

    /**
     * @param Entity $entity
     * @param string $property
     * @param mixed  $data
     */
    protected function hydrateEntityDataUsingPropertyReflection(Entity $entity, $property, $data)
    {
        try {
            $this
                ->getEntityReflectionProperty($entity, $property)
                ->setValue($entity, $data);
        } catch (\Exception $exception) {
            throw new RuntimeException('Could not set data using %s::%s.', null, $exception, $property, get_class($entity));
        }
    }

    /**
     * @param Entity $entity
     * @param string $property
     *
     * @return \ReflectionProperty
     */
    protected function getEntityReflectionProperty(Entity $entity, $property)
    {
        $entityReflection = new ClassReflectionAnalyser(new \ReflectionClass($entity));

        return $entityReflection->setPropertyPublic($property);
    }

    /**
     * @param Entity $entity
     * @param string $method
     *
     * @return \ReflectionMethod
     */
    protected function getEntityReflectionMethod(Entity $entity, $method)
    {
        $entityReflection = new ClassReflectionAnalyser(new \ReflectionClass($entity));

        return $entityReflection->setMethodPublic($method);
    }

    /**
     * @param Entity $entity
     * @param string $method
     *
     * @return \ReflectionParameter[]
     */
    protected function getEntityReflectionMethodParameters(Entity $entity, $method)
    {
        $methodReflection = $this->getEntityReflectionMethod($entity, $method);

        return $methodReflection->getParameters();
    }

    /**
     * @param string     $property
     * @param array|null $values
     *
     * @return array|mixed
     */
    protected function getFixtureDataValueResolved($property, array $values = null)
    {
        if (is_array($values[$property])) {
            return (array) array_map([$this, 'resolveFixtureDataValue'], $values[$property]);
        }

        return $this->resolveFixtureDataValue($values[$property]);
    }

    /**
     * @param mixed $value
     *
     * @return mixed
     */
    protected function resolveFixtureDataValue($value)
    {
        $resolver = new ReferenceResolver();

        if (null === ($type = $resolver->typeOf($value)) || null === ($resolution = $resolver->resolve($value, $type))) {
            return $value;
        }

        switch($type) {
            case ReferenceResolver::REF_TINT_SARR:
                return $this->getHydrationValueUsingInternalRefLookup($resolution['name'], $resolution['args']);

            case ReferenceResolver::REF_TSQL_SARR:
                throw new LogicException('Not implemented: '.ReferenceResolver::REF_TSQL_SARR);
        }

        return $value;

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
     * @param string   $type
     * @param string[] $distinct
     *
     * @return mixed|null
     */
    protected function getHydrationValueUsingInternalRefLookup($type, array $distinct)
    {
        return $this->getReference($type.':'.implode(':', $distinct));
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
        $repo = $this->metadata->getRepositoryInstance();
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
