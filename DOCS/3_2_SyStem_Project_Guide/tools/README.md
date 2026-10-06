# Tools

`merge-existing.ps1` imports authoritative material from your two old generated packages into the new canonical documentation tree. It is intentionally conservative: existing non-placeholder files are preserved, and conflicting imports get an `.imported` suffix unless `-Force` is used.
