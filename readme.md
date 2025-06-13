# WooCommerce AI Assistant

![AI Assistant](https://img.icons8.com/color/96/000000/artificial-intelligence.png)

A powerful AI-powered assistant for WooCommerce stores that helps customers discover products, manage carts, and get
e-commerce support through natural language interactions.

## Features

- 🛍️ **Product Search**: Natural language product discovery
- 🛒 **Cart Management**: Add/remove items, view cart contents
- 📝 **Content Creation**: Generate product posts and descriptions
- 🔌 **Extensible**: Easy to add new functions and capabilities

## Requirements

- WordPress 5.0+
- WooCommerce 6.0+
- PHP 8.1+
- Ollama server (or compatible LLM API)

## Testing and Development

- Clone the repository into your WordPress plugins directory.
- run `composer install` to install dependencies.
- Activate the plugin from the WordPress admin dashboard.
- You can use https://inspector.dev/ to test and check how the ai behaves and tune it's performance.
- Use `AIChatbot_INSPECTOR_API_KEY` constant in your `wp-config.php` to set the API key for Inspector.dev.
- In WP-CLI use `wp wc-ai index-products` command to index products to be searchable for the AI assistant.

## Contributions
We welcome contributions! Please follow these steps:
1. Fork the repository
2. Create a new branch for your feature or bug fix
3. Make your changes and commit them
4. Push to your branch
5. Create a pull request