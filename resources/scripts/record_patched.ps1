#Requires -Version 5.1
<#
.SYNOPSIS
    record_patched.ps1 - tell Patchmon this server has been patched.

.DESCRIPTION
    The Windows counterpart to record_patched.sh.

    First run (no token yet): provisions a per-machine token from Patchmon by the
    server's FQDN, saves it to the local config file, then records the patch.
    Every run after that just records the patch with the saved token.

    Download a copy (with this install's URL pre-filled) from Patchmon:
      Settings -> API examples -> Download record_patched.ps1

    Manual test checklist (the script itself has no automated tests by design;
    Patchmon's endpoints are covered by the PHP test suite):
      1. Fresh box, no config            -> provisions, writes config locked down, records.
      2. Second run                      -> uses the saved token, records, no re-provision.
      3. Provision an already-provisioned FQDN without resetting in the UI
                                         -> 409 message, exits non-zero.
      4. Run as a non-Administrator      -> prints the token instead of writing the
                                            file, still records the patch.
      5. Token regenerated in the UI     -> next run gets a 404, discards the stale
                                            token, re-enrols, and records again.
#>

$ErrorActionPreference = 'Stop'

# Windows PowerShell 5.1 does not always negotiate TLS 1.2 by default, which a
# modern HTTPS Patchmon needs. Newer PowerShell ignores this harmlessly.
[Net.ServicePointManager]::SecurityProtocol = `
    [Net.ServicePointManager]::SecurityProtocol -bor [Net.SecurityProtocolType]::Tls12

$ConfigFile = 'C:\ProgramData\Patchmon\patchmon.env'

function Write-Log {
    param([string] $Message)
    Write-Host "Patchmon: $Message"
}

# Print to stderr and exit non-zero, the PowerShell equivalent of the bash die().
function Stop-WithError {
    param([string] $Message)
    [Console]::Error.WriteLine("Patchmon: $Message")
    exit 1
}

# Parse a "KEY=value" / KEY="value" config file into a hashtable. Mirrors the
# Linux script sourcing /etc/patchmon.env, but read rather than executed.
function Read-ConfigValues {
    param([string] $Path)

    $values = @{}
    if (-not (Test-Path -LiteralPath $Path)) {
        return $values
    }

    foreach ($line in Get-Content -LiteralPath $Path) {
        if ($line -match '^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$') {
            $values[$matches[1]] = $matches[2].Trim().Trim('"')
        }
    }

    return $values
}

# The Linux script uses `hostname -f`. The domain-joined name is the closest
# equivalent; fall back to the bare computer name if the box isn't on a domain.
function Get-Fqdn {
    $system = Get-CimInstance -ClassName Win32_ComputerSystem -ErrorAction SilentlyContinue

    if ($system -and $system.Domain -and $system.Domain -ne 'WORKGROUP') {
        return "$($system.Name).$($system.Domain)"
    }

    if ($system -and $system.Name) {
        return $system.Name
    }

    return $env:COMPUTERNAME
}

function Test-IsAdministrator {
    $identity = [Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = New-Object Security.Principal.WindowsPrincipal($identity)

    return $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
}

# POST to Patchmon and return the status code (plus body for the few responses
# that carry one). An HTTP error response is reported by its code, not thrown -
# only a genuine "couldn't reach the server" failure is fatal here.
function Invoke-PatchmonPost {
    param(
        [Parameter(Mandatory)] [string] $Uri,
        [hashtable] $Body
    )

    $params = @{
        Uri             = $Uri
        Method          = 'Post'
        UseBasicParsing = $true
    }

    if ($PSBoundParameters.ContainsKey('Body')) {
        $params.ContentType = 'application/json'
        $params.Body = ($Body | ConvertTo-Json -Compress)
    }

    try {
        $response = Invoke-WebRequest @params

        return [pscustomobject]@{
            StatusCode = [int] $response.StatusCode
            Body       = [string] $response.Content
        }
    }
    catch {
        $webResponse = $_.Exception.Response

        if ($null -eq $webResponse) {
            Stop-WithError "could not reach Patchmon at $script:PatchmonUrl"
        }

        return [pscustomobject]@{
            StatusCode = [int] $webResponse.StatusCode
            Body       = ''
        }
    }
}

function Invoke-Provision {
    Write-Log "requesting a token for $script:Fqdn"

    # os_type is sent on first-run provision to save a triage click. This script
    # only ever runs on Windows, so the hint is always 'windows'.
    $payload = @{ fqdn = $script:Fqdn; os_type = 'windows' }
    $result = Invoke-PatchmonPost -Uri "$script:PatchmonUrl/record-patch/provision" -Body $payload

    switch ($result.StatusCode) {
        200 {
            $token = ($result.Body | ConvertFrom-Json).patch_token
            if (-not $token) {
                Stop-WithError "provision response contained no token"
            }
            $script:PatchmonToken = $token
            Save-Config
        }
        409 { Stop-WithError "a token has already been provisioned for $script:Fqdn. Reset it in Patchmon's web UI, then run again." }
        429 { Stop-WithError "Patchmon is rate-limiting provision requests - wait a moment and try again." }
        422 { Stop-WithError "Patchmon rejected the hostname '$script:Fqdn' - it must be a fully-qualified domain name." }
        default { Stop-WithError "unexpected response ($($result.StatusCode)) requesting a token for $script:Fqdn" }
    }
}

# Record a patch. The retry after re-enrolling passes -AllowReenrol:$false so a
# fresh token that is somehow still rejected fails loudly instead of looping.
function Invoke-RecordPatch {
    param([bool] $AllowReenrol = $true)

    $result = Invoke-PatchmonPost -Uri "$script:PatchmonUrl/record-patch/$script:PatchmonToken"

    switch ($result.StatusCode) {
        { $_ -in 200, 204 } {
            Write-Log "recorded patch for $script:Fqdn"
            return
        }
        404 {
            # Saved token rejected - most likely regenerated in Patchmon's web UI.
            # Discard it, claim a fresh one by FQDN, and record once more.
            if (-not $AllowReenrol) {
                Stop-WithError "token still rejected after re-enrolling $script:Fqdn"
            }
            Write-Log "saved token was rejected - re-enrolling $script:Fqdn"
            $script:PatchmonToken = ''
            Invoke-Provision
            Invoke-RecordPatch -AllowReenrol:$false
            return
        }
        default {
            Stop-WithError "unexpected response ($($result.StatusCode)) recording patch for $script:Fqdn"
        }
    }
}

function Save-Config {
    if (-not (Test-IsAdministrator)) {
        Write-Log "not running as Administrator - could not write $ConfigFile"
        Write-Log "save this line yourself: PATCHMON_TOKEN=`"$script:PatchmonToken`""
        return
    }

    $directory = Split-Path -Parent $ConfigFile
    if (-not (Test-Path -LiteralPath $directory)) {
        New-Item -ItemType Directory -Path $directory -Force | Out-Null
    }

    $contents = "PATCHMON_URL=`"$script:PatchmonUrl`"`r`nPATCHMON_TOKEN=`"$script:PatchmonToken`"`r`n"
    Set-Content -LiteralPath $ConfigFile -Value $contents -Encoding Ascii -NoNewline

    # Lock the file to Administrators and SYSTEM only - the Windows equivalent of
    # the chmod 0600 the Linux script applies. Well-known SIDs avoid trouble on
    # non-English Windows where the group names are localised.
    icacls $ConfigFile /inheritance:r /grant:r '*S-1-5-32-544:F' '*S-1-5-18:F' | Out-Null

    Write-Log "token saved to $ConfigFile"
}

