<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Glue\JsonApiConvention\Plugin;

use ReflectionClass;
use ReflectionMethod;
use Spryker\Glue\GlueApplication\Request\ApiRequestInterface;
use Spryker\Glue\GlueApplication\Resource\MissingResource;
use Spryker\Glue\GlueApplication\Resource\Resource;
use Spryker\Glue\GlueApplication\Resource\ResourceInterface;
use Spryker\Glue\GlueJsonApiExtension\Dependency\Plugin\ResourceRoutePluginInterface;
use Spryker\Shared\Kernel\Transfer\AbstractTransfer;

class RouteRequestMatcherPlugin
{
    /**
     * @var \Spryker\Glue\GlueJsonApiExtension\Dependency\Plugin\ResourceRoutePluginInterface[]
     */
    protected $resourceRouteCollection;

    /**
     * @param \Spryker\Glue\GlueJsonApiExtension\Dependency\Plugin\ResourceRoutePluginInterface[] $resourceRouteCollection
     */
    public function __construct(array $resourceRouteCollection)
    {
        $this->resourceRouteCollection = $resourceRouteCollection;
    }

    /**
     * @param \Spryker\Glue\GlueApplication\Request\ApiRequestInterface $apiRequest
     *
     * @return \Spryker\Glue\GlueApplication\Resource\ResourceInterface
     */
    public function matchRequest(ApiRequestInterface $apiRequest): ResourceInterface
    {
        foreach ($this->resourceRouteCollection as $resourceRoute) {
            if (!$this->isMethodMatching($resourceRoute, $apiRequest) || !$this->isPathMatching($resourceRoute, $apiRequest)) {
                continue;
            }

            return $this->executeApplicationRouting($resourceRoute, $apiRequest);
        }

        return new MissingResource(
            '404',
            sprintf('Route %s %s could not be found', $apiRequest->getMethod(), $apiRequest->getPath())
        );
    }

    /**
     * @param \Spryker\Glue\GlueJsonApiExtension\Dependency\Plugin\ResourceRoutePluginInterface $resourceRoute
     * @param \Spryker\Glue\GlueApplication\Request\ApiRequestInterface $apiRequest
     *
     * @return bool
     */
    protected function isMethodMatching(ResourceRoutePluginInterface $resourceRoute, ApiRequestInterface $apiRequest): bool
    {
        return $resourceRoute->getMethod() === $apiRequest->getMethod();
    }

    /**
     * @param \Spryker\Glue\GlueJsonApiExtension\Dependency\Plugin\ResourceRoutePluginInterface $resourceRoute
     * @param \Spryker\Glue\GlueApplication\Request\ApiRequestInterface $apiRequest
     *
     * @return bool
     */
    protected function isPathMatching(ResourceRoutePluginInterface $resourceRoute, ApiRequestInterface $apiRequest): bool
    {
        //@todo very simple implementation, which does not care about rest standards, sub-resources, versioning, etc. Only for PoC.
        return $resourceRoute->getPath() === $apiRequest->getPath();
    }

    /**
     * @param \Spryker\Glue\GlueJsonApiExtension\Dependency\Plugin\ResourceRoutePluginInterface $resourceRoute
     *
     * @return \Spryker\Glue\GlueApplication\Resource\Resource
     */
    protected function executeApplicationRouting(ResourceRoutePluginInterface $resourceRoute, ApiRequestInterface $apiRequest): Resource
    {
        if (class_exists($resourceRoute->getControllerClass()) === false) {
            //@todo routing exception
        }

        if (method_exists($resourceRoute->getControllerClass(), $resourceRoute->getAction())) {
            //@todo routing exception
        }

        $arguments = $this->getActionArguments($resourceRoute, $apiRequest);

        return new Resource(function () use ($resourceRoute, $arguments) {
            //@todo use a controller resolver here
            $controllerClass = $resourceRoute->getControllerClass();
            $controller = new $controllerClass();

            return call_user_func([
                $controller,
                $resourceRoute->getAction(),
            ], ...$arguments);
        });
    }

    /**
     * @param \Spryker\Glue\GlueJsonApiExtension\Dependency\Plugin\ResourceRoutePluginInterface $resourceRoute
     * @param \Spryker\Glue\GlueApplication\Request\ApiRequestInterface $apiRequest
     *
     * @return array
     */
    protected function getActionArguments(ResourceRoutePluginInterface $resourceRoute, ApiRequestInterface $apiRequest): array
    {
        $arguments = [];

        $methodReflection = new ReflectionMethod($resourceRoute->getControllerClass(), $resourceRoute->getAction());

        //@todo this will allow to have multiple transfers hydrated
        foreach ($methodReflection->getParameters() as $parameter) {
            if ($parameter->getClass() instanceof ReflectionClass && is_a($parameter->getClass(), AbstractTransfer::class)) {
                $transferClass = $parameter->getClass();
                /** @var \Spryker\Shared\Kernel\Transfer\AbstractTransfer $transfer */
                $transfer = new $transferClass();
                //@todo needs extraction of data.* to specific for JsonApi
                $transfer->fromArray(json_decode($apiRequest->getData()));
                $arguments[] = $transfer;
            }
        }

        $arguments[] = $apiRequest;

        return $arguments;
    }
}
