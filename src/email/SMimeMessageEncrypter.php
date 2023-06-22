<?php

declare( strict_types = 1 );

namespace Gzqsts\Core\email;

use Symfony\Component\Mime\Crypto\SMimeEncrypter;
use Symfony\Component\Mime\Message;

class SMimeMessageEncrypter implements MessageEncrypterInterface
{
    private SMimeEncrypter $encrypter;

    public function __construct(SMimeEncrypter $encrypter)
    {
        $this->encrypter = $encrypter;
    }

    public function encrypt(Message $message): Message
    {
        return $this->encrypter->encrypt( $message );
    }
}