# --- Main -------------------------------------------------------------------

# Patchmon URL. Pre-filled for this install when downloaded from the app; an
# existing environment value or the config file can still override it.
$script:PatchmonUrl = if ($env:PATCHMON_URL) { $env:PATCHMON_URL } else { '__PATCHMON_URL__' }
$script:PatchmonToken = if ($env:PATCHMON_TOKEN) { $env:PATCHMON_TOKEN } else { '' }

# Local config wins over the baked-in default and any pre-set environment value,
# matching the Linux script which sources its config file last.
$config = Read-ConfigValues -Path $ConfigFile
if ($config['PATCHMON_URL']) { $script:PatchmonUrl = $config['PATCHMON_URL'] }
if ($config['PATCHMON_TOKEN']) { $script:PatchmonToken = $config['PATCHMON_TOKEN'] }

$script:Fqdn = Get-Fqdn
if (-not $script:Fqdn) {
    Stop-WithError "could not determine this server's hostname"
}

# A rendered download fills PATCHMON_URL in above; the raw script (or a missing
# override) leaves it as the unfilled placeholder, which won't look like a URL.
if ($script:PatchmonUrl -notmatch '^https?://') {
    Stop-WithError "PATCHMON_URL is not set - edit this script or $ConfigFile"
}

if ($script:PatchmonToken) {
    Invoke-RecordPatch
}
else {
    Write-Log "no saved token in $ConfigFile"
    Invoke-Provision
    Invoke-RecordPatch
}
