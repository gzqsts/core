<?php

declare( strict_types = 1 );

namespace Gzqsts\Core\email;

use Symfony\Component\Mime\Crypto\SMimeSigner;
use Symfony\Component\Mime\Message;

class SMimeMessageSigner implements MessageSignerInterface
{
    private SMimeSigner $signer;

    public function __construct(SMimeSigner $signer)
    {
        $this->signer = $signer;
    }

    public function sign(Message $message,array $options = []): Message
    {
        return $this->signer->sign( $message );
    }
}
