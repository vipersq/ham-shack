# Installs / removes the "Ham Shack Rig Link" task in Windows Task Scheduler.
param([switch]$Remove)
$name = 'Ham Shack Rig Link'
$script = Join-Path $PSScriptRoot 'HamShack-RigLink.ps1'

if ($Remove) {
    Stop-ScheduledTask -TaskName $name -ErrorAction SilentlyContinue
    Unregister-ScheduledTask -TaskName $name -Confirm:$false -ErrorAction SilentlyContinue
    Get-CimInstance Win32_Process -Filter "Name='powershell.exe'" |
        Where-Object { $_.CommandLine -like '*HamShack-RigLink.ps1*' } |
        ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }
    Write-Host 'Autostart removed, Rig Link stopped.' -ForegroundColor Yellow
    exit 0
}

$action   = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument ('-NoProfile -WindowStyle Hidden -ExecutionPolicy Bypass -File "' + $script + '"') -WorkingDirectory $PSScriptRoot
$trigger  = New-ScheduledTaskTrigger -AtLogOn -User $env:USERNAME
$settings = New-ScheduledTaskSettingsSet -ExecutionTimeLimit ([TimeSpan]::Zero) -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries `
            -RestartCount 999 -RestartInterval (New-TimeSpan -Minutes 1) -MultipleInstances IgnoreNew -StartWhenAvailable
$principal = New-ScheduledTaskPrincipal -UserId $env:USERNAME -LogonType Interactive -RunLevel Limited
Register-ScheduledTask -TaskName $name -Action $action -Trigger $trigger -Settings $settings -Principal $principal -Force | Out-Null
Start-ScheduledTask -TaskName $name
Write-Host 'OK - Rig Link is running in the background and will start automatically every time you log in to Windows.' -ForegroundColor Green
Write-Host ('Log: ' + (Join-Path $PSScriptRoot 'RigLink.log'))
