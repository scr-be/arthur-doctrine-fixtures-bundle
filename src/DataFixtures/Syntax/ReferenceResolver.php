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

use Scribe\Wonka\Exception\InvalidArgumentException;

/**
 * Class ReferenceResolver.
 */
class ReferenceResolver
{
    /**
     * @var string
     */
    const REF_TINT_SARR = 'resolveReferenceTypeInternalUsingArraySyntax';
    /**
     * @var string
     */
    const REF_TSQL_SARR = 'resolveReferenceTypeSqlQueryUsingArraySyntax';

    /**
     * @var array[]
     */
    protected $blindCheckTypes = [
        self::REF_TINT_SARR => ['?=', ']'],
        self::REF_TSQL_SARR => ['?~', ']'],
    ];

    /**
     * @param mixed $what
     *
     * @return bool
     */
    public function supports($what)
    {
        return is_string($what) && null !== $this->typeOf($what);
    }

    /**
     * @param mixed $value
     *
     * @return null|string
     */
    public function typeOf($value)
    {
        $types = array_filter($this->blindCheckTypes, function(array $checks) use ($value) {
            if (!isset($checks[0]) || !isset($checks[1])) {
                return false;
            }

            if (substr($value, 0, strlen($checks[0])) !==  $checks[0]) {
                return false;
            }

            return (bool) (substr($value, -strlen($checks[1]), strlen($checks[1])) === $checks[1]);
        });

        return count($types) === 1 ? key($types) : null;
    }

    /**
     * @param mixed       $what
     * @param null|string $assertType
     *
     * @return null|string[]
     */
    public function resolve($what, $assertType = null)
    {
        if (($type = $this->typeOf($what)) !== $assertType && null !== $assertType) {
            throw new InvalidArgumentException('Invalid type assertion provided.');
        }

        return method_exists($this, $type) ? $this->{$type}($what) : null;
    }

    /**
     * @param string $what
     *
     * @return null|string[]
     */
    protected function resolveReferenceTypeInternalUsingArraySyntax($what)
    {
        if (false === ($r = $this->matchOne($what, '^\?\=([^\[]+)((?:\[[^\]]+\])+)$', 2))) {
            return null;
        }

        list($name, $where) = $r;

        if (false === ($r = $this->matchAll($where, '(?:\[([^\]]+)\])'))) {
            return null;
        }

        return $this->resolveReturn($name, array_values($r), __FUNCTION__);
    }

    /**
     * @param string $what
     *
     * @return null|string[]
     */
    protected function resolveReferenceTypeSqlQueryUsingArraySyntax($what)
    {
        if (false === ($r = $this->matchOne($what, '^\?~([^\[]+)((?:(?:\[[^=\n]+)=(?:[^\n\]]+\]))+)$', 2))) {
            return null;
        }

        list($name, $where) = $r;

        if (false === ($r = $this->matchAll($where, '\[((?:[^=]+)\=(?:[^\]]+))\]'))) {
            return null;
        }

        $hash = [];
        for ($i = 0; $i < count($r); $i++) {
            list($k, $v) = explode('=', $r[$i]);
            $hash[$k] = $v;
        }

        return $this->resolveReturn($name, $hash, __FUNCTION__);
    }

    /**
     * @param string      $name
     * @param null|mixed  $args
     * @param null|string $type
     *
     * @return mixed[]
     */
    protected function resolveReturn($name, array $args = null, $type = null)
    {
        return [
            'name' => $name,
            'args' => $args !== null ? $args : null,
            'type' => $type !== null ? $type : null,
        ];
    }

    /**
     * @param string $string
     * @param string $pattern
     * @param int    $assertCount
     *
     * @return mixed[]|false
     */
    protected function matchOne($string, $pattern, $assertCount = null)
    {
        if (1 !== preg_match(sprintf('{%s}mUu', $pattern), $string, $matches)) {
            return false;
        }

        array_shift($matches);
        $matches = $this->matchResultValidate($matches);

        return $this->matchAssertCount($matches, $assertCount) ? $matches : false;
    }

    /**
     * @param string $string
     * @param string $pattern
     * @param int    $assertCount
     *
     * @return mixed[]|false
     */
    protected function matchAll($string, $pattern, $assertCount = null)
    {
        if (false === preg_match_all(sprintf('{%s}mUu', $pattern), $string, $matches)) {
            return false;
        }

        $matches = $this->matchResultValidate($matches[1]);

        return $this->matchAssertCount($matches, $assertCount) ? $matches : false;
    }

    /**
     * @param mixed[] $matches
     *
     * @return string[]
     */
    protected function matchResultValidate(array $matches)
    {
        return (array) array_values(array_filter($matches, function($value) {
            return is_string($value) && strlen($value) > 0;
        }));
    }

    /**
     * @param string[] $matches
     * @param int|null $count
     *
     * @return bool
     */
    protected function matchAssertCount($matches, $count = null)
    {
        return (bool) (null === $count || count($matches) === $count);
    }
}

/* EOF */
