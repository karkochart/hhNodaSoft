<?php

namespace v2\Services;

use v2\Exceptions\EmptyResellerIdException;
use v2\Models\Contractor;
use v2\Models\Employee;
use v2\Models\Status;
use v2\Requests\OperationRequest;

class TsReturnOperation extends ReferencesOperation
{
    public const int TYPE_NEW = 1;
    public const int TYPE_CHANGE = 2;

    /**
     * @throws \Exception
     */
    public function doOperation(): array
    {
        $data = (array)$this->getRequest('data');
        $result = [
            'notificationEmployeeByEmail' => false,
            'notificationClientByEmail' => false,
            'notificationClientBySms' => [
                'isSent' => false,
                'message' => '',
            ],
        ];
        try {
            (new OperationRequest())->validate($data);
        } catch (EmptyResellerIdException $e) {
            $result['notificationClientBySms']['message'] = 'Empty resellerId';
            return $result;
        }

        $resellerId = $data['resellerId'];
        $notificationType = (int)$data['notificationType'];

        $client = Contractor::getById((int)$data['clientId']);
        $cFullName = $client->getFullName();
        if (!$client->getFullName()) {
            $cFullName = $client->name;
        }
        $cr = Employee::getById((int)$data['creatorId']);
        $et = Employee::getById((int)$data['expertId']);


        $differences = '';
        if ($notificationType === self::TYPE_NEW) {
            $differences = __('NewPositionAdded', null, $resellerId);
        } elseif ($notificationType === self::TYPE_CHANGE && !empty($data['differences'])) {
            $differences = __('PositionStatusHasChanged', [
                'FROM' => Status::getName((int)$data['differences']['from']),
                'TO' => Status::getName((int)$data['differences']['to']),
            ], $resellerId);
        }

        $templateData = [
            'COMPLAINT_ID' => (int)$data['complaintId'],
            'COMPLAINT_NUMBER' => (string)$data['complaintNumber'],
            'CREATOR_ID' => (int)$data['creatorId'],
            'CREATOR_NAME' => $cr->getFullName(),
            'EXPERT_ID' => (int)$data['expertId'],
            'EXPERT_NAME' => $et->getFullName(),
            'CLIENT_ID' => (int)$data['clientId'],
            'CLIENT_NAME' => $cFullName,
            'CONSUMPTION_ID' => (int)$data['consumptionId'],
            'CONSUMPTION_NUMBER' => (string)$data['consumptionNumber'],
            'AGREEMENT_NUMBER' => (string)$data['agreementNumber'],
            'DATE' => (string)$data['date'],
            'DIFFERENCES' => $differences,
        ];

        // Если хоть одна переменная для шаблона не задана, то не отправляем уведомления
        foreach ($templateData as $key => $tempData) {
            if (!$tempData) {
                throw new \Exception("Template Data ({$key}) is empty!", 400);
            }
        }

        $emailFrom = Emails::getResellerEmailFrom($resellerId);
        // Получаем email сотрудников из настроек
        if ($emailFrom) {
            foreach (Emails::getEmailsByPermit($resellerId, 'tsGoodsReturn') ?? [] as $email) {
                MessagesClient::sendMessage(
                    [
                        0 => [ // MessageTypes::EMAIL
                            'emailFrom' => $emailFrom,
                            'emailTo' => $email,
                            'subject' => __('complaintEmployeeEmailSubject', $templateData, $resellerId),
                            'message' => __('complaintEmployeeEmailBody', $templateData, $resellerId),
                        ],
                    ],
                    $resellerId,
                    $client->id,
                    NotificationEvents::CHANGE_RETURN_STATUS
                );
                $result['notificationEmployeeByEmail'] = true;

            }
        }

        // Шлём клиентское уведомление, только если произошла смена статуса
        if ($notificationType === self::TYPE_CHANGE && !empty($data['differences']['to'])) {
            if ($emailFrom && !empty($client->email)) {
                MessagesClient::sendMessage(
                    [
                        0 => [ // MessageTypes::EMAIL
                            'emailFrom' => $emailFrom,
                            'emailTo' => $client->email,
                            'subject' => __('complaintClientEmailSubject', $templateData, $resellerId),
                            'message' => __('complaintClientEmailBody', $templateData, $resellerId),
                        ],
                    ],
                    $resellerId,
                    $client->id,
                    NotificationEvents::CHANGE_RETURN_STATUS,
                    (int)$data['differences']['to']
                );
                $result['notificationClientByEmail'] = true;
            }

            if (!empty($client->mobile)) {
                $res = NotificationManager::send(
                    $resellerId,
                    $client->id,
                    NotificationEvents::CHANGE_RETURN_STATUS,
                    (int)$data['differences']['to'],
                    $templateData,
                    $error
                );
                if ($res) {
                    $result['notificationClientBySms']['isSent'] = true;
                }
                if ($error) {
                    $result['notificationClientBySms']['message'] = $error;
                }
            }
        }

        return $result;
    }
}
