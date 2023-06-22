<?php

declare( strict_types = 1 );

namespace Gzqsts\Core\email;

use Symfony\Component\Mime\Email;

interface MessageWrapperInterface
{
    public function getSymfonyEmail(): Email;
}
