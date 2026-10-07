# 3 2 SyStem — Integrated Smart Mall Project Guide

This package consolidates the project documentation around a **single agent/developer entry point** and a **Laravel modular MVC architecture**.

## First action
Read `AGENTS.md`, then `docs/00_START_HERE.md`.

## Import your old generated packages
The uploaded material contained their directory inventory but not their file bytes. Therefore this ZIP deliberately does **not** fake or regenerate existing ERDs/business rules. Copy this folder beside your existing `3-2-SyStem-ERD` and `3_2_SyStem_Module_Scaffold` directories and run:

```powershell
Set-ExecutionPolicy -Scope Process Bypass
.\tools\merge-existing.ps1 -ProjectRoot . -ExistingRoot '..'
```

Use `-OverwritePlaceholders` to replace placeholder business-rule files with your existing authoritative versions. The script does not overwrite non-placeholder files unless explicitly requested.
