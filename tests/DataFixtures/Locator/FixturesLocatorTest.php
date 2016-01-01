<?php

/*
 * This file is part of the Scribe Arthur Doctrine Fixtures Library.
 *
 * (c) Scribe Inc. <oss@scr.be>
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Scribe\Arthur\DoctrineFixturesBundle\Tests\DataFixtures\Locator;

use Scribe\Arthur\DoctrineFixturesBundle\DataFixtures\Locator\FixtureLocator;
use Scribe\Arthur\DoctrineFixturesBundle\DataFixtures\Paths\FixturePaths;
use Scribe\Wonka\Utility\UnitTest\WonkaTestCase;

/**
 * Class FixturesLocatorTest.
 */
class FixturesLocatorTest extends WonkaTestCase
{
    public function testAllInvalidPaths()
    {
        $fakePaths = [
            0 => 'one',
            1 => 'two',
            2 => 'three',
            3 => 'four.1',
            4 => 'four.2',
            5 => 'five.2',
            6 => 'six',
            7 => 'seven',
        ];

        $locator = new FixtureLocator();

        static::assertEquals([], $locator->locate('FixtureLocator.php'));
        $locator->setPaths(FixturePaths::create(...$fakePaths));
        static::assertEquals([], $locator->locate(''));
    }

    public function testValidAndInvalidPaths()
    {
        $input = [
            'bad/bad/path',
            'bin',
            'src',
            'invalid-path',
            'tests/DataFixtures/Locator',
        ];

        $expected = [
            'bin',
            'src',
            'tests/DataFixtures/Locator',
        ];

        $locator = new FixtureLocator($input);

        static::assertEquals([], $locator->locate('FixtureLocator.php'));

        $locator->setPaths(FixturePaths::create(...$input));
        $resultingPaths = $locator->locateValidPaths(null, FixturePaths::create(...$input));

        foreach ($resultingPaths as $i => $p) {
            static::assertEquals($expected[$i], substr($p, strlen($p) - strlen($expected[$i])));
        }
    }
}

/* EOF */
