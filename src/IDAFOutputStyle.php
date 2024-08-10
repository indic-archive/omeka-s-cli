<?php

namespace IDAF;

use Symfony\Component\Console\Style\SymfonyStyle;

class IDAFOutputStyle extends SymfonyStyle {
    public function taskDone($text = '') {
        $this->write($text);
        $this->write('<info> ✔</info>');
        $this->newLine();
    }
}
