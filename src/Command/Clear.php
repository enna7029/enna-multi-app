<?php

namespace Enna\App\Command;

use Enna\Framework\Console\Command;
use Enna\Framework\Console\Input;
use Enna\Framework\Console\Input\Argument;
use Enna\Framework\Console\Input\Option;
use Enna\Framework\Console\Output;

class Clear extends Command
{
    protected function configure()
    {
        $this->setName('clear')
            ->addArgument('app', Argument::OPTIONAL, 'app name')
            ->addOption('cache', 'c', Option::VALUE_NONE, 'clear cache file')
            ->addOption('log', 'l', Option::VALUE_NONE, 'clear log file')
            ->addOption('dir', 'r', Option::VALUE_NONE, 'clear empty dir')
            ->setDescription('Clear runtime file');
    }

    protected function execute(Input $input, Output $output)
    {
        $app = $input->getArgument('app') ?: '';
        $runtimePath = $this->app->getRuntimePath() . ($app ? $app . DIRECTORY_SEPARATOR : '');

        if ($input->getOption('cache')) {
            $path = $runtimePath . 'cache';
        } elseif ($input->getOption('log')) {
            $path = $runtimePath . 'log';
        } else {
            $path = $runtimePath;
        }

        $rmdir = $input->getOption('dir') ? true : false;
        $this->clear(rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR, $rmdir);

        $output->writeln("<info>Clear Successed</info>");
    }
    protected function clear(string $path, bool $rmdir)
    {
        $files = is_dir($path) ? scandir($path) : [];

        foreach ($files as $file) {
            if ($file != '.' && $file != '..' && is_dir($path . $file)) {
                array_map('unlink', glob($path . $file . DIRECTORY_SEPARATOR . '*.*'));
                if (is_dir($path . $file)) {
                    rmdir($path . $file);
                }
            } elseif ($file != '.gitignore' && is_file($path . $file)) {
                unlink($path . $file);
            }
        }
    }
}