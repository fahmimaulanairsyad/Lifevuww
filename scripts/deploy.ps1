#requires -Version 5.1
# Preview is the default. Only -Apply writes application files.
[CmdletBinding()]
param(
    [string]$Destination = 'C:\Lifevuww\lifevuww-deploy',
    [string]$Source = (Split-Path -Parent $PSScriptRoot),
    [switch]$IncludeSupportFiles,
    [string[]]$AdditionalFiles = @(),
    [switch]$Apply
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Get-FullPath([string]$Path) {
    return [IO.Path]::GetFullPath($Path).TrimEnd('\', '/')
}

function Test-Within([string]$Path, [string]$Root) {
    return $Path.Equals($Root, [StringComparison]::OrdinalIgnoreCase) -or
        $Path.StartsWith($Root + '\', [StringComparison]::OrdinalIgnoreCase)
}

function Assert-NoLinks([string]$Path) {
    $current = $Path
    while ($current) {
        if (Test-Path -LiteralPath $current) {
            $item = Get-Item -LiteralPath $current -Force
            if ($item.Attributes -band [IO.FileAttributes]::ReparsePoint) {
                throw "Symlink or junction is not allowed: $current"
            }
        }
        $current = Split-Path -Parent $current
    }
}

function Invoke-Git([string]$Arguments) {
    $info = New-Object Diagnostics.ProcessStartInfo
    $info.FileName = 'git.exe'
    $info.Arguments = $Arguments
    $info.WorkingDirectory = $Source
    $info.UseShellExecute = $false
    $info.RedirectStandardOutput = $true
    $info.RedirectStandardError = $true
    $info.StandardOutputEncoding = New-Object Text.UTF8Encoding($false)
    $process = [Diagnostics.Process]::Start($info)
    $stdout = $process.StandardOutput.ReadToEnd()
    $stderr = $process.StandardError.ReadToEnd()
    $process.WaitForExit()
    if ($process.ExitCode -ne 0) { throw "git $Arguments failed: $stderr" }
    $process.Dispose()
    return $stdout
}

function Get-Hash([string]$Path) {
    return (Get-FileHash -LiteralPath $Path -Algorithm SHA256).Hash
}

function Assert-SafeRelativePath([string]$Relative) {
    if (!$Relative -or $Relative.StartsWith('/') -or
        $Relative -match '[\x00-\x1f<>:"\\|?*]' -or $Relative -match '(^|/)\.{1,2}(/|$)' -or
        $Relative -match '//|/$|(^|/)[^/]*[. ](/|$)' -or
        $Relative -match '(^|/)(CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])(\.[^/]*)?(/|$)') {
        throw "Unsafe file name (use explicit repository-relative paths with /): $Relative"
    }
}

function Test-ExcludedPath([string]$Relative) {
    return $Relative -match '(^|/)(\.git[^/]*|\.env[^/]*|uploads?|avatars?|storage|data|cache|logs?|sessions?|backups?)(/|$)'
}

function Test-SelectedPath([string]$Relative) {
    $runtime = $Relative -match '^(assets|config|cron|includes|pages|migrations)/' -or
        $Relative -match '^[^/]+\.php$' -or
        $Relative -in @('.htaccess', 'manifest.json', 'sw.js', 'offline.html')
    $support = $Relative -in @('README.md', 'docs/deployment.md', 'docs/world-guide.md', 'scripts/deploy.ps1') -or
        $Relative -match '^scripts/(check|generate)[^/]*\.py$' -or $Relative -match '^tests/'
    return $runtime -or ($IncludeSupportFiles -and $support)
}

$Source = Get-FullPath $Source
$Destination = Get-FullPath $Destination
Assert-NoLinks $Source
Assert-NoLinks $Destination
if (!(Test-Path -LiteralPath $Source -PathType Container)) { throw 'Source must be an existing Git repository root.' }
if ((Test-Within $Source $Destination) -or (Test-Within $Destination $Source)) {
    throw 'Source and destination must be separate, non-overlapping directories.'
}
if ((Test-Path -LiteralPath $Destination) -and !(Test-Path -LiteralPath $Destination -PathType Container)) {
    throw 'Destination must be a directory.'
}
$gitRoot = Get-FullPath ((Invoke-Git 'rev-parse --show-toplevel').Trim())
if (!$Source.Equals($gitRoot, [StringComparison]::OrdinalIgnoreCase)) { throw 'Source must be the Git repository root.' }

# Tracked working-tree files by default, plus only explicitly named pending files.
$entries = (Invoke-Git 'ls-files --stage -z').Split([char]0)
$selected = New-Object 'System.Collections.Generic.List[string]'
$seen = New-Object 'System.Collections.Generic.HashSet[string]' ([StringComparer]::OrdinalIgnoreCase)
$trackedProtection = $false
foreach ($entry in $entries) {
    if (!$entry) { continue }
    if ($entry -notmatch '^(\d{6}) ([0-9a-f]+) ([0-3])\t(.+)$') { throw 'Unexpected Git index entry.' }
    $mode = $Matches[1]
    $stage = $Matches[3]
    $relative = $Matches[4]
    if ($stage -ne '0') { throw "Resolve Git conflicts before deploying: $relative" }
    if ((Test-ExcludedPath $relative) -or !(Test-SelectedPath $relative)) { continue }
    if ($mode -notin @('100644', '100755')) { throw "Only regular tracked files may be deployed: $relative" }
    Assert-SafeRelativePath $relative
    if (!$seen.Add($relative)) { throw "Case-insensitive path collision: $relative" }
    $selected.Add($relative)
    if ($relative -ceq 'tests/.htaccess') { $trackedProtection = $true }
}
foreach ($relative in $AdditionalFiles) {
    Assert-SafeRelativePath $relative
    if ((Test-ExcludedPath $relative) -or !(Test-SelectedPath $relative)) {
        throw "Additional file is outside the enabled runtime/support allowlist: $relative"
    }
    if ($seen.Add($relative)) { $selected.Add($relative) }
    elseif (!($selected -ccontains $relative)) { throw "Case-insensitive path collision: $relative" }
}
if (@($selected | Where-Object { $_ -match '^tests/' }).Count -gt 0 -and !$trackedProtection) {
    throw 'Deploying tests requires tracked tests/.htaccess web-access protection.'
}
$plan = New-Object 'System.Collections.Generic.List[object]'
foreach ($relative in $selected) {
    $sourceFile = Get-FullPath (Join-Path $Source $relative)
    $targetFile = Get-FullPath (Join-Path $Destination $relative)
    if (!(Test-Within $sourceFile $Source) -or !(Test-Within $targetFile $Destination)) { throw 'Path escapes its root.' }
    Assert-NoLinks $sourceFile
    Assert-NoLinks $targetFile
    if (!(Test-Path -LiteralPath $sourceFile -PathType Leaf)) { throw "Selected source file is missing: $relative" }
    $parent = Split-Path -Parent $targetFile
    while (Test-Within $parent $Destination) {
        if ((Test-Path -LiteralPath $parent) -and !(Test-Path -LiteralPath $parent -PathType Container)) {
            throw "Destination parent is a file: $parent"
        }
        $parent = Split-Path -Parent $parent
    }
    $sourceHash = Get-Hash $sourceFile
    $oldHash = $null
    if (Test-Path -LiteralPath $targetFile) {
        if (!(Test-Path -LiteralPath $targetFile -PathType Leaf)) { throw "Destination file is a directory: $relative" }
        $oldHash = Get-Hash $targetFile
    }
    $action = if ($oldHash -eq $sourceHash) { 'Unchanged' } elseif ($null -eq $oldHash) { 'Add' } else { 'Replace' }
    $plan.Add([pscustomobject]@{ Path = $relative; Action = $action; SourceHash = $sourceHash; PreviousHash = $oldHash })
}
if ($plan.Count -eq 0) { throw 'No runtime or enabled support files were selected.' }
$plan | Select-Object Action, Path | Format-Table -AutoSize | Out-Host
$changes = @($plan | Where-Object Action -ne 'Unchanged')
Write-Host "Selected: $($plan.Count); changes: $($changes.Count); apply: $($Apply.IsPresent)"
$replacements = @($changes | Where-Object Action -eq 'Replace')
if ($replacements.Count -gt 0) {
    Write-Warning "$($replacements.Count) destination files differ and will be replaced on apply. They may contain user edits. Review every Replace; all replaced versions are backed up before any application write. No merge is performed."
}
if (!$Apply) {
    Write-Host 'Preview only. No files or directories were written. Use -Apply to deploy.'
    return
}
if ($changes.Count -eq 0) { Write-Host 'Already matches source. No writes needed.'; return }

$backupRoot = Get-FullPath (Join-Path $env:LOCALAPPDATA 'Temp\opencode\lifevuww-deploy-backups')
Assert-NoLinks $backupRoot
if ((Test-Within $backupRoot $Destination) -or (Test-Within $Destination $backupRoot) -or
    (Test-Within $backupRoot $Source) -or (Test-Within $Source $backupRoot)) {
    throw 'Backup location must be separate from source and destination.'
}
$backup = Join-Path $backupRoot ((Get-Date -Format 'yyyyMMdd-HHmmss') + '-' + [guid]::NewGuid().ToString('N'))
[IO.Directory]::CreateDirectory($backup) | Out-Null
Write-Host "Backup and manifest: $backup"
$manifest = [pscustomobject]@{
    Source = $Source; Destination = $Destination; CreatedAt = (Get-Date -Format o); Files = $changes
}
$manifest | ConvertTo-Json -Depth 5 | Set-Content -LiteralPath (Join-Path $backup 'manifest.json') -Encoding UTF8

# Complete and verify every backup before the first application write.
foreach ($file in $changes) {
    $targetFile = Join-Path $Destination $file.Path
    Assert-NoLinks $targetFile
    if ($file.Action -eq 'Replace') {
        if ((Get-Hash $targetFile) -ne $file.PreviousHash) { throw "Destination changed since preview: $($file.Path)" }
        $backupFile = Join-Path (Join-Path $backup 'files') $file.Path
        [IO.Directory]::CreateDirectory((Split-Path -Parent $backupFile)) | Out-Null
        [IO.File]::Copy($targetFile, $backupFile, $false)
        if ((Get-Hash $backupFile) -ne $file.PreviousHash) { throw "Backup verification failed: $($file.Path)" }
    }
}
foreach ($file in $changes) {
    $sourceFile = Join-Path $Source $file.Path
    $targetFile = Join-Path $Destination $file.Path
    Assert-NoLinks $sourceFile
    Assert-NoLinks $targetFile
    if ((Get-Hash $sourceFile) -ne $file.SourceHash) { throw "Source changed during deployment: $($file.Path)" }
    if ($file.Action -eq 'Replace') {
        if ((Get-Hash $targetFile) -ne $file.PreviousHash) { throw "Destination changed during deployment: $($file.Path)" }
    } elseif (Test-Path -LiteralPath $targetFile) { throw "Destination appeared during deployment: $($file.Path)" }
    [IO.Directory]::CreateDirectory((Split-Path -Parent $targetFile)) | Out-Null
    [IO.File]::Copy($sourceFile, $targetFile, ($file.Action -eq 'Replace'))
    if ((Get-Hash $targetFile) -ne $file.SourceHash) { throw "Copy verification failed: $($file.Path). Backup: $backup" }
}
Write-Host "Applied and SHA-256 verified $($changes.Count) files. Backup: $backup"
