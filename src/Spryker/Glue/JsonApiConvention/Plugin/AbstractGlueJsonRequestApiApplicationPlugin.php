<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Glue\JsonApiConvention\Plugin;

use Spryker\Glue\GlueApplicationExtension\Dependency\Plugin\BootableApiApplicationInterface;
use Spryker\Glue\GlueApplicationExtension\Dependency\Plugin\RequestFlowApiApplicationPluginInterface;
use Spryker\Glue\GlueApplication\Request\ApiRequestInterface;
use Spryker\Glue\GlueApplication\Request\ApiRequestValidationResult;
use Spryker\Glue\GlueApplication\Resource\ResourceInterface;
use Spryker\Glue\GlueApplication\Response\ApiResponse;
use Spryker\Glue\GlueApplication\Response\ApiResponseInterface;
use Spryker\Glue\GlueApplication\Rest\JsonApi\RestResourceInterface;
use Spryker\Glue\GlueApplication\Rest\JsonApi\RestResponseInterface;
use Spryker\Glue\Kernel\AbstractPlugin;
use Symfony\Component\HttpFoundation\Request;

/**
 *
 */
abstract class AbstractGlueJsonRequestApiApplicationPlugin extends AbstractPlugin implements RequestFlowApiApplicationPluginInterface, BootableApiApplicationInterface
{
    /**
     * @return void
     */
    public function boot()
    {
        //would setup application plugins, e.g.: in memory session
    }

    public function buildRequest(ApiRequestInterface $apiRequest): ApiRequestInterface
    {
        //will be done by a stack of plugins which are specific to a certain protocol (e.g.: http)
        //and are part of a library (e.g.: GlueHttp)
        $httpRequest = Request::createFromGlobals();
        $apiRequest->setData($httpRequest->getContent());
        $apiRequest->setAuthentication($httpRequest->headers->get('Authorization', null));
        $apiRequest->setFormat($httpRequest->headers->get('Accept', 'json'));
        $version = '1.0';
        $versionMatches = [];
        $versionMatchResult = preg_match('/version=(\d+\.\d+)/', $httpRequest->headers->get('Accept', 'json'), $versionMatches);

        if ($versionMatchResult >= 1 && count($versionMatches) > 1) {
            $version = $versionMatches[1];
        }

        $apiRequest->setVersion($version);
        $apiRequest->addMeta('locale', $httpRequest->headers->get('Accept-Language', 'de_DE'));
        $apiRequest->setMethod($httpRequest->getMethod());
        $apiRequest->setPath($httpRequest->getRequestUri());

        return $apiRequest;
    }

    /**
     * @param ApiRequestInterface $request
     *
     * @return ResourceInterface
     */
    public function route(ApiRequestInterface $request): ResourceInterface
    {
        $requestMatcher = $this->getRouteRequestMatcherPlugin();

        return $requestMatcher->matchRequest($request);
    }

    /**
     * @param ResourceInterface $resource
     *
     * @return ApiResponseInterface
     */
    public function executeResource(ResourceInterface $resource): ApiResponseInterface
    {
        /** @var RestResponseInterface $result */
        $result = call_user_func($resource->getResource());

        //This would be the place to execute relationship plugins to add additional data
        $data = array_map(function (RestResourceInterface $restResource) {
            return $restResource->toArray(true);
        }, $result->getResources());

        return new ApiResponse(
            $result->getStatus() === 0 ? '200' : $result->getStatus(),
            json_encode($data),
            ['headers' => $result->getHeaders()],
        );
    }

    /**
     * @param ApiResponseInterface $response
     *
     * @return ApiResponseInterface
     */
    public function formatResponse(ApiResponseInterface $response): ApiResponseInterface
    {
        //Additional formatting can be applied here
        return $response;
    }

    /**
     * @param ApiResponseInterface $response
     */
    public function sendResponse(ApiResponseInterface $response): void
    {
        foreach ($response->getMeta() as $key => $value) {
            if ($key === 'headers' && is_array($value)) {
                array_map(function ($headerName, $headerValue): void {
                    header($headerName, $headerValue);
                }, array_keys($value), array_values($value));
            }
        }

        header(sprintf('HTTP/1.1 %s', $response->getStatusCode()));

        echo $response->getData();

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
    }

    /**
     * @param ApiRequestInterface $request
     *
     * @return ApiRequestValidationResult
     */
    public function validateRequest(ApiRequestInterface $request): ApiRequestValidationResult
    {
        //Additional request validation can be applied here
        return new ApiRequestValidationResult(true);
    }

    /**
     * @return RouteRequestMatcherPlugin
     */
    protected abstract function getRouteRequestMatcherPlugin(): RouteRequestMatcherPlugin;
}
