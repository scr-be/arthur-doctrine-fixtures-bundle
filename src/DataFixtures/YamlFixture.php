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

use Scribe\Arthur\DoctrineFixturesBundle\DataFixtures\Loader\FixtureLoaderInterface;
use Scribe\Arthur\DoctrineFixturesBundle\DataFixtures\Loader\YamlFixtureLoader;

/**
 * Class YamlFixture.
 */
class YamlFixture extends AbstractFixture
{
    /**
     * @return string
     */
    public function getType()
    {
        return FixtureLoaderInterface::RESOURCE_YAML;
    }

    /**
     * {@inherit-doc}.
     *
     * @return Loader\FixtureLoaderInterface[]
     */
    public function getFixtureFileLoaders()
    {
        return [new YamlFixtureLoader()];
    }
}

/* EOF */
