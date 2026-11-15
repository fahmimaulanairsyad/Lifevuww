# Lifevuww Pixel Assets Implementation Plan

**Goal:** Rebuild 5 bosses, 5 companions and 3 relics as dark fantasy pixel art approved in chat.
**Architecture:** Reproducible hand-drawn polygon sprites on a 64px working grid, exported at 128px with nearest-neighbor scaling and transparent backgrounds. Existing image-aware PHP templates are reused with PNG paths.
**Tech Stack:** Python/Pillow, PNG, PHP and CSS.
**Spec:** User approval in chat: 2D pixel art, dark fantasy, bright accents, transparent 128 x 128 assets.

## Constraints
- Preserve gameplay, names, rewards and mobile Focus Rift fixes.
- Keep images contained at small sizes and render pixels sharply.
- Do not publish or push changes as part of asset creation.

## Review Focus
- All 13 catalog paths resolve to real files.
- Transparent margins prevent clipping.
- Bosses and companions have distinct silhouettes.
- Small inline companion images do not expand rows.
- Relic and boss images fit mobile containers.

## Tasks
- [x] Draw and export all 13 sprites, plus a contact sheet for visual inspection.
- [x] Restore existing image integration, switch to PNG and apply pixel rendering.
- [x] Validate PNG sizes/transparency, PHP lint, test suite and inspect contact sheet.

## Execution record
Proceed inline on existing project at user instruction 'gas'. Asset-only work uses structural and visual verification rather than implementation-mirroring unit tests. Existing template support will be restored only if it matches the clean baseline. No unrelated changes will be reverted.
