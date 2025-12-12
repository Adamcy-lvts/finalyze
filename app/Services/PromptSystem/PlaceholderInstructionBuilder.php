<?php

namespace App\Services\PromptSystem;

use App\Models\Project;

class PlaceholderInstructionBuilder
{
    /**
     * Build placeholder with detailed instructions for user-created content
     */
    public function build(string $type, Project $project, array $context = []): string
    {
        $figureNumber = $context['figure_number'] ?? 'X.X';
        $description = $context['description'] ?? 'Visual content';
        $tool = $context['tool'] ?? null;
        $components = $context['components'] ?? [];

        return match ($type) {
            'circuit_diagram', 'circuit_schematic' => $this->buildCircuitPlaceholder($figureNumber, $project, $components),
            'screenshot' => $this->buildScreenshotPlaceholder($figureNumber, $project),
            'hardware_photo' => $this->buildHardwarePhotoPlaceholder($figureNumber, $project),
            'pcb_layout' => $this->buildPcbPlaceholder($figureNumber, $project),
            'wiring_diagram' => $this->buildWiringPlaceholder($figureNumber, $project),
            'experimental_setup' => $this->buildExperimentalSetupPlaceholder($figureNumber, $description),
            'oscilloscope_capture' => $this->buildOscilloscopePlaceholder($figureNumber),
            'field_layout' => $this->buildFieldLayoutPlaceholder($figureNumber, $project),
            'graph', 'chart' => $this->buildChartPlaceholder($figureNumber, $description, $tool),
            default => $this->buildGenericPlaceholder($figureNumber, $type, $description, $tool),
        };
    }

    /**
     * Build circuit diagram placeholder
     */
    private function buildCircuitPlaceholder(string $figureNumber, Project $project, array $components = []): string
    {
        $componentList = ! empty($components)
            ? implode("\n", array_map(fn ($c) => '    • '.ucfirst(str_replace('_', ' ', $c)), $components))
            : "    • Microcontroller\n    • Sensors\n    • Actuators\n    • Power supply\n    • Display (if applicable)";

        return <<<PLACEHOLDER
┌─────────────────────────────────────────────────────────────────────────┐
│                                                                         │
│                    [FIGURE {$figureNumber}: CIRCUIT DIAGRAM]                    │
│                                                                         │
│         ⚠️ THIS REQUIRES A CIRCUIT DIAGRAM THAT YOU MUST CREATE         │
│                                                                         │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  📋 WHAT TO INCLUDE:                                                    │
│  • Complete circuit schematic with all components                       │
│  • Power supply connections (VCC, GND)                                  │
│  • All pin connections labeled                                          │
│  • Component values (resistor values, capacitor values)                 │
│  • Signal flow directions                                               │
│                                                                         │
│  📦 KEY COMPONENTS TO SHOW:                                             │
{$componentList}
│                                                                         │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  🛠️ RECOMMENDED TOOLS (Choose ONE):                                     │
│                                                                         │
│  1. FRITZING (Beginner-Friendly) - fritzing.org                         │
│     ✓ Free, excellent for Arduino projects                              │
│     ✓ Has breadboard view and schematic view                            │
│     ✓ Large component library                                           │
│                                                                         │
│  2. EasyEDA (Online, Free) - easyeda.com                                │
│     ✓ Browser-based, no installation                                    │
│     ✓ Professional schematic symbols                                    │
│     ✓ Can order PCBs directly                                           │
│                                                                         │
│  3. PROTEUS (Simulation) - labcenter.com                                │
│     ✓ Circuit simulation capability                                     │
│     ✓ Microcontroller simulation                                        │
│     ✓ Academic license available                                        │
│                                                                         │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  📐 STEP-BY-STEP GUIDE:                                                 │
│                                                                         │
│  1. Open your chosen software                                           │
│  2. Create a new schematic project                                      │
│  3. Add power rails (VCC, GND)                                          │
│  4. Place the microcontroller component                                 │
│  5. Add sensors and their connections                                   │
│  6. Add output components (display, actuators)                          │
│  7. Connect all components with wires                                   │
│  8. Add labels and component values                                     │
│  9. Run design rule check (DRC)                                         │
│  10. Export as PNG (min 300 DPI) or PDF                                 │
│                                                                         │
│  💡 TIPS:                                                               │
│  • Use consistent wire colors (red=VCC, black=GND)                      │
│  • Align components neatly for readability                              │
│  • Add junction dots at wire connections                                │
│  • Include a title block with your name and date                        │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘

PLACEHOLDER;
    }

