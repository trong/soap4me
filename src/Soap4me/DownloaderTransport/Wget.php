<?php declare(strict_types=1);


namespace Soap4me\DownloaderTransport;


use League\Flysystem\FileNotFoundException;
use ptlis\ShellCommand\CommandBuilder;
use Soap4me\Episode;
use Soap4me\Exception\CurlException;


class Wget extends AbstractTransport
{
    public function download(Episode $episode)
    {
        $this->logger->info(sprintf(
            "download %s S%02dE%02d %s (%s) to %s",
            $episode->getShow(),
            $episode->getSeason(),
            $episode->getNumber(),
            $episode->getTitle(),
            $episode->getQuality(),
            $episode->getEpisodePath()
        ));

        // @todo check result?
        $this->createDir($episode);

        if ($this->filesystem->has($episode->getEpisodePath())) {
            try {
                $this->logger->debug(
                    sprintf(
                        "File %s already exists (%s)",
                        $episode->getEpisodePath(),
                        $this->filesystem->getSize($episode->getEpisodePath())
                    )
                );

                $ok = $this->filesystem->delete($episode->getEpisodePath());

                if (!$ok) {
                    $this->logger->debug(sprintf("can't remove file %s", $episode->getEpisodePath()));
                }
            } catch (FileNotFoundException $e) {
                $this->logger->debug(sprintf("I can't believe, this file is missing"));
            }
        }

        $result = $this->exec($episode);

        if (!$result) {
            try {
                $this->filesystem->delete($episode->getEpisodePath());
            } catch (FileNotFoundException $e) {
                // it's ok
            }

            return false;
        }

        return true;
    }

    /**
     * Create dir for episode
     *
     * @param Episode $episode
     *
     * @return bool
     */
    private function createDir(Episode $episode): bool
    {
        if (!$this->filesystem->has(dirname($episode->getSeasonPath()))) {
            return $this->filesystem->createDir(dirname($episode->getSeasonPath()));
        }

        return true;
    }

    private function exec(Episode $episode)
    {
        try {
            $url = $episode->getUrl();
        } catch (CurlException $e) {
            $this->logger->error(sprintf("Error when get url for episode :: %s", $e->getMessage()));
            return false;
        }

        $builder = new CommandBuilder();

        $command = $builder
            ->setCommand('wget')
            ->addArguments([
                '--no-check-certificate',
                '--random-wait',
                '--retry-connrefused',
                '--tries=10',
            ])
            ->addRawArgument(sprintf('--output-document="%s"', addslashes($episode->getEpisodePath())))
            ->addArgument($url)
            ->buildCommand();

        $this->logger->debug(sprintf("%s", $command));

        $result = $command->runSynchronous();

        if ($result->getExitCode() === 0) {
            $this->logger->debug($result->getStdOut());
            return true;
        }

        $this->logger->error($result->getStdErr());

        return false;
    }
}