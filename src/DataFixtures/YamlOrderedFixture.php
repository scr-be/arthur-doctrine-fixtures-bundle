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

use Doctrine\Common\DataFixtures\OrderedFixtureInterface;

/**
 * Class YamlOrderedFixture.
 */
class YamlOrderedFixture extends YamlFixture implements OrderedFixtureInterface
{
    /**
     * @return int
     */
    public function getOrder()
    {
        return (int) $this->metadata->getPriority();
    }
}

/* EOF */
