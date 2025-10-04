ustomer Care Chatbot (Popup)

Lightweight WordPress plugin that adds a floating popup chatbot for customer support.
Uses OpenAI Chat Completions and (optionally) embeds your contact form via shortcode (e.g., Contact Form 7).

Features

Clean popup widget with quick-reply (FAQ) buttons

Configurable system prompt + welcome message

Optional auto-open on page load

Optional contact form via shortcode

Pure JS/CSS (no jQuery on frontend), dashicons styling

Works on any theme

Requirements

WordPress 6.x+

An OpenAI API key

(Optional) A contact form plugin (e.g., Contact Form 7)

Installation

Create a folder customer-care-chatbot in wp-content/plugins/.

Put the plugin PHP file inside (the file provided to you).

Activate Customer Care Chatbot (Popup) in Plugins → Installed Plugins.

Setup

Go to Settings → Agent Chatbot (or Agent Chatbot in the left WP admin menu) and configure:

OpenAI API key — your sk-...

System prompt — how the assistant should behave
(e.g., “You are a helpful, concise customer support agent. …”)

Welcome message — first message shown in chat

Contact form shortcode — optional (e.g., [contact-form-7 id="123"])

Quick questions — add your FAQ buttons

Auto-open — whether the popup opens automatically on page load

Save your settings.

How it works

Frontend injects a floating chat button and a popup.

When a user sends a message, the plugin calls:

POST /wp-json/agent-chatbot/v1/chat

Body: { "message": "..." }

The server relays the request to OpenAI using your system prompt and returns the model’s reply.

Styling / Branding

Default accent is neutral blue.

To tweak colors quickly, edit the :root { --agc-accent: … } variables in the inline <style> section.

Security & Privacy

Keep your OpenAI API key private (set it only in WP admin).

The endpoint is public to allow frontend calls; consider adding extra protections if your site is high-traffic (rate limiting, CAPTCHA on contact, etc.).

Do not instruct the model to reveal secrets or process sensitive personal data.

Troubleshooting

“OpenAI API key is not set”: enter a valid key in settings.

No response / generic error: check server error logs and your site’s outbound HTTP is allowed; verify your key has credits.

Contact form not visible: ensure the shortcode is correct and the form plugin is active.

Uninstall

Deactivate the plugin from Plugins. (Options remain in DB unless you remove them manually.)

Changelog

1.0.0

First public, generic release (popup chatbot + admin settings + REST endpoint).

License

GPL-2.0-or-later
