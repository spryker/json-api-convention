<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Glue\JsonApiConvention\Plugin;

use Generated\Shared\Transfer\ApiContextTransfer;
use Spryker\Glue\GlueApplicationExtension\Dependency\Plugin\ApiApplicationContextExpanderPluginInterface;
use Spryker\Glue\Kernel\AbstractPlugin;

class HostApiApplicationContextExpander extends AbstractPlugin implements ApiApplicationContextExpanderPluginInterface
{
    public const HOST = 'host';

    /**
     * @param ApiContextTransfer $apiApplicationContext
     *
     * @return ApiContextTransfer
     */
    public function expand(ApiContextTransfer $apiApplicationContext): ApiContextTransfer
    {
        if (isset($_SERVER['HTTP_HOST'])) {
            $apiApplicationContext->setHost($_SERVER['HTTP_HOST']);
        }

        return $apiApplicationContext;
    }
}
