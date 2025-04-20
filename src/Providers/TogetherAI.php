<?php

declare( strict_types=1 );

namespace WoocommerceAIChatbot\Providers;

use NeuronAI\Providers\OpenAI\OpenAI;

defined( '\ABSPATH' ) || exit;

class TogetherAI extends OpenAI {
	protected string $baseUri = "https://api.together.xyz";
}