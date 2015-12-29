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

use Scribe\Wonka\Console\ConsoleStringColorSwatches;
use Scribe\Wonka\Console\OutBuffer;
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
            OutBuffer::conf([OutBuffer::CFG_PRE => '  '.ConsoleStringColorSwatches::$colors['+y/-'].'> '.ConsoleStringColorSwatches::$colors['+R/-']]);
            OutBuffer::line('+p/i-pre-load+p/b- [resolve]+w/i- %s');
            OutBuffer::show($resource);

            OutBuffer::stat('+p/i-resolver+p/b- [reading]+w/- reading file content into memory=+w/-[ +w/i-'.(filesize($resource)).' bytes +w/-]');
            $contents = $this->loadFileContents($resource);
            OutBuffer::stat('+p/i-resolver+p/b- [parsing]+w/- loading file content to native type');
            $decoded = $this->loadUsingSymfonyYaml($contents);
            echo PHP_EOL;
        } catch (\Exception $exception) {
            throw new RuntimeException('Could not decode YAML.', $exception);
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
        if (false === ($decoded = Yaml::parse($contents, true, true))) {
            throw new \RuntimeException('Could not decode YAML using Symfony "Yaml::parse" function.');
        }

        return $decoded;
    }
}

/* EOF */
