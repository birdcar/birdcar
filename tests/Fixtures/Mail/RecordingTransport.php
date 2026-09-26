<?php

namespace Tests\Fixtures\Mail;

use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

/**
 * Records sent messages without being an ArrayTransport, so invitation mailer-safety checks accept it.
 */
class RecordingTransport extends AbstractTransport
{
    /** @var list<SentMessage> */
    public array $messages = [];

    protected function doSend(SentMessage $message): void
    {
        $this->messages[] = $message;
    }

    public function __toString(): string
    {
        return 'recording://';
    }
}
