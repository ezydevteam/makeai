import { reactive } from 'vue'

interface PanelState {
  provider: string
  model: string
  systemPrompt: string
  temperature: number
  maxTokens: number
  topP: number
  output: string
  tokens: { input: number; output: number }
  cost: number
  streaming: boolean
}

interface HistoryItem {
  id: string
  prompt: string
  outputLeft: string
  outputRight: string
  paramsLeft: Record<string, unknown>
  paramsRight: Record<string, unknown>
  createdAt: string
}

const defaultPanel = (provider = 'openai', model = 'gpt-4o-mini'): PanelState => ({
  provider,
  model,
  systemPrompt: '',
  temperature: 0.7,
  maxTokens: 1000,
  topP: 1.0,
  output: '',
  tokens: { input: 0, output: 0 },
  cost: 0,
  streaming: false,
})

export const playgroundState = reactive({
  leftPanel: defaultPanel(),
  rightPanel: defaultPanel(),
  sharedMessage: '',
  syncPanels: false,
  history: [] as HistoryItem[],
  historyMax: 20,
})

function loadHistory() {
  try {
    const stored = localStorage.getItem('playground_history')
    if (stored) playgroundState.history = JSON.parse(stored).slice(0, playgroundState.historyMax)
  } catch {}
}

function saveHistory() {
  localStorage.setItem('playground_history', JSON.stringify(playgroundState.history))
}

function addToHistory(item: Omit<HistoryItem, 'id' | 'createdAt'>) {
  playgroundState.history.unshift({
    ...item,
    id: crypto.randomUUID(),
    createdAt: new Date().toISOString(),
  })
  if (playgroundState.history.length > playgroundState.historyMax) {
    playgroundState.history.length = playgroundState.historyMax
  }
  saveHistory()
}

export function usePlayground() {
  const runPanel = async (side: 'left' | 'right') => {
    const panel = playgroundState[`${side}Panel`]
    const messages: Array<{ role: string; content: string }> = []

    if (panel.systemPrompt) {
      messages.push({ role: 'system', content: panel.systemPrompt })
    }
    if (playgroundState.sharedMessage) {
      messages.push({ role: 'user', content: playgroundState.sharedMessage })
    }

    if (!playgroundState.sharedMessage) return

    panel.streaming = true
    panel.output = ''

    try {
      const response = await fetch(route('user.dashboard.playground.run'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '' },
        body: JSON.stringify({
          provider: panel.provider,
          model: panel.model,
          messages,
          temperature: panel.temperature,
          max_tokens: panel.maxTokens,
          top_p: panel.topP,
        }),
      })

      const reader = response.body?.getReader()
      if (!reader) return

      const decoder = new TextDecoder()
      let buffer = ''

      while (true) {
        const { done, value } = await reader.read()
        if (done) break

        buffer += decoder.decode(value, { stream: true })
        const lines = buffer.split('\n')
        buffer = lines.pop() || ''

        for (const line of lines) {
          if (line.startsWith('data: ')) {
            const payload = line.slice(6)
            if (payload === '[DONE]') continue

            try {
              const data = JSON.parse(payload)
              if (data.token) panel.output += data.token
              if (data.usage) {
                panel.tokens = { input: data.usage.input_tokens, output: data.usage.output_tokens }
              }
              if (data.done) {
                panel.tokens = { input: data.input_tokens, output: data.output_tokens }
              }
            } catch {}
          }
        }
      }
    } catch (err) {
      panel.output += '\n\n[Error: failed to connect]'
    } finally {
      panel.streaming = false
    }
  }

  const runBoth = async () => {
    playgroundState.rightPanel.systemPrompt = playgroundState.syncPanels
      ? playgroundState.leftPanel.systemPrompt
      : playgroundState.rightPanel.systemPrompt

    await Promise.all([runPanel('left'), runPanel('right')])

    addToHistory({
      prompt: playgroundState.sharedMessage,
      outputLeft: playgroundState.leftPanel.output,
      outputRight: playgroundState.rightPanel.output,
      paramsLeft: {
        provider: playgroundState.leftPanel.provider,
        model: playgroundState.leftPanel.model,
        temperature: playgroundState.leftPanel.temperature,
      },
      paramsRight: {
        provider: playgroundState.rightPanel.provider,
        model: playgroundState.rightPanel.model,
        temperature: playgroundState.rightPanel.temperature,
      },
    })
  }

  const shareSnapshot = async () => {
    try {
      const res = await fetch(route('user.dashboard.playground.share'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '' },
        body: JSON.stringify({
          prompt: playgroundState.sharedMessage,
          params_left: {
            provider: playgroundState.leftPanel.provider,
            model: playgroundState.leftPanel.model,
            temperature: playgroundState.leftPanel.temperature,
          },
          params_right: {
            provider: playgroundState.rightPanel.provider,
            model: playgroundState.rightPanel.model,
            temperature: playgroundState.rightPanel.temperature,
          },
          output_left: playgroundState.leftPanel.output.substring(0, 5000),
          output_right: playgroundState.rightPanel.output.substring(0, 5000),
        }),
      })
      const data = await res.json()
      await navigator.clipboard.writeText(window.location.origin + data.url)
      return data.url
    } catch {
      return null
    }
  }

  const clearOutputs = () => {
    playgroundState.leftPanel.output = ''
    playgroundState.rightPanel.output = ''
    playgroundState.leftPanel.tokens = { input: 0, output: 0 }
    playgroundState.rightPanel.tokens = { input: 0, output: 0 }
  }

  // Initialize
  loadHistory()

  return { playgroundState, runPanel, runBoth, shareSnapshot, clearOutputs }
}
