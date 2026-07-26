<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $tool->name }} — {{ $appName }}</title>
    @php
    // Guarded because a compiled Blade view is plain PHP included into the current process:
    // rendering this view twice in one process (a long-lived worker, or two embeds in one
    // test) hit "Cannot redeclare hex2rgb()" and killed the process.
    if (! function_exists('hex2rgb')) {
    function hex2rgb($hex) {
        $hex = str_replace("#", "", $hex);
        if(strlen($hex) == 3) {
            $r = hexdec(substr($hex,0,1).substr($hex,0,1));
            $g = hexdec(substr($hex,1,1).substr($hex,1,1));
            $b = hexdec(substr($hex,2,1).substr($hex,2,1));
        } else {
            $r = hexdec(substr($hex,0,2));
            $g = hexdec(substr($hex,2,2));
            $b = hexdec(substr($hex,4,2));
        }
        return "$r, $g, $b";
    }
    }
    $primaryColor = $embed->primary_color ?? '#1F75FE';
    $primaryRgb = hex2rgb($primaryColor);

    if (! function_exists('getNormalizedOptions')) {
    function getNormalizedOptions($field, $languages, $models) {
        $type = $field['type'] ?? 'text';
        if ($type === 'tone_select') {
            $tones = ['Professional', 'Friendly', 'Casual', 'Formal', 'Humorous', 'Persuasive', 'Inspirational', 'Empathetic'];
            return array_map(fn($t) => ['label' => $t, 'value' => strtolower($t)], $tones);
        }
        if ($type === 'length_select') {
            if (!empty($field['options'])) {
                return $field['options'];
            }
            return [
                ['label' => 'Short (~100 words)', 'value' => 'short'],
                ['label' => 'Medium (~300 words)', 'value' => 'medium'],
                ['label' => 'Long (~600 words)', 'value' => 'long'],
                ['label' => 'Very Long (~1200 words)', 'value' => 'very_long'],
            ];
        }
        if ($type === 'language_select') {
            return array_map(fn($lang) => ['label' => $lang['name'], 'value' => $lang['name']], $languages);
        }
        if ($type === 'model_select') {
            return array_map(fn($model) => ['label' => $model['name'] . ' (' . $model['provider'] . ')', 'value' => $model['slug']], $models);
        }

        $rawOptions = $field['options'] ?? [];
        $options = [];
        foreach ($rawOptions as $opt) {
            if (is_array($opt)) {
                $options[] = [
                    'label' => $opt['label'] ?? $opt['value'] ?? '',
                    'value' => $opt['value'] ?? '',
                ];
            } else {
                $options[] = [
                    'label' => $opt,
                    'value' => $opt,
                ];
            }
        }
        return $options;
    }
    }
    @endphp
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@500;600;700&display=swap');
        @import url('https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css');

        :root {
            --primary-color: {{ $primaryColor }};
            --primary-rgb: {{ $primaryRgb }};
            
            /* Light Mode Defaults */
            --bg-color: #f8fafc;
            --card-bg: rgba(255, 255, 255, 0.9);
            --border-color: rgba(226, 232, 240, 0.8);
            --text-color: #1e293b;
            --text-muted: #64748b;
            --input-bg: #ffffff;
            --input-border: #cbd5e1;
            --output-bg: #f8fafc;
            --shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
        }

        @media (prefers-color-scheme: dark) {
            :root {
                @if($embed->theme !== 'light')
                --bg-color: #0b0f19;
                --card-bg: rgba(17, 24, 39, 0.7);
                --border-color: rgba(255, 255, 255, 0.06);
                --text-color: #f3f4f6;
                --text-muted: #9ca3af;
                --input-bg: #111827;
                --input-border: rgba(255, 255, 255, 0.1);
                --output-bg: #0b0f19;
                --shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3), 0 8px 10px -6px rgba(0, 0, 0, 0.3);
                @endif
            }
        }

        @if($embed->theme === 'dark')
        :root {
            --bg-color: #0b0f19;
            --card-bg: rgba(17, 24, 39, 0.7);
            --border-color: rgba(255, 255, 255, 0.06);
            --text-color: #f3f4f6;
            --text-muted: #9ca3af;
            --input-bg: #111827;
            --input-border: rgba(255, 255, 255, 0.1);
            --output-bg: #0b0f19;
            --shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3), 0 8px 10px -6px rgba(0, 0, 0, 0.3);
        }
        @endif

        @if($embed->theme === 'light')
        :root {
            --bg-color: #f8fafc;
            --card-bg: rgba(255, 255, 255, 0.9);
            --border-color: rgba(226, 232, 240, 0.8);
            --text-color: #1e293b;
            --text-muted: #64748b;
            --input-bg: #ffffff;
            --input-border: #cbd5e1;
            --output-bg: #f8fafc;
            --shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
        }
        @endif

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            padding: 16px;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            position: relative;
            overflow-x: hidden;
        }

        /* Ambient Glow effect background */
        body::before {
            content: '';
            position: absolute;
            top: -50px;
            left: -50px;
            width: 250px;
            height: 250px;
            background: radial-gradient(circle, rgba(var(--primary-rgb), 0.12) 0%, rgba(var(--primary-rgb), 0) 70%);
            z-index: 0;
            pointer-events: none;
        }

        .container {
            width: 100%;
            max-width: 600px;
            z-index: 10;
        }

        .card-container {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-radius: 16px;
            padding: 20px;
            box-shadow: var(--shadow);
        }

        .embed-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 12px;
        }
        .embed-header h3 {
            font-family: 'Outfit', sans-serif;
            font-size: 16px;
            font-weight: 600;
        }
        .embed-header .tool-icon {
            font-size: 18px;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .field { margin-bottom: 16px; }
        .field label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 6px;
            opacity: 0.9;
        }
        .field input, .field textarea, .field select {
            width: 100%;
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid var(--input-border);
            background: var(--input-bg);
            color: inherit;
            font-size: 14px;
            font-family: inherit;
            outline: none;
            transition: all 0.2s ease;
        }
        .field input:focus, .field textarea:focus, .field select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.15);
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 11px 22px;
            border-radius: 8px;
            border: none;
            background: var(--primary-color);
            color: #fff;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            box-shadow: 0 4px 10px rgba(var(--primary-rgb), 0.2);
            transition: all 0.2s ease;
        }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; box-shadow: none; }
        .btn:hover:not(:disabled) {
            opacity: 0.95;
            transform: translateY(-1px);
            box-shadow: 0 6px 14px rgba(var(--primary-rgb), 0.28);
        }
        .btn:active:not(:disabled) {
            transform: translateY(0);
        }

        .output-wrapper {
            margin-top: 20px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            background: var(--output-bg);
            overflow: hidden;
        }
        .output-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 12px;
            border-bottom: 1px solid var(--border-color);
            font-size: 11px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .copy-btn {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            border: none;
            background: transparent;
            color: inherit;
            cursor: pointer;
            font-size: 11px;
            font-weight: 500;
            transition: color 0.2s;
            padding: 2px 6px;
            border-radius: 4px;
            outline: none;
        }
        .copy-btn:hover {
            color: var(--primary-color);
        }
        .copy-btn.copied {
            color: #10b981;
        }

        .output {
            padding: 12px;
            min-height: 80px;
            font-size: 14px;
            line-height: 1.6;
            white-space: pre-wrap;
            word-break: break-word;
        }

        /* Shimmer Loading Animation */
        .shimmer {
            background: linear-gradient(90deg, rgba(var(--primary-rgb), 0.02) 25%, rgba(var(--primary-rgb), 0.06) 50%, rgba(var(--primary-rgb), 0.02) 75%);
            background-size: 200% 100%;
            animation: loading-shimmer 1.5s infinite;
        }
        @keyframes loading-shimmer {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        .animate-spin {
            animation: spin 1s linear infinite;
            display: inline-block;
        }
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .password-gate {
            text-align: center;
            padding: 24px 0;
        }
        .lock-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: rgba(var(--primary-rgb), 0.1);
            color: var(--primary-color);
            font-size: 20px;
            margin-bottom: 12px;
        }
        .password-gate h3 {
            font-family: 'Outfit', sans-serif;
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 6px;
        }
        .password-gate p {
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 16px;
        }
        .password-gate input {
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid var(--input-border);
            font-size: 14px;
            margin-bottom: 8px;
            width: 100%;
            text-align: center;
            background: var(--input-bg);
            color: inherit;
            outline: none;
            transition: border-color 0.2s;
        }
        .password-gate input:focus {
            border-color: var(--primary-color);
        }
        .error { color: #ef4444; font-size: 12px; margin-top: 4px; font-weight: 500; }
        
        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 11px;
            color: var(--text-muted);
            opacity: 0.8;
        }
        .footer a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }
        .footer a:hover {
            text-decoration: underline;
        }

        .close-preview-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--card-bg);
            border: 1px solid var(--input-border);
            color: var(--text-color);
            display: none;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-size: 20px;
            transition: all 0.2s ease;
            z-index: 9999;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        .close-preview-btn:hover {
            background: var(--input-bg);
            transform: scale(1.05);
            color: var(--primary-color);
        }

        /* Shake animation for errors */
        .shake {
            animation: shake 0.4s ease-in-out;
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-6px); }
            40%, 80% { transform: translateX(6px); }
        }
    </style>
