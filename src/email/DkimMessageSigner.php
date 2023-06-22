<?php

declare( strict_types = 1 );

namespace Gzqsts\Core\email;

use Symfony\Component\Mime\Crypto\DkimSigner;
use Symfony\Component\Mime\Message;

class DkimMessageSigner implements MessageSignerInterface
{
    private DkimSigner $dkimSigner;

    public function __construct(DkimSigner $dkimSigner)
    {
        $this->dkimSigner = $dkimSigner;
    }

    public function sign(Message $message,array $options = []): Message
    {
        return $this->dkimSigner->sign( $message,$options );
    }
}
