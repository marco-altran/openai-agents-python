<?php

namespace OpenAI\Agents\Models;

use React\Promise\PromiseInterface;

/**
 * Interface for language models that can be used by agents.
 */
interface ModelInterface
{
    /**
     * Generate a model response based on messages.
     *
     * @param array $messages Array of message objects with role and content
     * @param array $options Additional options for the model
     * @return array The model's response
     */
    public function generate(array $messages, array $options = []): array;

    /**
     * Generate a model response asynchronously based on messages.
     *
     * @param array $messages Array of message objects with role and content
     * @param array $options Additional options for the model
     * @return PromiseInterface Promise that resolves to the model's response
     */
    public function generateAsync(array $messages, array $options = []): PromiseInterface;

    /**
     * Generate a streaming model response based on messages.
     *
     * @param array $messages Array of message objects with role and content
     * @param array $options Additional options for the model
     * @return \Generator A generator yielding chunks of the model's response
     */
    public function generateStream(array $messages, array $options = []): \Generator;

    /**
     * Generate a streaming model response asynchronously based on messages.
     *
     * @param array $messages Array of message objects with role and content
     * @param array $options Additional options for the model
     * @return PromiseInterface Promise that resolves to a Generator of response chunks
     */
    public function generateStreamAsync(array $messages, array $options = []): PromiseInterface;
}