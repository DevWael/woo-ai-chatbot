<?php

namespace WoocommerceAIChatbot\Providers;

use NeuronAI\RAG\Embeddings\EmbeddingsProviderInterface;
use NeuronAI\RAG\Embeddings\OllamaEmbeddingsProvider;

class Embeddings_Provider {
	public static function provider(): EmbeddingsProviderInterface {
		return new OllamaEmbeddingsProvider(
			model: 'nomic-embed-text:latest'
		);
	}
}