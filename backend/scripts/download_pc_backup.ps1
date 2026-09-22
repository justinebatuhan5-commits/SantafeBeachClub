param([string]$ConfigPath = (Join-Path $env:LOCALAPPDATA 'SantaBeachClubBackups\config.json'), [switch]$Force)
$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Net.Http
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
$config = Get-Content -LiteralPath $ConfigPath -Raw | ConvertFrom-Json
$taskRoot = Split-Path -Parent ([IO.Path]::GetFullPath($ConfigPath))
$statePath = Join-Path $taskRoot 'download-state.json'
$logPath = Join-Path $taskRoot 'download.log'
$lock = $null
$client = $null
$partial = $null
function Save-DownloadState($state) {
    $tempPath = Join-Path $taskRoot 'download-state.tmp'
    $state | ConvertTo-Json | Set-Content -LiteralPath $tempPath -Encoding UTF8
    Move-Item -LiteralPath $tempPath -Destination $statePath -Force
}
try {
    try { $lock = [IO.File]::Open((Join-Path $taskRoot 'download.lock'), 'OpenOrCreate', 'ReadWrite', 'None') }
    catch [IO.IOException] { exit 0 }
    $endpoint = [Uri]$config.endpoint
    if (!$endpoint.IsAbsoluteUri -or $endpoint.Scheme -ne 'https' -or $endpoint.UserInfo -or $endpoint.Query -or $endpoint.Fragment -or !$endpoint.AbsolutePath.EndsWith('/backend/api/pc_backup.php')) { throw 'Invalid HTTPS backup endpoint.' }
    $state = if (Test-Path -LiteralPath $statePath) { Get-Content -LiteralPath $statePath -Raw | ConvertFrom-Json } else { [pscustomobject]@{last_success=$null;request_id=$null} }
    if (!$Force -and $state.last_success -and !$state.request_id -and ([DateTimeOffset]::UtcNow - [DateTimeOffset]::Parse($state.last_success)).TotalHours -lt 6) { exit 0 }
    if (!$state.request_id) { $state.request_id = [Guid]::NewGuid().ToString('N'); Save-DownloadState $state }
    $credential = Import-Clixml -LiteralPath (Join-Path $taskRoot 'access-key.xml')
    $token = $credential.GetNetworkCredential().Password
    if ($token -notmatch '^[a-f0-9]{64}$') { throw 'Invalid PC backup access key. Install a new connection file.' }
    $destination = [IO.Path]::GetFullPath($config.destination)
    if (!(Test-Path -LiteralPath $destination)) { New-Item -ItemType Directory -Path $destination | Out-Null }
    $handler = New-Object Net.Http.HttpClientHandler
    $handler.AllowAutoRedirect = $false
    $client = New-Object Net.Http.HttpClient($handler)
    $client.Timeout = [TimeSpan]::FromMinutes(15)
    $request = New-Object Net.Http.HttpRequestMessage([Net.Http.HttpMethod]::Post, $endpoint)
    $request.Headers.Authorization = New-Object Net.Http.Headers.AuthenticationHeaderValue('Bearer', $token)
    $request.Headers.Add('X-Backup-Request', [string]$state.request_id)
    $response = $client.SendAsync($request, [Net.Http.HttpCompletionOption]::ResponseHeadersRead).GetAwaiter().GetResult()
    try {
        if (!$response.IsSuccessStatusCode) { throw ('Agila returned HTTP ' + [int]$response.StatusCode + '. Check deployment, PC access key and hosting backup support.') }
        $name = [string]($response.Headers.GetValues('X-Backup-Name') | Select-Object -First 1)
        $expectedHash = [string]($response.Headers.GetValues('X-Backup-SHA256') | Select-Object -First 1)
        if ($name -notmatch '^santafe_\d{8}_\d{6}_[a-f0-9]{8}_complete\.tar$' -or $expectedHash -notmatch '^[a-f0-9]{64}$') { throw 'The server did not return a valid complete backup.' }
        $partial = Join-Path $destination ($state.request_id + '.partial')
        $target = Join-Path $destination $name
        $output = [IO.File]::Open($partial, 'Create', 'Write', 'None')
        try { $response.Content.CopyToAsync($output).GetAwaiter().GetResult() } finally { $output.Dispose() }
        $size = (Get-Item -LiteralPath $partial).Length
        if ($size -eq 0 -or ($response.Content.Headers.ContentLength -and $size -ne $response.Content.Headers.ContentLength) -or (Get-FileHash -LiteralPath $partial -Algorithm SHA256).Hash -ne $expectedHash) { throw 'Backup integrity verification failed; the partial download was rejected.' }
        if (Test-Path -LiteralPath $target) {
            if ((Get-FileHash -LiteralPath $target -Algorithm SHA256).Hash -ne $expectedHash) { throw 'A different file already exists with this backup name.' }
            Remove-Item -LiteralPath $partial
        } else { Move-Item -LiteralPath $partial -Destination $target }
        $partial = $null
        $ack = New-Object Net.Http.HttpRequestMessage([Net.Http.HttpMethod]::Post, $endpoint)
        $ack.Headers.Authorization = New-Object Net.Http.Headers.AuthenticationHeaderValue('Bearer', $token)
        $ack.Headers.Add('X-Backup-Request', [string]$state.request_id)
        $ack.Headers.Add('X-Backup-Acknowledge', $expectedHash)
        $ackResponse = $client.SendAsync($ack).GetAwaiter().GetResult()
        try {
            if (!$ackResponse.IsSuccessStatusCode) { throw ('Backup was saved, but Agila could not confirm it (HTTP ' + [int]$ackResponse.StatusCode + ').') }
        } finally { $ackResponse.Dispose(); $ack.Dispose() }
        $state.last_success = [DateTimeOffset]::UtcNow.ToString('o')
        $state.request_id = $null
        Save-DownloadState $state
        Add-Content -LiteralPath $logPath -Value ($state.last_success + ' SUCCESS ' + $name)
        Write-Output ('Backup saved: ' + $target)
    } finally { $response.Dispose(); $request.Dispose() }
} catch {
    Add-Content -LiteralPath $logPath -Value ([DateTimeOffset]::UtcNow.ToString('o') + ' FAILED ' + $_.Exception.Message)
    Write-Error $_.Exception.Message
    exit 1
} finally {
    if ($partial -and (Test-Path -LiteralPath $partial)) { Remove-Item -LiteralPath $partial }
    if ($client) { $client.Dispose() }
    if ($lock) { $lock.Dispose() }
}
