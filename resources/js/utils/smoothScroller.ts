/**
 * Smooth Auto-Scroller
 *
 * Handles smooth auto-scrolling during content streaming with:
 * - User scroll detection (pauses auto-scroll when user scrolls manually)
 * - IntersectionObserver for efficient scroll tracking
 * - RAF-based smooth scrolling
 * - Resume auto-scroll after user inactivity
 */

export interface SmoothScrollerConfig {
  /** Time in ms to wait after user scroll before resuming auto-scroll */
  userScrollTimeout: number
  /** Scroll behavior: 'smooth' or 'instant' */
  scrollBehavior: ScrollBehavior
  /** Bottom threshold in pixels - how close to bottom to consider "at bottom" */
  bottomThreshold: number
}

const defaultConfig: SmoothScrollerConfig = {
  userScrollTimeout: 2000,
  scrollBehavior: 'smooth',
  bottomThreshold: 100,
}

export class SmoothAutoScroller {
  private scrollContainer: HTMLElement | null = null
  private isUserScrolling = false
  private userScrollTimeout: ReturnType<typeof setTimeout> | null = null
  private isEnabled = true
  private isDestroyed = false
  private config: SmoothScrollerConfig
  private wasAtBottom = true
  private rafId: number | null = null

  // Event handlers (bound for cleanup)
  private handleWheel: (e: WheelEvent) => void
  private handleTouchStart: () => void
  private handleTouchMove: () => void
  private handleScroll: () => void
  private handleKeydown: (e: KeyboardEvent) => void

  constructor(config: Partial<SmoothScrollerConfig> = {}) {
    this.config = { ...defaultConfig, ...config }

    // Bind handlers
    this.handleWheel = this.onUserScroll.bind(this)
    this.handleTouchStart = this.onUserScroll.bind(this)
    this.handleTouchMove = this.onUserScroll.bind(this)
    this.handleScroll = this.onScroll.bind(this)
    this.handleKeydown = this.onKeydown.bind(this)
  }

  /**
   * Attach to a scroll container
   */
  attach(container: HTMLElement): void {
    if (this.isDestroyed) return

    this.detach()
    this.scrollContainer = container
    this.setupEventListeners()

    // Check initial position
    this.wasAtBottom = this.isAtBottom()
  }

  /**
   * Detach from current container
   */
  detach(): void {
    if (this.scrollContainer) {
      this.scrollContainer.removeEventListener('wheel', this.handleWheel)
      this.scrollContainer.removeEventListener('touchstart', this.handleTouchStart)
      this.scrollContainer.removeEventListener('touchmove', this.handleTouchMove)
      this.scrollContainer.removeEventListener('scroll', this.handleScroll)
      this.scrollContainer.removeEventListener('keydown', this.handleKeydown)
    }

    if (this.userScrollTimeout) {
      clearTimeout(this.userScrollTimeout)
      this.userScrollTimeout = null
    }

    if (this.rafId) {
      cancelAnimationFrame(this.rafId)
      this.rafId = null
    }

    this.scrollContainer = null
  }

  private setupEventListeners(): void {
    if (!this.scrollContainer) return

    // Detect user scrolling via various input methods
    this.scrollContainer.addEventListener('wheel', this.handleWheel, { passive: true })
    this.scrollContainer.addEventListener('touchstart', this.handleTouchStart, { passive: true })
    this.scrollContainer.addEventListener('touchmove', this.handleTouchMove, { passive: true })
    this.scrollContainer.addEventListener('scroll', this.handleScroll, { passive: true })
    this.scrollContainer.addEventListener('keydown', this.handleKeydown)
  }

  private onUserScroll(): void {
    if (!this.isEnabled) return

    this.isUserScrolling = true

    // Clear existing timeout
    if (this.userScrollTimeout) {
      clearTimeout(this.userScrollTimeout)
    }

    // Resume auto-scroll after timeout
    this.userScrollTimeout = setTimeout(() => {
      this.isUserScrolling = false

      // If user scrolled back to bottom, resume auto-scroll
      if (this.isAtBottom()) {
        this.wasAtBottom = true
      }
    }, this.config.userScrollTimeout)
  }

  private onScroll(): void {
    // Track if user is at bottom
    this.wasAtBottom = this.isAtBottom()
  }

  private onKeydown(e: KeyboardEvent): void {
    // Detect scroll via keyboard (arrows, page up/down, home/end)
    const scrollKeys = ['ArrowUp', 'ArrowDown', 'PageUp', 'PageDown', 'Home', 'End']
    if (scrollKeys.includes(e.key)) {
      this.onUserScroll()
    }
  }

