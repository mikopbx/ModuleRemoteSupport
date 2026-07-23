<?php

declare(strict_types=1);

namespace Modules\ModuleRemoteSupport\Lib\RestAPI\Session;

use MikoPBX\PBXCoreREST\Lib\Common\AbstractDataStructure;
use MikoPBX\PBXCoreREST\Lib\Common\OpenApiSchemaProvider;

class DataStructure extends AbstractDataStructure implements OpenApiSchemaProvider
{
    /**
     * @return array<string, mixed>
     */
    public static function getListItemSchema(): array
    {
        return self::getDetailSchema();
    }

    /**
     * @return array<string, mixed>
     */
    public static function getDetailSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => self::getParameterDefinitions()['response'],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function getRelatedSchemas(): array
    {
        return [];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function getParameterDefinitions(): array
    {
        return [
            'request' => [],
            'response' => [
                'state' => [
                    'type' => 'string',
                    'description' => 'rest_schema_session_state',
                    'enum' => ['off', 'starting', 'active', 'stopping', 'error'],
                    'readOnly' => true,
                ],
                'code' => [
                    'type' => 'string',
                    'description' => 'rest_schema_session_code',
                    'readOnly' => true,
                ],
                'startedAt' => [
                    'type' => 'integer',
                    'description' => 'rest_schema_session_startedAt',
                    'readOnly' => true,
                ],
                'expiresAt' => [
                    'type' => 'integer',
                    'description' => 'rest_schema_session_expiresAt',
                    'readOnly' => true,
                ],
                'errorCode' => [
                    'type' => 'string',
                    'description' => 'rest_schema_session_errorCode',
                    'readOnly' => true,
                ],
                'contacts' => [
                    'type' => 'array',
                    'description' => 'rest_schema_session_contacts',
                    'items' => [
                        'type' => 'object',
                    ],
                    'readOnly' => true,
                ],
                'supportSite' => [
                    'type' => 'string',
                    'format' => 'uri',
                    'description' => 'rest_schema_session_supportSite',
                    'readOnly' => true,
                ],
            ],
            'related' => [],
        ];
    }
}
