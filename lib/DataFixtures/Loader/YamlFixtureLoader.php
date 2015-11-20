<?php

/*
 * This file is part of the Scribe Arthur Doctrine Fixtures Library.
 *
 * (c) Scribe Inc. <oss@scr.be>
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Scribe\Doctrine\DataFixtures\Loader;

use Scribe\Wonka\Exception\RuntimeException;
use Symfony\Component\Yaml\Yaml;

/**
 * Class YamlFixtureLoader.
 */
class YamlFixtureLoader extends AbstractFixtureLoader
{
    /**
     * @param mixed      $resource
     * @param null|mixed $type
     *
     * @return bool
     */
    public function supports($resource, $type = null)
    {
        $type = $type ?: $this->getResourceType($resource);

        return (bool) ($type === 'yml' ?: false);
    }

    /**
     * @param mixed $resource
     * @param null  $type
     *
     * @return string
     */
    public function load($resource, $type = null)
    {
        try {
            echo "Reading in $resource...".PHP_EOL;
            $contents = $this->loadFileContents($resource);
            $decoded = $this->loadUsingSymfonyYaml($contents);

        } catch (\Exception $exception) {
            throw new RuntimeException('Could not decode YAML for %s.', null, $exception, $resource);
        }

        return $decoded;
    }

    /**
     * @param string $contents
     *
     * @return array
     */
    protected function loadUsingSymfonyYaml($contents)
    {
        if (false === ($decoded = Yaml::parse($contents, true, true, true))) {
            throw new \RuntimeException('Could not decode YAML using Symfony "Yaml::parse" function.');
        }

        return $decoded;
    }
}

/* EOF */
