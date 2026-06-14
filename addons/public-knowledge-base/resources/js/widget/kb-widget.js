(function () {
  'use strict'

  const script = document.currentScript
  const kbUrl = script.getAttribute('data-kb-url') || ''
  const accent = script.getAttribute('data-accent') || '#10b981'

  const css = `
  #kb-widget-root { --kb-accent: ${accent}; font-family: system-ui, -apple-system, sans-serif; }
  #kb-widget-fab {
    position: fixed; bottom: 24px; right: 24px; z-index: 99998;
    width: 56px; height: 56px; border-radius: 50%; border: none;
    background: var(--kb-accent); color: #fff; font-size: 24px;
    cursor: pointer; box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    display: flex; align-items: center; justify-content: center;
  }
  #kb-widget-panel {
    position: fixed; bottom: 96px; right: 24px; z-index: 99999;
    width: 380px; max-width: calc(100vw - 48px); height: 520px; max-height: calc(100vh - 120px);
    background: #fff; border-radius: 12px; box-shadow: 0 8px 32px rgba(0,0,0,0.15);
    display: flex; flex-direction: column; overflow: hidden;
  }
  #kb-widget-panel[hidden] { display: none; }
  .kb-header {
    background: var(--kb-accent); color: #fff; padding: 12px 16px;
    display: flex; justify-content: space-between; align-items: center; font-weight: 600;
  }
  .kb-close { background: none; border: none; color: #fff; font-size: 18px; cursor: pointer; }
  .kb-body { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
  .kb-input-wrap { padding: 12px; border-top: 1px solid #e5e7eb; }
  .kb-input {
    width: 100%; padding: 10px 14px; border: 1px solid #d1d5db; border-radius: 8px;
    font-size: 14px; outline: none; box-sizing: border-box;
  }
  .kb-input:focus { border-color: var(--kb-accent); }
  .kb-results { flex: 1; overflow-y: auto; padding: 12px; font-size: 14px; }
  .kb-source {
    display: block; padding: 8px; margin-bottom: 6px; border: 1px solid #e5e7eb;
    border-radius: 8px; color: inherit; text-decoration: none; font-size: 13px;
  }
  .kb-source:hover { border-color: var(--kb-accent); }
  .kb-answer { margin-top: 8px; line-height: 1.6; white-space: pre-wrap; }
  .kb-spinner { display: inline-block; width: 16px; height: 16px; border: 2px solid #d1d5db; border-top-color: var(--kb-accent); border-radius: 50%; animation: kb-spin 0.6s linear infinite; }
  @keyframes kb-spin { to { transform: rotate(360deg); } }
  @media (prefers-color-scheme: dark) {
    #kb-widget-panel { background: #1f2937; color: #f3f4f6; }
    .kb-input { background: #374151; border-color: #4b5563; color: #f3f4f6; }
    .kb-source { border-color: #374151; }
    .kb-input-wrap { border-color: #374151; }
  }
  `

  const style = document.createElement('style')
  style.textContent = css
  document.head.appendChild(style)

  const root = document.createElement('div')
  root.id = 'kb-widget-root'
  root.innerHTML = `
    <button id="kb-widget-fab">?</button>
    <div id="kb-widget-panel" hidden>
      <div class="kb-header"><span>Help Center</span><button class="kb-close">&times;</button></div>
      <div class="kb-body">
        <div class="kb-results"><p style="color:#9ca3af;font-size:13px">Type a question below to search our help articles.</p></div>
        <div class="kb-input-wrap"><input class="kb-input" type="text" placeholder="Ask a question..." /></div>
      </div>
    </div>
  `
  document.body.appendChild(root)

  const fab = root.querySelector('#kb-widget-fab') as HTMLElement
  const panel = root.querySelector('#kb-widget-panel') as HTMLElement
  const closeBtn = root.querySelector('.kb-close') as HTMLElement
  const input = root.querySelector('.kb-input') as HTMLInputElement
  const results = root.querySelector('.kb-results') as HTMLElement

  let widgetSession = sessionStorage.getItem('kb_widget_session') || ''
  if (!widgetSession) {
    widgetSession = Math.random().toString(36).substring(2) + Date.now().toString(36)
    sessionStorage.setItem('kb_widget_session', widgetSession)
  }

  let abortCtrl: AbortController | null = null
  let debounceTimer: ReturnType<typeof setTimeout>

  fab.addEventListener('click', () => {
    panel.hidden = !panel.hidden
    if (!panel.hidden) input.focus()
  })

  closeBtn.addEventListener('click', () => { panel.hidden = true })

  input.addEventListener('input', () => {
    clearTimeout(debounceTimer)
    debounceTimer = setTimeout(doSearch, 600)
  })

  input.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') { clearTimeout(debounceTimer); doSearch() }
  })

  async function doSearch() {
    const query = input.value.trim()
    if (query.length < 2) return

    if (abortCtrl) abortCtrl.abort()
    abortCtrl = new AbortController()

    results.innerHTML = '<div class="kb-spinner"></div>'

    try {
      const res = await fetch(kbUrl + '/search', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ query, widget_session: widgetSession }),
        signal: abortCtrl.signal,
      })

      const reader = res.body!.getReader()
      const decoder = new TextDecoder()
      let buffer = ''
      let answerText = ''

      results.innerHTML = ''

      while (true) {
        const { done, value } = await reader.read()
        if (done) break
        buffer += decoder.decode(value, { stream: true })
        const lines = buffer.split('\n')
        buffer = lines.pop()!

        for (const line of lines) {
          if (!line.trim()) continue
          try {
            const event = JSON.parse(line)
            if (event.type === 'sources' && event.articles && event.articles.length > 0) {
              results.innerHTML = ''
              for (const a of event.articles) {
                const link = document.createElement('a')
                link.className = 'kb-source'
                link.href = a.slug ? '/' + a.slug : '#'
                link.target = '_blank'
                link.innerHTML = `<strong>${escapeHtml(a.title)}</strong><br><span style="color:#6b7280">${escapeHtml(a.excerpt || '')}</span>`
                results.appendChild(link)
              }
            } else if (event.type === 'delta') {
              answerText += event.text
              const ansEl = results.querySelector('.kb-answer')
              if (ansEl) {
                ansEl.textContent = answerText
              } else {
                const div = document.createElement('div')
                div.className = 'kb-answer'
                div.textContent = answerText
                results.appendChild(div)
              }
            }
          } catch {}
        }
      }
    } catch {
      if (!results.querySelector('.kb-answer')) {
        results.innerHTML = '<p style="color:#9ca3af;font-size:13px">Search unavailable. Please try again.</p>'
      }
    }
  }

  function escapeHtml(str: string): string {
    const div = document.createElement('div')
    div.textContent = str
    return div.innerHTML
  }
})()
