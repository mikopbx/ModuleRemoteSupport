<?php

declare(strict_types=1);

namespace Modules\ModuleRemoteSupport\App\Controllers;

class ModuleRemoteSupportController extends RemoteSupportBaseController
{
    public function indexAction(): void
    {
        $this->addModuleAssets();
        $this->view->pick('ModuleRemoteSupport/index');
    }
}
