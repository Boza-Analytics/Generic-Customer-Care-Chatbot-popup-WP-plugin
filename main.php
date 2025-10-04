<?php
/**
 * Plugin Name: Customer Care Chatbot (Popup)
 * Description: Lightweight popup chatbot for customer support with OpenAI + optional contact form (via shortcode).
 * Version: 1.0.0
 * Author: Bohuslav Sedláček
 * License: GPL-2.0-or-later
 */

if (!defined('ABSPATH')) exit;

// -----------------------------------------------------------------------------
// ADMIN PAGE & SETTINGS
// -----------------------------------------------------------------------------

// Add admin menu
add_action('admin_menu', function () {
    add_menu_page(
        'Agent Chatbot Settings',
        'Agent Chatbot',
        'manage_options',
        'agent-chatbot',
        'agc_settings_page',
        'dashicons-format-chat',
        60
    );
});

// Register settings
add_action('admin_init', function () {
    register_setting('agc_settings_group', 'agc_api_key');
    register_setting('agc_settings_group', 'agc_system_prompt');
    register_setting('agc_settings_group', 'agc_contact_shortcode');
    register_setting('agc_settings_group', 'agc_faqs', 'agc_sanitize_faqs');
    register_setting('agc_settings_group', 'agc_auto_open', 'intval');
    register_setting('agc_settings_group', 'agc_welcome_message');

    add_settings_section(
        'agc_basic_settings',
        'Basic Chatbot Settings',
        null,
        'agent-chatbot'
    );

    add_settings_field('agc_api_key', 'OpenAI API key', 'agc_api_key_callback', 'agent-chatbot', 'agc_basic_settings');
    add_settings_field('agc_system_prompt', 'System prompt (agent instructions)', 'agc_system_prompt_callback', 'agent-chatbot', 'agc_basic_settings');
    add_settings_field('agc_welcome_message', 'Welcome message', 'agc_welcome_message_callback', 'agent-chatbot', 'agc_basic_settings');
    add_settings_field('agc_contact_shortcode', 'Contact form shortcode', 'agc_contact_shortcode_callback', 'agent-chatbot', 'agc_basic_settings');
    add_settings_field('agc_auto_open', 'Auto-open popup on page load', 'agc_auto_open_callback', 'agent-chatbot', 'agc_basic_settings');

    add_settings_section(
        'agc_faq_settings',
        'Suggested questions (rendered as quick-reply buttons)',
        'agc_faq_description_callback',
        'agent-chatbot'
    );

    add_settings_field('agc_faqs', null, 'agc_faqs_callback', 'agent-chatbot', 'agc_faq_settings');
});

// --- Callbacks (admin fields)
function agc_api_key_callback() {
    $value = get_option('agc_api_key');
    echo '<input type="text" name="agc_api_key" value="' . esc_attr($value) . '" style="width: 400px;" placeholder="sk-..." />';
}

function agc_system_prompt_callback() {
    $value = get_option('agc_system_prompt', 'You are a helpful, concise customer support agent. Ask clarifying questions only when necessary. When an issue requires human help, offer the contact form.');
    echo '<textarea name="agc_system_prompt" rows="6" style="width: 100%;">' . esc_textarea($value) . '</textarea>';
}

function agc_welcome_message_callback() {
    $value = get_option('agc_welcome_message', 'Hi! I\'m your virtual assistant. How can I help today?');
    echo '<textarea name="agc_welcome_message" rows="3" style="width: 100%;">' . esc_textarea($value) . '</textarea>';
}

function agc_contact_shortcode_callback() {
    $value = get_option('agc_contact_shortcode');
    echo '<input type="text" name="agc_contact_shortcode" value="' . esc_attr($value) . '" style="width: 400px;" placeholder="e.g. [contact-form-7 id=\'123\']" />';
}

function agc_auto_open_callback() {
    $value = get_option('agc_auto_open', 1);
    echo '<label><input type="checkbox" name="agc_auto_open" value="1" ' . checked(1, $value, false) . ' /> Open popup automatically after page load</label>';
}

function agc_faq_description_callback() {
    echo '<p>Add common questions that will appear as quick-reply buttons in the chat.</p>';
}

