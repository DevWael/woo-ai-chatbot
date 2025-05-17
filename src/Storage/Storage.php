<?php

namespace WoocommerceAIChatbot\Storage;

use NeuronAI\RAG\Embeddings\EmbeddingsProviderInterface;
use NeuronAI\RAG\VectorStore\VectorStoreInterface;

interface Storage {
	public function supply_data( array $data );

	public function storage(): VectorStoreInterface;

	public function provider(): EmbeddingsProviderInterface;

	public function store();
}