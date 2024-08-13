<?php

namespace v2\Services;

class Emails
{

    public static function getResellerEmailFrom()
    {
        return 'contractor@example.com';
    }

    public static function getEmailsByPermit($resellerId, $event) : array
    {
        // fakes the method
        return ['someemeil@example.com', 'someemeil2@example.com'];
    }
}