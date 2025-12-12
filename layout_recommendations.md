# Mobile Header Layout Recommendations for Chapter Editor

Here are 5 layout strategies to ensure the full project title is visible on mobile devices without compromising the premium aesthetic.

## 1. The "stacked-focused" Layout (Recommended)
This layout treats the header as two distinct zones: "Navigation" and "Context". It prioritizes readability by giving the title its own secured space.

*   **Behavior:**
    *   **Row 1:** Navigation controls (Back, Menu) and Global Actions (Brain/AI, Dark Mode).
    *   **Row 2:** Project Title (full width, multi-line allowed) and Chapter Badge.
    *   **Row 3 (Optional):** Editor Toolbar (Save, Stats, etc.) - or merge with Row 1.
*   **Pros:** Guarantees title visibility regardless of length; extremely clear hierarchy.
*   **Cons:** Takes up more vertical screen real estate (approx 80-100px).
*   **Aesthetic:** Use a subtle separator or background gradient shift between Row 1 and Row 2.

## 2. The "Marquee" Scroller
Keep the compact single-row aesthetics but animate the text.

*   **Behavior:**
    *   The title sits between the left and right icons on a single line.
    *   If the text overflows, it automatically scrolls (marquee effect) or fades out with a "scroll hint".
    *   User can tap to pause or view full text.
*   **Pros:** Most space-efficient; maintains the "app-like" dense feel.
*   **Cons:** Motion can be distracting; requires waiting to read the full title.
*   **Implementation:** CSS animation or JS-based scroll container.

## 3. The "Expandable Glass" Header
A hybrid approach that starts compact but puts the user in control.

*   **Behavior:**
    *   Default view: Title truncated (1 line) with a subtle "chevron-down" icon.
    *   **Interaction:** Tapping the header expands strictly the header background (glassmorphism effect) to reveal the full title and metadata.
    *   Clicking outside collapses it.
*   **Pros:** Best of both worlds—clean by default, accessible on demand.
*   **Cons:** Requires an extra tap to see details.
*   **Aesthetic:** High-end glass blur transition when opening.

## 4. The "Prominent Overview" (Page-Like)
Shift the paradigm from an "App Bar" to a "Document Header".

*   **Behavior:**
    *   **Top Bar:** Strictly for navigation (Back, Menu, Brain, Save). No title.
    *   **Content Top:** The Project Title and Chapter Badge appear *inside* the scrolling content area as a large H1 element at the very top of the "paper".
    *   As you scroll down, the title scrolls away, leaving maximum writing space.
*   **Pros:** Feels like a real document editor; zero persistent clutter; infinite space for title.
*   **Cons:** Title is not visible once you scroll down (unless you tap top bar to scroll top).

## 5. The "Context Bar" Footer
Move the context to the bottom to balance the UI.

*   **Behavior:**
    *   **Top Header:** Navigation & Tools only.
    *   **Bottom Bar (Sticky):** Project Title (1-2 lines) + Chapter Info + Progress.
*   **Pros:** Easier for thumb reach on mobile; separates "Tools" (top) from "Context" (bottom).
*   **Cons:** Non-standard mobile pattern; might conflict with virtual keyboards.

---

### My Recommendation: **Option 1 (Optimized)**
Given the user's desire to "show full project title," **Option 1** is the most robust. We can optimize it to be less tall by merging the Toolbar into Row 1 or making the Title Row collapsible on scroll.

**Proposed Implementation Plan:**
1.  Force `flex-wrap` and specific ordering classes to ensure a 2-row layout on mobile.
2.  **Row 1:** Flex container with `justify-between`. Left: Back + Menu. Right: Brain + Save + Actions.
3.  **Row 2:** Title (Text-center or Text-left, fully expanded).
4.  Refine padding to keep it from feeling "heavy".

Would you like me to proceed with implementing **Option 1**?
