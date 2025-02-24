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

namespace ApiPlatform\Serializer;

use ApiPlatform\Exception\RuntimeException;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Util\RequestAttributesExtractor;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Encoder\CsvEncoder;

/**
 * {@inheritdoc}
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
final class SerializerContextBuilder implements SerializerContextBuilderInterface
{
    private $resourceMetadataFactory;

    public function __construct(ResourceMetadataCollectionFactoryInterface $resourceMetadataFactory)
    {
        $this->resourceMetadataFactory = $resourceMetadataFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function createFromRequest(Request $request, bool $normalization, array $attributes = null): array
    {
        if (null === $attributes && !$attributes = RequestAttributesExtractor::extractAttributes($request)) {
            throw new RuntimeException('Request attributes are not valid.');
        }

        $operation = $attributes['operation'] ?? $this->resourceMetadataFactory->create($attributes['resource_class'])->getOperation($attributes['operation_name']);
        $context = $normalization ? ($operation->getNormalizationContext() ?? []) : ($operation->getDenormalizationContext() ?? []);
        $context['operation_name'] = $operation->getName();
        $context['operation'] = $operation;
        $context['resource_class'] = $attributes['resource_class'];
        $context['skip_null_values'] = $context['skip_null_values'] ?? true;
        $context['iri_only'] = $context['iri_only'] ?? false;
        $context['request_uri'] = $request->getRequestUri();
        $context['uri'] = $request->getUri();
        $context['input'] = $operation->getInput();
        $context['output'] = $operation->getOutput();

        if ($operation->getTypes()) {
            $context['types'] = $operation->getTypes();
        }

        if ($operation->getUriVariables()) {
            $context['uri_variables'] = [];

            foreach (array_keys($operation->getUriVariables()) as $parameterName) {
                $context['uri_variables'][$parameterName] = $request->attributes->get($parameterName);
            }
        }

        if (!$normalization) {
            if (!isset($context['api_allow_update'])) {
                $context['api_allow_update'] = \in_array($method = $request->getMethod(), ['PUT', 'PATCH'], true);

                if ($context['api_allow_update'] && 'PATCH' === $method) {
                    $context['deep_object_to_populate'] = $context['deep_object_to_populate'] ?? true;
                }
            }

            if ('csv' === $request->getContentType()) {
                $context[CsvEncoder::AS_COLLECTION_KEY] = false;
            }
        }

        return $context;
    }
}
