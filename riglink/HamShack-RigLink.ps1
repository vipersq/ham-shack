<#
  Ham Shack Rig Link 1.0
  Reads the frequency and mode from Ham Radio Deluxe (HRD IP server, port 7809)
  and sends them to your Ham Shack WordPress website. Read-only - it never changes
  anything on the radio.

  Usage:
    HamShack-RigLink.ps1          continuous mode (the autostart task runs this hidden)
    HamShack-RigLink.ps1 -Test    one-off test: HRD -> website, result on screen
#>
param(
    [switch]$Test,
    [string]$ConfigPath = (Join-Path $PSScriptRoot 'config.txt')
)

$ErrorActionPreference = 'Stop'
$Version = '1.0'
try { [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12 } catch {}

# ---------------------------------------------------------------- config
$cfg = @{
    SiteUrl          = ''
    Token            = ''
    HrdHost          = '127.0.0.1'
    HrdPort          = '7809'
    PollSeconds      = '3'
    HeartbeatSeconds = '60'
}
if (-not (Test-Path $ConfigPath)) { Write-Host "config.txt not found: $ConfigPath" -ForegroundColor Red; exit 2 }
foreach ($line in Get-Content -LiteralPath $ConfigPath) {
    $l = $line.Trim()
    if ($l -eq '' -or $l.StartsWith('#')) { continue }
    $i = $l.IndexOf('=')
    if ($i -gt 0) { $cfg[$l.Substring(0, $i).Trim()] = $l.Substring($i + 1).Trim() }
}
$cfg.SiteUrl = $cfg.SiteUrl.TrimEnd('/')
if ($cfg.SiteUrl -eq '' -or $cfg.Token -eq '' -or $cfg.Token -like '*PASTE*') {
    Write-Host 'Fill in SiteUrl and Token in config.txt (Token: WordPress admin -> Ham Shack -> Live rig (HRD)).' -ForegroundColor Red
    exit 2
}
$ApiUrl    = $cfg.SiteUrl + '/?rest_route=/hamshack/v1/rig'
$Poll      = [Math]::Max(1, [int]$cfg.PollSeconds)
$Heartbeat = [Math]::Max(15, [int]$cfg.HeartbeatSeconds)
$LogFile   = Join-Path $PSScriptRoot 'RigLink.log'

function Log([string]$msg, [string]$color = 'Gray') {
    $line = (Get-Date -Format 'yyyy-MM-dd HH:mm:ss') + '  ' + $msg
    Write-Host $line -ForegroundColor $color
    try {
        if ((Test-Path $LogFile) -and (Get-Item $LogFile).Length -gt 1MB) { Move-Item $LogFile ($LogFile + '.old') -Force }
        Add-Content -LiteralPath $LogFile -Value $line -Encoding UTF8
    } catch {}
}

# ---------------------------------------------------------------- HRD client
$script:client = $null
$script:stream = $null
$Magic = [byte[]](0xCD, 0xAB, 0x34, 0x12, 0x34, 0x12, 0xCD, 0xAB)   # 0x1234ABCD, 0xABCD1234 (little endian)

function Close-Hrd {
    try { if ($script:stream) { $script:stream.Close() } } catch {}
    try { if ($script:client) { $script:client.Close() } } catch {}
    $script:client = $null; $script:stream = $null
}

function Connect-Hrd {
    Close-Hrd
    $c = New-Object System.Net.Sockets.TcpClient
    $ar = $c.BeginConnect($cfg.HrdHost, [int]$cfg.HrdPort, $null, $null)
    if (-not $ar.AsyncWaitHandle.WaitOne(4000)) { $c.Close(); throw "HRD does not answer on $($cfg.HrdHost):$($cfg.HrdPort) - is HRD Rig Control running?" }
    $c.EndConnect($ar)
    $c.ReceiveTimeout = 5000
    $c.SendTimeout = 5000
    $script:client = $c
    $script:stream = $c.GetStream()
}

function Read-Exact([int]$count) {
    $buf = New-Object byte[] $count
    $got = 0
    while ($got -lt $count) {
        $n = $script:stream.Read($buf, $got, $count - $got)
        if ($n -le 0) { throw 'HRD closed the connection.' }
        $got += $n
    }
    return ,$buf
}

function Invoke-Hrd([string]$cmd) {
    $payload = [Text.Encoding]::Unicode.GetBytes($cmd + [char]0)
    $size = 16 + $payload.Length
    $msg = New-Object byte[] $size
    [BitConverter]::GetBytes([uint32]$size).CopyTo($msg, 0)
    [Array]::Copy($Magic, 0, $msg, 4, 8)
    [Array]::Copy($payload, 0, $msg, 16, $payload.Length)   # bytes 12..15 = checksum 0
    $script:stream.Write($msg, 0, $size)
    $script:stream.Flush()

    $hdr = Read-Exact 16
    $rsize = [BitConverter]::ToUInt32($hdr, 0)
    if ($rsize -lt 16 -or $rsize -gt 1048576) { throw "Unexpected reply from HRD (size $rsize)." }
    $body = if ($rsize -gt 16) { Read-Exact ([int]$rsize - 16) } else { New-Object byte[] 0 }
    return ([Text.Encoding]::Unicode.GetString($body)).TrimEnd([char]0).Trim()
}

$script:ctx = ''
$script:radioName = ''
$script:modeDropdown = ''

function Initialize-Radio {
    Connect-Hrd
    [void](Invoke-Hrd 'get context')
    $radios = Invoke-Hrd 'get radios'              # np. "1:radio"
    $current = Invoke-Hrd 'get radio'              # np. "radio"
    if (-not $radios -or -not $current) { throw 'HRD is running but sees no radio (is Rig Control connected to the radio?).' }
    $id = $null
    foreach ($r in $radios.Split(',')) {
        $p = $r.Trim().Split(':')
        if ($p.Count -ge 2 -and ($id -eq $null -or $p[1].Trim() -eq $current)) { $id = $p[0].Trim() }
    }
    if (-not $id) { $id = '1' }
    $script:ctx = "[$id] "
    $script:radioName = $current
    $dds = (Invoke-Hrd ($script:ctx + 'get dropdowns')).Split(',') | ForEach-Object { $_.Trim() }
    $script:modeDropdown = ($dds | Where-Object { $_ -eq 'Mode' } | Select-Object -First 1)
    if (-not $script:modeDropdown) { $script:modeDropdown = ($dds | Where-Object { $_ -match 'mode' } | Select-Object -First 1) }
    Log ("Connected to HRD: radio '" + $script:radioName + "'" + $(if ($script:modeDropdown) { ", mode list: '" + $script:modeDropdown + "'" } else { ', (no mode list)' })) 'Green'
}

function Read-Rig {
    $f = Invoke-Hrd ($script:ctx + 'get frequency')
    $hz = [uint64]0
    if (-not [uint64]::TryParse(($f -replace '[^0-9]', ''), [ref]$hz)) { throw "HRD returned an unexpected frequency: '$f'" }
    $mode = ''
    if ($script:modeDropdown) {
        $t = Invoke-Hrd ($script:ctx + 'get dropdown-text {' + $script:modeDropdown + '}')
        $i = $t.IndexOf(':')
        $mode = if ($i -ge 0) { $t.Substring($i + 1).Trim() } else { $t.Trim() }
    }
    return @{ hz = $hz; mode = $mode }
}

# ---------------------------------------------------------------- website
function Send-Site($body) {
    $json = $body | ConvertTo-Json -Compress
    try {
        $r = Invoke-RestMethod -Method Post -Uri $ApiUrl -Headers @{ 'X-HamShack-Token' = $cfg.Token } `
             -ContentType 'application/json; charset=utf-8' -Body ([Text.Encoding]::UTF8.GetBytes($json)) `
             -UserAgent "HamShack-RigLink/$Version (Windows PowerShell)" -TimeoutSec 20
        return $r
    } catch {
        $code = $null
        try { $code = [int]$_.Exception.Response.StatusCode } catch {}
        if ($code -eq 401) { Log 'Website rejected the TOKEN - copy the current token from WordPress (Live rig) into config.txt.' 'Red' }
        elseif ($code -eq 403) { Log 'Website: Live rig is switched off (Ham Shack -> Live rig -> Enable) or the hosting firewall blocks the request.' 'Red' }
        elseif ($code) { Log "Website answered with HTTP error $code." 'Red' }
        else { Log ('Cannot reach the website: ' + $_.Exception.Message) 'Red' }
        return $null
    }
}

# ---------------------------------------------------------------- test mode
if ($Test) {
    Write-Host "Ham Shack Rig Link $Version - TEST" -ForegroundColor Cyan
    try {
        Initialize-Radio
        $r = Read-Rig
        Write-Host ("Radio: {0}   Frequency: {1:N0} Hz   Mode: {2}" -f $script:radioName, $r.hz, $r.mode) -ForegroundColor Yellow
        $res = Send-Site @{ freq_hz = $r.hz; mode = $r.mode; rig = $script:radioName }
        if ($res -and $res.ok) { Write-Host ("Website accepted the data: {0} MHz {1}  -> everything works!" -f $res.freq, $res.mode) -ForegroundColor Green; exit 0 }
        exit 1
    } catch {
        Write-Host ('ERROR: ' + $_.Exception.Message) -ForegroundColor Red
        exit 1
    } finally { Close-Hrd }
}

# ---------------------------------------------------------------- main loop
Log "Ham Shack Rig Link $Version start -> $($cfg.SiteUrl)" 'Cyan'
$last = $null
$lastSent = [DateTime]::MinValue
$online = $false
$downSince = $null
$connected = $false

try {
    while ($true) {
        try {
            if (-not $connected) { Initialize-Radio; $connected = $true; $downSince = $null }
            $r = Read-Rig
            if ($r.hz -gt 0) {
                $key = "$($r.hz)|$($r.mode)"
                $now = Get-Date
                if ($key -ne $last -or ($now - $lastSent).TotalSeconds -ge $Heartbeat) {
                    $res = Send-Site @{ freq_hz = $r.hz; mode = $r.mode; rig = $script:radioName }
                    if ($res -and $res.ok) {
                        if ($key -ne $last) { Log ("{0} MHz {1}" -f $res.freq, $res.mode) }
                        $last = $key; $lastSent = $now; $online = $true
                    }
                }
            }
            Start-Sleep -Seconds $Poll
        } catch {
            if ($connected) { Log ('HRD: ' + $_.Exception.Message) 'DarkYellow' }
            $connected = $false
            Close-Hrd
            if (-not $downSince) { $downSince = Get-Date }
            if ($online -and ((Get-Date) - $downSince).TotalSeconds -ge 30) {
                if (Send-Site @{ offline = $true }) { Log 'HRD/radio not available - website switched to OFF AIR.' 'DarkYellow' }
                $online = $false; $last = $null
            }
            Start-Sleep -Seconds 10
        }
    }
} finally {
    if ($online) { [void](Send-Site @{ offline = $true }) }
    Close-Hrd
}
