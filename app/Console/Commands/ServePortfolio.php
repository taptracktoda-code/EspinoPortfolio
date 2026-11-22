<?php

namespace App\Console\Commands;

use Illuminate\Foundation\Console\ServeCommand;

class ServePortfolio extends ServeCommand
{
    protected function serverCommand(): array
    {
        $command = parent::serverCommand();

        // These settings must reach the PHP process that receives multipart requests.
        array_splice($command, 1, 0, ['-d', 'upload_max_filesize=10M', '-d', 'post_max_size=48M']);

        return $command;
    }
}
