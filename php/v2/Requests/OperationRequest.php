<?php

namespace v2\Requests;

use v2\Exceptions\EmptyResellerIdException;
use v2\Models\Contractor;
use v2\Models\Employee;
use v2\Models\Seller;

class OperationRequest
{
    /**
     * @throws \Exception
     */
    public static function validate(array $data): void
    {
        if (!$data['resellerId']) {
            throw new EmptyResellerIdException();
        }

        if (!$data['notificationType']) {
            throw new \Exception('Empty notificationType', 400);
        }

        if (is_null($seller = Seller::getById((int)$data['resellerId']))) {
            throw new \Exception('Seller not found!', 400);
        }

        $client = Contractor::getById((int)$data['clientId']);
        if (is_null($client) || $client->type !== Contractor::TYPE_CUSTOMER || $seller->id !== $data['resellerId']) {
            throw new \Exception('ñlient not found!', 400);
        }

        if (is_null(Employee::getById((int)$data['creatorId']))) {
            throw new \Exception('Creator not found!', 400);
        }

        if (is_null(Employee::getById((int)$data['expertId']))) {
            throw new \Exception('Expert not found!', 400);
        }
    }
}