<?php

namespace hiapi\gogetssl\vendor;

class GoGetSSLAuthException extends \Exception
{
    public function __construct() {
        parent::__construct('Please authorize first');
    }
}