    /**
     * Build screenshot placeholder
     */
    private function buildScreenshotPlaceholder(string $figureNumber, Project $project): string
    {
        return <<<PLACEHOLDER
┌─────────────────────────────────────────────────────────────────────────┐
│                                                                         │
│                    [FIGURE {$figureNumber}: APPLICATION SCREENSHOT]             │
│                                                                         │
│         ⚠️ THIS REQUIRES A SCREENSHOT THAT YOU MUST CAPTURE            │
│                                                                         │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  📋 WHAT TO CAPTURE:                                                    │
│  • The specific feature/page being described                            │
│  • Clear, readable text and UI elements                                 │
│  • Active state showing functionality                                   │
│  • No sensitive/personal data visible                                   │
│                                                                         │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  🛠️ TOOLS FOR SCREENSHOTS:                                              │
│                                                                         │
│  Windows:                                                               │
│  • Snipping Tool (built-in) - Win + Shift + S                          │
│  • ShareX (free) - getsharex.com                                        │
│  • Lightshot (free) - app.prntscr.com                                   │
│                                                                         │
│  macOS:                                                                 │
│  • Built-in - Cmd + Shift + 4                                           │
│  • CleanShot X - cleanshot.com                                          │
│                                                                         │
│  Browser Extensions:                                                    │
│  • GoFullPage (full page capture)                                       │
│  • Nimbus Screenshot                                                    │
│                                                                         │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  📐 STEPS:                                                              │
│                                                                         │
│  1. Navigate to the relevant page/feature                               │
│  2. Ensure the window is the right size                                 │
│  3. Use screenshot tool to capture                                      │
│  4. Crop to remove unnecessary elements                                 │
│  5. Add annotations if needed (arrows, boxes)                           │
│  6. Save as PNG format                                                  │
│  7. Insert into your document                                           │
│  8. Add proper caption below the figure                                 │
│                                                                         │
│  💡 TIPS:                                                               │
│  • Use consistent browser window size for all screenshots               │
│  • Remove browser bookmarks bar for cleaner look                        │
│  • Add red circles/arrows to highlight key elements                     │
│  • Use sample data, not real user data                                  │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘

PLACEHOLDER;
    }

    /**
     * Build hardware photo placeholder
     */
    private function buildHardwarePhotoPlaceholder(string $figureNumber, Project $project): string
    {
        return <<<PLACEHOLDER
┌─────────────────────────────────────────────────────────────────────────┐
│                                                                         │
│                    [FIGURE {$figureNumber}: HARDWARE PROTOTYPE PHOTO]           │
│                                                                         │
│         ⚠️ THIS REQUIRES A PHOTO THAT YOU MUST CAPTURE                 │
│                                                                         │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  📋 WHAT TO PHOTOGRAPH:                                                 │
│  • Complete assembled prototype                                         │
│  • All major components visible                                         │
│  • Clear labeling of key parts                                          │
│  • Multiple angles if needed                                            │
│                                                                         │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  📐 PHOTOGRAPHY TIPS:                                                   │
│                                                                         │
│  1. LIGHTING:                                                           │
│     • Use natural daylight or bright LED lights                         │
│     • Avoid harsh shadows                                               │
│     • Use diffused light for even illumination                          │
│                                                                         │
│  2. BACKGROUND:                                                         │
│     • Use plain white or neutral background                             │
│     • A white sheet of paper works well                                 │
│     • Avoid cluttered backgrounds                                       │
│                                                                         │
│  3. CAMERA SETTINGS:                                                    │
│     • Use macro mode for close-ups                                      │
│     • Keep camera steady (use tripod if available)                      │
│     • Smartphone cameras are usually sufficient                         │
│                                                                         │
│  4. COMPOSITION:                                                        │
│     • Center the prototype in frame                                     │
│     • Include scale reference if relevant                               │
│     • Show overall view + detail shots                                  │
│                                                                         │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  📸 SUGGESTED SHOTS:                                                    │
│  • Figure X.Xa: Overall system view                                     │
│  • Figure X.Xb: Control panel/display close-up                          │
│  • Figure X.Xc: Internal components (if accessible)                     │
│  • Figure X.Xd: System in operation                                     │
│                                                                         │
│  💡 POST-PROCESSING:                                                    │
│  • Crop to remove excess background                                     │
│  • Adjust brightness/contrast if needed                                 │
│  • Add labels using PowerPoint or image editor                          │
│  • Export as high-quality JPEG or PNG                                   │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘

PLACEHOLDER;
    }

