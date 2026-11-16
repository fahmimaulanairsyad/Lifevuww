#requires -Version 5.1
# Real Git/file-system fixture only. Never targets the application deployment.
[CmdletBinding()]
param()

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$deployScript = Join-Path (Split-Path -Parent $PSScriptRoot) 'scripts\deploy.ps1'
$sandbox = Join-Path $env:LOCALAPPDATA ('Temp\opencode\lifevuww-deploy-test-' + [guid]::NewGuid().ToString('N'))
$fixtureSource = Join-Path $sandbox 'source'
$fixtureDestination = Join-Path $sandbox 'destination'
$originalLocalAppData = $env:LOCALAPPDATA
$script:assertions = 0

function Assert-True([bool]$Condition, [string]$Message) {
    if (!$Condition) { throw "FAIL: $Message" }
    $script:assertions++
}

function Write-Fixture([string]$Root, [string]$Relative, [string]$Content) {
    $file = Join-Path $Root $Relative
    [IO.Directory]::CreateDirectory((Split-Path -Parent $file)) | Out-Null
    [IO.File]::WriteAllText($file, $Content, (New-Object Text.UTF8Encoding($false)))
}

function Read-Fixture([string]$Root, [string]$Relative) {
    return [IO.File]::ReadAllText((Join-Path $Root $Relative))
}

function Get-TreeState([string]$Root) {
    if (!(Test-Path -LiteralPath $Root)) { return '<missing>' }
    return (@(Get-ChildItem -LiteralPath $Root -Force -Recurse | Sort-Object FullName | ForEach-Object {
        $name = $_.FullName.Substring($Root.Length)
        if ($_.PSIsContainer) { "D $name" }
        else { "F $name $((Get-FileHash -LiteralPath $_.FullName -Algorithm SHA256).Hash)" }
    }) -join "`n")
}

function Invoke-FixtureDeploy([hashtable]$Options) {
    & $deployScript -Source $fixtureSource -Destination $fixtureDestination @Options
}

function Assert-Rejected([hashtable]$Options, [string]$Message) {
    $before = Get-TreeState $sandbox
    $rejected = $false
    try { Invoke-FixtureDeploy $Options *> $null }
    catch { $rejected = $true }
    Assert-True $rejected $Message
    Assert-True ((Get-TreeState $sandbox) -ceq $before) "Rejected request must not write: $Message"
}

