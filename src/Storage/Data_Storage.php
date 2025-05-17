<?php

namespace WoocommerceAIChatbot\Storage;

use NeuronAI\RAG\DataLoader\StringDataLoader;
use NeuronAI\RAG\Embeddings\EmbeddingsProviderInterface;
use NeuronAI\RAG\VectorStore\FileVectorStore;
use NeuronAI\RAG\VectorStore\VectorStoreInterface;
use WoocommerceAIChatbot\Providers\Embeddings_Provider;

class Data_Storage implements Storage {
	private EmbeddingsProviderInterface $provider;

	public function __construct(
		protected string $directory,
		protected int $topK = 4,
		protected string $name = 'neuron',
		protected string $ext = '.store'
	) {
		$this->directory = $directory;
		$this->topK      = $topK;
		$this->name      = $name;
		$this->ext       = $ext;
		$this->provider  = $this->provider();
		$this->storage   = $this->storage();
	}

	public function supply_data( array $data ) {
		$this->data = $data;
	}

	public function provider(): EmbeddingsProviderInterface {
		return Embeddings_Provider::provider();
	}

	public function storage(): VectorStoreInterface {
		return new FileVectorStore(
			directory: $this->directory,
			topK: $this->topK,
			name: $this->name,
			ext: $this->ext
		);
	}

	public function store() {
		if ( empty( $this->data ) ) {
			throw new \Exception( 'No data to store' );
		}

		foreach ( $this->data as $data ) {
			// Register the PDF reader
			$documents = StringDataLoader::for( $data['content'] )->getDocuments();

			// Set $id for each document
			foreach ( $documents as $index => $document ) {
				$document->id = $data['id']; // Unique ID based on data
			}

			$embeddedDocuments = $this->provider->embedDocuments( $documents );
			// Save the embedded documents into the vector store for later use running your Agent.
			$this->storage->addDocuments( $embeddedDocuments );
		}
	}

	public function search( array $embedding ): array {
		return $this->storage->similaritySearch( $embedding );
	}
}