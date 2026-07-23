---
name: cavemen
description: Apply the lean markdown procedure (minimize tokens, reuse stored context, no filler) to a .md file. Use when asked to process, summarize, or optimize a markdown file "the cavemen way", or when the cavemen plugin's hook flags a markdown file for review.
---

# Cavemen procedure

For any `.md` file:

1. Check `.claude/cavemen-ledger.md` first — do not re-read or re-explain what is already recorded there.
2. Keep output terse: no filler, no restated context, no unnecessary explanation.
3. After reading/editing, record one line in `.claude/cavemen-ledger.md`: path + what matters, so future turns reuse it instead of repeating it.

This mirrors the "AI Optimization" rule for `.md` files, packaged as an automated procedure instead of a manually-followed instruction.
