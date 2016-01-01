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

use Doctrine\Common\DataFixtures\DependentFixtureInterface;

/**
 * Class YamlDependentFixture.
 */
class YamlDependentFixture extends YamlFixture implements DependentFixtureInterface
{
    /**
     * @return string[]
     */
    public function getDependencies()
    {
        return $this->metadata->getAutoLoadedDependencies(get_called_class());
    }
}

/* EOF */