    /**
     * Build PCB layout placeholder
     */
    private function buildPcbPlaceholder(string $figureNumber, Project $project): string
    {
        return <<<PLACEHOLDER
┌─────────────────────────────────────────────────────────────────────────┐
│                                                                         │
│                    [FIGURE {$figureNumber}: PCB LAYOUT]                         │
│                                                                         │
│         ⚠️ THIS REQUIRES A PCB LAYOUT THAT YOU MUST DESIGN             │
│                                                                         │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  📋 WHAT TO INCLUDE:                                                    │
│  • Component footprints properly placed                                 │
│  • Copper traces connecting components                                  │
│  • Ground plane (if used)                                               │
│  • Mounting holes and board outline                                     │
│                                                                         │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  🛠️ RECOMMENDED TOOLS:                                                  │
│                                                                         │
│  1. EasyEDA (Online, Free) - easyeda.com                                │
│  2. KiCad (Desktop, Free) - kicad.org                                   │
│  3. Eagle (Desktop) - autodesk.com/eagle                                │
│                                                                         │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  📐 STEPS:                                                              │
│  1. Import or create schematic in PCB software                          │
│  2. Define board dimensions and shape                                   │
│  3. Place components logically                                          │
│  4. Route traces (auto-route or manual)                                 │
│  5. Add ground pour/plane                                               │
│  6. Run Design Rule Check (DRC)                                         │
│  7. Add silkscreen labels                                               │
│  8. Export as PNG or Gerber files                                       │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘

PLACEHOLDER;
    }

    /**
     * Build wiring diagram placeholder
     */
    private function buildWiringPlaceholder(string $figureNumber, Project $project): string
    {
        return <<<PLACEHOLDER
┌─────────────────────────────────────────────────────────────────────────┐
│                                                                         │
│                    [FIGURE {$figureNumber}: WIRING DIAGRAM]                     │
│                                                                         │
│         ⚠️ THIS REQUIRES A WIRING DIAGRAM THAT YOU MUST CREATE         │
│                                                                         │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  📋 WHAT TO SHOW:                                                       │
│  • Physical wire connections between components                         │
│  • Wire colors for each connection                                      │
│  • Pin labels on each component                                         │
│  • Power and ground connections highlighted                             │
│                                                                         │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  🛠️ RECOMMENDED: Fritzing (fritzing.org)                                │
│  • Use breadboard view for realistic representation                     │
│  • Color-code wires: Red=VCC, Black=GND, Others=signals                 │
│                                                                         │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  📐 STEPS:                                                              │
│  1. Open Fritzing and select breadboard view                            │
│  2. Place all components from Parts library                             │
│  3. Connect wires following your circuit design                         │
│  4. Use appropriate wire colors                                         │
│  5. Add labels and notes                                                │
│  6. Export as PNG or PDF                                                │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘

PLACEHOLDER;
    }

    /**
     * Build experimental setup placeholder
     */
    private function buildExperimentalSetupPlaceholder(string $figureNumber, string $description): string
    {
        return <<<PLACEHOLDER
┌─────────────────────────────────────────────────────────────────────────┐
│                                                                         │
│                    [FIGURE {$figureNumber}: EXPERIMENTAL SETUP]                 │
│                                                                         │
│         ⚠️ THIS REQUIRES A DIAGRAM/PHOTO THAT YOU MUST CREATE          │
│                                                                         │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  📋 DESCRIPTION:                                                        │
│  {$description}                                                            │
│                                                                         │
│  📋 WHAT TO INCLUDE:                                                    │
│  • All equipment and apparatus used                                     │
│  • Spatial arrangement of components                                    │
│  • Sample positions and measurement points                              │
│  • Scale or dimensions                                                  │
│  • Labels for all components                                            │
│                                                                         │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  🛠️ OPTIONS:                                                            │
│                                                                         │
│  A. CREATE A DIAGRAM:                                                   │
│     • Draw.io (free) - draw.io                                          │
│     • PowerPoint/Google Slides                                          │
│     • ChemDraw (for chemistry setups)                                   │
│                                                                         │
│  B. TAKE A PHOTOGRAPH:                                                  │
│     • Photograph actual laboratory setup                                │
│     • Add labels using image editor                                     │
│     • Ensure proper lighting                                            │
│                                                                         │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  📐 LABELING:                                                           │
│  • Number each component (1, 2, 3...)                                   │
│  • Add legend below explaining numbers                                  │
│  • Include arrows showing flow direction                                │
│  • Add measurement units where relevant                                 │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘

PLACEHOLDER;
    }

    /**
     * Build oscilloscope capture placeholder
     */
    private function buildOscilloscopePlaceholder(string $figureNumber): string
    {
        return <<<PLACEHOLDER
┌─────────────────────────────────────────────────────────────────────────┐
│                                                                         │
│                    [FIGURE {$figureNumber}: OSCILLOSCOPE WAVEFORM]              │
│                                                                         │
│         ⚠️ THIS REQUIRES AN OSCILLOSCOPE CAPTURE                       │
│                                                                         │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  📋 WHAT TO CAPTURE:                                                    │
│  • Clear waveform display                                               │
│  • Visible scale settings (V/div, Time/div)                             │
│  • Measurement values (if applicable)                                   │
│  • Channel labels                                                       │
│                                                                         │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  📐 STEPS:                                                              │
│  1. Connect probe to test point                                         │
│  2. Adjust vertical scale for clear display                             │
│  3. Adjust timebase to show complete waveform                           │
│  4. Trigger properly to stabilize display                               │
│  5. Use SAVE/SCREENSHOT function on oscilloscope                        │
│  6. Transfer file via USB to computer                                   │
│  7. Add annotations if needed                                           │
│                                                                         │
│  💡 TIPS:                                                               │
│  • Use persistence mode for noise visualization                         │
│  • Enable automatic measurements                                        │
│  • Include cursor measurements for key values                           │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘

PLACEHOLDER;
    }

