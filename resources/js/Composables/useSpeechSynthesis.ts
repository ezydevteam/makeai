import { ref, onUnmounted } from 'vue'

export function useSpeechSynthesis() {
    const isSpeaking = ref(false)
    const isPaused = ref(false)
    const isSupported = ref(false)
    const error = ref<string | null>(null)
    const currentTextId = ref<string | null>(null)

    if (typeof window !== 'undefined') {
        isSupported.value = 'speechSynthesis' in window
    }

    function speak(text: string, id: string) {
        if (!isSupported.value) {
            error.value = 'Speech synthesis is not supported in this browser'
            return
        }

        // If already speaking this text, stop it
        if (currentTextId.value === id && isSpeaking.value) {
            stop()
            return
        }

        // Stop any current speech
        stop()

        const utterance = new SpeechSynthesisUtterance(text)
        utterance.rate = 1.0
        utterance.pitch = 1.0
        utterance.volume = 1.0

        utterance.onstart = () => {
            isSpeaking.value = true
            isPaused.value = false
            currentTextId.value = id
        }

        utterance.onend = () => {
            isSpeaking.value = false
            isPaused.value = false
            currentTextId.value = null
        }

        utterance.onerror = (event) => {
            if (event.error !== 'canceled') {
                error.value = `Speech synthesis error: ${event.error}`
            }
            isSpeaking.value = false
            isPaused.value = false
            currentTextId.value = null
        }

        window.speechSynthesis.speak(utterance)
    }

    function pause() {
        if (isSpeaking.value && !isPaused.value) {
            window.speechSynthesis.pause()
            isPaused.value = true
        }
    }

    function resume() {
        if (isPaused.value) {
            window.speechSynthesis.resume()
            isPaused.value = false
        }
    }

    function stop() {
        window.speechSynthesis.cancel()
        isSpeaking.value = false
        isPaused.value = false
        currentTextId.value = null
    }

    onUnmounted(() => {
        stop()
    })

    return {
        isSpeaking,
        isPaused,
        isSupported,
        error,
        currentTextId,
        speak,
        pause,
        resume,
        stop,
    }
}
