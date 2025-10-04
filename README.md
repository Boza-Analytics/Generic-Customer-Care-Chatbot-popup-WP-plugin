# Customer Care Chatbot (Popup)

Lightweight WordPress plugin that adds a floating popup chatbot for customer support.  
Uses OpenAI Chat Completions and (optionally) embeds your contact form via shortcode (e.g., Contact Form 7).

## Features
- Clean popup widget with quick-reply (FAQ) buttons  
- Configurable system prompt + welcome message  
- Optional auto-open on page load  
- Optional contact form via shortcode  
- Pure JS/CSS (no jQuery on frontend), dashicons styling  
- Works on any theme

## Requirements
- WordPress 6.x+  
- An OpenAI API key  
- (Optional) A contact form plugin (e.g., Contact Form 7)

## Installation
1. Create a folder `customer-care-chatbot` in `wp-content/plugins/`.
2. Put the plugin PHP file inside that folder.
3. Activate **Customer Care Chatbot (Popup)** in **Plugins → Installed Plugins**.

## Setup
Go to **Agent Chatbot** in the left WP admin menu and configure:

- **OpenAI API key** — your `sk-...`  
- **System prompt** — how the assistant should behave  
  *(e.g., “You are a helpful, concise customer support agent…”)*
- **Welcome message** — first message shown in chat  
- **Contact form shortcode** — optional (e.g., `[contact-form-7 id="123"]`)  
- **Quick questions** — add your FAQ buttons  
- **Auto-open** — whether the popup opens automatically on page load  

Click **Save** when done.

## How it works
- Frontend injects a floating chat button and popup.
- When a user sends a message, the plugin calls:
