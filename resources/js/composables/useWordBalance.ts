import { ref, computed, onMounted, onUnmounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

export interface WordBalance {
    balance: number
    formatted_balance: string
    total_purchased: number
    total_used: number
    bonus_received: number
    total_allocated: number
    percentage_used: number
    percentage_remaining: number
}

export interface BalanceCheckResult {
    canProceed: boolean
    balance: number
    required: number
    shortage: number
}

// Shared reactive balance state for real-time updates
const sharedBalanceOverride = ref<WordBalance | null>(null)
let balanceChannelSubscribed = false
let subscriberCount = 0
let subscribedUserId: number | null = null

/**
 * Initialize the balance WebSocket listener
 * Uses the global window.Echo instance
 */
function initBalanceListener(userId: number): void {
    subscriberCount++
    console.log(`[WordBalance] Init listener called, subscriber count: ${subscriberCount}, already subscribed: ${balanceChannelSubscribed}`)

    if (balanceChannelSubscribed) {
        console.log('[WordBalance] Already subscribed, skipping')
        return
    }

    if (!window.Echo) {
        console.warn('[WordBalance] window.Echo not available')
        return
    }

    try {
        console.log(`[WordBalance] Subscribing to private channel: user.${userId}`)
        const channel = window.Echo.private(`user.${userId}`)

        channel.listen('.balance.updated', (event: { balance: WordBalance; reason: string; timestamp: string }) => {
            console.log('💰 [WordBalance] Event received:', event)
            console.log(`💰 [WordBalance] New balance: ${event.balance.formatted_balance} (reason: ${event.reason})`)
            sharedBalanceOverride.value = event.balance
        })

        // Log subscription events
        channel.subscribed(() => {
            console.log(`✅ [WordBalance] Successfully subscribed to user.${userId} channel`)
        })

        channel.error((error: any) => {
            console.error('[WordBalance] Channel error:', error)
        })

        balanceChannelSubscribed = true
        subscribedUserId = userId
        console.log(`✅ [WordBalance] Listener registered for user.${userId}`)
    } catch (error) {
        console.error('[WordBalance] Failed to subscribe to balance channel:', error)
    }
}

/**
 * Clean up the balance WebSocket listener
 * Only actually unsubscribes when the last subscriber unmounts
 */
function cleanupBalanceListener(): void {
    subscriberCount--

    // Only unsubscribe when no more subscribers
    if (subscriberCount > 0 || !balanceChannelSubscribed || !window.Echo || !subscribedUserId) return

    try {
        window.Echo.leave(`user.${subscribedUserId}`)
        balanceChannelSubscribed = false
        subscribedUserId = null
        // Don't clear the override - it will be refreshed on next mount
        console.log('🔌 Unsubscribed from balance updates channel')
    } catch (error) {
        console.error('Failed to unsubscribe from balance channel:', error)
    }
}

/**
 * Composable for managing word balance state and checks
 */
export function useWordBalance() {
    const page = usePage()

    // Reactive state
    const isLoading = ref(false)
    const showPurchaseModal = ref(false)
    const requiredWordsForModal = ref(0)
    const actionDescriptionForModal = ref('')

    // Get user ID for WebSocket subscription
    const userId = computed(() => {
        const auth = page.props.auth as any
        return auth?.user?.id ?? null
    })

    // Get current balance - prefer WebSocket override, fallback to page props
    const wordBalance = computed<WordBalance | null>(() => {
        // Use WebSocket-updated balance if available
        if (sharedBalanceOverride.value) {
            return sharedBalanceOverride.value
        }
        // Fallback to page props
        const auth = page.props.auth as any
        return auth?.user?.word_balance_data ?? null
    })

    const balance = computed(() => wordBalance.value?.balance ?? 0)
    const formattedBalance = computed(() => wordBalance.value?.formatted_balance ?? '0')

    const hasWords = computed(() => balance.value > 0)

    const isLowBalance = computed(() => {
        if (!wordBalance.value) return false
        return wordBalance.value.percentage_remaining < 20
    })

    /**
     * Check if user has enough words for an action
     */
    const checkBalance = (requiredWords: number): BalanceCheckResult => {
        const currentBalance = balance.value
        const canProceed = currentBalance >= requiredWords

        return {
            canProceed,
            balance: currentBalance,
            required: requiredWords,
            shortage: canProceed ? 0 : requiredWords - currentBalance,
        }
    }

    /**
     * Check balance and show modal if insufficient
     * Returns true if user can proceed, false if modal is shown
     */
    const checkAndPrompt = (requiredWords: number, action: string = 'continue'): boolean => {
        const result = checkBalance(requiredWords)

        if (!result.canProceed) {
            requiredWordsForModal.value = requiredWords
            actionDescriptionForModal.value = action
            showPurchaseModal.value = true
            return false
        }

        return true
    }

    /**
     * Fetch fresh balance from server
     */
    const refreshBalance = async (): Promise<WordBalance | null> => {
        isLoading.value = true

        try {
            const response = await fetch(route('api.balance'))
            const data = await response.json()

            // Update page props (this is a bit of a hack, ideally use Inertia reload)
            if (data.balance) {
                const auth = page.props.auth as any
                if (auth?.user) {
                    auth.user.word_balance_data = data.balance
                }
            }

            return data.balance
        } catch (error) {
            console.error('Failed to fetch balance:', error)
            return null
        } finally {
            isLoading.value = false
        }
    }

    /**
     * Estimate words for common actions
     */
    const estimates = {
        chapter: (targetWords: number) => Math.ceil(targetWords * 1.1),
        suggestion: () => 200,
        chat: () => 500,
        defense: () => 1000,
        expand: () => 300,
        rephrase: () => 150,
    }

    /**
     * Close purchase modal
     */
    const closePurchaseModal = () => {
        showPurchaseModal.value = false
        requiredWordsForModal.value = 0
        actionDescriptionForModal.value = ''
    }

    // Initialize WebSocket listener on mount
    onMounted(() => {
        if (userId.value) {
            initBalanceListener(userId.value)
        }
    })

    // Cleanup WebSocket listener on unmount
    onUnmounted(() => {
        cleanupBalanceListener()
    })

    return {
        // State
        wordBalance,
        balance,
        formattedBalance,
        hasWords,
        isLowBalance,
        isLoading,

        // Modal state
        showPurchaseModal,
        requiredWordsForModal,
        actionDescriptionForModal,

        // Methods
        checkBalance,
        checkAndPrompt,
        refreshBalance,
        closePurchaseModal,

        // Estimates
        estimates,
    }
}

/**
 * Hook for deducting words after an action
 * Call this in your API service after successful generation
 */
export async function recordWordUsage(
    wordsUsed: number,
    description: string,
    referenceType: string,
    referenceId?: number
): Promise<void> {
    try {
        await fetch(route('api.words.record-usage'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                words: wordsUsed,
                description,
                reference_type: referenceType,
                reference_id: referenceId,
            }),
        })
    } catch (error) {
        console.error('Failed to record word usage:', error)
    }
}
