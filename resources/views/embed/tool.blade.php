<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $tool->name }} — {{ $appName }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: {{ $embed->theme === 'dark' ? '#111827' : '#ffffff' }};
            color: {{ $embed->theme === 'dark' ? '#e5e7eb' : '#1f2937' }};
            padding: 16px;
            min-height: 100vh;
        }
        .container { max-width: 640px; margin: 0 auto; }
        .field { margin-bottom: 12px; }
        .field label { display: block; font-size: 13px; font-weight: 500; margin-bottom: 4px; opacity: 0.8; }
        .field input, .field textarea, .field select {
            width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid {{ $embed->theme === 'dark' ? '#374151' : '#d1d5db' }};
            background: {{ $embed->theme === 'dark' ? '#1f2937' : '#f9fafb' }};
            color: inherit; font-size: 14px; outline: none;
        }
        .field input:focus, .field textarea:focus {
            border-color: {{ $embed->primary_color ?? '#10b981' }};
            box-shadow: 0 0 0 2px {{ $embed->primary_color ?? '#10b981' }}33;
        }
        .btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 10px 20px; border-radius: 8px; border: none;
            background: {{ $embed->primary_color ?? '#10b981' }}; color: #fff;
            font-size: 14px; font-weight: 600; cursor: pointer; transition: opacity 0.2s;
        }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn:hover:not(:disabled) { opacity: 0.9; }
        .output {
            margin-top: 16px; padding: 12px; border-radius: 8px;
            background: {{ $embed->theme === 'dark' ? '#1f2937' : '#f3f4f6' }};
            min-height: 80px; white-space: pre-wrap; font-size: 14px; line-height: 1.6;
        }
        .footer {
            margin-top: 20px; text-align: center; font-size: 11px; opacity: 0.5;
        }
        .password-gate { text-align: center; padding: 40px 0; }
        .password-gate input {
            padding: 8px 12px; border-radius: 8px; border: 1px solid #d1d5db; font-size: 14px;
            margin-bottom: 8px;
        }
        .error { color: #ef4444; font-size: 13px; margin-top: 8px; }
    </style>
</head>
<body>
<div class="container">
    @if($requiresPassword)
    <div class="password-gate" id="passwordGate">
        <h3 style="margin-bottom:16px">This tool is password-protected</h3>
        <input type="password" id="pwInput" placeholder="Enter password" style="display:block;width:100%">
        <div id="pwError" class="error"></div>
        <button class="btn" onclick="unlock()" style="margin-top:8px">Unlock</button>
    </div>
    @endif

    <div id="mainForm" style="{{ $requiresPassword ? 'display:none' : '' }}">
        @foreach(($tool->fields ?? []) as $field)
        @php $key = $field['name'] ?? $field['key'] ?? $field['id'] ?? 'field_' . $loop->index; @endphp
        <div class="field">
            <label>{{ $field['label'] ?? ucfirst(str_replace('_', ' ', $key)) }}</label>
            @if(($field['type'] ?? 'text') === 'textarea')
            <textarea id="field_{{ $key }}" name="{{ $key }}" rows="3" placeholder="{{ $field['placeholder'] ?? '' }}"></textarea>
            @elseif(($field['type'] ?? 'text') === 'select')
            <select id="field_{{ $key }}" name="{{ $key }}">
                @foreach($field['options'] ?? [] as $option)
                <option value="{{ is_array($option) ? ($option['value'] ?? $option) : $option }}">
                    {{ is_array($option) ? ($option['label'] ?? $option['value'] ?? $option) : $option }}
                </option>
                @endforeach
            </select>
            @else
            <input type="{{ $field['type'] ?? 'text' }}" id="field_{{ $key }}" name="{{ $key }}"
                   placeholder="{{ $field['placeholder'] ?? '' }}">
            @endif
        </div>
        @endforeach

        <button class="btn" id="generateBtn" onclick="generate()">
            Generate
        </button>

        <div class="output" id="output"></div>

        @if($embed->show_branding)
        <div class="footer">Powered by {{ $appName }}</div>
        @endif
    </div>
</div>

<script>
const TOKEN = '{{ $token }}';
const ORIGIN = '{{ request()->getHttpHost() }}';

@if($requiresPassword)
async function unlock() {
    const pw = document.getElementById('pwInput').value;
    const err = document.getElementById('pwError');
    try {
        const res = await fetch('/embed/' + TOKEN + '/unlock', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ password: pw }),
        });
        if (!res.ok) { err.textContent = 'Incorrect password'; return; }
        document.getElementById('passwordGate').style.display = 'none';
        document.getElementById('mainForm').style.display = '';
    } catch (e) {
        err.textContent = 'Error. Try again.';
    }
}
@endif

async function generate() {
    const btn = document.getElementById('generateBtn');
    const output = document.getElementById('output');
    const fields = {};

    document.querySelectorAll('#mainForm input, #mainForm textarea, #mainForm select').forEach(el => {
        fields[el.name] = el.value;
    });

    btn.disabled = true;
    btn.textContent = 'Generating...';
    output.textContent = '';
    let content = '';

    try {
        const res = await fetch('/embed/' + TOKEN + '/run', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ fields }),
        });

        const reader = res.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';

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
                        if (data.token) { content += data.token; output.textContent = content; }
                    } catch {}
                }
            }
        }
    } catch (err) {
        output.textContent = 'Error: ' + err.message;
    } finally {
        btn.disabled = false;
        btn.textContent = 'Generate';
    }

    // Notify parent of height change
    window.parent.postMessage({ type: 'makeai-embed-resize', height: document.body.scrollHeight }, '*');
}
</script>
</body>
</html>