function agc_faqs_callback() {
    $faqs = get_option('agc_faqs', []);
    ?>
    <div id="agc-faqs-admin">
        <?php if (!empty($faqs)) {
            foreach ($faqs as $index => $faq) { ?>
                <div class="faq-item" style="margin-bottom: 15px; padding: 10px; border: 1px solid #ddd; background-color: #f9f9f9;">
                    <p>
                        <label>Question:</label>
                        <input type="text" name="agc_faqs[<?php echo $index; ?>][question]" value="<?php echo esc_attr($faq['question']); ?>" style="width: 100%;" />
                    </p>
                    <p>
                        <label>Optional reference answer (not shown in popup):</label>
                        <textarea name="agc_faqs[<?php echo $index; ?>][answer]" rows="2" style="width: 100%;"><?php echo esc_textarea($faq['answer'] ?? ''); ?></textarea>
                    </p>
                    <button type="button" class="button remove-faq">Remove</button>
                </div>
            <?php }
        } ?>
    </div>

    <button type="button" id="agc-add-faq" class="button button-secondary">Add question</button>

    <script>
        jQuery(document).ready(function($) {
            let faqCount = $('#agc-faqs-admin .faq-item').length;
            $('#agc-add-faq').on('click', function() {
                const html = `
                    <div class="faq-item" style="margin-bottom: 15px; padding: 10px; border: 1px solid #ddd; background-color: #f9f9f9;">
                        <p>
                            <label>Question:</label>
                            <input type="text" name="agc_faqs[${faqCount}][question]" value="" style="width: 100%;" />
                        </p>
                        <p>
                            <label>Optional reference answer (not shown in popup):</label>
                            <textarea name="agc_faqs[${faqCount}][answer]" rows="2" style="width: 100%;"></textarea>
                        </p>
                        <button type="button" class="button remove-faq">Remove</button>
                    </div>
                `;
                $('#agc-faqs-admin').append(html);
                faqCount++;
            });
            $(document).on('click', '.remove-faq', function() {
                $(this).closest('.faq-item').remove();
            });
        });
    </script>
    <?php
}

function agc_sanitize_faqs($input) {
    if (empty($input)) return [];
    $output = [];
    foreach ($input as $faq) {
        if (!empty($faq['question'])) {
            $output[] = [
                'question' => sanitize_text_field($faq['question']),
                'answer'   => sanitize_textarea_field($faq['answer'] ?? '')
            ];
        }
    }
    return $output;
}

function agc_settings_page() {
    ?>
    <div class="wrap">
        <h1>Customer Care Chatbot – Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields('agc_settings_group'); ?>
            <?php do_settings_sections('agent-chatbot'); ?>
            <?php submit_button('Save settings'); ?>
        </form>
    </div>
    <?php
}

// -----------------------------------------------------------------------------
// REST API (CHAT)
// -----------------------------------------------------------------------------
add_action('rest_api_init', function () {
    register_rest_route('agent-chatbot/v1', '/chat', [
        'methods'  => 'POST',
        'callback' => 'agc_handle_chat',
        'permission_callback' => '__return_true', // public endpoint for frontend
    ]);
});

function agc_handle_chat($request) {
    $api_key = get_option('agc_api_key');
    $prompt  = get_option('agc_system_prompt', 'You are a helpful, concise customer support agent.');

    if (empty($api_key)) {
        return new WP_Error('api_key_missing', 'OpenAI API key is not set.', ['status' => 400]);
    }

    $user_message = sanitize_text_field($request['message']);
    if (empty($user_message)) {
        return new WP_Error('message_missing', 'Message is empty.', ['status' => 400]);
    }

    $messages = [
        ['role' => 'system', 'content' => $prompt],
        ['role' => 'user',   'content' => $user_message],
    ];

    $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
        ],
        'body' => wp_json_encode([
            'model'       => 'gpt-4o',
            'messages'    => $messages,
            'temperature' => 0.3,
            'max_tokens'  => 1500,
        ]),
        'timeout'   => 20,
        'sslverify' => true,
    ]);

    if (is_wp_error($response)) {
        return new WP_Error('api_error', 'Error contacting OpenAI: ' . $response->get_error_message(), ['status' => 500]);
    }

    $code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if ($code !== 200) {
        $error_message = $data['error']['message'] ?? 'Unknown API error';
        return new WP_Error('api_http_error', 'OpenAI API returned an error: ' . $error_message, ['status' => $code]);
    }

    if (isset($data['choices'][0]['message']['content'])) {
        return rest_ensure_response(['response' => $data['choices'][0]['message']['content']]);
    }

    return new WP_Error('api_response_error', 'Invalid response from OpenAI API.', ['status' => 500]);
}

