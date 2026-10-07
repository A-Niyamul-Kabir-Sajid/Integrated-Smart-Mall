param(
    [string]$ProjectRoot = ".",
    [string]$ExistingRoot = "..",
    [switch]$OverwritePlaceholders,
    [switch]$Force
)

$ErrorActionPreference = "Stop"
$ProjectRoot = (Resolve-Path $ProjectRoot).Path
$ExistingRoot = (Resolve-Path $ExistingRoot).Path
$erdRoot = Join-Path $ExistingRoot "3-2-SyStem-ERD"
$scaffoldRoot = Join-Path $ExistingRoot "3_2_SyStem_Module_Scaffold"

function Ensure-Dir([string]$Path) {
    if (!(Test-Path $Path)) { New-Item -ItemType Directory -Force -Path $Path | Out-Null }
}

function Copy-Safe([string]$Source, [string]$Destination) {
    if (!(Test-Path $Source)) { Write-Warning "Missing source: $Source"; return }
    Ensure-Dir (Split-Path $Destination -Parent)
    if (Test-Path $Destination) {
        $isPlaceholder = Select-String -Path $Destination -Pattern "TODO: import existing authoritative|Populate from the approved|placeholder" -Quiet -ErrorAction SilentlyContinue
        if ($Force -or ($OverwritePlaceholders -and $isPlaceholder)) {
            Copy-Item $Source $Destination -Force
            Write-Host "REPLACED: $Destination"
        } else {
            $legacy = "$Destination.imported"
            Copy-Item $Source $legacy -Force
            Write-Host "PRESERVED BOTH: existing kept; imported as $legacy"
        }
    } else {
        Copy-Item $Source $Destination
        Write-Host "COPIED: $Destination"
    }
}

if (!(Test-Path $erdRoot)) { Write-Warning "ERD package not found at $erdRoot" }
if (!(Test-Path $scaffoldRoot)) { Write-Warning "Module scaffold not found at $scaffoldRoot" }

# Database-level authoritative docs
$dbSrc = Join-Path $erdRoot "DOC\03-database"
$dbDst = Join-Path $ProjectRoot "docs\database\legacy-authoritative"
Ensure-Dir $dbDst
if (Test-Path $dbSrc) { Copy-Item "$dbSrc\*" $dbDst -Recurse -Force }

# Architecture source
$archSrc = Join-Path $erdRoot "DOC\02-architecture\MODULE-CONNECTIONS.md"
Copy-Safe $archSrc (Join-Path $ProjectRoot "docs\architecture\ERD-MODULE-CONNECTIONS.md")

# ERD module -> canonical module
$map = @{
 "01-accounts-access"       = "accounts"
 "02-mall-shop-directory"  = "directory"
 "03-products-catalogue"   = "products"
 "04-maps-navigation"      = "navigation"
 "05-purchase-verification"= "purchase-verification"
 "06-reviews-moderation"   = "reviews"
 "07-favourites-preferences"= "favourites"
 "08-chatbot-rag"          = "chatbot"
 "09-analytics-reports"    = "analytics"
}
foreach ($srcName in $map.Keys) {
    $src = Join-Path $erdRoot "DOC\05-modules\$srcName"
    $dst = Join-Path $ProjectRoot "docs\modules\$($map[$srcName])"
    if (!(Test-Path $src)) { Write-Warning "Missing ERD module: $src"; continue }
    foreach ($name in @("BUSINESS-RULES.md","DATA-DICTIONARY.md","ERD.md","ERD.mmd","ERD.svg")) {
        Copy-Safe (Join-Path $src $name) (Join-Path $dst $name)
    }
    if (Test-Path (Join-Path $src "views")) {
        Ensure-Dir (Join-Path $dst "erd-views")
        Copy-Item (Join-Path $src "views\*") (Join-Path $dst "erd-views") -Recurse -Force
    }
    Copy-Safe (Join-Path $src "README.md") (Join-Path $dst "ERD-SOURCE-README.md")
}

# Functional module GUIDE.md imports; keep originals under functional-source for traceability
$funcRoot = Join-Path $scaffoldRoot "DOC\modules"
$funcDst = Join-Path $ProjectRoot "docs\functional-source"
if (Test-Path $funcRoot) {
    Ensure-Dir $funcDst
    Get-ChildItem $funcRoot -Directory | ForEach-Object {
        $dst = Join-Path $funcDst $_.Name
        Ensure-Dir $dst
        if (Test-Path (Join-Path $_.FullName "GUIDE.md")) {
            Copy-Item (Join-Path $_.FullName "GUIDE.md") (Join-Path $dst "GUIDE.md") -Force
        }
    }
}

# Project-level scaffold docs useful for reconciliation
$importPairs = @(
 @("DOC\integration\CONTRACTS.md", "docs\legacy-scaffold\CONTRACTS.md"),
 @("DOC\integration\SCENARIOS.md", "docs\legacy-scaffold\SCENARIOS.md"),
 @("DOC\integration\SOURCE_LAYOUT.md", "docs\legacy-scaffold\SOURCE_LAYOUT.md"),
 @("DOC\planning\BUILD_ORDER.md", "docs\legacy-scaffold\BUILD_ORDER.md"),
 @("DOC\planning\PROGRESS.md", "docs\legacy-scaffold\PROGRESS.md"),
 @("DOC\project\DECISIONS.md", "docs\legacy-scaffold\DECISIONS.md"),
 @("DOC\project\FEATURE_CATALOGUE.md", "docs\legacy-scaffold\FEATURE_CATALOGUE.md"),
 @("DOC\project\PROJECT_CONTEXT.md", "docs\legacy-scaffold\PROJECT_CONTEXT.md"),
 @("DOC\quality\SECURITY_VERIFICATION.md", "docs\legacy-scaffold\SECURITY_VERIFICATION.md"),
 @("DOC\MODULE_CONNECTIONS.md", "docs\legacy-scaffold\MODULE_CONNECTIONS.md"),
 @("DOC\ai\MODULE_TASK_PROMPT.md", "docs\legacy-scaffold\MODULE_TASK_PROMPT.md")
)
foreach ($pair in $importPairs) {
    $src = Join-Path $scaffoldRoot $pair[0]
    $dst = Join-Path $ProjectRoot $pair[1]
    if (Test-Path $src) { Ensure-Dir (Split-Path $dst -Parent); Copy-Item $src $dst -Force }
}

Write-Host ""
Write-Host "Import complete. Next: read docs/00_START_HERE.md, compare docs/legacy-scaffold with canonical docs, and record accepted reconciliations in docs/DECISIONS.md."
