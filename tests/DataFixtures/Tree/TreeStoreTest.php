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

use Scribe\Arthur\DoctrineFixturesBundle\DataFixtures\Tree\TreeStore;
use Scribe\Wonka\Utility\UnitTest\WonkaTestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Class TreeStoreTest.
 */
class TreeStoreTest extends WonkaTestCase
{
    /**
     * @var array[]
     */
    protected $data;

    public function setUp()
    {
        $this->data = Yaml::parse(file_get_contents('./tests/fixtures/BasicTree.yml'));

        parent::setUp();
    }

    public function testGettingKeys()
    {
        $t = TreeStore::create($this->data, 'Role');

        static::assertEquals('1.0.0', $t->get('version', 'structure'));
        static::assertEquals('ROLE_ROOT', $t->get('data', 1, 'name'));
        static::assertNull($t->get('does', 'not', 'exist'));
    }
}

/* EOF */
