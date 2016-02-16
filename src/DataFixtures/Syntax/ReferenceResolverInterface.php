<?php

/*
 * This file is part of the Scribe Arthur Doctrine Fixtures Library.
 *
 * (c) Scribe Inc. <oss@scr.be>
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Scribe\Arthur\DoctrineFixturesBundle\DataFixtures\Syntax;

/**
 * ReferenceResolverInterface.
 */
interface ReferenceResolverInterface
{
    /**
     * @param mixed $what
     *
     * @return bool
     */
    public function supports($what);

    /**
     * @param mixed $value
     *
     * @return null|string
     */
    public function typeOf($value);

    /**
     * @param mixed       $what
     * @param null|string $assertType
     *
     * @return null|string[]
     */
    public function resolve($what, $assertType = null);
}

/* EOF */
