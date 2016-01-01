<?php

/*
 * This file is part of the Arthur Doctrine Fixture Bundle.
 *
 * Some of the code was originally distributed inside the Symfony framework
 * as well as with the Doctrine Fixtures bundle.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 * (c) Rob Frawley 2nd  <rmf@src.run>
 * (c) Scribe Inc       <scr@src.run>
 * (c) Doctrine Project
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Scribe\Arthur\DoctrineFixturesBundle\Command;

use Doctrine\Bundle\FixturesBundle\Command\LoadDataFixturesDoctrineCommand as DataFixturesCommand;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\DBAL\Sharding\PoolingShardConnection;
use Doctrine\ORM\EntityManager;
use Scribe\Arthur\DoctrineFixturesBundle\DataFixtures\Registry\FixtureRegistry;
use Scribe\Wonka\Exception\InvalidArgumentException;
use Symfony\Bridge\Doctrine\DataFixtures\ContainerAwareLoader as DataFixturesLoader;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Load data fixtures from bundles.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jonathan H. Wage <jonwage@gmail.com>
 */
class LoadDataFixturesDoctrineCommand extends DataFixturesCommand
{
    /**
     * @var RegistryInterface
     */
    protected $doctrine;

    /**
     * @var EntityManager
     */
    protected $em;

    /**
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('arthur:fixtures:load')
            ->setDefinition([
                new InputArgument('fixtures', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, 'The directory/files to load data fixtures from.'),
                new InputOption('table-transactions', null, InputOption::VALUE_NONE, 'Use one transaction per fixture file instead of a single transaction for all'),
            ])
            ->setDescription('Load YML data fixtures to your database.')
            ->setHelp(<<<EOT
The <info>%command.name%</info> command loads YML data fixtures from your bundles:

  <info>php %command.full_name%</info>

You can also optionally specify the path to fixtures by passing any number of arguments:

  <info>./app/console %command.name% /path/to/fixtures1 /path/to/fixtures2</info>

EOT
        );
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     * 
     * @return void
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->doctrine = $this
            ->getContainer()
            ->get('doctrine');

        $this->em = $this
            ->doctrine
            ->getManager();
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     * 
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        if (!($searchPaths = $input->getArgument('fixtures'))) {
            $searchPaths = $this->getSearchPaths();
        }

        if (count($searchPaths) === 0) {
            $io->error('No search paths could be determinened.');

            return;
        }

        $io->section('Determening ORM paths');
        $io->listing($searchPaths);

        $io->section('Registering fixtures');

        $registry = new FixtureRegistry($this->getContainer());

        foreach ($searchPaths as $path) {
            $registry->loadFromPath($path);
        }

        $fixtures = $registry->getFixtures();

        if (!$fixtures) {
            $io->error('Unable to find any fixtures within the following search paths.');
            $io->listing($searchPaths);

            return;
        }

        if ($input->isInteractive() && !$io->confirm('Do you want to begin fixture loading', false)) {
            return;
        }

        $purger = new ORMPurger($this->em);
        $purger->setPurgeMode(ORMPurger::PURGE_MODE_TRUNCATE);

        $executor = new ORMExecutor($this->em, $purger);
        $executor->setLogger(function ($message) use ($output) {
            $output->writeln(sprintf('  <comment>></comment> <info>%s</info>', $message));
        });

        $io->section('Importing fixtures');

        $executor->execute($fixtures, true);

        $io->success('Complete!');
        /*
        if ($input->isInteractive() && !$input->getOption('append')) {
            if (!$this->askConfirmation($input, $output, '<question>Careful, database will be purged. Do you want to continue y/N ?</question>', false)) {
                return;
            }
        }
        */
    }

    /**
     * @return string[]
     */
    protected function getSearchPaths()
    {
        $pathPostfix = $this
            ->getContainer()
            ->getParameter('s.arthur_doctrine_fixtures.fixture_search_path_postfix');

        $bundleWhiteList = $this
            ->getContainer()
            ->getParameter('s.arthur_doctrine_fixtures.bundles_enabled');

        $bundles = $this
            ->getContainer()
            ->get('kernel')
            ->getBundles();

        if (count($bundleWhiteList) > 0) {
            $bundles = array_filter($bundles, function (Bundle $b) use ($bundleWhiteList) {
                return in_array($b->getName(), $bundleWhiteList);
            });
        }

        $paths = array_map(function (Bundle $bundle) use ($pathPostfix) {
            return $bundle->getPath() . $pathPostfix;
        }, $bundles);

        $paths = array_filter($paths, function ($path) {
            return (bool) realpath($path);
        });

        return array_values($paths);
    }
}

/* EOF */
