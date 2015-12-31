<?php

/*
 * This file is part of the Wonka Bundle.
 *
 * (c) Scribe Inc.     <scr@src.run>
 * (c) Rob Frawley 2nd <rmf@src.run>
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Scribe\Arthur\DoctrineFixturesBundle\Tests;

use PHPUnit_Framework_TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Scribe\Wonka\Utility\UnitTest\WonkaTestCase;

/**
 * Class ScribeArthurDoctrineFixturesBundleTest.
 */
class ScribeArthurDoctrineFixturesBundleTest extends WonkaTestCase
{
    /**
     * @var \AppKernel
     */
    public static $kernel;

    public function setUp()
    {
        $kernel = new \AppKernel('test', true);
        $kernel->boot();

        self::$kernel = $kernel;
    }

    public function tearDown()
    {
        self::$kernel->shutdown();
    }

    public function test_kernel_build_container()
    {
        static::assertInstanceOf('Symfony\Component\DependencyInjection\ContainerInterface', self::$kernel->getContainer());
    }
}

/* EOF */
