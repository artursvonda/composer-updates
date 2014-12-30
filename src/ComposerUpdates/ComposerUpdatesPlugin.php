<?php

namespace ComposerUpdates;

use Composer\Composer;
use Composer\DependencyResolver\Pool;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Package\Link;
use Composer\Package\PackageInterface;
use Composer\Repository\PlatformRepository;
use Composer\Script\CommandEvent;
use Composer\Plugin\PluginInterface;

class ComposerUpdatesPlugin implements PluginInterface, EventSubscriberInterface
{
    /**
     * @var Composer
     */
    private $composer;

    /**
     * @var IOInterface
     */
    private $io;

    /**
     * {@inheritdoc}
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io       = $io;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            'check-updates'  => array(
                array('checkUpdates', 0),
            ),
        );
    }

    public function checkUpdates(CommandEvent $event)
    {
        $root        = $this->composer->getPackage();
        $output      = $event->getIO();
        $repoManager = $this->composer->getRepositoryManager();

        $output->write('');
        $output->write('Checking for available updates');

        $platformRepo = new PlatformRepository();

        $globalPool = new Pool($root->getMinimumStability(), $root->getStabilityFlags());
        $globalPool->addRepository($platformRepo);
        foreach ($repoManager->getRepositories() as $repo) {
            $globalPool->addRepository($repo);
        }

        $localPool = new Pool($root->getMinimumStability(), $root->getStabilityFlags());
        $localRepo = $repoManager->getLocalRepository();
        $localPool->addRepository($platformRepo);
        if ($localRepo) {
            $localPool->addRepository($repoManager->getLocalRepository());
        } else {
            foreach ($repoManager->getRepositories() as $repo) {
                $localPool->addRepository($repo);
            }
        }

        foreach ($this->getRequires() as $require) {
            $constraint      = $require->getConstraint();
            $packagesCurrent = $localPool->whatProvides($require->getTarget(), $constraint, true);
            if (!$packagesCurrent) {
                $output->write(sprintf('  - <info>%s</info> package not found', $require->getTarget()));
                continue;
            }
            $packageCurrent = $packagesCurrent ? $packagesCurrent[0] : null;

            $packagesConstrained = $globalPool->whatProvides($require->getTarget(), $constraint, true);
            if (!$packagesConstrained) {
                $output->write('Did not find global package (constrained) ' . $require->getTarget());
                continue;
            }
            /** @var PackageInterface $packageConstrained */
            $packageConstrained = end($packagesConstrained);

            $packagesLatest = $globalPool->whatProvides($require->getTarget(), null, true);
            if (!$packagesLatest) {
                $output->write('Did not find global package (un-constrained)' . $require->getTarget());
                continue;
            }
            /** @var PackageInterface $packageLatest */
            $packageLatest = end($packagesLatest);

            $toConstrained = version_compare($packageCurrent->getVersion(), $packageConstrained->getVersion());
            $toLatest      = version_compare($packageConstrained->getVersion(), $packageLatest->getVersion());

            if ($toConstrained > 0 && $toLatest > 0) {
                continue;
            }

            if (!$toConstrained && !$toLatest && $this->io->isVeryVerbose()) {
                $output->write(sprintf(
                    '  - <info>%s %s</info> is currently at max version (<comment>%s</comment>)',
                    $require->getTarget(),
                    $constraint->getPrettyString(),
                    $packageCurrent->getPrettyVersion()
                ));
            } else {
                if ($toConstrained < 0) {
                    $output->write(sprintf(
                        '  - <info>%s %s</info> has update available (<comment>%s</comment> => <comment>%s</comment>)',
                        $require->getTarget(),
                        $constraint->getPrettyString(),
                        $packageCurrent->getPrettyVersion(),
                        $packageConstrained->getPrettyVersion()
                    ));
                }

                if ($toLatest < 0) {
                    $output->write(sprintf(
                        '  - <info>%s</info> has upgrade available (<comment>%s</comment> => <comment>%s</comment>)',
                        $require->getTarget(),
                        $packageCurrent->getPrettyVersion(),
                        $packageLatest->getPrettyVersion()
                    ));
                }
            }
        }

        $output->write('');
    }

    /**
     * @return Link[]
     */
    private function getRequires()
    {
        return $this->composer->getPackage()->getRequires();
    }
}
