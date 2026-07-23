<?php

declare(strict_types=1);

namespace Modules\ModuleRemoteSupport\App\Controllers;

use MikoPBX\AdminCabinet\Controllers\BaseController;
use MikoPBX\Modules\PbxExtensionUtils;
use Modules\ModuleRemoteSupport\App\Providers\AssetProvider;

class RemoteSupportBaseController extends BaseController
{
    protected const string MODULE_UNIQUE_ID = 'ModuleRemoteSupport';

    protected string $moduleDir;

    public function initialize(): void
    {
        $this->moduleDir = PbxExtensionUtils::getModuleDir(self::MODULE_UNIQUE_ID);
        $this->view->setVar(
            'logoImagePath',
            $this->url->get()
                . 'assets/img/cache/'
                . self::MODULE_UNIQUE_ID
                . '/logo.svg',
        );
        $this->view->setVar('submitMode', null);
        parent::initialize();
    }

    protected function addModuleAssets(): void
    {
        AssetProvider::addCss($this->assets);
        AssetProvider::addJs($this->assets);
    }
}
