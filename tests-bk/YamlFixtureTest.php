<?php

/*
 * This file is part of the Scribe Arthur Doctrine Fixtures Library.
 *
 * (c) Scribe Inc. <oss@scr.be>
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Scribe\Doctrine\Tests\DataFixtures\Locator;

use Scribe\Doctrine\DataFixtures\Loader\FixtureLoaderInterface;
use Scribe\Doctrine\DataFixtures\Paths\FixturePaths;
use Scribe\Wonka\Utility\UnitTest\WonkaTestCase;
use Scribe\Doctrine\DataFixtures\YamlFixture;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class YamlFixtureTest.
 */
class YamlFixtureTest extends WonkaTestCase
{
    /**
     * @var YamlFixture
     */
    protected $f;

    /**
     * @var ContainerInterface
     */
    protected $c;

    public function setUp()
    {
        $this->c = $this->getMockBuilder('Symfony\Component\DependencyInjection\Container')
            ->setMethods(['getParameter'])
            ->getMock();

        $this->c->expects($this->any())
            ->method('getParameter')
            ->will($this->returnValue('./tests/fixtures/'));

        $this->f = $this->getMockBuilder('Scribe\Doctrine\DataFixtures\YamlFixture')
            ->setMethods(['getFixtureFileSearchPaths'])
            ->getMock();

        $this->f->expects($this->any())
            ->method('getFixtureFileSearchPaths')
            ->will($this->returnValue(FixturePaths::create()->cartesianProductFromPaths(['tests/fixtures'])));

        $reflectionF = new \ReflectionClass('Scribe\Doctrine\DataFixtures\YamlFixture');
        $reflectionContainer = $reflectionF->getProperty('container');
        $reflectionContainer->setAccessible(true);
        $reflectionContainer->setValue($this->f, $this->c);

        parent::setUp();
    }

    public function testNoMatchingFixtureFiles()
    {
        $this->setExpectedException('Scribe\Wonka\Exception\RuntimeException');
        $this->f->setFixtureFileSearchRegex('\b');
        $this->f->setContainer($this->c);
    }

    public function testMatchingFixtureFile()
    {
        $this->f->setFixtureFileSearchRegex('\bMock_([a-zA-Z]{1,})_');
        $this->f->setContainer($this->c);

        static::assertEquals(FixtureLoaderInterface::RESOURCE_YAML, $this->f->getType());
    }
}

/* EOF */
