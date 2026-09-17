param([string]$PairingFile = (Join-Path $PSScriptRoot 'santabeachclub-pc.json'), [string]$Destination = (Join-Path ([Environment]::GetFolderPath('MyDocuments')) 'SantaBeachClub Backups'))
$ErrorActionPreference = 'Stop'
$pair = Get-Content -LiteralPath $PairingFile -Raw | ConvertFrom-Json
$uri = [Uri]$pair.endpoint
if (!$uri.IsAbsoluteUri -or $uri.Scheme -ne 'https' -or $uri.UserInfo -or $uri.Query -or $uri.Fragment -or !$uri.AbsolutePath.EndsWith('/backend/api/pc_backup.php') -or $pair.token -notmatch '^[a-f0-9]{64}$') { throw 'Invalid PC connection file.' }
$source = Join-Path $PSScriptRoot 'download_pc_backup.ps1'
if (!(Test-Path -LiteralPath $source)) { throw 'Save download_pc_backup.ps1 beside this installer.' }
$taskRoot = Join-Path $env:LOCALAPPDATA 'SantaBeachClubBackups'
New-Item -ItemType Directory -Path $taskRoot -Force | Out-Null
$identity = [Security.Principal.WindowsIdentity]::GetCurrent()
$acl = New-Object Security.AccessControl.DirectorySecurity
$acl.SetOwner($identity.User)
$acl.SetAccessRuleProtection($true, $false)
$rule = New-Object Security.AccessControl.FileSystemAccessRule($identity.User, 'FullControl', 'ContainerInherit,ObjectInherit', 'None', 'Allow')
$acl.AddAccessRule($rule)
Set-Acl -LiteralPath $taskRoot -AclObject $acl
$secret = ConvertTo-SecureString -String $pair.token -AsPlainText -Force
$credential = New-Object Management.Automation.PSCredential('SantaBeachClubBackup', $secret)
$credential | Export-Clixml -LiteralPath (Join-Path $taskRoot 'access-key.xml')
$runner = Join-Path $taskRoot 'download_pc_backup.ps1'
Copy-Item -LiteralPath $source -Destination $runner -Force
$configPath = Join-Path $taskRoot 'config.json'
@{endpoint=$uri.AbsoluteUri;destination=[IO.Path]::GetFullPath($Destination)} | ConvertTo-Json | Set-Content -LiteralPath $configPath -Encoding UTF8
# A newly paired server invalidates any pending request from the old installation.
@{last_success=$null;request_id=$null} | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $taskRoot 'download-state.json') -Encoding UTF8
$taskName = 'SantaBeachClub Agila PC Backups'
$existing = Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue
if ($existing -and ($existing.Actions.Arguments -join ' ') -notlike "*$runner*") { throw 'A different task already uses this name.' }
$powershell = Join-Path $env:SystemRoot 'System32\WindowsPowerShell\v1.0\powershell.exe'
$action = New-ScheduledTaskAction -Execute $powershell -Argument ('-NoProfile -NonInteractive -WindowStyle Hidden -ExecutionPolicy Bypass -File "' + $runner + '" -ConfigPath "' + $configPath + '"')
# Check frequently so an interrupted or delayed run does not defer the next
# backup by another six-hour cycle. The downloader enforces the six-hour interval.
$triggers = @((New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes 15)), (New-ScheduledTaskTrigger -AtLogOn -User $identity.Name))
$settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 20) -RestartCount 3 -RestartInterval (New-TimeSpan -Minutes 10)
$principal = New-ScheduledTaskPrincipal -UserId $identity.Name -LogonType Interactive -RunLevel Limited
Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $triggers -Settings $settings -Principal $principal -Description 'Check every 15 minutes while signed in; download a verified database backup when six hours have elapsed since the last success. Catch up at logon.' -Force | Out-Null
Start-ScheduledTask -TaskName $taskName
Write-Output ('Installed. Backups: ' + $Destination)
Write-Output ('Check first-download status in: ' + (Join-Path $taskRoot 'download.log'))
Write-Output 'After the first successful download, remove santabeachclub-pc.json from your Downloads folder.'