// -----------------------------------------------------------------------------
// FRONTEND (POPUP & STYLES)
// -----------------------------------------------------------------------------
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('dashicons');
});

add_action('wp_footer', function () {
    $faqs            = get_option('agc_faqs', []);
    $auto_open       = get_option('agc_auto_open', 1);
    $welcome_message = get_option('agc_welcome_message', 'Hi! I\'m your virtual assistant. How can I help today?');

    if (empty($faqs)) {
        $faqs = [
            ['question' => 'What are your shipping times?'],
            ['question' => 'How can I track my order?'],
            ['question' => 'What\'s your return policy?'],
            ['question' => 'Do you offer technical support?'],
            ['question' => 'How can I contact a human agent?']
        ];
    }

    $faq_buttons_html = '';
    foreach ($faqs as $faq) {
        $q_attr   = esc_attr($faq['question']);
        $q_label  = esc_html($faq['question']);
        $faq_buttons_html .= '<button class="agc-faq-btn" data-question="' . $q_attr . '">' . $q_label . '</button>';
    }

    $contact_form_shortcode = get_option('agc_contact_shortcode');
    $contact_form_html = '';
    if (!empty($contact_form_shortcode)) {
        $contact_form_html = do_shortcode($contact_form_shortcode);
    }

    ?>

    <div id="agc-trigger"><span class="dashicons dashicons-format-chat"></span></div>
    <div id="agc-overlay"></div>

    <div id="agc-popup">
        <div id="agc-header">
            <div class="agc-header-info">
                <span class="dashicons dashicons-businessman agc-bot-avatar-header"></span>
                <h3>Customer Care Assistant</h3>
            </div>
            <div class="agc-header-actions">
                <span class="dashicons dashicons-minus agc-minimize-btn" title="Minimize"></span>
                <span class="dashicons dashicons-no agc-close-btn" title="Close"></span>
            </div>
        </div>

        <div id="agc-body">
            <div id="agc-chat-wrapper">
                <div id="agc-chat-window">
                    <div class="agc-message agc-bot">
                        <div class="agc-avatar"><span class="dashicons dashicons-businessman"></span></div>
                        <div class="agc-content"><p><?php echo esc_html($welcome_message); ?></p></div>
                    </div>
                </div>

                <div id="agc-typing" style="display:none;">
                    <div class="agc-message agc-bot">
                        <div class="agc-avatar"><span class="dashicons dashicons-businessman"></span></div>
                        <div class="agc-typing-indicator"><span></span><span></span><span></span></div>
                    </div>
                </div>

                <div id="agc-faq-section">
                    <h4>Quick questions:</h4>
                    <div id="agc-faq-buttons"><?php echo $faq_buttons_html; ?></div>
                </div>

                <div id="agc-input-area">
                    <input type="text" id="agc-user-input" placeholder="Type your question..." autocomplete="off" />
                    <button id="agc-send-btn" title="Send"><span class="dashicons dashicons-arrow-right-alt2"></span></button>
                </div>
            </div>

            <div id="agc-contact-form-area" style="display:none;">
                <?php if (!empty($contact_form_html)) : ?>
                    <div class="agc-contact-form-content"><?php echo $contact_form_html; ?></div>
                    <button id="agc-back-to-chat" class="agc-back-btn"><span class="dashicons dashicons-arrow-left-alt"></span> Back to chat</button>
                <?php else : ?>
                    <p>Contact form is not configured. Please reach out via another channel.</p>
                    <button id="agc-back-to-chat-no-form" class="agc-back-btn"><span class="dashicons dashicons-arrow-left-alt"></span> Back to chat</button>
                <?php endif; ?>
            </div>
        </div>

        <div id="agc-footer">
            <button id="agc-show-contact" class="agc-footer-btn" title="Show contact form"><span class="dashicons dashicons-phone"></span> Contact</button>
            <button id="agc-clear-chat" class="agc-footer-btn" title="Clear conversation"><span class="dashicons dashicons-trash"></span> Clear</button>
        </div>
    </div>

    <style>
        :root {
            --agc-accent: #2563eb; /* brand-agnostic blue */
            --agc-accent-soft: #e8efff;
            --agc-text: #333;
        }
        #agc-popup *, #agc-popup *:before, #agc-popup *:after,
        #agc-contact-form-area *, #agc-contact-form-area *:before, #agc-contact-form-area *:after { box-sizing: border-box; }
        #agc-popup { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; font-size: 15px; color: var(--agc-text); }
        #agc-trigger { position: fixed; bottom: 20px; right: 20px; width: 60px; height: 60px; background-color: var(--agc-accent); border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 4px 12px rgba(0,0,0,.2); z-index: 9998; transition: transform .3s ease, opacity .3s ease; border: none; }
        #agc-trigger .dashicons { color: #fff; font-size: 30px; width: 30px; height: 30px; }
        #agc-trigger:hover { transform: scale(1.1); }
        #agc-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.6); z-index: 9999; display: none; }
        #agc-popup { position: fixed; bottom: 90px; right: 20px; width: 400px; height: 550px; max-width: calc(100% - 40px); max-height: calc(100% - 40px); background: #fff; border-radius: 12px; box-shadow: 0 5px 25px rgba(0,0,0,.3); z-index: 10000; display: none; flex-direction: column; overflow: hidden; transition: transform .3s ease, opacity .3s ease; transform-origin: bottom right; }
        #agc-popup.minimized { height: auto; max-height: 60px; }
        #agc-popup.minimized #agc-body, #agc-popup.minimized #agc-footer { display:none; }
        #agc-header { background: var(--agc-accent); color: #fff; padding: 15px 20px; display:flex; justify-content: space-between; align-items: center; cursor:pointer; }
        #agc-header .agc-header-info { display:flex; align-items:center; }
        #agc-header .agc-bot-avatar-header { font-size: 24px; width:24px; height:24px; margin-right:8px; color: rgba(255,255,255,.9); }
        #agc-header h3 { margin:0; font-size:16px; font-weight:600; }
        #agc-header .agc-header-actions { display:flex; align-items:center; gap:10px; }
        .agc-close-btn, .agc-minimize-btn { cursor:pointer; font-size:20px; width:20px; height:20px; display:flex; align-items:center; justify-content:center; opacity:.95; }
        #agc-body { display:flex; flex-direction:column; flex-grow:1; overflow:hidden; }
        #agc-chat-wrapper { display:flex; flex-direction:column; height:100%; overflow:hidden; }
        #agc-chat-window { flex:1; overflow-y:auto; padding:15px; background:#f8f8f8; display:flex; flex-direction:column; }
        .agc-message { display:flex; margin-bottom:15px; align-items:flex-start; max-width:90%; }
        .agc-bot { align-self:flex-start; }
        .agc-user { align-self:flex-end; flex-direction:row-reverse; }
        .agc-avatar { width:32px; height:32px; min-width:32px; border-radius:50%; background: var(--agc-accent-soft); display:flex; align-items:center; justify-content:center; margin:0 8px; flex-shrink:0; }
        .agc-user .agc-avatar { background: var(--agc-accent); }
        .agc-avatar .dashicons { color: var(--agc-accent); font-size:18px; width:18px; height:18px; }
        .agc-user .agc-avatar .dashicons { color:#fff; }
        .agc-content { background:#fff; border-radius:8px; padding:10px 14px; box-shadow:0 1px 2px rgba(0,0,0,.08); max-width:100%; font-size:14px; word-wrap:break-word; overflow-wrap:break-word; }
        .agc-user .agc-content { background: var(--agc-accent); color:#fff; }
        .agc-content p { margin:0; line-height:1.4; }
        #agc-typing { align-self:flex-start; margin-left:8px; margin-bottom:15px; display:flex; align-items:flex-start; }
        .agc-typing-indicator { background:#fff; border-radius:8px; padding:12px; box-shadow:0 1px 2px rgba(0,0,0,.08); display:flex; align-items:center; gap:4px; }
        .agc-typing-indicator span { height:8px; width:8px; background: var(--agc-accent); border-radius:50%; display:inline-block; opacity:.7; animation: agc-typing 1s infinite ease-in-out; }
        .agc-typing-indicator span:nth-child(2){ animation-delay:.15s; }
        .agc-typing-indicator span:nth-child(3){ animation-delay:.3s; }
        @keyframes agc-typing { 0%,80%,100%{ transform: translateY(0);} 40%{ transform: translateY(-5px);} }
        #agc-faq-section { padding:10px 15px; background:#f0f0f0; border-top:1px solid #e0e0e0; flex-shrink:0; transition:max-height .3s ease, padding .3s ease, opacity .3s ease; overflow:hidden; max-height:150px; opacity:1; }
        #agc-faq-section.hidden { max-height:0; padding-top:0; padding-bottom:0; opacity:0; }
        #agc-faq-section h4 { margin:0 0 8px 0; font-size:14px; color:#444; }
        #agc-faq-buttons { display:flex; flex-wrap:nowrap; gap:8px; overflow-x:auto; padding-bottom:5px; -webkit-overflow-scrolling:touch; }
        #agc-faq-buttons::-webkit-scrollbar{ height:6px; }
        .agc-faq-btn { background: var(--agc-accent-soft); border:1px solid var(--agc-accent); color: var(--agc-accent); padding:6px 12px; border-radius:16px; cursor:pointer; font-size:13px; white-space:nowrap; flex-shrink:0; transition: background .2s, color .2s; }
        .agc-faq-btn:hover { background: var(--agc-accent); color:#fff; }
        #agc-input-area { display:flex; align-items:center; padding:10px 15px; background:#fff; border-top:1px solid #e0e0e0; flex-shrink:0; }
        #agc-user-input { flex:1; padding:10px 14px; border:1px solid #ddd; border-radius:20px; outline:none; font-size:14px; margin-right:8px; }
        #agc-user-input:focus { border-color: var(--agc-accent); box-shadow:0 0 0 2px rgba(37,99,235,.2); }
        #agc-send-btn { background: var(--agc-accent); color:#fff; border:none; width:38px; height:38px; border-radius:50%; display:flex; align-items:center; justify-content:center; cursor:pointer; flex-shrink:0; transition: background .2s; padding:0; }
        #agc-send-btn:hover { filter: brightness(0.95); }
        #agc-footer { display:flex; justify-content:space-around; padding:10px 15px; background:#f0f0f0; border-top:1px solid #e0e0e0; flex-shrink:0; }
        .agc-footer-btn { background:none; border:none; color:#444; padding:5px 10px; border-radius:4px; font-size:13px; cursor:pointer; display:flex; align-items:center; transition: background .2s, color .2s; }
        .agc-footer-btn:hover { background:#e0e0e0; color:#222; }
        .agc-footer-btn .dashicons{ font-size:16px; width:16px; height:16px; margin-right:5px; }
        #agc-contact-form-area { padding:15px; background:#fff; flex-grow:1; overflow-y:auto; display:flex; flex-direction:column; }
        #agc-contact-form-area .agc-contact-form-content { flex-grow:1; }
        .agc-back-btn { display:flex; align-items:center; justify-content:center; background:#f0f0f0; border:1px solid #ddd; color:#444; padding:8px 12px; border-radius:4px; margin-top:15px; cursor:pointer; font-size:14px; transition: background .2s; width:100%; }
        .agc-back-btn:hover { background:#e0e0e0; }
        #agc-contact-form-area form.wpcf7-form { display:flex; flex-direction:column; gap:12px; }
        #agc-contact-form-area input[type="text"],
        #agc-contact-form-area input[type="email"],
        #agc-contact-form-area input[type="tel"],
        #agc-contact-form-area textarea { width:100%; padding:8px; border:1px solid #ccc; border-radius:4px; font-size:14px; }
        #agc-contact-form-area input[type="submit"] { background: var(--agc-accent); color:#fff; border:none; padding:10px 15px; border-radius:4px; cursor:pointer; font-size:14px; transition: filter .2s; display:block !important; visibility:visible !important; }
        #agc-contact-form-area input[type="submit"]:hover { filter: brightness(0.95); }
        @media (max-width: 600px) { #agc-popup { right:10px; bottom:80px; width:calc(100% - 20px); max-height:calc(100% - 20px); border-radius:8px; } #agc-trigger{ bottom:10px; right:10px; width:50px; height:50px;} #agc-trigger .dashicons{ font-size:24px; width:24px; height:24px;} #agc-header{ padding:10px 15px;} #agc-header h3{ font-size:15px;} .agc-close-btn,.agc-minimize-btn{ font-size:18px; width:18px; height:18px;} #agc-chat-window{ padding:10px;} .agc-message{ margin-bottom:10px;} .agc-avatar{ width:28px; height:28px; min-width:28px; margin:0 6px;} .agc-avatar .dashicons{ font-size:16px; } .agc-content{ padding:8px 12px; font-size:13px;} .agc-typing-indicator{ padding:10px;} #agc-faq-section{ padding:8px 10px;} #agc-faq-section h4{ font-size:13px; margin-bottom:6px;} .agc-faq-btn{ padding:5px 10px; font-size:12px; border-radius:14px;} #agc-input-area{ padding:8px 10px; } #agc-user-input{ padding:8px 12px; font-size:13px; margin-right:6px;} #agc-send-btn{ width:34px; height:34px;} #agc-footer{ padding:8px 10px; } .agc-footer-btn{ padding:4px 8px; font-size:12px; } .agc-footer-btn .dashicons{ font-size:14px; width:14px; height:14px; margin-right:4px;} #agc-contact-form-area{ padding:10px; } .agc-back-btn{ padding:6px 10px; font-size:13px; } }
    </style>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const popup = document.getElementById('agc-popup');
        const overlay = document.getElementById('agc-overlay');
        const trigger = document.getElementById('agc-trigger');
        const closeBtn = popup.querySelector('.agc-close-btn');
        const minimizeBtn = popup.querySelector('.agc-minimize-btn');
        const header = document.getElementById('agc-header');
        const chatWindow = document.getElementById('agc-chat-window');
        const userInput = document.getElementById('agc-user-input');
        const sendBtn = document.getElementById('agc-send-btn');
        const typing = document.getElementById('agc-typing');
        const faqButtons = document.querySelectorAll('#agc-faq-buttons .agc-faq-btn');
        const faqSection = document.getElementById('agc-faq-section');
        const chatWrapper = document.getElementById('agc-chat-wrapper');
        const contactArea = document.getElementById('agc-contact-form-area');
        const showContactBtn = document.getElementById('agc-show-contact');
        const backBtn = document.getElementById('agc-back-to-chat');
        const backBtnNoForm = document.getElementById('agc-back-to-chat-no-form');
        const clearBtn = document.getElementById('agc-clear-chat');

        const api_url = '<?php echo esc_url_raw(rest_url("agent-chatbot/v1/chat")); ?>';
        const auto_open = <?php echo (int) $auto_open; ?>;

        function scrollBottom(){ chatWindow.scrollTop = chatWindow.scrollHeight; }
        function togglePopup(show=null){ const visible = popup.style.display === 'flex'; if(show===null){ show=!visible; } if(show){ popup.style.display='flex'; overlay.style.display='block'; trigger.style.transform='scale(0.8)'; popup.classList.remove('minimized'); userInput.focus(); scrollBottom(); } else { popup.style.display='none'; overlay.style.display='none'; trigger.style.transform='scale(1)'; chatWrapper.style.display='flex'; contactArea.style.display='none'; } }
        function toggleMin(){ popup.classList.toggle('minimized'); }

        function addUser(text){ const el=document.createElement('div'); el.className='agc-message agc-user'; el.innerHTML = '<div class="agc-avatar"><span class="dashicons dashicons-admin-users"></span></div><div class="agc-content"><p></p></div>'; el.querySelector('p').textContent = text; chatWindow.appendChild(el); scrollBottom(); }
        function addBot(text){ const el=document.createElement('div'); el.className='agc-message agc-bot'; const safe = String(text).replace(/<script.*?>.*?<\/script>/gis,'').replace(/<\/?\w+.*?>/gis, ''); el.innerHTML = '<div class="agc-avatar"><span class="dashicons dashicons-businessman"></span></div><div class="agc-content"><p></p></div>'; el.querySelector('p').innerHTML = safe.replace(/\n/g,'<br>'); chatWindow.appendChild(el); scrollBottom(); }
        function clearChat(){ const msgs = chatWindow.querySelectorAll('.agc-message'); for(let i=1;i<msgs.length;i++){ msgs[i].remove(); } faqSection.classList.remove('hidden'); }
        function showContact(){ if(!contactArea.querySelector('.wpcf7-form')){ addBot('Contact form is currently unavailable.'); return; } chatWrapper.style.display='none'; contactArea.style.display='flex'; }
        function backToChat(){ contactArea.style.display='none'; chatWrapper.style.display='flex'; scrollBottom(); }

        trigger.addEventListener('click', () => togglePopup());
        closeBtn.addEventListener('click', () => togglePopup(false));
        overlay.addEventListener('click', () => togglePopup(false));
        minimizeBtn.addEventListener('click', () => toggleMin());
        header.addEventListener('click', (e) => { if(!e.target.closest('.agc-close-btn') && !e.target.closest('.agc-minimize-btn')){ toggleMin(); } });

        sendBtn.addEventListener('click', sendMessage);
        userInput.addEventListener('keypress', function(e){ if(e.key==='Enter'){ e.preventDefault(); sendMessage(); }});
        faqButtons.forEach(btn=>{ btn.addEventListener('click', function(){ userInput.value = this.getAttribute('data-question'); sendMessage(); }); });
        showContactBtn.addEventListener('click', showContact);
        if(backBtn) backBtn.addEventListener('click', backToChat);
        if(backBtnNoForm) backBtnNoForm.addEventListener('click', backToChat);
        clearBtn.addEventListener('click', clearChat);

        async function sendMessage(){
            const message = userInput.value.trim();
            if(!message) return;
            addUser(message);
            userInput.value='';
            faqSection.classList.add('hidden');
            typing.style.display='flex';
            scrollBottom();
            try{
                const res = await fetch(api_url, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({message}) });
                if(!res.ok){ let errText = ''; try{ const j=await res.json(); errText = j.message || res.statusText; }catch(_){ errText = res.statusText; } throw new Error(`API error: ${res.status} - ${errText}`); }
                const data = await res.json();
                typing.style.display='none';
                if(data && data.response){ addBot(data.response); } else { addBot('Sorry, I received an invalid response from the API.'); }
            }catch(e){ typing.style.display='none'; console.error(e); addBot('Sorry, a technical error occurred while contacting the assistant. Please try again later.'); }
        }

        if(auto_open){ setTimeout(()=>togglePopup(true), 1000); } else { trigger.style.display='flex'; }
        if(chatWindow.children.length>1){ faqSection.classList.add('hidden'); }
    });
    </script>
    <?php
});

// Optional: small admin styles for the FAQ editor
add_action('admin_enqueue_scripts', function($hook_suffix){
    if ('toplevel_page_agent-chatbot' !== $hook_suffix) return;
    wp_enqueue_style('wp-admin');
    ?>
    <style>
        #agc-faqs-admin .faq-item { border:1px solid #c3c4c7; padding:15px; margin-bottom:20px; background:#fff; box-shadow:0 1px 1px rgba(0,0,0,.04); }
        #agc-faqs-admin .faq-item label { display:block; margin-bottom:5px; font-weight:600; }
        #agc-faqs-admin .faq-item input[type="text"], #agc-faqs-admin .faq-item textarea { display:block; width:100%; padding:8px 12px; margin-bottom:10px; border:1px solid #8c8f94; border-radius:4px; box-shadow: inset 0 1px 2px rgba(0,0,0,.04); }
        #agc-faqs-admin .faq-item .remove-faq { float:right; margin-top:-35px; }
        #agc-faqs-admin::after { content:""; display:block; clear:both; }
    </style>
    <?php
});