</head>
<body>
<a href="/user/dashboard/tool-embeds" id="closeBtn" class="close-preview-btn" title="{{ translate('Close Preview') }}" onclick="if(window.history.length > 1) { window.close(); return false; }">
    <i class="ti ti-x"></i>
</a>
<div class="container">
    <div class="card-container">
        @if($requiresPassword)
        <div class="password-gate" id="passwordGate">
            <div class="lock-icon">
                <i class="ti ti-lock"></i>
            </div>
            <h3>{{ translate('Password Protected') }}</h3>
            <p>{{ translate('Please enter the password to access this tool.') }}</p>
            <input type="password" id="pwInput" placeholder="{{ translate('Enter password') }}" onkeydown="if(event.key === 'Enter') unlock()">
            <div id="pwError" class="error"></div>
            <button class="btn" onclick="unlock()" style="margin-top: 8px">{{ translate('Unlock Tool') }}</button>
        </div>
        @endif

        <div id="mainForm" style="{{ $requiresPassword ? 'display:none' : '' }}">
            <div class="embed-header">
                <div class="tool-icon">
                    <i class="{{ $tool->icon || 'ti ti-wand' }}"></i>
                </div>
                <h3>{{ $embed->label ?: $tool->name }}</h3>
            </div>

            @foreach(($tool->fields ?? []) as $field)
            @php 
                $key = $field['name'] ?? $field['key'] ?? $field['id'] ?? 'field_' . $loop->index; 
                $type = $field['type'] ?? 'text';
            @endphp
            <div class="field">
                <label>{{ $field['label'] ?? ucfirst(str_replace('_', ' ', $key)) }}</label>
                
                @if($type === 'textarea' || $type === 'code_input')
                <textarea id="field_{{ $key }}" name="{{ $key }}" rows="{{ $type === 'code_input' ? 8 : ($field['rows'] ?? 3) }}" placeholder="{{ $field['placeholder'] ?? '' }}"></textarea>
                
                @elseif(in_array($type, ['select', 'tone_select', 'language_select', 'length_select', 'model_select']))
                <select id="field_{{ $key }}" name="{{ $key }}">
                    @if(!empty($field['placeholder']))
                    <option value="" disabled selected>{{ $field['placeholder'] }}</option>
                    @endif
                    @foreach(getNormalizedOptions($field, $languages, $models) as $option)
                    <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                    @endforeach
                </select>
                
                @elseif($type === 'toggle')
                <div style="display: flex; align-items: center; min-height: 40px;">
                    <label class="toggle-container" style="display: flex; align-items: center; gap: 8px; font-size: 14px; cursor: pointer; user-select: none;">
                        <input type="checkbox" id="field_{{ $key }}" name="{{ $key }}" style="width: 18px; height: 18px; cursor: pointer; accent-color: var(--primary-color);">
                        <span>{{ translate('Enabled') }}</span>
                    </label>
                </div>

                @elseif($type === 'slider')
                <div style="display: flex; flex-direction: column; gap: 6px;">
                    <input type="range" id="field_{{ $key }}" name="{{ $key }}" min="{{ $field['min'] ?? 0 }}" max="{{ $field['max'] ?? 1 }}" step="{{ $field['step'] ?? 0.1 }}" style="cursor: pointer; accent-color: var(--primary-color); height: 8px; padding: 0;">
                    <div style="font-size: 12px; color: var(--text-muted);">{{ $field['default'] ?? ($field['min'] ?? 0) }}</div>
                </div>

                @else
                <input type="{{ $type === 'url' ? 'url' : ($type === 'number' ? 'number' : ($type === 'date' ? 'date' : ($type === 'datetime_local' ? 'datetime-local' : ($type === 'color' ? 'color' : 'text')))) }}" 
                       id="field_{{ $key }}" name="{{ $key }}"
                       placeholder="{{ $field['placeholder'] ?? '' }}">
                @endif
            </div>
            @endforeach

            <button class="btn" id="generateBtn" onclick="generate()">
                <i class="ti ti-sparkles"></i> {{ translate('Generate') }}
            </button>

            <div class="output-wrapper" id="outputWrapper" style="display: none;">
                <div class="output-header">
                    <span><i class="ti ti-align-left" style="margin-right: 4px;"></i>{{ translate('Result') }}</span>
                    <button class="copy-btn" id="copyBtn" onclick="copyOutput()">
                        <i class="ti ti-copy"></i> <span class="copy-label">{{ translate('Copy') }}</span>
                    </button>
                </div>
                <div class="output" id="output"></div>
            </div>

            @if($embed->show_branding)
            <div class="footer">
                {{ translate('Powered by') }} <a href="/" target="_blank">{{ $appName }}</a>
            </div>
            @endif
        </div>
    </div>
