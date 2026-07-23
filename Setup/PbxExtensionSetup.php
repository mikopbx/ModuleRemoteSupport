<?php

declare(strict_types=1);

namespace Modules\ModuleRemoteSupport\Setup;

use MikoPBX\Common\Models\PbxSettings;
use MikoPBX\Modules\Setup\PbxExtensionSetupBase;
use Modules\ModuleRemoteSupport\Lib\ModuleLifecycleManager;
use Modules\ModuleRemoteSupport\Lib\SessionStatus;
use Modules\ModuleRemoteSupport\Models\RemoteSupportSession;
use RuntimeException;
use Throwable;

final class PbxExtensionSetup extends PbxExtensionSetupBase
{
    public function installDB(): bool
    {
        if (!parent::installDB()) {
            return false;
        }

        $session = RemoteSupportSession::findFirstById(1);
        if (!$session instanceof RemoteSupportSession) {
            $session = new RemoteSupportSession();
            $session->id = 1;
            $session->status = SessionStatus::OFF->value;
            if (!$session->save()) {
                $this->messages[] = implode(', ', $session->getMessages());

                return false;
            }
        }

        return true;
    }

    public function addToSidebar(): bool
    {
        $menuSettings = PbxSettings::findFirstByKey(
            'AdditionalMenuItemModuleRemoteSupport',
        );
        if (!$menuSettings instanceof PbxSettings) {
            $menuSettings = new PbxSettings();
            $menuSettings->key = 'AdditionalMenuItemModuleRemoteSupport';
        }

        $menuSettings->value = json_encode(
            [
                'uniqid' => 'ModuleRemoteSupport',
                'group' => 'modules',
                'iconClass' => 'life ring outline',
                'caption' => 'AdditionalMenuItemModuleRemoteSupport',
                'showAtSidebar' => true,
            ],
            JSON_THROW_ON_ERROR,
        );

        return $menuSettings->save();
    }

    public function unInstallDB(bool $keepSettings = false): bool
    {
        try {
            $this->lifecycleManager()->cleanupBeforeUninstall();
        } catch (Throwable) {
            $this->messages[] = 'Remote support access could not be revoked';

            return false;
        }

        if (!$keepSettings) {
            $session = RemoteSupportSession::findFirstById(1);
            if ($session instanceof RemoteSupportSession && !$session->delete()) {
                $this->messages[] = implode(', ', $session->getMessages());

                return false;
            }
        }

        $menuSettings = PbxSettings::findFirstByKey(
            'AdditionalMenuItemModuleRemoteSupport',
        );
        if ($menuSettings instanceof PbxSettings && !$menuSettings->delete()) {
            throw new RuntimeException('Unable to remove remote support menu entry');
        }

        return parent::unInstallDB($keepSettings);
    }

    protected function lifecycleManager(): ModuleLifecycleManager
    {
        return new ModuleLifecycleManager();
    }
}
