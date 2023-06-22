<?php

declare( strict_types = 1 );

namespace Gzqsts\Core\email;

use Symfony\Component\Mime\Message;

interface MessageSignerInterface
{
    public function sign(Message $message,array $options = []): Message;
}
