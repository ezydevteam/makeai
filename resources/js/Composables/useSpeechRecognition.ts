import { ref, onUnmounted } from 'vue'

// Type declarations for Web Speech API
interface SpeechRecognitionEvent extends Event {
    results: SpeechRecognitionResultList
    resultIndex: number
}

interface SpeechRecognitionResultList {
    length: number
    [index: number]: SpeechRecognitionResult
}

interface SpeechRecognitionResult {
    isFinal: boolean
    length: number
    [index: number]: SpeechRecognitionAlternative
}

interface SpeechRecognitionAlternative {
    transcript: string
    confidence: number
}

interface SpeechRecognitionErrorEvent extends Event {
    error: string
    message: string
}

interface SpeechRecognition extends EventTarget {
    continuous: boolean
    interimResults: boolean
    lang: string
    start(): void
    stop(): void
    abort(): void
    onresult: ((event: SpeechRecognitionEvent) => void) | null
    onerror: ((event: SpeechRecognitionErrorEvent) => void) | null
    onend: (() => void) | null
}

declare global {
    interface Window {
        SpeechRecognition: new () => SpeechRecognition
        webkitSpeechRecognition: new () => SpeechRecognition
    }
}

export function useSpeechRecognition() {
    const isRecording = ref(false)
    const transcript = ref('')
    const isSupported = ref(false)
    const error = ref<string | null>(null)

    let recognition: SpeechRecognition | null = null

    // Check if SpeechRecognition is supported
    if (typeof window !== 'undefined') {
        const SpeechRecognitionCtor = window.SpeechRecognition || window.webkitSpeechRecognition
        isSupported.value = !!SpeechRecognitionCtor

        if (SpeechRecognitionCtor) {
            recognition = new SpeechRecognitionCtor()
            recognition.continuous = false
            recognition.interimResults = true
            recognition.lang = 'en-US'

            recognition.onresult = (event: SpeechRecognitionEvent) => {
                let finalTranscript = ''
                let interimTranscript = ''

                for (let i = event.resultIndex; i < event.results.length; i++) {
                    const result = event.results[i]
                    if (result.isFinal) {
                        finalTranscript += result[0].transcript
                    } else {
                        interimTranscript += result[0].transcript
                    }
                }

                transcript.value = finalTranscript || interimTranscript
            }

            recognition.onerror = (event: SpeechRecognitionErrorEvent) => {
                error.value = `Speech recognition error: ${event.error}`
                isRecording.value = false
            }

            recognition.onend = () => {
                isRecording.value = false
            }
        }
    }

    function startRecording() {
        if (!recognition || !isSupported.value) {
            error.value = 'Speech recognition is not supported in this browser'
            return
        }

        error.value = null
        transcript.value = ''

        try {
            recognition.start()
            isRecording.value = true
        } catch (err) {
            error.value = 'Failed to start speech recognition'
            isRecording.value = false
        }
    }

    function stopRecording() {
        if (!recognition) return

        recognition.stop()
        isRecording.value = false
    }

    function resetTranscript() {
        transcript.value = ''
    }

    onUnmounted(() => {
        if (recognition && isRecording.value) {
            recognition.stop()
        }
    })

    return {
        isRecording,
        transcript,
        isSupported,
        error,
        startRecording,
        stopRecording,
        resetTranscript,
    }
}