</div>

<script>
const TOKEN = @js($embed->token);
const ORIGIN = '{{ request()->getHttpHost() }}';

// Grant returned by /unlock. The server requires it on /run for a password-protected
// embed — a session flag cannot work here, since this page runs in a third-party
// iframe where the SameSite=Lax session cookie is never sent back.
let UNLOCK_TOKEN = '';

function notifyResize() {
    setTimeout(() => {
        window.parent.postMessage({ type: 'makeai-embed-resize', height: document.body.scrollHeight }, '*');
    }, 100);
}

// Initial resize notification
window.addEventListener('load', notifyResize);

// Slider range value updates
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('input[type="range"]').forEach(el => {
        el.addEventListener('input', (e) => {
            const valDiv = el.nextElementSibling;
            if (valDiv) valDiv.textContent = e.target.value;
        });
    });
});

@if($requiresPassword)
async function unlock() {
    const pwInput = document.getElementById('pwInput');
    const pw = pwInput.value;
    const err = document.getElementById('pwError');
    const gate = document.getElementById('passwordGate');
    
    try {
        err.textContent = '';
        gate.classList.remove('shake');
        
        const res = await fetch('/embed/' + TOKEN + '/unlock', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ password: pw }),
        });
        
        if (!res.ok) {
            gate.classList.add('shake');
            err.textContent = res.status === 419 ? 'Session expired. Please reload.' : 'Incorrect password';
            return;
        }

        const data = await res.json();
        UNLOCK_TOKEN = data.unlock_token || '';

        document.getElementById('passwordGate').style.display = 'none';
        document.getElementById('mainForm').style.display = '';
        notifyResize();
    } catch (e) {
        gate.classList.add('shake');
        err.textContent = 'Connection error. Please try again.';
    }
}
@endif

