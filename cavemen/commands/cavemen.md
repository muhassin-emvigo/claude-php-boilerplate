---
description: Run the cavemen lean-markdown procedure against a file or topic — minimize tokens, reuse stored context, and update the ledger.
---

Apply the cavemen procedure to: $ARGUMENTS

Rules:
- Check `.claude/cavemen-ledger.md` first. Do not re-explain or re-read content already recorded there — reuse it.
- Minimize token usage: no filler, no restated context, no unnecessary explanation.
- After processing, append or refresh a one-line entry in `.claude/cavemen-ledger.md` (path + what matters) so future turns can reuse it instead of repeating it.
