<?php

namespace v2\Exceptions;

class EmptyResellerIdException extends \Exception
{
    protected $message = 'Empty resellerId';
}