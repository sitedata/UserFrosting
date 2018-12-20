<?php
/**
 * UserFrosting (http://www.userfrosting.com)
 *
 * @link      https://github.com/userfrosting/UserFrosting
 * @license   https://github.com/userfrosting/UserFrosting/blob/master/licenses/UserFrosting.md (MIT License)
 */

namespace UserFrosting\System\Bakery\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use UserFrosting\System\Bakery\BaseCommand;

/**
 * Assets builder CLI Tools.
 * Wrapper for npm/node commands
 *
 * @author Alex Weissman (https://alexanderweissman.com)
 */
class BuildAssets extends BaseCommand
{
    /**
     * @var string Path to the build/ directory
     */
    protected $buildPath;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('build-assets')
             ->setDescription('Build the assets using node and npm')
             ->setHelp('The build directory contains the scripts and configuration files required to download Javascript, CSS, and other assets used by UserFrosting. This command will install Gulp, Bower, and several other required npm packages locally. With <info>npm</info> set up with all of its required packages, it can be use it to automatically download and install the assets in the correct directories. For more info, see <comment>https://learn.userfrosting.com/basics/installation</comment>')
             ->addOption('compile', 'c', InputOption::VALUE_NONE, 'Compile the assets and asset bundles for production environment')
             ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force assets compilation by deleting cached data and installed assets before proceeding');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Display header,
        $this->io->title("UserFrosting's Assets Builder");

        // Set $path
        $this->buildPath = \UserFrosting\ROOT_DIR . \UserFrosting\DS . \UserFrosting\BUILD_DIR_NAME;

        // Delete cached data is requested
        if ($input->getOption('force')) {
            $this->clean();
        }

        // Perform tasks
        $this->npmInstall($input->getOption('force'));
        $this->assetsInstall();

        // Compile if requested
        if ($input->getOption('compile') || $this->isProduction()) {
            $this->buildAssets();
        }

        // If all went well and there's no fatal errors, we are successful
        $this->io->success('Assets install looks successful, check output for specifics');
    }

    /**
     * Install npm package
     * 
     * @param bool $force Force `npm install` to be run, ignoring evidence of a previous run.
     */
    protected function npmInstall($force)
    {
        $this->io->section('<info>Installing npm dependencies</info>');

        // Skip install if lockfile is newer
        if (!$force || filemtime("package.json") > filemtime("package-lock.json") + 1000) {
            $this->io->writeln('> <comment>Skipping npm install which has been previously run</comment>');
            return;
        }

        $this->io->writeln('> <comment>npm install</comment>');

        // Temporarily change the working directory so we can install npm dependencies
        $wd = getcwd();
        chdir($this->buildPath);
        $exitCode = 0;
        passthru('npm install', $exitCode);
        chdir($wd);

        if ($exitCode !== 0) {
            $this->io->error('npm dependency installation has failed');
            exit(1);
        }
    }

    /**
     * Perform UF Assets installation
     */
    protected function assetsInstall()
    {
        $this->io->section('Installing frontend vendor assets');
        $this->io->writeln('> <comment>npm run uf-assets-install</comment>');

        // Temporarily change the working directory (more reliable than --prefix npm switch)
        $wd = getcwd();
        chdir($this->buildPath);
        $exitCode = 0;
        passthru('npm run uf-assets-install', $exitCode);
        chdir($wd);

        if ($exitCode !== 0) {
            $this->io->error('assets installation has failed');
            exit(1);
        }

        return $exitCode;
    }

    /**
     * Build the production bundle.
     */
    protected function buildAssets()
    {
        $this->io->section('Building assets for production');

        // Temporarily change the working directory (more reliable than --prefix npm switch)
        $wd = getcwd();
        chdir($this->buildPath);

        $exitCode = 0;

        $this->io->writeln('> <comment>npm run uf-bundle</comment>');
        passthru('npm run uf-bundle');

        if ($exitCode !== 0) {
            $this->io->error('bundling has failed');
            exit(1);
        }

        chdir($wd);
    }

    /**
     * Run the `uf-clean` command to delete installed assets, delete compiled
     * bundle config file and delete compiled assets
     */
    protected function clean()
    {
        $this->io->section('Cleaning cached data');
        $this->io->writeln('> <comment>npm run uf-clean</comment>');

        // Temporarily change the working directory (more reliable than --prefix npm switch)
        $wd = getcwd();
        chdir($this->buildPath);
        $exitCode = 0;
        passthru('npm run uf-clean', $exitCode);
        chdir($wd);

        if ($exitCode !== 0) {
            $this->io->error('Failed to clean cached data');
            exit(1);
        }
    }
}