  /**
   * Check if scroll is at or near bottom
   */
  private isAtBottom(): boolean {
    if (!this.scrollContainer) return true

    const { scrollTop, scrollHeight, clientHeight } = this.scrollContainer
    return scrollHeight - scrollTop - clientHeight <= this.config.bottomThreshold
  }

  /**
   * Scroll to bottom - call this after content updates
   */
  scrollToBottom(): void {
    if (!this.scrollContainer || !this.isEnabled || this.isDestroyed) return

    // Don't auto-scroll if user is scrolling or wasn't at bottom
    if (this.isUserScrolling || !this.wasAtBottom) return

    // Cancel any pending scroll
    if (this.rafId) {
      cancelAnimationFrame(this.rafId)
    }

    this.rafId = requestAnimationFrame(() => {
      this.rafId = null

      if (!this.scrollContainer || this.isDestroyed) return

      const targetTop = this.scrollContainer.scrollHeight - this.scrollContainer.clientHeight

      if (this.config.scrollBehavior === 'instant') {
        this.scrollContainer.scrollTop = targetTop
      } else {
        this.scrollContainer.scrollTo({
          top: targetTop,
          behavior: 'smooth',
        })
      }

      this.wasAtBottom = true
    })
  }

  /**
   * Force scroll to bottom (ignores user scroll state)
   */
  forceScrollToBottom(): void {
    if (!this.scrollContainer || this.isDestroyed) return

    this.isUserScrolling = false
    this.wasAtBottom = true

    if (this.userScrollTimeout) {
      clearTimeout(this.userScrollTimeout)
      this.userScrollTimeout = null
    }

    this.scrollToBottom()
  }

  /**
   * Enable auto-scrolling
   */
  enable(): void {
    this.isEnabled = true
  }

  /**
   * Disable auto-scrolling
   */
  disable(): void {
    this.isEnabled = false
  }

  /**
   * Check if auto-scroll is currently active
   */
  isAutoScrollActive(): boolean {
    return this.isEnabled && !this.isUserScrolling && this.wasAtBottom
  }

  /**
   * Check if user is manually scrolling
   */
  isUserScrollingNow(): boolean {
    return this.isUserScrolling
  }

  /**
   * Reset state (call when starting new stream)
   */
  reset(): void {
    this.isUserScrolling = false
    this.wasAtBottom = true

    if (this.userScrollTimeout) {
      clearTimeout(this.userScrollTimeout)
      this.userScrollTimeout = null
    }
  }

  /**
   * Cleanup - call when done
   */
  destroy(): void {
    this.isDestroyed = true
    this.detach()
  }
}

/**
 * Vue composable wrapper for SmoothAutoScroller
 */
import { ref, onUnmounted, watch, type Ref } from 'vue'

export function useSmoothScroller(config: Partial<SmoothScrollerConfig> = {}) {
  const scroller = new SmoothAutoScroller(config)
  const isAutoScrollActive = ref(true)
  const isUserScrolling = ref(false)

  // Periodic state sync
  let stateInterval: ReturnType<typeof setInterval> | null = null

  function attach(container: HTMLElement | null): void {
    if (container) {
      scroller.attach(container)
      startStateSync()
    } else {
      scroller.detach()
      stopStateSync()
    }
  }

  function attachRef(containerRef: Ref<HTMLElement | null>): void {
    watch(
      containerRef,
      (container) => {
        attach(container)
      },
      { immediate: true }
    )
  }

  function startStateSync(): void {
    stopStateSync()
    stateInterval = setInterval(() => {
      isAutoScrollActive.value = scroller.isAutoScrollActive()
      isUserScrolling.value = scroller.isUserScrollingNow()
    }, 100)
  }

  function stopStateSync(): void {
    if (stateInterval) {
      clearInterval(stateInterval)
      stateInterval = null
    }
  }

  function scrollToBottom(): void {
    scroller.scrollToBottom()
  }

  function forceScrollToBottom(): void {
    scroller.forceScrollToBottom()
  }

  function enable(): void {
    scroller.enable()
  }

  function disable(): void {
    scroller.disable()
  }

  function reset(): void {
    scroller.reset()
    isAutoScrollActive.value = true
    isUserScrolling.value = false
  }

  onUnmounted(() => {
    stopStateSync()
    scroller.destroy()
  })

  return {
    attach,
    attachRef,
    scrollToBottom,
    forceScrollToBottom,
    enable,
    disable,
    reset,
    isAutoScrollActive,
    isUserScrolling,
  }
}
