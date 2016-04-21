<?php

namespace ComposerUpdates;

use Composer\Composer;
use Composer\DependencyResolver\Pool;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Package\Link;
use Composer\Package\PackageInterface;
use Composer\Plugin\PluginInterface;
use Composer\Repository\PlatformRepository;
use Composer\Script\Event;

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
        $this->io = $io;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            'check-updates' => array(
                array('checkUpdates', 0),
            ),
        );
    }

    public function checkUpdates(Event $event)
    {
        $root = $this->composer->getPackage();
        $output = $event->getIO();
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

        $output->write(str_repeat('-', 80));
        $this->writeRow($output, 'Package', 'Require', 'Current', 'Update', 'Latest', $color = false);
        $output->write(str_repeat('-', 80));

        foreach ($this->getRequires() as $require) {
            $constraint = $require->getConstraint();
            $packagesCurrent = $localPool->whatProvides($require->getTarget(), $constraint, $mustMatchName = true);
            if (!$packagesCurrent) {
                $output->write(sprintf('<fg=red>!!!</> <info>%s</info> package not found', $require->getTarget()));
                continue;
            }
            $packageCurrent = $packagesCurrent ? $packagesCurrent[0] : null;

            $packagesConstrained = $globalPool->whatProvides($require->getTarget(), $constraint, $mustMatchName = true);
            if (!$packagesConstrained) {
                $output->write(
                    sprintf(
                        '<fg=red>!!!</> <info>%s</info> global package not found (constrained)',
                        $require->getTarget()
                    )
                );
                continue;
            }

            $this->sortPackages($packagesConstrained);
            $packageConstrained = end($packagesConstrained);

            $packagesLatest = $globalPool->whatProvides($require->getTarget(), null, $mustMatchName = true);
            if (!$packagesLatest) {
                $output->write(
                    sprintf(
                        '<fg=red>!!!</> <info>%s</info> global package not found (un-constrained)',
                        $require->getTarget()
                    )
                );
                continue;
            }

            $this->sortPackages($packagesLatest);
            $packageLatest = end($packagesLatest);

            $requiredVersion = $constraint->getPrettyString();
            $currentVersion = $packageCurrent->getPrettyVersion();
            $constrainedVersion = $packageConstrained->getPrettyVersion();
            $latestVersion = $packageLatest->getPrettyVersion();

            if ($packageCurrent->isDev()) {
                $currentVersion = substr($packageCurrent->getDistReference(), 0, 10);
                $constrainedVersion = substr($packageConstrained->getDistReference(), 0, 10);
                $latestVersion = substr($packageLatest->getDistReference(), 0, 10);
            }

            $this->writeRow(
                $output,
                $require->getTarget(),
                $requiredVersion,
                $currentVersion,
                $constrainedVersion,
                $latestVersion
            );
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

    private function writeRow(IOInterface $output, $package, $required, $current, $update, $latest, $color = true)
    {
        $isLatest = $current === $latest;
        $isUpdated = $current === $update;
        $update = $color ? $this->wrapColor($update, $isUpdated ? 'green' : 'red') : $update;
        $latest = $color ? $this->wrapColor($latest, $isLatest ? 'green' : 'red') : $latest;

        $output->write(
            sprintf(
                '%-30s | %-10s | %-10s | %-10s | %-10s',
                substr($package, 0, 30),
                $required,
                $current,
                $update,
                $latest
            )
        );
    }

    private function wrapColor($text, $color)
    {
        return sprintf('<fg=%s>%-10s</>', $color, $text);
    }

    /**
     * @param PackageInterface[] $packages
     */
    private function sortPackages(&$packages)
    {
        usort(
            $packages,
            function (PackageInterface $a, PackageInterface $b) {
                return version_compare($a->getVersion(), $b->getVersion());
            }
        );
    }
}
