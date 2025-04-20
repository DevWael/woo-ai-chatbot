<?php

declare( strict_types=1 );

namespace WoocommerceAIChatbot\Providers;

use NeuronAI\Providers\OpenAI\OpenAI;

defined( '\ABSPATH' ) || exit;

class OpenRouterAI extends OpenAI {
	/**
	 * The main URL of the provider API.
	 *
	 * @var string
	 */
	protected string $baseUri = "https://openrouter.ai/api/v1";
}