function copyOutput() {
    const outputText = document.getElementById('output').innerText;
    if (!outputText) return;
    
    const showSuccess = () => {
        const copyBtn = document.getElementById('copyBtn');
        const icon = copyBtn.querySelector('i');
        const label = copyBtn.querySelector('.copy-label');
        
        icon.className = 'ti ti-check';
        label.textContent = '{{ translate("Copied!") }}';
        copyBtn.classList.add('copied');
        
        setTimeout(() => {
            icon.className = 'ti ti-copy';
            label.textContent = '{{ translate("Copy") }}';
            copyBtn.classList.remove('copied');
        }, 2000);
    };

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(outputText).then(() => {
            showSuccess();
            window.parent.postMessage({ type: 'makeai-embed-copy', text: outputText }, '*');
        }).catch(() => {
            fallbackCopy(outputText, showSuccess);
        });
    } else {
        fallbackCopy(outputText, showSuccess);
    }
}

function fallbackCopy(text, onSuccess) {
    let copied = false;
    try {
        const textArea = document.createElement("textarea");
        textArea.value = text;
        textArea.style.position = "fixed";
        textArea.style.top = "-9999px";
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        copied = document.execCommand('copy');
        document.body.removeChild(textArea);
    } catch (e) {
        copied = false;
    }

    if (copied) {
        onSuccess();
    }
    
    window.parent.postMessage({ type: 'makeai-embed-copy', text: text }, '*');
    
    if (!copied) {
        onSuccess();
    }
}

