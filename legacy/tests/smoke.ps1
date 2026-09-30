param(
    [string]$BaseUrl = 'http://library.test'
)

$ErrorActionPreference = 'Stop'
$passed = 0
$failed = 0

function Assert-True {
    param(
        [bool]$Condition,
        [string]$Message
    )

    if ($Condition) {
        $script:passed++
        Write-Host "PASS  $Message" -ForegroundColor Green
    } else {
        $script:failed++
        Write-Host "FAIL  $Message" -ForegroundColor Red
    }
}

function Get-Page {
    param(
        [string]$Path,
        [Microsoft.PowerShell.Commands.WebRequestSession]$Session
    )

    return Invoke-WebRequest `
        -Uri ($BaseUrl.TrimEnd('/') + $Path) `
        -WebSession $Session `
        -UseBasicParsing `
        -MaximumRedirection 5 `
        -SkipHttpErrorCheck
}

function Get-CsrfToken {
    param(
        [string]$Html
    )

    $match = [regex]::Match($Html, 'name="csrf_token" value="([^"]+)"')
    if (-not $match.Success) {
        throw 'CSRF token was not found.'
    }
    return $match.Groups[1].Value
}

function Get-AuthenticatedSession {
    param(
        [string]$Email,
        [string]$Password
    )

    $session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $loginPage = Get-Page -Path '/login' -Session $session
    $csrf = Get-CsrfToken -Html $loginPage.Content
    $response = Invoke-WebRequest `
        -Uri ($BaseUrl.TrimEnd('/') + '/login') `
        -Method Post `
        -Body @{
            email = $Email
            password = $Password
            csrf_token = $csrf
        } `
        -WebSession $session `
        -UseBasicParsing `
        -MaximumRedirection 5 `
        -SkipHttpErrorCheck

    Assert-True ($response.StatusCode -eq 200 -and $response.Content -match 'Dashboard') "login reaches dashboard for $Email"
    return $session
}

Write-Host "Digital Library smoke tests: $BaseUrl"

$publicRoutes = @('/', '/catalog', '/news', '/e-resources', '/digital-collections', '/login', '/register')
foreach ($route in $publicRoutes) {
    $response = Get-Page -Path $route
    Assert-True ($response.StatusCode -eq 200) "public route $route returns 200"
}

$unauthenticatedAdmin = Get-Page -Path '/admin/catalog'
Assert-True ($unauthenticatedAdmin.StatusCode -eq 200 -and $unauthenticatedAdmin.Content -match 'Masuk') 'unauthenticated admin access reaches the login page'

$unauthenticatedReservations = Get-Page -Path '/reservations'
Assert-True ($unauthenticatedReservations.StatusCode -eq 200 -and $unauthenticatedReservations.Content -match 'Masuk') 'unauthenticated reservations access reaches the login page'

$memberSession = Get-AuthenticatedSession -Email 'anggota.demo@library.test' -Password 'Member123!'
$memberDashboard = Get-Page -Path '/dashboard' -Session $memberSession
Assert-True ($memberDashboard.StatusCode -eq 200 -and $memberDashboard.Content -match 'anggota\.demo@library\.test') 'member dashboard keeps the authenticated session'

$memberReservations = Get-Page -Path '/reservations' -Session $memberSession
Assert-True ($memberReservations.StatusCode -eq 200 -and $memberReservations.Content -match 'Reservasi saya') 'member reservations link reaches the dashboard'

$memberAdmin = Get-Page -Path '/admin/catalog' -Session $memberSession
Assert-True ($memberAdmin.StatusCode -eq 200 -and $memberAdmin.Content -match 'Dashboard' -and $memberAdmin.Content -notmatch 'Kelola katalog') 'member cannot access librarian catalog administration'

$librarianSession = Get-AuthenticatedSession -Email 'pustakawan.demo@library.test' -Password 'Admin123!'
$librarianDashboard = Get-Page -Path '/dashboard' -Session $librarianSession
Assert-True ($librarianDashboard.StatusCode -eq 200 -and $librarianDashboard.Content -match 'pustakawan\.demo@library\.test') 'librarian dashboard keeps the authenticated session'
Assert-True ($librarianDashboard.Content -match 'Buku dalam tahap reservasi') 'librarian dashboard shows active reservations'

$librarianAdmin = Get-Page -Path '/admin/catalog' -Session $librarianSession
Assert-True ($librarianAdmin.StatusCode -eq 200 -and $librarianAdmin.Content -match 'Kelola katalog') 'librarian can access catalog administration'

$homeAfterLogin = Get-Page -Path '/' -Session $memberSession
Assert-True ($homeAfterLogin.StatusCode -eq 200 -and $homeAfterLogin.Content -match 'account-menu' -and $homeAfterLogin.Content -notmatch 'href="/login"') 'authenticated home shows the account menu'
$contentSecurityPolicy = ($homeAfterLogin.Headers['Content-Security-Policy'] -join ';')
Assert-True ($contentSecurityPolicy -match "default-src 'self'") 'security headers include a restrictive content security policy'

$logoutWithoutCsrf = Invoke-WebRequest `
    -Uri ($BaseUrl.TrimEnd('/') + '/logout') `
    -Method Post `
    -WebSession $memberSession `
    -Body @{} `
    -UseBasicParsing `
    -MaximumRedirection 5 `
    -SkipHttpErrorCheck
Assert-True ($logoutWithoutCsrf.StatusCode -eq 200 -and $logoutWithoutCsrf.Content -match 'Dashboard') 'logout rejects requests without CSRF protection'

Write-Host ''
Write-Host "Passed: $passed  Failed: $failed"
if ($failed -gt 0) {
    exit 1
}
