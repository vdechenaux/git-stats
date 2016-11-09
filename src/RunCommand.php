<?php
declare(strict_types = 1);

namespace GitIterator;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;
use function jubianchi\async\runtime\await;
use function jubianchi\async\runtime\all;

/**
 * @author Matthieu Napoli <matthieu@mnapoli.fr>
 */
class RunCommand
{
    /**
     * @var Git
     */
    private $git;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var CommandRunner
     */
    private $commandRunner;

    public function __construct(Git $git, Filesystem $filesystem, CommandRunner $commandRunner)
    {
        $this->git = $git;
        $this->filesystem = $filesystem;
        $this->commandRunner = $commandRunner;
    }

    public function __invoke(bool $graphite = null, int $threads = null, OutputInterface $output)
    {
        $graphite = $graphite ?: false;
        $threads = $threads ?: 1;
        $repositoryDirectory = __DIR__ . '/../repository';
        $firstMirrorDirectory = $repositoryDirectory . '/0';

        // Load configuration
        if (!file_exists('conf.yml')) {
            throw new \Exception('Configuration file "conf.yml" missing');
        }
        $configuration = Yaml::parse(file_get_contents('conf.yml'));

        // Create base repository path
        if (!is_dir($repositoryDirectory)) {
            $this->filesystem->mkdir($repositoryDirectory);
        }

        // Check the existing directory
        if (is_dir($firstMirrorDirectory)) {
            $url = $this->git->getRemoteUrl($firstMirrorDirectory);
            if ($url !== $configuration['repository']) {
                $this->outputInfo('<comment>Existing directory "repository" found, removing it</comment>', $graphite, $output);
                $this->filesystem->remove($firstMirrorDirectory);
            }
        }

        // Clone the repository
        if (!is_dir($firstMirrorDirectory)) {
            $this->outputInfo(sprintf('<comment>Cloning %s in directory "repository"</comment>', $configuration['repository']), $graphite, $output);
            $this->git->clone($configuration['repository'], $firstMirrorDirectory);
        }

        // Mirror the repository for each threads
        for ($i = 1; $i < $threads; $i++) {
            $mirrorRepositoryDirectory = $repositoryDirectory . '/' . $i;

            if (is_dir($mirrorRepositoryDirectory)) {
                $url = $this->git->getRemoteUrl($mirrorRepositoryDirectory);
                if ($url === $configuration['repository']) {
                    continue;
                } else {
                    $this->outputInfo('<comment>Existing directory "repository" found, removing it</comment>', $graphite, $output);
                    $this->filesystem->remove($mirrorRepositoryDirectory);
                }
            }

            $this->filesystem->mirror($firstMirrorDirectory, $mirrorRepositoryDirectory);
        }

        $commits = $this->git->getCommitList($firstMirrorDirectory, 'master');
        $this->outputInfo(sprintf('<comment>Iterating through %d commits</comment>', count($commits)), $graphite, $output);

        $i = 0;
        $processes = [];
        foreach ($commits as $commit) {
            $currentThreadRepository = $repositoryDirectory . '/' . $i;
            $this->git->checkoutCommit($currentThreadRepository, $commit);

            $timestamp = $this->git->getCommitTimestamp($currentThreadRepository, $commit);
            foreach ($configuration['tasks'] as $taskId => $taskCommand) {
                $processCallback = function ($result) use($taskId, $commit, $graphite, $timestamp, $output) {
                    $this->outputInfo(sprintf(
                        '<info>%s: %s</info> on commit <info>%s</info> (%s)',
                        $taskId,
                        $result,
                        $commit,
                        date('j M Y', $timestamp)
                    ), $graphite, $output);
                    if ($graphite) {
                        $output->writeln("$taskId $result $timestamp");
                    }
                };

                $processes[] = $this->commandRunner->runInDirectoryAsync($currentThreadRepository, $taskCommand, $processCallback);

                if (++$i === $threads) {
                    await(
                        all(
                            ...$processes
                        )
                    );

                    $i = 0;
                    $processes = [];
                }
            }
        }

        if (!empty($processes)) {
            await(
                all(
                    ...$processes
                )
            );
        }
    }

    private function outputInfo(string $message, bool $graphite, OutputInterface $output)
    {
        if ($graphite) {
            return;
        }
        $output->writeln($message);
    }
}