    /**
     * Build field layout placeholder (Agriculture)
     */
    private function buildFieldLayoutPlaceholder(string $figureNumber, Project $project): string
    {
        return <<<PLACEHOLDER
┌─────────────────────────────────────────────────────────────────────────┐
│                                                                         │
│                    [FIGURE {$figureNumber}: FIELD LAYOUT]                       │
│                                                                         │
│         ⚠️ THIS REQUIRES A FIELD LAYOUT DIAGRAM                        │
│                                                                         │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  📋 WHAT TO SHOW:                                                       │
│  • Plot arrangement (blocks and treatments)                             │
│  • Plot dimensions and spacing                                          │
│  • Randomization pattern                                                │
│  • Buffer zones and paths                                               │
│  • Orientation (North arrow)                                            │
│                                                                         │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  📐 EXAMPLE RCBD LAYOUT:                                                │
│                                                                         │
│  ┌─────────────────────────────────────────────────────┐               │
│  │  Block I    │  T3  │  T1  │  T2  │  T4  │          │               │
│  │─────────────│──────│──────│──────│──────│          │               │
│  │  Block II   │  T2  │  T4  │  T3  │  T1  │          │               │
│  │─────────────│──────│──────│──────│──────│          │               │
│  │  Block III  │  T1  │  T2  │  T4  │  T3  │          │               │
│  └─────────────────────────────────────────────────────┘               │
│                                                                         │
│  Scale: Each plot = X m × Y m                                           │
│  Spacing between plots: X m                                             │
│  Path width: X m                                                        │
│                                                                         │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  🛠️ TOOLS:                                                              │
│  • Microsoft Excel (use cells as grid)                                  │
│  • Draw.io (free, online)                                               │
│  • Microsoft Word (using tables)                                        │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘

PLACEHOLDER;
    }

    /**
     * Build chart placeholder
     */
    private function buildChartPlaceholder(string $figureNumber, string $description, ?string $tool = null): string
    {
        $toolSuggestion = $tool ?? 'Excel, SPSS, or Python/R';

        return <<<PLACEHOLDER
┌─────────────────────────────────────────────────────────────────────────┐
│                                                                         │
│                    [FIGURE {$figureNumber}: {$description}]                       │
│                                                                         │
│         ⚠️ THIS REQUIRES A CHART/GRAPH THAT YOU MUST CREATE            │
│                                                                         │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  📋 WHAT TO INCLUDE:                                                    │
│  • Clear axis labels with units                                         │
│  • Appropriate scale and intervals                                      │
│  • Legend (if multiple series)                                          │
│  • Data point markers (for scatter plots)                               │
│  • Title/caption                                                        │
│                                                                         │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  🛠️ RECOMMENDED: {$toolSuggestion}                                        │
│                                                                         │
│  📐 STEPS:                                                              │
│  1. Enter your data into the software                                   │
│  2. Select appropriate chart type                                       │
│  3. Customize appearance (colors, fonts)                                │
│  4. Add axis labels and title                                           │
│  5. Export as high-resolution image                                     │
│  6. Insert into your document                                           │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘

PLACEHOLDER;
    }

    /**
     * Build generic placeholder
     */
    private function buildGenericPlaceholder(string $figureNumber, string $type, string $description, ?string $tool = null): string
    {
        $typeName = ucwords(str_replace('_', ' ', $type));
        $toolText = $tool ? "Recommended Tool: {$tool}" : 'Use appropriate software for this content type';

        return <<<PLACEHOLDER
┌─────────────────────────────────────────────────────────────────────────┐
│                                                                         │
│                    [FIGURE {$figureNumber}: {$typeName}]                          │
│                                                                         │
│         ⚠️ THIS REQUIRES CONTENT THAT YOU MUST CREATE                  │
│                                                                         │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  📋 DESCRIPTION:                                                        │
│  {$description}                                                            │
│                                                                         │
│  🛠️ {$toolText}                                                           │
│                                                                         │
│  📐 GENERAL STEPS:                                                      │
│  1. Gather all necessary information/data                               │
│  2. Create content using appropriate tool                               │
│  3. Ensure clarity and readability                                      │
│  4. Export in high quality format                                       │
│  5. Insert into your document                                           │
│  6. Add proper caption                                                  │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘

PLACEHOLDER;
    }
}
