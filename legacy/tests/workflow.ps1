param(
    [string]$BaseUrl = 'http://library.test'
)

$ErrorActionPreference = 'Stop'
$stamp = Get-Date -Format 'yyyyMMddHHmmss'
$email = "e2e.$stamp@library.test"
$password = 'E2eTest123!'
$mysql = 'C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe'
$createdUserId = $null

function Get-CsrfToken {
    param([string]$Html)
    $match = [regex]::Match($Html, 'name="csrf_token" value="([^"]+)"')
    if (-not $match.Success) { throw 'CSRF token was not found.' }
    $match.Groups[1].Value
}

function Get-Page {
    param([string]$Path, [Microsoft.PowerShell.Commands.WebRequestSession]$Session)
    Invoke-WebRequest -Uri ($BaseUrl.TrimEnd('/') + $Path) -WebSession $Session -UseBasicParsing -MaximumRedirection 5 -SkipHttpErrorCheck
}

function Post-Page {
    param([string]$Path, [hashtable]$Body, [Microsoft.PowerShell.Commands.WebRequestSession]$Session)
    Invoke-WebRequest -Uri ($BaseUrl.TrimEnd('/') + $Path) -Method Post -Body $Body -WebSession $Session -UseBasicParsing -MaximumRedirection 5 -SkipHttpErrorCheck
}

function Get-DbValue {
    param([string]$Query)
    $value = & $mysql -h 127.0.0.1 -u root digital_library_bi -N -e $Query
    if ($LASTEXITCODE -ne 0) { throw 'Database query failed.' }
    ($value | Select-Object -First 1).ToString().Trim()
}

function Assert-True {
    param([bool]$Condition, [string]$Message)
    if (-not $Condition) { throw "FAIL: $Message" }
    Write-Host "PASS  $Message" -ForegroundColor Green
}

try {
    $external = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $register = Get-Page '/register' $external
    $registered = Post-Page '/register' @{
        email = $email
        password = $password
        password_confirmation = $password
        csrf_token = Get-CsrfToken $register.Content
    } $external
    Assert-True ($registered.Content -match 'Tautan pengujian') 'registration creates a pending account'
    $createdUserId = Get-DbValue "SELECT id FROM users WHERE email = '$email'"

    $token = [regex]::Match($registered.Content, '/verify-email\?token=([a-f0-9]{64})').Groups[1].Value
    $verified = Get-Page ('/verify-email?token=' + $token) $external
    Assert-True ($verified.Content -match 'Email terverifikasi') 'email verification succeeds'

    $admin = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $login = Get-Page '/login' $admin
    $loggedIn = Post-Page '/login' @{
        email = 'pustakawan.demo@library.test'
        password = 'Admin123!'
        csrf_token = Get-CsrfToken $login.Content
    } $admin
    Assert-True ($loggedIn.Content -match 'Dashboard') 'librarian login succeeds'

    $members = Get-Page '/admin/members' $admin
    Post-Page '/admin/members' @{
        member_id = $createdUserId
        status = 'active'
        csrf_token = Get-CsrfToken $members.Content
    } $admin | Out-Null
    Assert-True ((Get-DbValue "SELECT status FROM users WHERE id = $createdUserId") -eq 'active') 'librarian approves the account'

    $internal = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $internalLogin = Get-Page '/login' $internal
    $internalResponse = Post-Page '/login' @{
        email = 'anggota.demo@library.test'
        password = 'Member123!'
        csrf_token = Get-CsrfToken $internalLogin.Content
    } $internal
    Assert-True ($internalResponse.Content -match 'Dashboard') 'internal member login succeeds'

    $proposal = Get-Page '/proposals' $internal
    Post-Page '/proposals' @{
        title = "Permanent E2E $stamp"
        author = 'Automated test'
        csrf_token = Get-CsrfToken $proposal.Content
    } $internal | Out-Null
    $proposalId = Get-DbValue "SELECT id FROM book_proposals WHERE title = 'Permanent E2E $stamp'"
    Assert-True ($proposalId -match '^\d+$') 'member submits a collection proposal'

    $adminProposals = Get-Page '/admin/proposals' $admin
    Post-Page '/admin/proposals' @{
        proposal_id = $proposalId
        status = 'approved'
        csrf_token = Get-CsrfToken $adminProposals.Content
    } $admin | Out-Null
    Assert-True ((Get-DbValue "SELECT status FROM book_proposals WHERE id = $proposalId") -eq 'approved') 'librarian approves a proposal'

    $catalog = Get-Page '/catalog?type=digital' $internal
    Assert-True ($catalog.Content -match '<title>Koleksi digital') 'digital collection page has a dedicated title'
    Write-Host 'Workflow checks completed.' -ForegroundColor Green
} finally {
    if ($createdUserId) {
        $cleanup = @"
START TRANSACTION;
DELETE FROM e_resource_activities WHERE user_id = $createdUserId;
DELETE FROM circulations WHERE user_id = $createdUserId;
DELETE FROM reservations WHERE user_id = $createdUserId;
DELETE FROM book_proposals WHERE user_id = $createdUserId;
DELETE FROM email_verifications WHERE user_id = $createdUserId;
DELETE FROM users WHERE id = $createdUserId;
COMMIT;
"@
        $cleanup | & $mysql -h 127.0.0.1 -u root digital_library_bi
    }
}
