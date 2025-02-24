<?php

/*
 * This file is part of the API Platform project.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ApiPlatform\Util;

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
trait OperationRequestInitiatorTrait
{
    /**
     * @var ResourceMetadataCollectionFactoryInterface|null
     */
    private $resourceMetadataCollectionFactory;

    /**
     * TODO: Kernel terminate remove the _api_operation attribute?
     */
    private function initializeOperation(Request $request): ?HttpOperation
    {
        if ($request->attributes->get('_api_operation')) {
            return $request->attributes->get('_api_operation');
        }

        if (null === $request->attributes->get('_api_resource_class') || null === $this->resourceMetadataCollectionFactory) {
            return null;
        }

        $operationName = $request->attributes->get('_api_operation_name') ?? null;
        /** @var HttpOperation $operation */
        $operation = $this->resourceMetadataCollectionFactory->create($request->attributes->get('_api_resource_class'))->getOperation($operationName);
        $request->attributes->set('_api_operation', $operation);

        return $operation;
    }
}