async function generate() {
    const btn = document.getElementById('generateBtn');
    const output = document.getElementById('output');
    const outputWrapper = document.getElementById('outputWrapper');
    const fields = {};

    document.querySelectorAll('#mainForm input, #mainForm textarea, #mainForm select').forEach(el => {
        if (el.name) {
            if (el.type === 'checkbox') {
                fields[el.name] = el.checked;
            } else {
                fields[el.name] = el.value;
            }
        }
    });

    btn.disabled = true;
    btn.innerHTML = '<i class="ti ti-loader-3 animate-spin"></i> {{ translate("Generating...") }}';
    
    outputWrapper.style.display = '';
    output.classList.add('shimmer');
    output.textContent = '{{ translate("Processing your request...") }}';
    notifyResize();
    
    let content = '';

    try {
        const res = await fetch('/embed/' + TOKEN + '/run', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ fields, unlock_token: UNLOCK_TOKEN }),
        });

        if (!res.ok) {
            output.classList.remove('shimmer');
            output.textContent = res.status === 403
                ? 'Error: This tool is locked. Please reload and enter the password.'
                : 'Error: Generation failed.';
            return;
        }

        const reader = res.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';
        output.textContent = '';
        output.classList.remove('shimmer');

        while (true) {
            const { done, value } = await reader.read();
            if (done) break;
            buffer += decoder.decode(value, { stream: true });
            const lines = buffer.split('\n');
            buffer = lines.pop() || '';
            for (const line of lines) {
                if (line.startsWith('data: ') && line.slice(6) !== '[DONE]') {
                    try {
                        const data = JSON.parse(line.slice(6));
                        if (data.token) { 
                            content += data.token; 
                            output.textContent = content; 
                            notifyResize();
                        }
                    } catch {}
                }
            }
        }
    } catch (err) {
        output.classList.remove('shimmer');
        output.textContent = 'Error: ' + err.message;
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="ti ti-sparkles"></i> {{ translate("Generate") }}';
        notifyResize();
    }
}

// Show close preview button if loaded outside an iframe (directly in browser)
if (window.self === window.top) {
    const closeBtn = document.getElementById('closeBtn');
    if (closeBtn) closeBtn.style.display = 'flex';
}
</script>
</body>
</html>
