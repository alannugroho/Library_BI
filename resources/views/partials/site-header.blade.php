<header class="site-header">
    <div class="container navigation">
        <a class="brand" href="/" aria-label="Digital Library BI">
            <span class="brand-mark">BI</span>
            <span><strong>Digital Library</strong><small>Bank Indonesia Institute</small></span>
        </a>
        <nav class="nav-links" aria-label="Navigasi utama">
            <a href="/">Beranda</a>
            <a href="/catalog">Katalog</a>
            <a href="/news">News</a>
            <a href="/e-resources">E-Resources</a>
            @auth
                <details class="account-menu">
                    <summary class="account-trigger">
                        <span class="account-icon" aria-hidden="true"><svg viewBox="0 0 24 24" role="img"><circle cx="12" cy="8" r="3.5"></circle><path d="M5.5 20c.8-3.4 3-5 6.5-5s5.7 1.6 6.5 5"></path></svg></span>
                        <span class="account-label"><strong>{{ auth()->user()->email }}</strong><small>{{ auth()->user()->role }}</small></span>
                        <span class="account-chevron">⌄</span>
                    </summary>
                    <div class="account-dropdown">
                        <a href="/dashboard">Dashboard</a>
                        <form method="post" action="/logout">@csrf<button type="submit">Keluar</button></form>
                    </div>
                </details>
            @else
                <a class="button button-outline" href="/login">Masuk</a>
            @endauth
        </nav>
    </div>
</header>