try {
    [IO.Directory]::CreateDirectory($fixtureSource) | Out-Null
    $env:LOCALAPPDATA = Join-Path $sandbox 'local-app-data'
    & git -C $fixtureSource init --quiet
    if ($LASTEXITCODE -ne 0) { throw 'Fixture Git init failed.' }
    $tracked = @{
        'index.php' = '<?php echo "index version 1";'
        'config/app.php' = '<?php return "configuration";'
        'migrations/001_fixture.sql' = 'SELECT 1;'
        '.htaccess' = 'DirectoryIndex index.php'
        'README.md' = 'Fixture readme'
        'docs/deployment.md' = 'Fixture deployment guide'
        'docs/private.md' = 'Not deploy support'
        'scripts/deploy.ps1' = '# Fixture deploy script'
        'scripts/check_fixture.py' = '# Fixture asset check'
        'scripts/generate_fixture.py' = '# Fixture asset generation'
        'scripts/private.py' = '# Not deploy support'
        'tests/.htaccess' = 'Require all denied'
        'tests/cli_test.php' = '<?php echo "CLI only";'
        '.env' = 'SOURCE_SECRET=never-copy'
        'config/.env.local' = 'NESTED_SECRET=never-copy'
        'assets/uploads/private.txt' = 'User upload, never copy'
    }
    foreach ($relative in $tracked.Keys) { Write-Fixture $fixtureSource $relative $tracked[$relative] }
    & git -C $fixtureSource -c core.autocrlf=false add --all
    if ($LASTEXITCODE -ne 0) { throw 'Fixture Git add failed.' }
    Write-Fixture $fixtureSource 'index.php' '<?php echo "working tree version 2";'
    Write-Fixture $fixtureSource 'migrations/002_pending.sql' 'SELECT 2;'
    Write-Fixture $fixtureSource 'config/pending.php' '<?php return "pending";'
    Write-Fixture $fixtureSource 'pages/unnamed.php' '<?php echo "not requested";'
    Write-Fixture $fixtureSource 'tests/pending_test.ps1' '# Explicit pending test'
    Write-Fixture $fixtureSource 'tests/unrequested_test.php' '<?php echo "not requested";'
    Write-Fixture $fixtureDestination 'index.php' '<?php echo "destination user edit";'
    Write-Fixture $fixtureDestination '.env' 'DESTINATION_SECRET=keep'
    Write-Fixture $fixtureDestination 'uploads/avatar.txt' 'Keep user upload'
    Write-Fixture $fixtureDestination '.git/HEAD' 'Keep old destination history'
    Write-Fixture $fixtureDestination 'user-notes.txt' 'Keep destination-only file'

    $beforePreview = Get-TreeState $sandbox
    $preview = Invoke-FixtureDeploy @{} 3>&1
    Assert-True ((Get-TreeState $sandbox) -ceq $beforePreview) 'Default preview writes no files or directories.'
    $warnings = @($preview | Where-Object { $_ -is [Management.Automation.WarningRecord] })
    Assert-True ($warnings.Count -gt 0 -and ($warnings -join ' ') -match '1.*destination.*(replac|differ)') 'Preview warns and counts replacements that may contain destination edits.'
    Invoke-FixtureDeploy @{ Apply = $true } *> $null
    Assert-True ((Read-Fixture $fixtureDestination 'index.php') -ceq '<?php echo "working tree version 2";') 'Copies working-tree content rather than the Git index.'
    Assert-True (!(Test-Path (Join-Path $fixtureDestination 'README.md'))) 'Default apply does not copy support files.'
    Assert-True (!(Test-Path (Join-Path $fixtureDestination 'tests'))) 'Default apply does not create tests.'
    Assert-True (!(Test-Path (Join-Path $fixtureDestination 'migrations/002_pending.sql'))) 'Default apply does not copy untracked runtime files.'
    Assert-True ((Read-Fixture $fixtureDestination '.env') -ceq 'DESTINATION_SECRET=keep') 'Destination environment is preserved.'
    Assert-True (!(Test-Path (Join-Path $fixtureDestination 'config/.env.local'))) 'Nested tracked environment files never leak.'
    Assert-True (!(Test-Path (Join-Path $fixtureDestination 'assets/uploads/private.txt'))) 'Tracked upload data never leaks.'
    Assert-True ((Read-Fixture $fixtureDestination 'uploads/avatar.txt') -ceq 'Keep user upload') 'User uploads are preserved.'
    Assert-True ((Read-Fixture $fixtureDestination '.git/HEAD') -ceq 'Keep old destination history') 'Destination Git history is untouched.'
    Assert-True ((Read-Fixture $fixtureDestination 'user-notes.txt') -ceq 'Keep destination-only file') 'Extra destination files are preserved.'
    $backupRoot = Join-Path $env:LOCALAPPDATA 'Temp/opencode/lifevuww-deploy-backups'
    $manifests = @(Get-ChildItem -LiteralPath $backupRoot -Filter manifest.json -Recurse)
    Assert-True ($manifests.Count -eq 1) 'Apply with changes records exactly one backup manifest.'
    $firstBackup = $manifests[0].DirectoryName
    Assert-True ((Read-Fixture (Join-Path $firstBackup 'files') 'index.php') -ceq '<?php echo "destination user edit";') 'Destination user edits are backed up intact.'
    $manifest = Get-Content -LiteralPath $manifests[0].FullName -Raw | ConvertFrom-Json
    Assert-True (@($manifest.Files | Where-Object Path -eq 'index.php').Count -eq 1) 'Manifest records the replaced file.'
    $beforeNoop = Get-TreeState $sandbox
    Invoke-FixtureDeploy @{ Apply = $true } *> $null
    Assert-True ((Get-TreeState $sandbox) -ceq $beforeNoop) 'Repeated matching apply does not write or create another backup.'

    Invoke-FixtureDeploy @{ Apply = $true; AdditionalFiles = @('migrations/002_pending.sql', 'config/pending.php') } *> $null
    Assert-True ((Read-Fixture $fixtureDestination 'migrations/002_pending.sql') -ceq 'SELECT 2;') 'Explicit pending runtime migration works without support opt-in.'
    Assert-True ((Read-Fixture $fixtureDestination 'config/pending.php') -ceq '<?php return "pending";') 'Explicit pending runtime config works without support opt-in.'
    Assert-True (!(Test-Path (Join-Path $fixtureDestination 'pages/unnamed.php'))) 'Adding named runtime files does not scan other pending files.'

    $supportOptions = @{ IncludeSupportFiles = $true; AdditionalFiles = @('migrations/002_pending.sql', 'config/pending.php', 'tests/pending_test.ps1') }
    $beforeSupportPreview = Get-TreeState $sandbox
    Invoke-FixtureDeploy $supportOptions *> $null
    Assert-True ((Get-TreeState $sandbox) -ceq $beforeSupportPreview) 'Support and additional-file preview also writes nothing.'
    $supportOptions.Apply = $true
    Invoke-FixtureDeploy $supportOptions *> $null
    foreach ($relative in @('README.md', 'docs/deployment.md', 'scripts/deploy.ps1', 'scripts/check_fixture.py', 'scripts/generate_fixture.py', 'tests/.htaccess', 'tests/cli_test.php')) {
        Assert-True ((Read-Fixture $fixtureDestination $relative) -ceq $tracked[$relative]) "Support opt-in copies $relative."
    }
    Assert-True ((Read-Fixture $fixtureDestination 'migrations/002_pending.sql') -ceq 'SELECT 2;') 'Only explicitly named pending migration is included.'
    Assert-True ((Read-Fixture $fixtureDestination 'config/pending.php') -ceq '<?php return "pending";') 'Explicit pending runtime config is included.'
    Assert-True ((Read-Fixture $fixtureDestination 'tests/pending_test.ps1') -ceq '# Explicit pending test') 'Explicit pending support file is included with support opt-in.'
    foreach ($relative in @('docs/private.md', 'scripts/private.py', 'pages/unnamed.php', 'tests/unrequested_test.php')) {
        Assert-True (!(Test-Path (Join-Path $fixtureDestination $relative))) "Unselected file stays excluded: $relative"
    }

    foreach ($relative in @('.env', 'config/.env.local', 'assets/uploads/private.txt', '.git/HEAD', '../outside.php', 'C:/outside.php', 'pages/*.php', 'config\pending.php', 'config//pending.php', 'config/CON.php', 'docs/private.md', 'scripts/private.py', 'migrations/missing.sql', '')) {
        Assert-Rejected @{ Apply = $true; IncludeSupportFiles = $true; AdditionalFiles = @('config/pending.php', $relative) } "Unsafe, forbidden, or missing additional file: $relative"
    }
    Assert-Rejected @{ Apply = $true; AdditionalFiles = @('tests/pending_test.ps1') } 'Support additional files require explicit support opt-in.'
    Assert-Rejected @{ Apply = $true; AdditionalFiles = @('README.md') } 'Tracked support files cannot bypass support opt-in through AdditionalFiles.'
    $outside = Join-Path $sandbox 'outside'
    Write-Fixture $outside 'escape.php' '<?php echo "outside source";'
    $sourceJunction = Join-Path $fixtureSource 'config/junction'
    New-Item -ItemType Junction -Path $sourceJunction -Target $outside | Out-Null
    try {
        Assert-Rejected @{ Apply = $true; AdditionalFiles = @('config/junction/escape.php') } 'Additional source files cannot traverse a junction.'
    }
    finally { [IO.Directory]::Delete($sourceJunction) }
    $destinationJunction = Join-Path $fixtureDestination 'config/junction'
    Write-Fixture $fixtureSource 'config/junction/escape.php' '<?php echo "safe source";'
    New-Item -ItemType Junction -Path $destinationJunction -Target $outside | Out-Null
    try {
        Assert-Rejected @{ Apply = $true; AdditionalFiles = @('config/junction/escape.php') } 'Additional destination files cannot traverse a junction.'
    }
    finally { [IO.Directory]::Delete($destinationJunction) }
    Remove-Item -LiteralPath (Join-Path $fixtureSource 'tests/.htaccess')
    Assert-Rejected @{ Apply = $true; IncludeSupportFiles = $true } 'Support tests must not deploy without their tracked web-access protection.'
    Write-Fixture $fixtureSource 'tests/.htaccess' $tracked['tests/.htaccess']
    Remove-Item -LiteralPath (Join-Path $fixtureSource 'config/app.php')
    Assert-Rejected @{ Apply = $true } 'Missing tracked runtime files abort before any writes.'

    $freshDestination = Join-Path $sandbox 'fresh-destination'
    Write-Fixture $fixtureSource 'config/app.php' $tracked['config/app.php']
    & $deployScript -Source $fixtureSource -Destination $freshDestination *> $null
    Assert-True (!(Test-Path -LiteralPath $freshDestination)) 'Preview does not create a missing destination.'
    Write-Host "PASS: $script:assertions deploy fixture assertions. No real deployment was used."
}
finally {
    $env:LOCALAPPDATA = $originalLocalAppData
    if (Test-Path -LiteralPath $sandbox) { Remove-Item -LiteralPath $sandbox -Recurse -Force }
}
