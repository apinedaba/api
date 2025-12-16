<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class BaseMail extends Mailable
{
    public function __construct(
        public string $subjectLine,
        public string $viewName,
        public array $params = []
    ) {
    }

    public function build()
    {
        return $this->subject($this->subjectLine)
            ->view($this->viewName)
            ->with($this->params);
    }
}
