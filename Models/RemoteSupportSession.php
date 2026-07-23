<?php

declare(strict_types=1);

namespace Modules\ModuleRemoteSupport\Models;

use MikoPBX\Modules\Models\ModulesModelsBase;

final class RemoteSupportSession extends ModulesModelsBase
{
    /**
     * @Primary
     * @Column(type="integer", nullable=false)
     */
    /** @var int */
    public $id = 1;

    /**
     * @Column(type="string", nullable=false)
     */
    public ?string $status = 'off';

    /**
     * @Column(type="string", nullable=true)
     */
    public ?string $session_id = '';

    /**
     * @Column(type="string", nullable=true)
     */
    public ?string $code = '';

    /**
     * @Column(type="integer", nullable=true)
     */
    public ?string $slot = '';

    /**
     * @Column(type="integer", nullable=true)
     */
    public ?string $tunnel_port = '';

    /**
     * @Column(type="integer", nullable=true)
     */
    public ?string $started_at = '';

    /**
     * @Column(type="integer", nullable=true)
     */
    public ?string $expires_at = '';

    /**
     * @Column(type="string", nullable=true)
     */
    public ?string $error_code = '';

    /**
     * @Column(type="integer", nullable=false)
     */
    public ?string $updated_at = '0';

    public function initialize(): void
    {
        $this->setSource('m_RemoteSupportSession');
        parent::initialize();
    }
}
