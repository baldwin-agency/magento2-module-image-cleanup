<?php

declare(strict_types=1);

namespace Baldwin\ImageCleanup\Console;

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

class ProgressIndicator
{
    /** @var ProgressBar */
    private $progressBar;

    /** @var OutputInterface */
    private $output;

    public function init(OutputInterface $output): void
    {
        $this->output = $output;
        $this->progressBar = new ProgressBar($output);
    }

    public function start(string $message): void
    {
        $this->progressBar->setFormat(" %message%:\n %current% [%bar%]");
        $this->progressBar->setMessage($message);
        $this->progressBar->start();
    }

    public function advance(): void
    {
        $this->progressBar->advance();
    }

    public function stop(): void
    {
        $this->progressBar->finish();
        $this->output->writeln('');
    }
}
