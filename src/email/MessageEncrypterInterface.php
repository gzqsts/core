<?php

declare( strict_types = 1 );

namespace Gzqsts\Core\email;

use Symfony\Component\Mime\Message as Message;

interface MessageEncrypterInterface
{
    public function encrypt(Message $message): Message;
}
