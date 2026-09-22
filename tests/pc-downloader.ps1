$ErrorActionPreference = 'Stop'
$root = Join-Path ([IO.Path]::GetTempPath()) ('sf_pc_download_test_' + [Guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $root | Out-Null
try {
    $secure = ConvertTo-SecureString ('a' * 64) -AsPlainText -Force
    $credential = New-Object Management.Automation.PSCredential('test', $secure)
    $credential | Export-Clixml -LiteralPath (Join-Path $root 'access-key.xml')
    $configPath = Join-Path $root 'config.json'
    @{endpoint='https://example.test/backend/api/pc_backup.php';destination=(Join-Path $root 'downloads')} | ConvertTo-Json | Set-Content -LiteralPath $configPath
    Add-Type -AssemblyName System.Net.Http
    Add-Type -ReferencedAssemblies System.Net.Http -TypeDefinition @'
using System;
using System.Net;
using System.Net.Http;
using System.Security.Cryptography;
using System.Text;
using System.Threading.Tasks;
public class PcBackupTestClient : IDisposable {
    public TimeSpan Timeout {get;set;}
    public static bool BadHash = false;
    public Task<HttpResponseMessage> SendAsync(HttpRequestMessage request, HttpCompletionOption mode) {
        if (request.Method != HttpMethod.Post || request.Headers.Authorization.Scheme != "Bearer" || !request.Headers.Contains("X-Backup-Request")) throw new Exception("Missing authenticated POST request.");
        byte[] bytes = Encoding.UTF8.GetBytes("complete TAR fixture");
        string hash = BitConverter.ToString(SHA256.Create().ComputeHash(bytes)).Replace("-", "").ToLowerInvariant();
        var response = new HttpResponseMessage(HttpStatusCode.OK);
        response.Headers.Add("X-Backup-Name", "santafe_20260917_010000_1234abcd_complete.tar");
        response.Headers.Add("X-Backup-SHA256", BadHash ? new string('0', 64) : hash);
        response.Content = new ByteArrayContent(bytes);
        return Task.FromResult(response);
    }
    public void Dispose() {}
}
'@
    $source = Get-Content -LiteralPath (Join-Path $PSScriptRoot '../backend/scripts/download_pc_backup.ps1') -Raw
    $source = $source.Replace('$client = New-Object Net.Http.HttpClient($handler)', '$client = New-Object PcBackupTestClient')
    # Keep script failure observable inside the test host instead of exiting it.
    $source = $source.Replace('Write-Error $_.Exception.Message', 'throw $_.Exception.Message')
    & ([ScriptBlock]::Create($source)) -ConfigPath $configPath -Force
    $download = Join-Path $root 'downloads/Complete Backups/santafe_20260917_010000_1234abcd_complete.tar'
    if (!(Test-Path -LiteralPath $download)) { throw 'Verified download was not saved.' }
    $state = Get-Content -LiteralPath (Join-Path $root 'download-state.json') -Raw | ConvertFrom-Json
    if (!$state.last_success -or $state.request_id) { throw 'Success state was not saved.' }
    Write-Output 'PASS: Authenticated response is streamed, verified and saved with success state.'
    [PcBackupTestClient]::BadHash = $true
    $failed = $false
    try { & ([ScriptBlock]::Create($source)) -ConfigPath $configPath -Force } catch { $failed = $true }
    if (!$failed) { throw 'Corrupted download was accepted.' }
    if ((Get-ChildItem -LiteralPath (Join-Path $root 'downloads/Complete Backups') -Filter '*.partial').Count -ne 0) { throw 'Partial file remained.' }
    $state = Get-Content -LiteralPath (Join-Path $root 'download-state.json') -Raw | ConvertFrom-Json
    if (!$state.request_id) { throw 'Failed request ID was not kept for retry.' }
    Write-Output 'PASS: Corruption is rejected, partial data removed and retry request retained.'
} finally {
    $resolved = [IO.Path]::GetFullPath($root)
    $tempRoot = [IO.Path]::GetFullPath([IO.Path]::GetTempPath()).TrimEnd('\') + '\'
    if (!$resolved.StartsWith($tempRoot, [StringComparison]::OrdinalIgnoreCase) -or (Split-Path -Leaf $resolved) -notlike 'sf_pc_download_test_*') { throw 'Unsafe test cleanup path.' }
    Remove-Item -LiteralPath $resolved -Recurse -Force
}
