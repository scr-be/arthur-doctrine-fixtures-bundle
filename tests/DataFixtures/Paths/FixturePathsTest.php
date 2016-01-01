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

use Scribe\Arthur\DoctrineFixturesBundle\DataFixtures\Paths\FixturePaths;
use Scribe\Wonka\Utility\UnitTest\WonkaTestCase;

/**
 * Class FixturesPathsTest.
 */
class FixturePathsTest extends WonkaTestCase
{
    public function testCartesianProduct()
    {
        $a = new FixturePaths();
        static::assertFalse($a->hasPaths());
        $a->addPaths('/root/one/', '/root/two/');
        static::assertTrue($a->hasPaths());

        $b = new FixturePaths();
        static::assertFalse($b->hasPaths());
        $b->addPaths('some/random/path', 'another/random/path', 'one/last/one');
        static::assertTrue($b->hasPaths());

        $product = $a->cartesianProduct($b);
        $result = $product->getPaths();

        $expect = [
            '/root/one/some/random/path',
            '/root/two/some/random/path',
            '/root/one/another/random/path',
            '/root/two/another/random/path',
            '/root/one/one/last/one',
            '/root/two/one/last/one',
        ];

        static::assertTrue($product->hasPaths());
        static::assertEquals($expect, $result);
        static::assertCount(6, $product);
    }

    public function testCartesianProductFromPaths()
    {
        $cartesianStringPaths = [
            ['/../../../app', 'app', '', 'application'],
            ['config', 'cfg'],
            ['public/fixtures', 'proprietary/fixtures'],
        ];

        $expectedWithLeadingSlashes = [
            '/../../../app/config/public/fixtures',
            'app/config/public/fixtures',
            'config/public/fixtures',
            'application/config/public/fixtures',
            '/../../../app/cfg/public/fixtures',
            'app/cfg/public/fixtures',
            'cfg/public/fixtures',
            'application/cfg/public/fixtures',
            '/../../../app/config/proprietary/fixtures',
            'app/config/proprietary/fixtures',
            'config/proprietary/fixtures',
            'application/config/proprietary/fixtures',
            '/../../../app/cfg/proprietary/fixtures',
            'app/cfg/proprietary/fixtures',
            'cfg/proprietary/fixtures',
            'application/cfg/proprietary/fixtures',
        ];

        $expectedWithoutLeadingSlashes = [
            '../../../app/config/public/fixtures',
            'app/config/public/fixtures',
            'config/public/fixtures',
            'application/config/public/fixtures',
            '../../../app/cfg/public/fixtures',
            'app/cfg/public/fixtures',
            'cfg/public/fixtures',
            'application/cfg/public/fixtures',
            '../../../app/config/proprietary/fixtures',
            'app/config/proprietary/fixtures',
            'config/proprietary/fixtures',
            'application/config/proprietary/fixtures',
            '../../../app/cfg/proprietary/fixtures',
            'app/cfg/proprietary/fixtures',
            'cfg/proprietary/fixtures',
            'application/cfg/proprietary/fixtures',
        ];

        $result = FixturePaths::cartesianProductFromPaths(...$cartesianStringPaths);
        static::assertCount(16, $result);
        static::assertEquals($expectedWithLeadingSlashes, $result->getPaths());

        $result = FixturePaths::cartesianProductFromPaths(...$cartesianStringPaths)->removeLeadingSlashes()->getPaths();
        static::assertEquals($expectedWithoutLeadingSlashes, $result);
    }
}

/* EOF */
