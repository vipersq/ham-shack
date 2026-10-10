<#
  Ham Shack Rig Link 2.0
  1) Reads frequency and mode from Ham Radio Deluxe (HRD IP server, port 7809).
  2) Listens to WSJT-X (UDP, port 2237): live FT8/FT4 decodes + QSOs logged in WSJT-X.
  Sends everything to your Ham Shack website. Read-only - never changes anything in the radio or WSJT-X.

  Usage:
    HamShack-RigLink.ps1          run continuously (autostart runs this hidden)
    HamShack-RigLink.ps1 -Test    one-off test: HRD + WSJT-X -> website, result on screen
#>
param(
    [switch]$Test,
    [string]$ConfigPath = (Join-Path $PSScriptRoot 'config.txt')
)

$ErrorActionPreference = 'Stop'
$Version = '2.0'
try { [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12 } catch {}

# ---------------------------------------------------------------- config
$cfg = @{
    SiteUrl          = ''
    Token            = ''
    HrdEnabled       = '1'
    HrdHost          = '127.0.0.1'
    HrdPort          = '7809'
    PollSeconds      = '3'
    HeartbeatSeconds = '60'
    WsjtxEnabled     = '1'
    WsjtxPort        = '2237'
    WsjtxMulticast   = ''
    WsjtxSendSeconds = '15'
    WsjtxForRig      = '1'
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
    Write-Host 'Fill in SiteUrl and Token in config.txt (Token: WordPress admin -> Ham Shack -> Live rig -> Generate token).' -ForegroundColor Red
    exit 2
}
$ApiRig    = $cfg.SiteUrl + '/?rest_route=/hamshack/v1/rig'
$ApiWsjtx  = $cfg.SiteUrl + '/?rest_route=/hamshack/v1/wsjtx'
$Poll      = [Math]::Max(1, [int]$cfg.PollSeconds)
$Heartbeat = [Math]::Max(15, [int]$cfg.HeartbeatSeconds)
$UseHrd    = $cfg.HrdEnabled -ne '0'
$UseWsjtx  = $cfg.WsjtxEnabled -ne '0'
$WsjtxSend = [Math]::Max(5, [int]$cfg.WsjtxSendSeconds)
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
    if (-not $ar.AsyncWaitHandle.WaitOne(3000)) { $c.Close(); throw "HRD does not answer on $($cfg.HrdHost):$($cfg.HrdPort) - is HRD Rig Control running?" }
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
    $radios = Invoke-Hrd 'get radios'              # e.g. "1:FT-450D"
    $current = Invoke-Hrd 'get radio'              # e.g. "FT-450D"
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

# ---------------------------------------------------------------- WSJT-X (UDP, Qt QDataStream, big endian)
$script:udp = $null
$script:wsStatus = $null        # last Status message from WSJT-X
$script:wsStatusTime = [DateTime]::MinValue
$script:wsDecodes = New-Object System.Collections.ArrayList
$script:wsLogged = New-Object System.Collections.ArrayList
$script:wsClosed = $false

function Open-Wsjtx {
    $u = New-Object System.Net.Sockets.UdpClient
    $u.Client.SetSocketOption([System.Net.Sockets.SocketOptionLevel]::Socket, [System.Net.Sockets.SocketOptionName]::ReuseAddress, $true)
    # Only localhost unless multicast is used (no Windows Firewall prompt, nothing exposed to the network).
    $addr = if ($cfg.WsjtxMulticast) { [System.Net.IPAddress]::Any } else { [System.Net.IPAddress]::Loopback }
    $u.Client.Bind((New-Object System.Net.IPEndPoint($addr, [int]$cfg.WsjtxPort)))
    if ($cfg.WsjtxMulticast) { $u.JoinMulticastGroup([System.Net.IPAddress]::Parse($cfg.WsjtxMulticast)) }
    $script:udp = $u
    Log ("Listening for WSJT-X on UDP " + $cfg.WsjtxPort + $(if ($cfg.WsjtxMulticast) { " (multicast " + $cfg.WsjtxMulticast + ")" } else { '' })) 'Green'
}

function New-Reader([byte[]]$data) { return @{ b = $data; p = 0 } }
function R-Bytes($r, [int]$n) {
    if ($r.p + $n -gt $r.b.Length) { throw 'end of packet' }
    $out = New-Object byte[] $n
    [Array]::Copy($r.b, $r.p, $out, 0, $n)
    $r.p += $n
    [Array]::Reverse($out)            # big endian -> little endian
    return ,$out
}
function R-U32($r) { return [BitConverter]::ToUInt32((R-Bytes $r 4), 0) }
function R-I32($r) { return [BitConverter]::ToInt32((R-Bytes $r 4), 0) }
function R-U64($r) { return [BitConverter]::ToUInt64((R-Bytes $r 8), 0) }
function R-Dbl($r) { return [BitConverter]::ToDouble((R-Bytes $r 8), 0) }
function R-Bool($r) { if ($r.p -ge $r.b.Length) { throw 'end of packet' }; $v = $r.b[$r.p]; $r.p += 1; return ($v -ne 0) }
function R-Str($r) {
    $len = R-U32 $r
    if ($len -eq [uint32]::MaxValue -or $len -eq 0) { return '' }
    if ($r.p + $len -gt $r.b.Length) { throw 'end of packet' }
    $s = [Text.Encoding]::UTF8.GetString($r.b, $r.p, [int]$len)
    $r.p += [int]$len
    return $s
}

function Read-WsjtxPacket([byte[]]$data) {
    $r = New-Reader $data
    if ((R-U32 $r) -ne [uint32]2914831322) { return }   # 0xADBCCBDA
    [void](R-U32 $r)                  # schema
    $type = R-U32 $r
    [void](R-Str $r)                  # id (e.g. "WSJT-X")
    switch ($type) {
        1 {                            # Status
            $dial = R-U64 $r; $mode = R-Str $r; $dx = R-Str $r; [void](R-Str $r); [void](R-Str $r)
            $txEn = R-Bool $r; $tx = R-Bool $r; $dec = R-Bool $r
            $script:wsStatus = @{ dial = [uint64]$dial; mode = $mode; dx = $dx; tx = $tx; decoding = $dec }
            $script:wsStatusTime = Get-Date
            $script:wsClosed = $false
        }
        2 {                            # Decode
            $isNew = R-Bool $r; $ms = R-U32 $r; $snr = R-I32 $r; $dt = R-Dbl $r; $df = R-U32 $r
            $mode = R-Str $r; $msg = R-Str $r
            if ($isNew) {
                $t = [TimeSpan]::FromMilliseconds($ms)
                [void]$script:wsDecodes.Add(@{ t = ('{0:00}{1:00}{2:00}' -f $t.Hours, $t.Minutes, $t.Seconds); snr = $snr; dt = [Math]::Round($dt, 1); df = $df; mode = $mode; msg = $msg.Trim() })
                if ($script:wsDecodes.Count -gt 200) { $script:wsDecodes.RemoveRange(0, $script:wsDecodes.Count - 200) }
            }
        }
        6 { $script:wsClosed = $true }  # Close
        12 {                           # Logged ADIF
            $adif = R-Str $r
            if ($adif) { [void]$script:wsLogged.Add($adif); Log 'WSJT-X: QSO logged - sending it to the website logbook.' 'Green' }
        }
    }
}

function Receive-Wsjtx {
    if (-not $script:udp) { return }
    $ep = New-Object System.Net.IPEndPoint([System.Net.IPAddress]::Any, 0)
    $n = 0
    while ($script:udp.Available -gt 0 -and $n -lt 500) {
        $data = $script:udp.Receive([ref]$ep)
        try { Read-WsjtxPacket $data } catch {}
        $n++
    }
}

function Wsjtx-Alive { return ($script:wsStatus -ne $null -and -not $script:wsClosed -and ((Get-Date) - $script:wsStatusTime).TotalSeconds -lt 90) }

# ---------------------------------------------------------------- website
function Send-Site([string]$url, $body) {
    $json = $body | ConvertTo-Json -Compress -Depth 5
    try {
        $r = Invoke-RestMethod -Method Post -Uri $url -Headers @{ 'X-HamShack-Token' = $cfg.Token } `
             -ContentType 'application/json; charset=utf-8' -Body ([Text.Encoding]::UTF8.GetBytes($json)) `
             -UserAgent "HamShack-RigLink/$Version (Windows PowerShell)" -TimeoutSec 20
        return $r
    } catch {
        $code = $null
        try { $code = [int]$_.Exception.Response.StatusCode } catch {}
        if ($code -eq 401) { Log 'Website rejected the TOKEN - copy the current token from WordPress (Live rig) into config.txt.' 'Red' }
        elseif ($code -eq 403) { Log 'Website: Live rig is switched off (Ham Shack -> Live rig -> Enable) or the hosting firewall blocks the request.' 'Red' }
        elseif ($code -eq 404) { Log 'Website does not know this address - update the Ham Shack theme to version 2.0.' 'Red' }
        elseif ($code) { Log "Website answered with HTTP error $code." 'Red' }
        else { Log ('Cannot reach the website: ' + $_.Exception.Message) 'Red' }
        return $null
    }
}

function Flush-Wsjtx([switch]$Force) {
    $hasData = $script:wsDecodes.Count -gt 0 -or $script:wsLogged.Count -gt 0
    if (-not $hasData -and -not $Force) { return $true }
    $body = @{}
    if ($script:wsStatus) { $body.status = $script:wsStatus }
    if ($script:wsDecodes.Count -gt 0) { $body.decodes = @($script:wsDecodes.ToArray() | Select-Object -Last 60) }
    if ($script:wsLogged.Count -gt 0) { $body.logged = ($script:wsLogged.ToArray() -join "`n") }
    $res = Send-Site $ApiWsjtx $body
    if ($res -and $res.ok) {
        $script:wsDecodes.Clear(); $script:wsLogged.Clear()
        return $true
    }
    if ($script:wsLogged.Count -gt 50) { $script:wsLogged.RemoveRange(0, $script:wsLogged.Count - 50) }
    return $false
}

# ---------------------------------------------------------------- test mode
if ($Test) {
    Write-Host "Ham Shack Rig Link $Version - TEST" -ForegroundColor Cyan
    $ok = $false
    if ($UseHrd) {
        try {
            Initialize-Radio
            $r = Read-Rig
            Write-Host ("HRD:     radio {0}   {1:N0} Hz   {2}" -f $script:radioName, $r.hz, $r.mode) -ForegroundColor Yellow
            $res = Send-Site $ApiRig @{ freq_hz = $r.hz; mode = $r.mode; rig = $script:radioName }
            if ($res -and $res.ok) { Write-Host ("Website accepted the HRD data: {0} MHz {1}" -f $res.freq, $res.mode) -ForegroundColor Green; $ok = $true }
        } catch {
            Write-Host ('HRD:     ' + $_.Exception.Message) -ForegroundColor DarkYellow
        } finally { Close-Hrd }
    }
    if ($UseWsjtx) {
        try {
            Open-Wsjtx
            Write-Host 'WSJT-X: waiting up to 20 s for data (WSJT-X must be running)...' -ForegroundColor Gray
            $until = (Get-Date).AddSeconds(20)
            while ((Get-Date) -lt $until -and -not $script:wsStatus) { Receive-Wsjtx; Start-Sleep -Milliseconds 250 }
            if ($script:wsStatus) {
                Start-Sleep -Seconds 2; Receive-Wsjtx
                Write-Host ("WSJT-X:  {0:N0} Hz  {1}  (decodes buffered: {2})" -f $script:wsStatus.dial, $script:wsStatus.mode, $script:wsDecodes.Count) -ForegroundColor Yellow
                if (Flush-Wsjtx -Force) { Write-Host 'Website accepted the WSJT-X data.' -ForegroundColor Green; $ok = $true }
            } else {
                Write-Host ('WSJT-X:  no data on UDP port ' + $cfg.WsjtxPort + ' (WSJT-X not running, or a different port/address in Settings -> Reporting).') -ForegroundColor DarkYellow
            }
        } catch {
            Write-Host ('WSJT-X:  ' + $_.Exception.Message) -ForegroundColor DarkYellow
        } finally { try { $script:udp.Close() } catch {} }
    }
    if ($ok) { Write-Host '-> everything works!' -ForegroundColor Green; exit 0 }
    Write-Host 'Nothing reached the website - see the messages above.' -ForegroundColor Red
    exit 1
}

# ---------------------------------------------------------------- main loop
Log "Ham Shack Rig Link $Version start -> $($cfg.SiteUrl)" 'Cyan'
if ($UseWsjtx) { try { Open-Wsjtx } catch { Log ('WSJT-X: cannot open UDP port ' + $cfg.WsjtxPort + ': ' + $_.Exception.Message) 'Red'; $UseWsjtx = $false } }

$last = $null
$lastSent = [DateTime]::MinValue
$online = $false
$downSince = $null
$connected = $false
$nextHrd = [DateTime]::MinValue
$nextWs = (Get-Date).AddSeconds($WsjtxSend)
$wsWasAlive = $false

try {
    while ($true) {
        $now = Get-Date
        if ($UseWsjtx) { Receive-Wsjtx }

        # --- HRD (or WSJT-X as the frequency source when HRD is not running)
        if ($now -ge $nextHrd) {
            $nextHrd = $now.AddSeconds($Poll)
            $reading = $null
            $source = ''
            if ($UseHrd) {
                try {
                    if (-not $connected) { Initialize-Radio; $connected = $true }
                    $reading = Read-Rig
                    $source = $script:radioName
                } catch {
                    if ($connected) { Log ('HRD: ' + $_.Exception.Message) 'DarkYellow' }
                    $connected = $false
                    Close-Hrd
                    $nextHrd = $now.AddSeconds(10)
                }
            }
            if (-not $reading -and $cfg.WsjtxForRig -ne '0' -and (Wsjtx-Alive) -and $script:wsStatus.dial -gt 0) {
                $reading = @{ hz = $script:wsStatus.dial; mode = $script:wsStatus.mode }
                $source = 'WSJT-X'
            }
            if ($reading -and $reading.hz -gt 0) {
                $downSince = $null
                $key = "$($reading.hz)|$($reading.mode)"
                if ($key -ne $last -or ($now - $lastSent).TotalSeconds -ge $Heartbeat) {
                    $res = Send-Site $ApiRig @{ freq_hz = $reading.hz; mode = $reading.mode; rig = $source }
                    if ($res -and $res.ok) {
                        if ($key -ne $last) { Log ("{0} MHz {1}  ({2})" -f $res.freq, $res.mode, $source) }
                        $last = $key; $lastSent = $now; $online = $true
                    }
                }
            } else {
                if (-not $downSince) { $downSince = $now }
                if ($online -and ($now - $downSince).TotalSeconds -ge 30) {
                    if (Send-Site $ApiRig @{ offline = $true }) { Log 'Radio not available (HRD/WSJT-X) - website switched to OFF AIR.' 'DarkYellow' }
                    $online = $false; $last = $null
                }
            }
        }

        # --- WSJT-X decodes / logged QSOs
        if ($UseWsjtx -and $now -ge $nextWs) {
            $nextWs = $now.AddSeconds($WsjtxSend)
            $alive = Wsjtx-Alive
            if ($alive) {
                if (-not $wsWasAlive) { Log 'WSJT-X is sending data - forwarding decodes to the website.' 'Green' }
                [void](Flush-Wsjtx -Force)
            } elseif ($script:wsLogged.Count -gt 0) {
                [void](Flush-Wsjtx)
            } elseif ($wsWasAlive) {
                Log 'WSJT-X closed / quiet.' 'DarkYellow'
                [void](Send-Site $ApiWsjtx @{ closed = $true })
                $script:wsDecodes.Clear()
            }
            $wsWasAlive = $alive
        }

        Start-Sleep -Milliseconds 400
    }
} finally {
    if ($online) { [void](Send-Site $ApiRig @{ offline = $true }) }
    if ($wsWasAlive) { [void](Send-Site $ApiWsjtx @{ closed = $true }) }
    Close-Hrd
    try { if ($script:udp) { $script:udp.Close() } } catch {}
}
